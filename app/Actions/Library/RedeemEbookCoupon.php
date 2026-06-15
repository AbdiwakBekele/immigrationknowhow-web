<?php

namespace App\Actions\Library;

use App\Models\EbookCoupon;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RedeemEbookCoupon
{
    public function __construct(
        private readonly GrantLibraryItemAccess $grantAccess,
    ) {}

    public function __invoke(EbookCoupon $coupon, LibraryItem $item, int $userId): LibraryUserAccess
    {
        if ((int) $coupon->user_id !== $userId) {
            throw new RuntimeException('This coupon does not belong to your account.');
        }

        if (! $coupon->isUsable()) {
            throw new RuntimeException('This coupon has already been used or has expired.');
        }

        if (! $item->is_active) {
            throw new RuntimeException('This title is not available.');
        }

        $requiresPaid = (bool) $item->is_premium || (float) ($item->price ?? 0) > 0;
        if (! $requiresPaid) {
            throw new RuntimeException('This title is already free. Add it from the library without a coupon.');
        }

        if ($item->userAccess()->where('user_id', $userId)->whereNotNull('purchased_at')->exists()) {
            throw new RuntimeException('You already own this title.');
        }

        return DB::transaction(function () use ($coupon, $item, $userId) {
            $coupon->refresh();
            if (! $coupon->isUsable()) {
                throw new RuntimeException('This coupon has already been used or has expired.');
            }

            $access = ($this->grantAccess)($item, $userId, [
                'purchase_amount' => 0,
                'purchase_currency' => $item->currency ?? 'USD',
                'purchase_source' => 'coupon',
            ]);

            $coupon->update([
                'redeemed_at' => now(),
                'library_item_id' => $item->id,
                'library_user_access_id' => $access->id,
            ]);

            return $access;
        });
    }
}
