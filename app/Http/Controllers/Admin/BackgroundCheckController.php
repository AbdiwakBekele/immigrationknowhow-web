<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BackgroundCheckStatus;
use App\Http\Controllers\Controller;
use App\Models\BackgroundCheck;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BackgroundCheckController extends Controller
{
    /**
     * Display a listing of background checks.
     */
    public function index(Request $request): Response
    {
        $query = BackgroundCheck::query()
            ->with(['serviceProvider.user:id,first_name,last_name,email,avatar']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('serviceProvider', function ($sq) use ($search) {
                        $sq->where('business_name', 'like', "%{$search}%");
                    });
            });
        }

        // Sort
        $query->orderByDesc('created_at');

        $backgroundChecks = $query->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'total' => BackgroundCheck::count(),
            'pending' => BackgroundCheck::whereIn('status', [
                BackgroundCheckStatus::PENDING,
                BackgroundCheckStatus::INVITED,
            ])->count(),
            'in_progress' => BackgroundCheck::where('status', BackgroundCheckStatus::COMPLETED)->count(),
            'cleared' => BackgroundCheck::where('status', BackgroundCheckStatus::CLEAR)->count(),
            'needs_review' => BackgroundCheck::whereIn('status', [
                BackgroundCheckStatus::CONSIDER,
                BackgroundCheckStatus::SUSPENDED,
                BackgroundCheckStatus::DISPUTE,
            ])->count(),
            'expired' => BackgroundCheck::where('status', BackgroundCheckStatus::EXPIRED)->count(),
        ];

        return Inertia::render('Admin/BackgroundChecks/Index', [
            'backgroundChecks' => $backgroundChecks,
            'filters' => $request->only(['status', 'search']),
            'stats' => $stats,
            'statuses' => collect(BackgroundCheckStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    /**
     * Display the specified background check.
     */
    public function show(BackgroundCheck $backgroundCheck): Response
    {
        $backgroundCheck->load(['serviceProvider.user']);

        return Inertia::render('Admin/BackgroundChecks/Show', [
            'backgroundCheck' => [
                'id' => $backgroundCheck->id,
                'uuid' => $backgroundCheck->uuid,
                'status' => $backgroundCheck->status->value,
                'status_display' => $backgroundCheck->status_display,
                'full_name' => $backgroundCheck->full_name,
                'email' => $backgroundCheck->email,
                'phone' => $backgroundCheck->phone,
                'zipcode' => $backgroundCheck->zipcode,
                'dob' => $backgroundCheck->dob?->format('M d, Y'),
                'package' => $backgroundCheck->package,
                'adjudication' => $backgroundCheck->adjudication,
                'checkr_candidate_id' => $backgroundCheck->checkr_candidate_id,
                'checkr_report_id' => $backgroundCheck->checkr_report_id,
                'initiated_at' => $backgroundCheck->created_at->format('M d, Y \a\t g:i A'),
                'completed_at' => $backgroundCheck->completed_at?->format('M d, Y \a\t g:i A'),
                'expires_at' => $backgroundCheck->expires_at?->format('M d, Y'),
                'days_until_expiry' => $backgroundCheck->days_until_expiry,
                'is_valid' => $backgroundCheck->is_valid,
                'report_summary' => $backgroundCheck->report_summary,
                'webhook_history' => $backgroundCheck->webhook_history,
                'last_webhook_at' => $backgroundCheck->last_webhook_at?->format('M d, Y \a\t g:i A'),
            ],
            'provider' => $backgroundCheck->serviceProvider ? [
                'id' => $backgroundCheck->serviceProvider->id,
                'slug' => $backgroundCheck->serviceProvider->slug,
                'business_name' => $backgroundCheck->serviceProvider->business_name,
                'user' => [
                    'id' => $backgroundCheck->serviceProvider->user->id,
                    'name' => $backgroundCheck->serviceProvider->user->full_name,
                    'email' => $backgroundCheck->serviceProvider->user->email,
                    'avatar' => $backgroundCheck->serviceProvider->user->avatar,
                ],
            ] : null,
        ]);
    }
}
