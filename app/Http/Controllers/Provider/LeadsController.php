<?php

namespace App\Http\Controllers\Provider;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadsController extends Controller
{
    public function index(Request $request): Response
    {
        $provider = auth()->user()->serviceProvider;

        $query = Lead::query()
            ->where('service_provider_id', $provider->id)
            ->with(['user:id,first_name,last_name,email,avatar', 'conversation:id,uuid,lead_id']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $leads = $query->orderByDesc('created_at')->paginate(20);

        // Calculate stats
        $stats = [
            'total' => Lead::where('service_provider_id', $provider->id)->count(),
            'new' => Lead::where('service_provider_id', $provider->id)->where('status', 'new')->count(),
            'in_progress' => Lead::where('service_provider_id', $provider->id)->where('status', 'in_progress')->count(),
            'converted' => Lead::where('service_provider_id', $provider->id)->where('status', 'converted')->count(),
        ];

        $stats['conversion_rate'] = $stats['total'] > 0 
            ? round(($stats['converted'] / $stats['total']) * 100) . '%'
            : '0%';

        return Inertia::render('Provider/Leads/Index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => $request->only(['status', 'urgency', 'search']),
        ]);
    }

    public function show(Lead $lead): Response
    {
        $this->authorize('view', $lead);

        $lead->load([
            'user:id,first_name,last_name,email,phone,avatar,city,state,preferred_language',
            'conversation',
        ]);

        // Get activity log (notes, status changes, etc.)
        $activityLog = collect();

        // Add lead creation
        $activityLog->push([
            'id' => 'created',
            'type' => 'lead_created',
            'description' => 'Lead received from ' . ($lead->user?->full_name ?? 'Anonymous'),
            'created_at' => $lead->created_at,
        ]);

        // Add status changes from notes/logs if you have them
        if ($lead->notes) {
            foreach ($lead->notes as $index => $note) {
                $activityLog->push([
                    'id' => 'note-' . $index,
                    'type' => 'note',
                    'description' => $note['content'] ?? $note,
                    'created_at' => $note['created_at'] ?? $lead->created_at,
                ]);
            }
        }

        return Inertia::render('Provider/Leads/Show', [
            'lead' => $lead,
            'conversation' => $lead->conversation,
            'activityLog' => $activityLog->sortByDesc('created_at')->values(),
        ]);
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
        ]);

        $oldStatus = $lead->status;
        $newStatus = LeadStatus::from($validated['status']);

        $lead->update(['status' => $newStatus]);

        // Update timestamps based on status
        if ($newStatus === LeadStatus::CONTACTED && !$lead->contacted_at) {
            $lead->update(['contacted_at' => now()]);
        } elseif ($newStatus === LeadStatus::CONVERTED && !$lead->converted_at) {
            $lead->update(['converted_at' => now()]);
        } elseif (in_array($newStatus, [LeadStatus::CLOSED, LeadStatus::DECLINED]) && !$lead->closed_at) {
            $lead->update(['closed_at' => now()]);
        }

        // Add to activity log / notes
        $notes = $lead->notes ?? [];
        $notes[] = [
            'type' => 'status_change',
            'content' => "Status changed from {$oldStatus->value} to {$newStatus->value}",
            'created_at' => now()->toISOString(),
        ];
        $lead->updateQuietly(['notes' => $notes]);

        return back()->with('success', 'Lead status updated.');
    }

    public function addNote(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $notes = $lead->notes ?? [];
        $notes[] = [
            'type' => 'note',
            'content' => $validated['note'],
            'created_at' => now()->toISOString(),
            'user_id' => auth()->id(),
        ];

        $lead->update(['notes' => $notes]);

        return back()->with('success', 'Note added.');
    }

    public function createConversation(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        // Check if conversation already exists
        if ($lead->conversation) {
            return redirect()->route('provider.messages.show', $lead->conversation);
        }

        // Create conversation
        $conversation = Conversation::create([
            'user_id' => $lead->user_id,
            'service_provider_id' => $lead->service_provider_id,
            'lead_id' => $lead->id,
        ]);

        // Update lead status if still new
        if ($lead->status === LeadStatus::NEW) {
            $lead->update([
                'status' => LeadStatus::CONTACTED,
                'contacted_at' => now(),
            ]);
        }

        return redirect()->route('provider.messages.show', $conversation)
            ->with('success', 'Conversation started.');
    }

    public function decline(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $notes = $lead->notes ?? [];
        $notes[] = [
            'type' => 'declined',
            'content' => $validated['reason'] ?? 'Lead declined by provider',
            'created_at' => now()->toISOString(),
        ];

        $lead->update([
            'status' => LeadStatus::DECLINED,
            'closed_at' => now(),
            'notes' => $notes,
        ]);

        return back()->with('success', 'Lead declined.');
    }
}
