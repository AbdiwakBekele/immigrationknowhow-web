<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('Legal/PrivacyPolicy');
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/TermsOfService');
    }
}
