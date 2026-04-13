<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Copy library binaries from the public disk (web-exposed via /storage) to
     * the private library_media disk and remove the public copy.
     */
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('library_media');

        foreach (DB::table('library_items')->whereNotNull('file_path')->cursor() as $row) {
            $path = $row->file_path;
            if ($path === '' || $path === null) {
                continue;
            }
            if ($private->exists($path)) {
                continue;
            }
            if (! $public->exists($path)) {
                continue;
            }

            $stream = $public->readStream($path);
            if ($stream === false) {
                continue;
            }

            $private->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $public->delete($path);
        }
    }

    /**
     * Move files back to public (restores old direct-URL behavior).
     */
    public function down(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('library_media');

        foreach (DB::table('library_items')->whereNotNull('file_path')->cursor() as $row) {
            $path = $row->file_path;
            if ($path === '' || $path === null) {
                continue;
            }
            if ($public->exists($path)) {
                continue;
            }
            if (! $private->exists($path)) {
                continue;
            }

            $stream = $private->readStream($path);
            if ($stream === false) {
                continue;
            }

            $public->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $private->delete($path);
        }
    }
};
