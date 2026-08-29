<?php

namespace App\Actions\Library;

use App\Models\EbookCoupon;
use App\Models\EbookShareCampaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\RoleAwareTransactionalEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class IssueSocialShareEbookCoupon
{
    public function __invoke(User $user, EbookShareCampaign $campaign): EbookCoupon
    {
        if ($campaign->ebook_coupon_id) {
            return EbookCoupon::query()->findOrFail($campaign->ebook_coupon_id);
        }

        $existing = EbookCoupon::activeSocialShareCouponForUser((int) $user->id);
        if ($existing) {
            return $existing;
        }

        $coupon = DB::transaction(function () use ($user) {
            return EbookCoupon::query()->create([
                'user_id' => $user->id,
                'code' => $this->generateUniqueCode(),
                'issued_for' => EbookCoupon::ISSUED_FOR_SOCIAL_SHARE,
            ]);
        });

        $role = $user->roles->first()?->name ?? 'user';

        try {
            $user->notify(new RoleAwareTransactionalEmailNotification(EmailTemplate::EVENT_EBOOK_COUPON, $user, [
                'role' => $role,
                'coupon_code' => $coupon->code,
                'library_link' => url('/library'),
            ]));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $coupon;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'IKH-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (EbookCoupon::query()->where('code', $code)->exists());

        return $code;
    }
}
