<?php

namespace App\Http\Middleware;

use App\Actions\Affiliates\CaptureAffiliateVisitAction;
use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAffiliateAttribution
{
    public function __construct(
        protected CaptureAffiliateVisitAction $captureAffiliateVisit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ref = strtoupper((string) $request->query('ref', ''));

        if ($ref !== '') {
            $affiliate = Affiliate::query()
                ->with('user:id,is_active')
                ->where('code', $ref)
                ->whereIn('status', [
                    AffiliateStatus::PENDING_VERIFICATION->value,
                    AffiliateStatus::VERIFIED->value,
                    AffiliateStatus::ACTIVE->value,
                ])
                ->first();

            if ($affiliate && $affiliate->user?->is_active) {
                $this->captureAffiliateVisit->handle($request, $affiliate);
            }
        }

        return $next($request);
    }
}
