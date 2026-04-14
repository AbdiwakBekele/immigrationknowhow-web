<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LibraryManualPaymentController extends Controller
{
    public function index(): Response
    {
        $pending = LibraryUserAccess::query()
            ->whereNotNull('manual_payment_requested_at')
            ->whereNull('purchased_at')
            ->with(['user:id,name,email', 'libraryItem:id,title,slug,type,price,currency,is_premium,is_active'])
            ->orderByDesc('manual_payment_requested_at')
            ->paginate(25);

        return Inertia::render('Admin/Library/ManualPayments', [
            'pending' => $pending,
        ]);
    }

    public function approve(LibraryUserAccess $libraryUserAccess): RedirectResponse
    {
        if ($libraryUserAccess->purchased_at !== null) {
            return redirect()
                ->route('admin.library-manual-payments.index')
                ->with('info', 'This purchase was already approved.');
        }

        if ($libraryUserAccess->manual_payment_requested_at === null) {
            return redirect()
                ->route('admin.library-manual-payments.index')
                ->with('error', 'This row is not a manual payment request.');
        }

        $item = $libraryUserAccess->libraryItem;
        if (! $item instanceof LibraryItem || ! $item->is_active) {
            return redirect()
                ->route('admin.library-manual-payments.index')
                ->with('error', 'Library item is missing or inactive.');
        }

        $requiresPaidAccess = in_array($item->type, ['audiobook', 'video'], true);
        if (! $item->is_premium && ! $requiresPaidAccess) {
            return redirect()
                ->route('admin.library-manual-payments.index')
                ->with('error', 'This title is not a paid library product.');
        }

        DB::transaction(function () use ($libraryUserAccess, $item) {
            $libraryUserAccess->refresh();

            if ($libraryUserAccess->purchased_at !== null) {
                return;
            }

            $libraryUserAccess->update([
                'purchased_at' => now(),
                'purchase_amount' => (float) $item->price,
                'purchase_currency' => strtoupper((string) ($item->currency ?? 'USD')),
            ]);
        });

        return redirect()
            ->route('admin.library-manual-payments.index')
            ->with('success', 'Access granted. The user can download from their library.');
    }
}
