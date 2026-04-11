<?php

namespace App\Http\Controllers\Affiliate\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Affiliate/VerifyEmail', [
            'status' => session('status'),
        ]);
    }
}
