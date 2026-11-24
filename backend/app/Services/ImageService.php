<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Prüft hochgeladene Bilder anhand ihres Inhalts, kodiert sie neu als WebP (entfernt damit
 * Metadaten und eingeschleusten Code), verkleinert sie und erzeugt Vorschaubilder.
 */
class ImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = extension_loaded('imagick')
            ? ImageManager::imagick(strip: true)
            : ImageManager::gd(strip: true);
    }

    public function store(UploadedFile $file, string $field = 'file'): Media
    {
        $config = config('leonida.images');
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => $message]);

        if (! $file->isValid()) {
            $fail('Der Upload ist fehlgeschlagen.');
        }
        if ($file->getSize() > $config['max_mb'] * 1024 * 1024) {
            $fail("Das Bild ist größer als {$config['max_mb']} MB.");
        }
        $info = @getimagesize($file->getRealPath());
        $mime = $info['mime'] ?? (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! $info || ! in_array($mime, $config['allowed_mimes'], true)) {
            $fail('Erlaubt sind nur JPEG-, PNG- und WebP-Bilder.');
        }
        if ($info[0] * $info[1] > $config['max_pixels']) {
            $fail('Das Bild hat zu viele Pixel.');
        }

        try {
            $image = $this->manager->read($file->getRealPath())->orient();
        } catch (Throwable) {
            $fail('Das Bild konnte nicht gelesen werden.');
        }

        $id = (string) Str::uuid();
        $dir = substr($id, 0, 2).'/'.substr($id, 2, 2);
        $root = rtrim(config('leonida.upload_dir'), '/');
        if (! is_dir("$root/$dir") && ! mkdir("$root/$dir", 0755, true) && ! is_dir("$root/$dir")) {
            throw new \RuntimeException("Upload-Verzeichnis $root/$dir kann nicht angelegt werden");
        }

        $main = $image->scaleDown($config['max_edge'], $config['max_edge'])->toWebp($config['quality']);
        $main->save("$root/$dir/$id.webp");
        $width = $image->width();
        $height = $image->height();
        $image->scaleDown($config['thumb_edge'], $config['thumb_edge'])->toWebp($config['quality'] - 4)->save("$root/$dir/{$id}_t.webp");

        return Media::query()->create([
            'id' => $id,
            'path' => "$dir/$id.webp",
            'thumb_path' => "$dir/{$id}_t.webp",
            'mime' => 'image/webp',
            'width' => $width,
            'height' => $height,
            'bytes' => filesize("$root/$dir/$id.webp"),
            'sha256' => hash_file('sha256', "$root/$dir/$id.webp"),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'created_by' => Auth::id(),
        ]);
    }

    /** Löscht Datensatz und Dateien, wenn das Bild nirgends mehr verwendet wird. */
    public function deleteIfUnused(?string $mediaId): void
    {
        if (! $mediaId) {
            return;
        }
        $media = Media::query()->find($mediaId);
        if (! $media) {
            return;
        }
        $used = \DB::table('pin_images')->where('media_id', $mediaId)->exists()
            || \DB::table('weapons')->where('image_media_id', $mediaId)->exists()
            || \DB::table('vehicles')->where('image_media_id', $mediaId)->exists();
        if ($used) {
            return;
        }
        $root = rtrim(config('leonida.upload_dir'), '/');
        @unlink("$root/{$media->path}");
        @unlink("$root/{$media->thumb_path}");
        $media->delete();
    }
}
