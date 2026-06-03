<?php

namespace App\Http\Controllers\Api\Mobile\Concerns;

use Illuminate\Http\Request;

trait DetectsMobileClient
{
    protected function mobileClientIsIos(Request $request): bool
    {
        $header = strtolower(trim((string) $request->header('X-IKH-Client', '')));

        return $header === 'ios';
    }
}
