<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\ServiceType;
use App\Models\Lead;
use App\Models\ServiceProvider;
use App\Notifications\NewLeadNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function create(ServiceProvider $provider): Response
    {
        $this->authorize('create', Lead::class);

        return Inertia::render('Leads/Create', [
            'provider' => $provider->load('user:id,first_name,last_name,avatar'),
            'serviceTypes' => collect($provider->service_types)
                ->map(fn($type) => [
                    'value' => $type,
                    'label' => \App\Enums\ServiceType::tryFrom($type)?->label() ?? $type,
                ])
                ->toArray(),
        ]);
    }

    public function store(Request $request, ServiceProvider $provider): RedirectResponse
    {
        $this->authorize('create', Lead::class);
        $sender = $request->user();

        $allowedServiceTypes = ! empty($provider->service_types)
            ? $provider->service_types
            : [ServiceType::OTHER->value];

        $validated = $request->validate([
            'service_type' => ['required', 'string', Rule::in($allowedServiceTypes)],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
            'requirements' => ['nullable', 'array'],
            'preferred_contact_method' => ['nullable', 'in:message,email,phone'],
            'preferred_contact_time' => ['nullable', 'string', 'max:255'],
            'urgency' => ['required', 'in:low,normal,high,urgent'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'budget_range' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['preferred_contact_method'] = $validated['preferred_contact_method'] ?? 'message';

        $lead = DB::transaction(function () use ($validated, $provider, $sender) {
            $lead = Lead::create([
                ...$validated,
                'user_id' => auth()->id(),
                'service_provider_id' => $provider->id,
                'source' => 'marketplace',
            ]);

            // Increment provider's lead count
            $provider->incrementLeadCount();

            // Create initial conversation
            $conversation = $lead->createConversation();
            $conversation->addMessage($sender, $lead->message);

            return $lead;
        });

        // Notify the provider
        $provider->user->notify(new NewLeadNotification($lead));

        return redirect()->route('messages.show', $lead->conversation)
            ->with('success', 'Your inquiry has been sent! The provider will respond soon.');
    }

    // For providers to view their leads
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        abort_unless($provider, 403, 'You must be a service provider to view leads.');

        $query = $provider->leads()
            ->with(['user:id,first_name,last_name,avatar,email,phone'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $status = LeadStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        // Filter by service type
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->input('service_type'));
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $leads = $query->paginate(15)->withQueryString();

        // Get stats
        $stats = [
            'total' => $provider->leads()->count(),
            'new' => $provider->leads()->new()->count(),
            'open' => $provider->leads()->open()->count(),
            'converted' => $provider->leads()->where('status', LeadStatus::CONVERTED)->count(),
        ];

        return Inertia::render('Provider/Leads/Index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => $request->only(['status', 'service_type', 'from', 'to']),
            'statuses' => collect(LeadStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->toArray(),
        ]);
    }

    public function show(Lead $lead): Response
    {
        $user = auth()->user();

        // Ensure user can view this lead
        $this->authorize('view', $lead);

        // Mark as viewed if provider is viewing
        if ($user->serviceProvider?->id === $lead->service_provider_id) {
            $lead->markAsViewed();
        }

        $lead->load([
            'user:id,first_name,last_name,avatar,email,phone,city,state',
            'serviceProvider.user:id,first_name,last_name,avatar',
            'conversation.messages.sender:id,first_name,last_name,avatar',
        ]);

        return Inertia::render('Provider/Leads/Show', [
            'lead' => $lead,
            'statuses' => collect(LeadStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ])->toArray(),
        ]);
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'decline_reason' => ['nullable', 'required_if:status,declined', 'string', 'max:500'],
        ]);

        $status = LeadStatus::tryFrom($validated['status']);
        abort_unless($status, 422, 'Invalid status');

        match ($status) {
            LeadStatus::CONTACTED => $lead->markAsContacted(),
            LeadStatus::IN_PROGRESS => $lead->markAsInProgress(),
            LeadStatus::CONVERTED => $lead->markAsConverted(),
            LeadStatus::CLOSED => $lead->markAsClosed(),
            LeadStatus::DECLINED => $lead->decline($validated['decline_reason'] ?? null),
            default => $lead->update(['status' => $status]),
        };

        if (!empty($validated['notes'])) {
            $lead->update(['provider_notes' => $validated['notes']]);
        }

        return back()->with('success', 'Lead status updated.');
    }
}
