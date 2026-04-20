<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Affiliate as AffiliatePortal;
use App\Http\Controllers\Affiliate\Auth as AffiliateAuth;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\Auth;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LocationLookupController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Provider;
use App\Http\Controllers\User;
use App\Http\Controllers\VideoProductController;
use App\Http\Controllers\Webhooks\CheckrWebhookController;
use App\Http\Controllers\Webhooks\InboundEmailWebhookController;
use App\Http\Controllers\Webhooks\StripeLibraryWebhookController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', WelcomeController::class)->name('home');
Route::get('/favicon.ico', fn () => redirect('/favicon.svg', 301));

Route::post('/locale', function (Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'string', 'in:en,fr,es'],
    ]);
    $request->session()->put('locale', $validated['locale']);

    return back();
})->name('locale.update');

// Public library access
Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
Route::get('/ebooks', [LibraryController::class, 'ebooks'])->name('library.ebooks');
Route::get('/audiobooks', [LibraryController::class, 'audiobooks'])->name('library.audiobooks');
Route::redirect('/library/ebooks', '/ebooks', 301);
Route::redirect('/library/audiobooks', '/audiobooks', 301);

// Purchasable video files (admin digital products — separate from Library)
Route::get('/videos', [VideoProductController::class, 'index'])->name('videos.index');
Route::get('/videos/{video:slug}', [VideoProductController::class, 'show'])->name('videos.show');
Route::get('/videos/{video:slug}/stream', [VideoProductController::class, 'stream'])->name('videos.stream');

// Marketplace (public)
Route::get('/providers', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/providers/{provider:slug}', [MarketplaceController::class, 'show'])->name('marketplace.show');

// Affiliate tracking
Route::get('/go/{tracking_code}', [AffiliateController::class, 'track'])->name('affiliate.track');

// DV Lottery link
Route::get('/dv-lottery', fn () => redirect('https://dvprogram.state.gov/'))->name('dv-lottery');

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/checkr', CheckrWebhookController::class)->name('checkr');
    Route::post('/stripe', StripeLibraryWebhookController::class)->name('stripe');
    Route::post('/email/inbound', InboundEmailWebhookController::class)->name('email.inbound');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [Auth\RegisterController::class, 'create'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store']);

    Route::get('/affiliate/register', [AffiliateAuth\RegistrationController::class, 'create'])->name('affiliate.register');
    Route::post('/affiliate/register', [AffiliateAuth\RegistrationController::class, 'store'])->name('affiliate.register.store');
    Route::get('/affiliate/invites/{token}', [AffiliateAuth\InviteAcceptanceController::class, 'show'])->name('affiliate.invites.show');
    Route::post('/affiliate/invites/{token}', [AffiliateAuth\InviteAcceptanceController::class, 'store'])->name('affiliate.invites.store');
});

// Login is not behind `guest`: authenticated users must still reach this page (e.g. "Sign in"
// from register) instead of being redirected to dashboard → onboarding by RedirectIfAuthenticated.
Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
Route::post('/login', [Auth\LoginController::class, 'store']);

Route::post('/logout', [Auth\LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/impersonation/leave', [Admin\UserController::class, 'stopImpersonating'])
    ->middleware('auth')
    ->name('impersonation.leave');

Route::middleware(['auth', 'role:affiliate', 'affiliate.access'])->prefix('affiliate')->name('affiliate.')->group(function () {
    Route::get('/email/verify', AffiliateAuth\EmailVerificationPromptController::class)->name('verification.notice');
    Route::post('/email/verification-notification', AffiliateAuth\EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', AffiliateAuth\EmailVerificationController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});

Route::middleware(['auth', 'role:affiliate', 'affiliate.access'])->group(function () {
    Route::get('/affiliate/email/verify', AffiliateAuth\EmailVerificationPromptController::class)->name('verification.notice');
    Route::post('/affiliate/email/verification-notification', AffiliateAuth\EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/affiliate/email/verify/{id}/{hash}', AffiliateAuth\EmailVerificationController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('/affiliate/profile', [AffiliatePortal\ProfileController::class, 'edit'])->name('affiliate.profile.edit');
    Route::patch('/affiliate/profile', [AffiliatePortal\ProfileController::class, 'update'])->name('affiliate.profile.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

// Phone verification after registration (before onboarding)
Route::middleware(['auth'])->group(function () {
    Route::get('/address-detail', [Auth\AddressDetailsController::class, 'show'])->name('address-detail');
    Route::get('/address-detail/autocomplete', [Auth\AddressDetailsController::class, 'autocomplete'])->name('address-detail.autocomplete');
    Route::post('/address-detail', [Auth\AddressDetailsController::class, 'sendOtp'])->name('address-detail.send');
    Route::get('/address-detail/otp', [Auth\AddressDetailsController::class, 'showOtp'])->name('address-detail.otp');
    Route::post('/address-detail/verify', [Auth\AddressDetailsController::class, 'verify'])->name('address-detail.verify');

    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');

    Route::get('/api/locations/states', [LocationLookupController::class, 'states'])->name('locations.states');
    Route::get('/api/locations/search', [LocationLookupController::class, 'search'])->name('locations.search');
});

// Onboarding actions — require verified phone
Route::middleware(['auth', 'phone.verified'])->group(function () {
    Route::post('/onboarding/progress', [OnboardingController::class, 'saveProgress'])->name('onboarding.progress');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});

// Digital Library checkout and protected media stay available after sign-in, even before onboarding is complete.
Route::middleware(['auth'])->prefix('library')->name('library.')->group(function () {
    Route::get('/purchase/return', [LibraryController::class, 'purchaseReturn'])->name('purchase.return');
    Route::get('/purchase/cancel/{item:slug}', [LibraryController::class, 'purchaseCancel'])->name('purchase.cancel');
    Route::get('/cart', [LibraryController::class, 'cart'])->name('cart');
    Route::post('/cart/items/{item:slug}', [LibraryController::class, 'addToCart'])
        ->middleware('throttle:60,1')
        ->name('cart.add');
    Route::delete('/cart/items/{item:slug}', [LibraryController::class, 'removeFromCart'])
        ->middleware('throttle:60,1')
        ->name('cart.remove');
    Route::post('/cart/checkout', [LibraryController::class, 'checkoutCart'])
        ->middleware('throttle:10,1')
        ->name('cart.checkout');
    Route::get('/{item:slug}/pay', [LibraryController::class, 'pay'])
        ->middleware('throttle:10,1')
        ->name('pay');
    Route::post('/{item:slug}/manual-payment', [LibraryController::class, 'storeManualPayment'])
        ->middleware('throttle:10,1')
        ->name('manual-payment');
    Route::get('/{item:slug}/read', [LibraryController::class, 'read'])->name('read');
    Route::get('/{item:slug}/media', [LibraryController::class, 'media'])->name('media');
    Route::post('/{item:slug}/progress', [LibraryController::class, 'updateProgress'])
        ->middleware('throttle:120,1')
        ->name('progress');
    Route::get('/{item:slug}', [LibraryController::class, 'show'])->name('show');
    Route::get('/{item:slug}/download', [LibraryController::class, 'download'])->name('download');
    Route::post('/{item:slug}/purchase', [LibraryController::class, 'purchase'])->name('purchase');
    Route::post('/{item:slug}/favorite', [LibraryController::class, 'toggleFavorite'])->name('favorite');
});

// Routes requiring completed onboarding
Route::middleware(['auth', 'onboarding.complete'])->group(function () {

    // User Dashboard
    Route::get('/dashboard', [User\DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [User\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [User\ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [User\ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::patch('/profile/notifications', [User\ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::post('/profile/provider-invites', [User\ProfileController::class, 'storeProviderInvite'])->name('profile.provider-invites.store');
    Route::post('/profile/avatar', [User\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [User\ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::delete('/profile', [User\ProfileController::class, 'destroy'])->name('profile.destroy');

    // Messaging
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/', [MessagingController::class, 'index'])->name('index');
        Route::get('/archived', [MessagingController::class, 'archived'])->name('archived');
        Route::get('/unread-count', [MessagingController::class, 'unreadCount'])->name('unread-count');
        Route::get('/{conversation:uuid}', [MessagingController::class, 'show'])->name('show');
        Route::post('/{conversation:uuid}', [MessagingController::class, 'sendMessage'])->name('send');
        Route::post('/{conversation:uuid}/read', [MessagingController::class, 'markAsRead'])->name('read');
        Route::post('/{conversation:uuid}/archive', [MessagingController::class, 'archive'])->name('archive');
        Route::post('/{conversation:uuid}/unarchive', [MessagingController::class, 'unarchive'])->name('unarchive');
        Route::delete('/{conversation:uuid}', [MessagingController::class, 'destroy'])->name('destroy');
    });

    // Leads (for users - creating inquiries)
    Route::get('/providers/{provider:slug}/contact', [LeadController::class, 'create'])->name('leads.create');
    Route::post('/providers/{provider:slug}/contact', [LeadController::class, 'store'])->name('leads.store');

    // User Contracts (service provider acceptance and contract lifecycle)
    Route::prefix('contracts')->name('contracts.')->group(function () {
        Route::get('/', [User\ContractController::class, 'index'])->name('index');
        Route::patch('/{lead}/send', [User\ContractController::class, 'send'])->name('send');
        Route::patch('/{lead}/end', [User\ContractController::class, 'end'])->name('end');
    });

    // Video digital products (file downloads / Stripe)
    Route::prefix('videos')->name('videos.')->group(function () {
        Route::get('/purchase/return', [VideoProductController::class, 'purchaseReturn'])->name('purchase.return');
        Route::get('/purchase/cancel/{video:slug}', [VideoProductController::class, 'purchaseCancel'])->name('purchase.cancel');
        Route::get('/{video:slug}/pay', [VideoProductController::class, 'pay'])
            ->middleware('throttle:10,1')
            ->name('pay');
        Route::get('/{video:slug}/download', [VideoProductController::class, 'download'])->name('download');
        Route::post('/{video:slug}/purchase', [VideoProductController::class, 'purchase'])->name('purchase');
        Route::post('/{video:slug}/favorite', [VideoProductController::class, 'toggleFavorite'])->name('favorite');
    });

    // Reviews
    Route::get('/reviews', [User\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/providers/{provider:slug}/reviews', [User\ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/reviews/{review:uuid}/helpful', [User\ReviewController::class, 'markHelpful'])->name('reviews.helpful');
});

/*
|--------------------------------------------------------------------------
| Provider Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:provider', 'onboarding.complete'])
    ->prefix('provider')
    ->name('provider.')
    ->group(function () {

        Route::get('/dashboard', [Provider\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/subscriptions', [Provider\SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscriptions/checkout/{plan:uuid}', [Provider\SubscriptionController::class, 'checkout'])->name('subscriptions.checkout');
        Route::post('/subscriptions/{subscription:uuid}/cancel', [Provider\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription:uuid}/resume', [Provider\SubscriptionController::class, 'resume'])->name('subscriptions.resume');
        Route::post('/subscriptions/{subscription:uuid}/change-plan/{plan:uuid}', [Provider\SubscriptionController::class, 'changePlan'])->name('subscriptions.change-plan');

        Route::get('/notifications', [Provider\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [Provider\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [Provider\NotificationController::class, 'markAsRead'])->name('notifications.read');

        // Profile management
        Route::get('/profile', [Provider\ProfileController::class, 'index'])->name('profile.index');
        Route::get('/profile/edit', [Provider\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [Provider\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [Provider\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::post('/profile/feed', [Provider\ProfilePostController::class, 'store'])->name('profile-posts.store');
        Route::patch('/profile/feed/{profilePost}', [Provider\ProfilePostController::class, 'update'])->name('profile-posts.update');
        Route::delete('/profile/feed/{profilePost}', [Provider\ProfilePostController::class, 'destroy'])->name('profile-posts.destroy');

        // Leads
        Route::get('/leads', [Provider\LeadsController::class, 'index'])->name('leads.index');
        Route::get('/leads/{lead}', [Provider\LeadsController::class, 'show'])->name('leads.show');
        Route::patch('/leads/{lead}/status', [Provider\LeadsController::class, 'updateStatus'])->name('leads.status');
        Route::post('/leads/{lead}/notes', [Provider\LeadsController::class, 'addNote'])->name('leads.notes');
        Route::post('/leads/{lead}/conversation', [Provider\LeadsController::class, 'createConversation'])->name('leads.conversation');
        Route::post('/leads/{lead}/decline', [Provider\LeadsController::class, 'decline'])->name('leads.decline');

        // Reviews
        Route::get('/reviews', [Provider\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review:uuid}/respond', [Provider\ReviewController::class, 'respond'])->name('reviews.respond');

        // Messages (provider portal — separate from service-seeker /messages)
        Route::get('/messages', [Provider\MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/archived', [Provider\MessageController::class, 'archived'])->name('messages.archived');
        Route::get('/messages/{conversation:uuid}', [Provider\MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{conversation:uuid}', [MessagingController::class, 'sendMessage'])->name('messages.send');
        Route::post('/messages/{conversation:uuid}/read', [MessagingController::class, 'markAsRead'])->name('messages.read');
        Route::post('/messages/{conversation:uuid}/archive', [MessagingController::class, 'archive'])->name('messages.archive');
        Route::post('/messages/{conversation:uuid}/unarchive', [MessagingController::class, 'unarchive'])->name('messages.unarchive');
        Route::delete('/messages/{conversation:uuid}', [MessagingController::class, 'destroy'])->name('messages.destroy');

        // Background Check (replaces old Identity Verification)
        // Redirect old verification URL for backward compatibility
        Route::get('/verification', fn () => redirect()->route('provider.background-check.index'))->name('verification.index');
        Route::prefix('background-check')->name('background-check.')->group(function () {
            Route::get('/', [Provider\BackgroundCheckController::class, 'index'])->name('index');
            Route::post('/', [Provider\BackgroundCheckController::class, 'store'])->name('store');
            Route::get('/{backgroundCheck:uuid}', [Provider\BackgroundCheckController::class, 'show'])->name('show');
            Route::post('/{backgroundCheck:uuid}/refresh', [Provider\BackgroundCheckController::class, 'refresh'])->name('refresh');
        });

        // Analytics
        Route::get('/analytics', [Provider\AnalyticsController::class, 'index'])->name('analytics.index');
    });

Route::middleware(['auth', 'role:affiliate', 'affiliate.access', 'verified'])
    ->prefix('affiliate')
    ->name('affiliate.')
    ->group(function () {
        Route::get('/dashboard', AffiliatePortal\DashboardController::class)->name('dashboard');
        Route::get('/earnings', [AffiliatePortal\EarningsController::class, 'index'])->name('earnings.index');
        Route::get('/payouts', [AffiliatePortal\PayoutController::class, 'index'])->name('payouts.index');
    });

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin|super_admin', 'onboarding.complete'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');
        Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');
        Route::get('/notifications', [Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/email-logs', [Admin\EmailLogController::class, 'index'])->name('email-logs.index');
        Route::post('/notifications/read-all', [Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [Admin\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::get('/profile', [Admin\ProfileController::class, 'index'])->name('profile.index');
        Route::patch('/profile', [Admin\ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [Admin\ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/avatar', [Admin\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [Admin\ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

        // Users
        Route::get('/users/check-email', [Admin\UserController::class, 'checkEmail'])->name('users.check-email');
        Route::post('/users/{user}/impersonate', [Admin\UserController::class, 'impersonate'])->name('users.impersonate');
        Route::resource('users', Admin\UserController::class);
        Route::get('/subscribers', [Admin\SubscriberController::class, 'index'])->name('subscribers.index');

        // Providers
        Route::resource('providers', Admin\ProviderController::class);
        Route::post('/providers/{provider}/verify', [Admin\ProviderController::class, 'verify'])->name('providers.verify');

        // Background Checks
        Route::get('/background-checks', [Admin\BackgroundCheckController::class, 'index'])->name('background-checks.index');
        Route::get('/background-checks/{backgroundCheck:uuid}', [Admin\BackgroundCheckController::class, 'show'])->name('background-checks.show');

        // Reviews
        Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review:uuid}/approve', [Admin\ReviewController::class, 'approve'])->name('reviews.approve');
        Route::post('/reviews/{review:uuid}/reject', [Admin\ReviewController::class, 'reject'])->name('reviews.reject');

        // Library (manual payment queue before resource so "library-manual-payments" is not captured as {library})
        Route::get('/library-manual-payments', [Admin\LibraryManualPaymentController::class, 'index'])
            ->name('library-manual-payments.index');
        Route::post('/library-manual-payments/{libraryUserAccess}/approve', [Admin\LibraryManualPaymentController::class, 'approve'])
            ->name('library-manual-payments.approve');
        Route::resource('library', Admin\LibraryController::class);
        Route::resource('library-categories', Admin\LibraryCategoryController::class);

        // Legacy partner links
        Route::resource('partner-links', Admin\AffiliateController::class);

        // Affiliate Program
        Route::get('/affiliates/invite', [Admin\AffiliateInviteController::class, 'create'])->name('affiliates.invite.create');
        Route::post('/affiliates/invite', [Admin\AffiliateInviteController::class, 'store'])->name('affiliates.invite.store');
        Route::post('/affiliates/invite/{affiliateInvite}/resend', [Admin\AffiliateInviteController::class, 'resend'])->name('affiliates.invite.resend');
        Route::get('/affiliates/commissions', [Admin\AffiliateCommissionController::class, 'index'])->name('affiliates.commissions.index');
        Route::post('/affiliates/commissions', [Admin\AffiliateCommissionController::class, 'store'])->name('affiliates.commissions.store');
        Route::patch('/affiliates/commissions/{affiliateCommission}', [Admin\AffiliateCommissionController::class, 'update'])->name('affiliates.commissions.update');
        Route::delete('/affiliates/commissions/{affiliateCommission}', [Admin\AffiliateCommissionController::class, 'destroy'])->name('affiliates.commissions.destroy');
        Route::get('/affiliates/payouts', [Admin\AffiliatePayoutController::class, 'index'])->name('affiliates.payouts.index');
        Route::post('/affiliates/payouts', [Admin\AffiliatePayoutController::class, 'store'])->name('affiliates.payouts.store');
        Route::resource('affiliates', Admin\AffiliatePartnerController::class);

        // Video embeds + admin file uploads (streamed privately)
        Route::get('videos/{video}/stream', [Admin\VideoController::class, 'stream'])->name('videos.stream');
        Route::resource('videos', Admin\VideoController::class)->except(['show']);
        Route::get('/service-types/create', [Admin\ServiceTypeController::class, 'create'])->name('service-types.create');
        Route::get('/service-types', [Admin\ServiceTypeController::class, 'index'])->name('service-types.index');
        Route::post('/service-types', [Admin\ServiceTypeController::class, 'store'])->name('service-types.store');
        Route::get('/service-types/{serviceType}/edit', [Admin\ServiceTypeController::class, 'edit'])->name('service-types.edit');
        Route::patch('/service-types/{serviceType}', [Admin\ServiceTypeController::class, 'update'])->name('service-types.update');
        Route::delete('/service-types/{serviceType}', [Admin\ServiceTypeController::class, 'destroy'])->name('service-types.destroy');
        Route::patch('/service-types/{serviceType}/toggle-active', [Admin\ServiceTypeController::class, 'toggleActive'])->name('service-types.toggle-active');

        // Reports
        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/users', [Admin\ReportController::class, 'users'])->name('reports.users');
        Route::get('/reports/leads', [Admin\ReportController::class, 'leads'])->name('reports.leads');
        Route::get('/reports/revenue', [Admin\ReportController::class, 'revenue'])->name('reports.revenue');

        // Settings
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
            Route::patch('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
            Route::get('/email-templates', [Admin\EmailTemplateController::class, 'index'])->name('email-templates.index');
            Route::get('/email-templates/{emailTemplate}', [Admin\EmailTemplateController::class, 'show'])->name('email-templates.show');
            Route::patch('/email-templates/{emailTemplate}', [Admin\EmailTemplateController::class, 'update'])->name('email-templates.update');
            Route::get('/subscription-plans', [Admin\SubscriptionPlanController::class, 'index'])->name('subscription-plans.index');
            Route::get('/subscription-plans/create', [Admin\SubscriptionPlanController::class, 'create'])->name('subscription-plans.create');
            Route::post('/subscription-plans', [Admin\SubscriptionPlanController::class, 'store'])->name('subscription-plans.store');
            Route::get('/subscription-plans/{subscriptionPlan:uuid}/edit', [Admin\SubscriptionPlanController::class, 'edit'])->name('subscription-plans.edit');
            Route::patch('/subscription-plans/{subscriptionPlan:uuid}', [Admin\SubscriptionPlanController::class, 'update'])->name('subscription-plans.update');
            Route::delete('/subscription-plans/{subscriptionPlan:uuid}', [Admin\SubscriptionPlanController::class, 'destroy'])->name('subscription-plans.destroy');
            Route::get('/subscriptions/reports', [Admin\SubscriptionReportController::class, 'index'])->name('subscriptions.reports');
        });

    });
