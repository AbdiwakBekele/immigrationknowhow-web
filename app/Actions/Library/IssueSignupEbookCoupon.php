<?php

namespace App\Actions\Library;

use App\Models\EbookCoupon;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\RoleAwareTransactionalEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class IssueSignupEbookCoupon
{
    public function __invoke(User $user, string $role): ?EbookCoupon
    {
        $eligibleRoles = config('library.signup_coupon_roles', ['user', 'provider']);
        if (! in_array($role, $eligibleRoles, true)) {
            return null;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('ebook_coupons')) {
            return null;
        }

        $existing = EbookCoupon::query()
            ->where('user_id', $user->id)
            ->where('issued_for', EbookCoupon::ISSUED_FOR_SIGNUP)
            ->exists();

        if ($existing) {
            return EbookCoupon::activeSignupCouponForUser((int) $user->id);
        }

        $coupon = DB::transaction(function () use ($user) {
            return EbookCoupon::query()->create([
                'user_id' => $user->id,
                'code' => $this->generateUniqueCode(),
                'issued_for' => EbookCoupon::ISSUED_FOR_SIGNUP,
            ]);
        });

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
