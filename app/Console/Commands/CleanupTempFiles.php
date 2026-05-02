<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTempFiles extends Command
{
    protected $signature = 'storage:cleanup-temp';
    protected $description = 'Clean up temporary and old files from storage';

    public function handle(): int
    {
        $this->info('🧹 Cleaning up temporary files...');

        $deleted = 0;

        // Clean temp uploads older than 24 hours
        if (Storage::exists('temp')) {
            $files = Storage::files('temp');
            foreach ($files as $file) {
                $lastModified = Storage::lastModified($file);
                if (now()->diffInHours(now()->createFromTimestamp($lastModified)) > 24) {
                    Storage::delete($file);
                    $deleted++;
                }
            }
        }

        // Clean old generation previews
        if (Storage::exists('previews')) {
            $files = Storage::files('previews');
            foreach ($files as $file) {
                $lastModified = Storage::lastModified($file);
                if (now()->diffInDays(now()->createFromTimestamp($lastModified)) > 7) {
                    Storage::delete($file);
                    $deleted++;
                }
            }
        }

        $this->info("✅ Cleaned up {$deleted} temporary file(s).");
        return Command::SUCCESS;
    }
}
