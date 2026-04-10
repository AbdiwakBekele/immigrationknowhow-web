<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Review;
use App\Models\ServiceProvider;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'stats' => [
                'users' => User::count(),
                'providers' => ServiceProvider::count(),
                'leads' => Lead::count(),
                'reviews' => Review::count(),
            ],
        ]);
    }

    public function users(): Response
    {
        return $this->index();
    }

    public function leads(): Response
    {
        return $this->index();
    }

    public function revenue(): Response
    {
        return $this->index();
    }
}
