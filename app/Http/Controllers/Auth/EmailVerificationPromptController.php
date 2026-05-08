<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()
            ->route('dashboard')
            ->with('warning', 'Please verify your email address to secure your account.');
    }
}
