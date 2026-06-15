<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $jsonContent = config('firebase.credentials_json');
        $filePath = (string) config('firebase.credentials');

        if (! empty($jsonContent)) {
            $decoded = base64_decode($jsonContent, true);
            $jsonContent = $decoded !== false && str_starts_with(trim($decoded), '{')
                ? $decoded
                : $jsonContent;

            $filePath = str_starts_with($filePath, DIRECTORY_SEPARATOR)
                ? $filePath
                : base_path($filePath);

            $directory = dirname($filePath);

            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Sync the file with the .env content if it's different or doesn't exist
            if (! File::exists($filePath) || File::get($filePath) !== $jsonContent) {
                File::put($filePath, $jsonContent);
            }
        }
    }
}
