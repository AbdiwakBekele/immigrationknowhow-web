<?php

namespace App\Services\Apple;

use App\Support\AppleIapConfig;
use Firebase\JWT\JWT;
use RuntimeException;

final class AppStoreJwtFactory
{
    public function make(): string
    {
        $issuerId = AppleIapConfig::issuerId();
        $keyId = AppleIapConfig::keyId();
        $bundleId = AppleIapConfig::bundleId();
        $privateKey = AppleIapConfig::privateKey();

        if ($issuerId === '' || $keyId === '' || $bundleId === '' || $privateKey === '') {
            throw new RuntimeException('Apple In-App Purchase API credentials are not configured.');
        }

        $issuedAt = time();

        return JWT::encode(
            [
                'iss' => $issuerId,
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
                'aud' => 'appstoreconnect-v1',
                'bid' => $bundleId,
            ],
            $privateKey,
            'ES256',
            $keyId,
        );
    }
}
