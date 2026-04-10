<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoEmbed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = VideoEmbed::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%");
            });
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->string('platform')->toString());
        }

        $videos = $query
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Videos/Index', [
            'videos' => $videos,
            'filters' => $request->only(['search', 'platform']),
            'stats' => [
                'total' => VideoEmbed::count(),
                'featured' => VideoEmbed::where('is_featured', true)->count(),
                'active' => VideoEmbed::where('is_active', true)->count(),
            ],
        ]);
    }
}
