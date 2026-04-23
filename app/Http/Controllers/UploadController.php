<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreS3UploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadController extends Controller
{
    public function store(StoreS3UploadRequest $request): JsonResponse
    {
        $diskName = (string) config('uploads.s3.disk', 's3');
        $disk = Storage::disk($diskName);
        $file = $request->file('file');
        $visibility = strtolower((string) config('uploads.s3.visibility', 'private')) === 'public'
            ? 'public'
            : 'private';

        $baseDirectory = trim((string) config('uploads.s3.directory', 'uploads'), '/');
        $requestDirectory = trim((string) $request->input('directory', ''), '/');
        $directory = trim(($baseDirectory !== '' ? $baseDirectory : 'uploads').($requestDirectory !== '' ? '/'.$requestDirectory : ''), '/');

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $fileName = (string) Str::ulid().($extension !== '' ? '.'.$extension : '');
        $path = $disk->putFileAs($directory, $file, $fileName, ['visibility' => $visibility]);

        $url = null;
        $temporaryUrl = null;
        $expiresAt = null;

        if ($visibility === 'public') {
            $url = $disk->url($path);
        } else {
            try {
                $expiryMinutes = max(1, (int) config('uploads.s3.signed_url_ttl_minutes', 10));
                $expiresAtDate = now()->addMinutes($expiryMinutes);
                $temporaryUrl = $disk->temporaryUrl($path, $expiresAtDate);
                $expiresAt = $expiresAtDate->toIso8601String();
            } catch (Throwable) {
                // Some S3-compatible providers may not support temporary URLs.
            }
        }

        return response()->json([
            'message' => 'File uploaded successfully.',
            'disk' => $diskName,
            'path' => $path,
            'visibility' => $visibility,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'url' => $url,
            'temporary_url' => $temporaryUrl,
            'temporary_url_expires_at' => $expiresAt,
        ]);
    }
}
