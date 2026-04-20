<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        if (! Schema::hasTable('email_logs')) {
            return Inertia::render('Admin/EmailLogs/Index', [
                'logs' => [
                    'data' => [],
                    'links' => [],
                    'prev_page_url' => null,
                    'next_page_url' => null,
                ],
                'email_logs_table_missing' => true,
                'filters' => $request->only(['direction', 'status', 'search']),
            ]);
        }

        $query = EmailLog::query()->latest('created_at');

        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($subQuery) use ($term) {
                $subQuery
                    ->where('subject', 'like', "%{$term}%")
                    ->orWhere('from_email', 'like', "%{$term}%")
                    ->orWhere('to_email', 'like', "%{$term}%")
                    ->orWhere('message_id', 'like', "%{$term}%");
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        return Inertia::render('Admin/EmailLogs/Index', [
            'logs' => $logs->through(fn (EmailLog $log) => [
                'id' => $log->id,
                'direction' => $log->direction,
                'status' => $log->status,
                'subject' => $log->subject,
                'from_email' => $log->from_email,
                'to_email' => $log->to_email,
                'cc' => $log->cc,
                'bcc' => $log->bcc,
                'message_id' => $log->message_id,
                'provider' => $log->provider,
                'payload' => $log->payload,
                'sent_at' => $log->sent_at?->toIso8601String(),
                'received_at' => $log->received_at?->toIso8601String(),
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'email_logs_table_missing' => false,
            'filters' => $request->only(['direction', 'status', 'search']),
        ]);
    }
}
