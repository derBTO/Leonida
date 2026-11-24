<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    protected $guarded = [];

    public function url(): string
    {
        return config('leonida.media_url').'/'.$this->path;
    }

    public function thumbUrl(): string
    {
        return config('leonida.media_url').'/'.$this->thumb_path;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'thumb_url' => $this->thumbUrl(),
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
