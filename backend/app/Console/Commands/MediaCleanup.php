<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\ImageService;
use Illuminate\Console\Command;

/** Löscht hochgeladene Bilder, die seit mehr als einem Tag nirgends verwendet werden (z. B. verworfene Hinweis-Schritte). */
class MediaCleanup extends Command
{
    protected $signature = 'leonida:media-cleanup {--hours=24 : Mindestalter unbenutzter Bilder}';

    protected $description = 'Unbenutzte hochgeladene Bilder löschen';

    public function handle(ImageService $images): int
    {
        $before = Media::query()->count();
        Media::query()->where('created_at', '<', now()->subHours((int) $this->option('hours')))->pluck('id')
            ->each(fn ($id) => $images->deleteIfUnused($id));
        $removed = $before - Media::query()->count();
        $this->info("$removed unbenutzte Bilder gelöscht.");

        return self::SUCCESS;
    }
}
