<?php

namespace App\Support;

class UploadLimit
{
    public static function videoMaxKb(): int
    {
        $configured = max(1, (int) config('filesystems.video_upload_max_kb', 102400));

        $phpLimits = array_filter([
            self::iniSizeToKb((string) ini_get('upload_max_filesize')),
            self::iniSizeToKb((string) ini_get('post_max_size')),
        ], static fn (int $value): bool => $value > 0);

        if (count($phpLimits) === 0) {
            return $configured;
        }

        return min($configured, min($phpLimits));
    }

    public static function videoMaxMb(): int
    {
        return (int) max(1, floor(self::videoMaxKb() / 1024));
    }

    public static function uploadFailedMessage(): string
    {
        return 'Upload failed before validation. The file likely exceeds the server upload limit. Try a file up to about '.self::videoMaxMb().' MB, or increase PHP upload_max_filesize/post_max_size.';
    }

    private static function iniSizeToKb(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }

        $unit = strtolower(substr($trimmed, -1));
        $numeric = (float) $trimmed;

        if ($numeric <= 0) {
            return 0;
        }

        return match ($unit) {
            'g' => (int) round($numeric * 1024 * 1024),
            'm' => (int) round($numeric * 1024),
            'k' => (int) round($numeric),
            default => (int) round($numeric / 1024),
        };
    }
}
