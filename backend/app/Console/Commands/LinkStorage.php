<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Hängt Upload- und Kartenverzeichnis per Symlink unter public/ ein (/media, /map). */
class LinkStorage extends Command
{
    protected $signature = 'leonida:link';

    protected $description = 'Symlinks public/media und public/map auf die Datenverzeichnisse setzen';

    public function handle(): int
    {
        foreach (['media' => config('leonida.upload_dir'), 'map' => config('leonida.map_dir')] as $name => $target) {
            $target = rtrim($target, '/');
            if (! is_dir($target) && ! @mkdir($target, 0755, true)) {
                $this->error("Verzeichnis $target fehlt und kann nicht angelegt werden.");

                return self::FAILURE;
            }
            $link = public_path($name);
            if (! is_link($link) && is_dir($link) && realpath($link) === realpath($target)) {
                $this->info("public/$name liegt bereits im Webverzeichnis ($target)");

                continue;
            }
            if (is_link($link)) {
                unlink($link);
            } elseif (file_exists($link)) {
                $this->error("public/$name existiert bereits und ist kein Symlink.");

                return self::FAILURE;
            }
            symlink($target, $link);
            $this->info("public/$name → $target");
        }

        return self::SUCCESS;
    }
}
