<?php

namespace App\Support;

final class SocialReferrer
{
    /** @var list<string> */
    private const HOST_SUFFIXES = [
        'facebook.com',
        'fb.com',
        'twitter.com',
        'x.com',
        't.co',
    ];

    public static function isSocial(?string $referrer): bool
    {
        if ($referrer === null || trim($referrer) === '') {
            return false;
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach (self::HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
