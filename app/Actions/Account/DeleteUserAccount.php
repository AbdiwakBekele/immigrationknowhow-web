<?php

namespace App\Actions\Account;

use App\Models\AiAssistantSubscription;
use App\Models\ProviderSubscription;
use App\Models\User;
use App\Support\StripeConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class DeleteUserAccount
{
    public function handle(User $user): void
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'account' => 'Administrator accounts cannot be deleted from the profile. Contact support.',
            ]);
        }

        $this->cancelStripeSubscriptions($user);
        $this->deleteAvatarFile($user);
        $user->tokens()->delete();
        $this->anonymizeUser($user);
        $user->delete();
    }

    private function cancelStripeSubscriptions(User $user): void
    {
        if (! StripeConfig::hasSecretKey()) {
            return;
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
        } catch (\Throwable $exception) {
            Log::warning('account.delete.stripe_init_failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $aiSubscription = AiAssistantSubscription::forUser((int) $user->id);
        if ($aiSubscription && is_string($aiSubscription->stripe_subscription_id) && $aiSubscription->stripe_subscription_id !== '') {
            $this->cancelStripeSubscriptionId($aiSubscription->stripe_subscription_id, [
                'context' => 'ai_assistant',
                'user_id' => $user->id,
            ]);
            $aiSubscription->update([
                'cancel_at_period_end' => true,
                'canceled_at' => now(),
            ]);
        }

        $provider = $user->serviceProvider;
        if (! $provider) {
            return;
        }

        ProviderSubscription::query()
            ->where('service_provider_id', $provider->id)
            ->whereNotNull('stripe_subscription_id')
            ->get()
            ->each(function (ProviderSubscription $subscription) use ($user): void {
                $stripeId = (string) $subscription->stripe_subscription_id;
                if ($stripeId === '') {
                    return;
                }

                $this->cancelStripeSubscriptionId($stripeId, [
                    'context' => 'provider_subscription',
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                ]);

                $subscription->update([
                    'cancel_at_period_end' => true,
                    'canceled_at' => now(),
                ]);
            });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function cancelStripeSubscriptionId(string $stripeSubscriptionId, array $context): void
    {
        try {
            StripeSubscription::update($stripeSubscriptionId, [
                'cancel_at_period_end' => true,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('account.delete.stripe_cancel_failed', array_merge($context, [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'message' => $exception->getMessage(),
            ]));
        }
    }

    private function deleteAvatarFile(User $user): void
    {
        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }
    }

    private function anonymizeUser(User $user): void
    {
        $user->update([
            'email' => 'deleted-'.$user->id.'@deleted.immigrationknowhow.local',
            'phone' => null,
            'phone_verified_at' => null,
            'first_name' => 'Deleted',
            'last_name' => 'Account',
            'avatar' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
            'latitude' => null,
            'longitude' => null,
            'languages' => [],
            'onboarding_data' => null,
            'onboarding_completed' => false,
            'onboarding_completed_at' => null,
            'is_active' => false,
        ]);
    }
}
