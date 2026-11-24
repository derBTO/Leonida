<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageTest extends TestCase
{
    private function pin(): int
    {
        return $this->postJson('/api/pins', ['title' => 'Galerie', 'x' => 0, 'y' => 0, 'loot_types' => []])->json('pin.id');
    }

    public function test_upload_creates_webp_and_thumbnail(): void
    {
        $this->actingAs($this->editor());
        $pin = $this->pin();
        $image = $this->post("/api/pins/$pin/images", ['file' => $this->jpeg(3000, 1000), 'caption' => 'Eingang'], ['Accept' => 'application/json'])
            ->assertCreated()->json('image');
        $this->assertSame('Eingang', $image['caption']);
        $this->assertSame(2560, $image['width']);
        $root = config('leonida.upload_dir');
        $main = $root.'/'.substr($image['url'], 7);
        $thumb = $root.'/'.substr($image['thumb_url'], 7);
        $this->assertSame('image/webp', getimagesize($main)['mime']);
        $this->assertSame(400, getimagesize($thumb)[0]);
    }

    public function test_disguised_file_is_rejected(): void
    {
        $this->actingAs($this->editor());
        $pin = $this->pin();
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, '<html><script>alert(1)</script></html>');
        $fake = new UploadedFile($path, 'harmlos.jpg', 'image/jpeg', null, true);
        $this->post("/api/pins/$pin/images", ['file' => $fake], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'Erlaubt sind nur JPEG-, PNG- und WebP-Bilder.');
    }

    public function test_gallery_caption_order_and_delete(): void
    {
        $this->actingAs($this->editor());
        $pin = $this->pin();
        $ids = [];
        foreach ([1, 2, 3] as $i) {
            $ids[] = $this->post("/api/pins/$pin/images", ['file' => $this->jpeg()], ['Accept' => 'application/json'])->json('image.id');
        }
        $this->patchJson("/api/pin-images/{$ids[1]}", ['caption' => 'Mitte'])->assertJsonPath('image.caption', 'Mitte');
        $order = [$ids[2], $ids[0], $ids[1]];
        $this->putJson("/api/pins/$pin/images/order", ['ids' => $order])->assertOk();
        $this->assertSame($order, array_column($this->getJson("/api/pins/$pin")->json('pin.images'), 'id'));
        $this->putJson("/api/pins/$pin/images/order", ['ids' => [$ids[0]]])->assertStatus(409);
        $this->deleteJson("/api/pin-images/{$ids[0]}")->assertOk();
        $this->assertCount(2, $this->getJson("/api/pins/$pin")->json('pin.images'));
    }
}
