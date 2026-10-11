<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImageUploadService
{
    /**
     * Upload an image file to Supabase Storage if configured,
     * otherwise fallback to local public disk storage.
     */
    public function upload(UploadedFile $file, string $folder = 'menus'): string
    {
        $supabaseUrl = config('services.supabase.url');
        $supabaseKey = config('services.supabase.key');
        $supabaseBucket = config('services.supabase.bucket');

        if (! empty($supabaseUrl) && ! empty($supabaseKey) && ! empty($supabaseBucket)) {
            try {
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = "{$folder}/" . Str::random(32) . ".{$ext}";

                $response = Http::withHeaders([
                    'apikey' => $supabaseKey,
                    'Authorization' => "Bearer {$supabaseKey}",
                ])->withBody(
                    file_get_contents($file->getRealPath()),
                    $file->getMimeType() ?: 'application/octet-stream'
                )->post("{$supabaseUrl}/storage/v1/object/{$supabaseBucket}/{$filename}");

                if ($response->successful()) {
                    return rtrim($supabaseUrl, '/') . "/storage/v1/object/public/{$supabaseBucket}/{$filename}";
                }

                Log::warning('Supabase Storage upload failed, falling back to local storage', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (Throwable $e) {
                Log::warning('Supabase Storage upload exception, falling back to local storage', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Local public storage fallback
        $path = $file->store($folder, 'public');

        return Storage::disk('public')->url($path);
    }
}
