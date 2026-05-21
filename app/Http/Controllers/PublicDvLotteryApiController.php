<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;

class PublicDvLotteryApiController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(PlatformSetting::current()->dvLotteryContent());
    }
}
