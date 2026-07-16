# Stripe webhooks (production)

## Correct endpoints

Stripe live webhooks for Immigrant Know How must hit the Laravel hub handler:

| URL | Role |
|-----|------|
| `https://hub.immigrantknowhow.com/webhooks/stripe` | Canonical Laravel webhook (signature verify + fulfill) |
| `https://immigrantknowhow.com/webhooks/stripe` | Marketing site proxy → forwards raw body + `Stripe-Signature` to hub |

Do **not** point Stripe at a Railway-only hostname unless that hostname still serves this Laravel app.

## Required production env (hub)

```
STRIPE_KEY=pk_live_…
STRIPE_SECRET=sk_live_…
STRIPE_WEBHOOK_SECRET=whsec_…   # from the Dashboard endpoint you actually use
STRIPE_WEBHOOK_EXPECT_LIVE=true # default when APP_ENV=production
```

Never put these values in mobile/`EXPO_PUBLIC_*` variables.

## After env changes

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

Restart PHP-FPM / the app process if config is cached in workers.

## Verify

```bash
php artisan route:list --path=webhooks/stripe
php artisan test --filter=StripeWebhookTest
```

In Stripe Dashboard (Live mode) → Webhooks → endpoint → Resend a failed event and confirm HTTP 200.
