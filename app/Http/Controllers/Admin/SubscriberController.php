<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Lead::query()
            ->with([
                'user:id,first_name,last_name,email',
                'serviceProvider:id,user_id,business_name,slug,service_types',
                'serviceProvider.user:id,first_name,last_name,email',
            ]);

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('serviceProvider', function ($providerQuery) use ($search) {
                    $providerQuery->where('business_name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, LeadStatus::values(), true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->string('service_type')->toString());
        }

        $subscribers = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Subscribers/Index', [
            'subscribers' => $subscribers,
            'filters' => $request->only(['search', 'status', 'service_type']),
            'serviceTypes' => ServiceTypeOptions::selectOptions(),
            'statusOptions' => collect(LeadStatus::cases())->map(fn (LeadStatus $status) => [
                'value' => $status->value,
                'label' => str($status->value)->replace('_', ' ')->title()->toString(),
            ])->values(),
        ]);
    }
}
