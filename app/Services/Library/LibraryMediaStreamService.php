<?php

namespace App\Services\Library;

use App\Models\LibraryItem;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryMediaStreamService
{
    public function deliver(LibraryItem $item, ?string $asset, Request $request): BinaryFileResponse|StreamedResponse
    {
        [$fileDisk, $path, $fileName, $fileType] = $this->resolveMediaAsset($item, $asset);

        if ($fileDisk === null) {
            abort(404);
        }

        $disk = Storage::disk($fileDisk);
        $headers = $this->mediaHeaders($fileName, $fileType, $path);

        if ($disk instanceof AwsS3V3Adapter) {
            return $this->streamS3Media($disk, $path, $headers, $request);
        }

        $localPath = $disk->path($path);
        if (! is_file($localPath)) {
            abort(404);
        }

        return response()->file($localPath, $headers);
    }

    private function resolveMediaAsset(LibraryItem $item, mixed $asset): array
    {
        $asset = is_string($asset) ? $asset : null;

        if ($asset !== null && $asset !== '' && ! in_array($asset, ['pdf', 'audio'], true)) {
            abort(404);
        }

        if ($asset === 'audio' && $item->type === 'ebook') {
            abort_unless($item->audio_file_path, 404);

            return [
                $item->resolveAudioFileDisk(),
                $item->audio_file_path,
                $item->audio_file_name ?: basename((string) $item->audio_file_path),
                $item->audio_file_type,
            ];
        }

        if ($asset === 'pdf' && $item->type !== 'ebook') {
            abort(404);
        }

        if ($asset === 'audio' && $item->type !== 'audiobook') {
            abort(404);
        }

        return [
            $item->resolveLibraryFileDisk(),
            $item->file_path,
            $item->file_name ?: basename((string) $item->file_path),
            $item->file_type,
        ];
    }

    private function mediaContentType(?string $fileType, ?string $path = null): string
    {
        $extension = strtolower((string) ($fileType ?: pathinfo((string) $path, PATHINFO_EXTENSION)));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }

    private function mediaHeaders(string $fileName, ?string $fileType, string $path): array
    {
        return [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $fileName),
            'Content-Type' => $this->mediaContentType($fileType, $path),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];
    }

    private function streamS3Media(AwsS3V3Adapter $disk, string $path, array $headers, Request $request): StreamedResponse
    {
        try {
            $size = (int) $disk->size($path);
        } catch (\Throwable) {
            abort(404);
        }

        if ($size < 1) {
            abort(404);
        }

        $range = $this->parseByteRange($request->header('Range'), $size);
        if ($range === false) {
            return response()->stream(static function (): void {}, 416, array_merge($headers, [
                'Content-Length' => '0',
                'Content-Range' => "bytes */{$size}",
            ]));
        }

        $s3Request = [
            'Bucket' => $disk->getConfig()['bucket'],
            'Key' => $disk->path($path),
        ];

        $status = 200;
        if (is_array($range)) {
            $status = 206;
            $s3Request['Range'] = "bytes={$range['start']}-{$range['end']}";
            $headers['Content-Length'] = (string) ($range['end'] - $range['start'] + 1);
            $headers['Content-Range'] = "bytes {$range['start']}-{$range['end']}/{$size}";
        } else {
            $headers['Content-Length'] = (string) $size;
        }

        try {
            $object = $disk->getClient()->getObject($s3Request);
        } catch (\Throwable) {
            abort(404);
        }

        $body = $object['Body'];

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(1024 * 1024);

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        }, $status, $headers);
    }

    private function parseByteRange(?string $rangeHeader, int $size): array|false|null
    {
        if ($rangeHeader === null || trim($rangeHeader) === '') {
            return null;
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
            return false;
        }

        if ($matches[1] === '' && $matches[2] === '') {
            return false;
        }

        if ($matches[1] === '') {
            $suffixLength = (int) $matches[2];
            if ($suffixLength < 1) {
                return false;
            }

            return [
                'start' => max(0, $size - $suffixLength),
                'end' => $size - 1,
            ];
        }

        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : (int) $matches[2];

        if ($start > $end || $start >= $size) {
            return false;
        }

        return [
            'start' => $start,
            'end' => min($end, $size - 1),
        ];
    }
}
