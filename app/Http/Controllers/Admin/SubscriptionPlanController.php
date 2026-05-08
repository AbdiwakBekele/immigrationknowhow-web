<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTypeOption;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\HttpClient\CurlClient;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class SubscriptionPlanController extends Controller
{
    public function index(): Response
    {
        $plans = SubscriptionPlan::query()
            ->with('serviceTypeOption:id,label,value')
            ->withCount(['subscriptions as subscribers_count' => function ($query) {
                $query->whereIn('status', ['trialing', 'active', 'past_due']);
            }])
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        return Inertia::render('Admin/SubscriptionPlans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SubscriptionPlans/Form', [
            'plan' => null,
            'serviceTypeOptions' => $this->serviceTypeOptionsForPlans(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $plan = new SubscriptionPlan;
        $plan->fill($validated);
        $plan->created_by = $request->user()?->id;
        $plan->features = $this->normalizeFeatures($request->input('features'));
        $plan->save();

        $stripeSyncWarning = null;
        try {
            $this->syncStripePlan($plan);
        } catch (\Throwable $e) {
            Log::warning('Subscription plan created but Stripe sync failed.', [
                'plan_id' => $plan->id,
                'plan_uuid' => $plan->uuid,
                'message' => $e->getMessage(),
            ]);
            $stripeSyncWarning = 'Plan saved, but Stripe sync failed. Please update the plan again when Stripe is reachable.';
        }

        $redirect = redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Subscription plan created.');

        if ($stripeSyncWarning) {
            $redirect->with('warning', $stripeSyncWarning);
        }

        return $redirect;
    }

    public function edit(SubscriptionPlan $subscriptionPlan): Response
    {
        return Inertia::render('Admin/SubscriptionPlans/Form', [
            'plan' => $subscriptionPlan->load('serviceTypeOption:id,label,value'),
            'serviceTypeOptions' => $this->serviceTypeOptionsForPlans(),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $validated = $this->validatePayload($request, $subscriptionPlan->id);

        $previousPrice = (int) $subscriptionPlan->price_cents;
        $previousCycle = (string) $subscriptionPlan->billing_cycle;

        $subscriptionPlan->fill($validated);
        $subscriptionPlan->features = $this->normalizeFeatures($request->input('features'));
        $subscriptionPlan->save();

        $stripeSyncWarning = null;
        try {
            if ($previousPrice !== (int) $subscriptionPlan->price_cents || $previousCycle !== (string) $subscriptionPlan->billing_cycle) {
                $this->syncStripePlan($subscriptionPlan, createNewPrice: true);
            } else {
                $this->syncStripePlan($subscriptionPlan, createNewPrice: false);
            }
        } catch (\Throwable $e) {
            Log::warning('Subscription plan updated but Stripe sync failed.', [
                'plan_id' => $subscriptionPlan->id,
                'plan_uuid' => $subscriptionPlan->uuid,
                'message' => $e->getMessage(),
            ]);
            $stripeSyncWarning = 'Plan updated, but Stripe sync failed. Please retry once Stripe is reachable.';
        }

        $redirect = redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Subscription plan updated.');

        if ($stripeSyncWarning) {
            $redirect->with('warning', $stripeSyncWarning);
        }

        return $redirect;
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $subscriptionPlan->delete();

        return back()->with('success', 'Subscription plan removed.');
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        if ($request->input('service_type_option_id') === '' || $request->input('service_type_option_id') === 'all') {
            $request->merge(['service_type_option_id' => null]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('subscription_plans', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            // min:0 allows free plans (price_cents === 0)
            'price' => ['required', 'numeric', 'gte:0', 'max:999999.99'],
            'currency' => ['required', 'in:USD,EUR,GBP,CAD,AUD'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,yearly'],
            'features' => ['nullable'],
            'status' => ['required', 'in:draft,active,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'recurring_commission_enabled' => ['nullable', 'boolean'],
            'max_recurring_commission_cycles' => ['nullable', 'integer', 'min:1'],
            'service_type_option_id' => ['nullable', 'integer', 'exists:service_type_options,id'],
            // Optional manual Stripe configuration (needed for Switch Plan; must be a persistent Stripe Price ID).
            'stripe_product_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stripe_price_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $validated['price_cents'] = (int) round(((float) $validated['price']) * 100);
        unset($validated['price']);

        $validated['service_type_option_id'] = $validated['service_type_option_id'] !== null
            ? (int) $validated['service_type_option_id']
            : null;

        return $validated;
    }

    /**
     * @return Collection<int, ServiceTypeOption>
     */
    private function serviceTypeOptionsForPlans()
    {
        return ServiceTypeOption::query()
            ->active()
            ->where('for_provider', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'value', 'label']);
    }

    private function normalizeFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $features), fn ($v) => $v !== ''));
        }

        if (! is_string($features) || trim($features) === '') {
            return [];
        }

        $parts = preg_split('/\r\n|\r|\n/', $features) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));
    }

    private function syncStripePlan(SubscriptionPlan $plan, bool $createNewPrice = true): void
    {
        if ((int) $plan->price_cents <= 0) {
            $plan->stripe_price_id = null;
            $plan->saveQuietly();

            return;
        }

        $secret = config('services.stripe.secret');
        if (! is_string($secret) || trim($secret) === '') {
            return;
        }

        // Prevent long-hanging requests from blocking admin plan creation.
        $httpClient = CurlClient::instance();
        $httpClient->setConnectTimeout(5);
        $httpClient->setTimeout(10);
        Stripe::setHttpClient($httpClient);
        Stripe::setApiKey($secret);

        if (! is_string($plan->stripe_product_id) || $plan->stripe_product_id === '') {
            $product = Product::create([
                'name' => $plan->name,
                'description' => $plan->description,
                'metadata' => [
                    'app' => 'provider_subscription',
                    'plan_uuid' => $plan->uuid,
                ],
            ]);

            $plan->stripe_product_id = $product->id;
            $plan->saveQuietly();
        } else {
            Product::update($plan->stripe_product_id, [
                'name' => $plan->name,
                'description' => $plan->description,
            ]);
        }

        if (! $createNewPrice && is_string($plan->stripe_price_id) && $plan->stripe_price_id !== '') {
            return;
        }

        $interval = 'month';
        $intervalCount = 1;
        if ($plan->billing_cycle === 'quarterly') {
            $intervalCount = 3;
        } elseif ($plan->billing_cycle === 'yearly') {
            $interval = 'year';
        }

        $price = Price::create([
            'product' => $plan->stripe_product_id,
            'unit_amount' => (int) $plan->price_cents,
            'currency' => strtolower((string) $plan->currency),
            'recurring' => [
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ],
            'metadata' => [
                'app' => 'provider_subscription',
                'plan_uuid' => $plan->uuid,
            ],
        ]);

        $plan->stripe_price_id = $price->id;
        $plan->saveQuietly();
    }
}
