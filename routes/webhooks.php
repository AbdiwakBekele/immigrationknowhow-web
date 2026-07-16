<?php

use App\Http\Controllers\Webhooks\CheckrWebhookController;
use App\Http\Controllers\Webhooks\InboundEmailWebhookController;
use App\Http\Controllers\Webhooks\StripeLibraryWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook routes (no web session / CSRF / Inertia stack)
|--------------------------------------------------------------------------
|
| Stripe, Checkr, and inbound email must receive the raw body without
| session cookies or CSRF. Keep these outside routes/web.php.
|
*/

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/checkr', CheckrWebhookController::class)->name('checkr');
    Route::post('/stripe', StripeLibraryWebhookController::class)->name('stripe');
    Route::post('/email/inbound', InboundEmailWebhookController::class)->name('email.inbound');
});
