<?php

use App\Http\Controllers\Api\Mobile\AiAssistantController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\ContractsController;
use App\Http\Controllers\Api\Mobile\DvLotteryController;
use App\Http\Controllers\Api\Mobile\MessagesController;
use App\Http\Controllers\Api\Mobile\MobileAdsController;
use App\Http\Controllers\Api\Mobile\MobileLibraryController;
use App\Http\Controllers\Api\Mobile\MobileLibraryStreamController;
use App\Http\Controllers\Api\Mobile\MobileSeekerReviewsController;
use App\Http\Controllers\Api\Mobile\MobileVideoController;
use App\Http\Controllers\Api\Mobile\MobileVideoStreamController;
use App\Http\Controllers\Api\Mobile\OnboardingController;
use App\Http\Controllers\Api\Mobile\ProviderDashboardController;
use App\Http\Controllers\Api\Mobile\ProviderLeadsController;
use App\Http\Controllers\Api\Mobile\ProviderBackgroundChecksController;
use App\Http\Controllers\Api\Mobile\ProviderAnalyticsController;
use App\Http\Controllers\Api\Mobile\MobileProviderReviewsController;
use App\Http\Controllers\Api\Mobile\ProviderNotificationsController;
use App\Http\Controllers\Api\Mobile\ProvidersController;
use App\Http\Controllers\Api\Mobile\ProviderSubscriptionsController;
use App\Http\Controllers\Api\Mobile\SeekerDashboardController;
use App\Http\Controllers\Api\Mobile\SeekerLeadsController;
use App\Http\Controllers\CommunityController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::get('/library/stream/{token}', [MobileLibraryStreamController::class, 'show'])
        ->middleware('throttle:120,1');

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('onboarding')->group(function () {
        Route::get('/meta', [OnboardingController::class, 'meta']);
        Route::post('/address/send-otp', [OnboardingController::class, 'sendOtp']);
        Route::post('/phone/verify', [OnboardingController::class, 'verifyOtp']);
        Route::post('/progress', [OnboardingController::class, 'saveProgress']);
        Route::post('/complete', [OnboardingController::class, 'complete']);
    });

    // Marketplace (public read)
    Route::get('/providers', [ProvidersController::class, 'index']);
    Route::get('/providers/{provider:slug}', [ProvidersController::class, 'show']);

    // Seeker actions
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/providers/{provider:slug}/leads', [SeekerLeadsController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'role:user'])->get('/seeker/dashboard', SeekerDashboardController::class);

    Route::middleware('auth:sanctum')->get('/dv-lottery', DvLotteryController::class);

    Route::middleware(['auth:sanctum', 'role:user'])->prefix('ai-assistant')->group(function () {
        Route::get('/', [AiAssistantController::class, 'show']);
        Route::post('/checkout', [AiAssistantController::class, 'checkout']);
        Route::post('/ask', [AiAssistantController::class, 'ask']);
    });

    Route::middleware('auth:sanctum')->prefix('library')->group(function () {
        Route::get('/browse', [MobileLibraryController::class, 'browse']);
        Route::get('/my', [MobileLibraryController::class, 'my']);
        Route::get('/items/{item:slug}', [MobileLibraryController::class, 'show']);
        Route::post('/items/{item:slug}/favorite', [MobileLibraryController::class, 'toggleFavorite']);
        Route::post('/items/{item:slug}/checkout', [MobileLibraryController::class, 'stripeCheckout']);
        Route::post('/items/{item:slug}/grant-free', [MobileLibraryController::class, 'grantFree']);
        Route::post('/items/{item:slug}/progress', [MobileLibraryController::class, 'updateProgress']);
        Route::get('/items/{item:slug}/summary', [MobileLibraryController::class, 'summary']);
        Route::post('/items/{item:slug}/stream-urls', [MobileLibraryController::class, 'streamUrls']);
    });

    Route::middleware(['auth:sanctum', 'role:user'])->prefix('videos')->group(function () {
        Route::get('/', [MobileVideoController::class, 'index']);
        Route::get('/{video:slug}/stream', MobileVideoStreamController::class);
        Route::post('/{video:slug}/checkout', [MobileVideoController::class, 'stripeCheckout']);
        Route::post('/{video:slug}/grant-free', [MobileVideoController::class, 'grantFree']);
        Route::get('/{video:slug}', [MobileVideoController::class, 'show']);
    });

    Route::middleware('auth:sanctum')->prefix('community')->group(function () {
        Route::get('/posts', [CommunityController::class, 'posts']);
        Route::get('/news', [CommunityController::class, 'news']);
        Route::get('/posts/{communityPost}/comments', [CommunityController::class, 'comments']);
        Route::get('/posts/{communityPost}', [CommunityController::class, 'show']);
        Route::post('/posts/{communityPost}/react', [CommunityController::class, 'react'])
            ->middleware('throttle:120,1');
        Route::post('/posts/{communityPost}/comments', [CommunityController::class, 'addComment'])
            ->middleware('throttle:60,1');
    });

    Route::middleware(['auth:sanctum', 'role:user|provider|advertiser'])->prefix('ads')->group(function () {
        Route::get('/', [MobileAdsController::class, 'index']);
        Route::get('/analytics', [MobileAdsController::class, 'analytics']);
        Route::post('/', [MobileAdsController::class, 'store']);
        Route::get('/{ad:uuid}', [MobileAdsController::class, 'show']);
        Route::patch('/{ad:uuid}', [MobileAdsController::class, 'update']);
        Route::delete('/{ad:uuid}', [MobileAdsController::class, 'destroy']);
        Route::post('/{ad:uuid}/checkout', [MobileAdsController::class, 'checkout']);
        Route::post('/{ad:uuid}/resubmit', [MobileAdsController::class, 'resubmit']);
    });

    Route::middleware(['auth:sanctum', 'role:user'])->prefix('reviews')->group(function () {
        Route::get('/', [MobileSeekerReviewsController::class, 'index']);
        Route::post('/providers/{provider:slug}', [MobileSeekerReviewsController::class, 'store']);
        Route::post('/{review:uuid}/helpful', [MobileSeekerReviewsController::class, 'helpful']);
        Route::patch('/{review:uuid}', [MobileSeekerReviewsController::class, 'update']);
        Route::delete('/{review:uuid}', [MobileSeekerReviewsController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'role:provider'])->prefix('provider')->group(function () {
        Route::get('/dashboard', ProviderDashboardController::class);
        Route::get('/subscriptions', [ProviderSubscriptionsController::class, 'index']);
        Route::post('/subscriptions/checkout/{plan:uuid}', [ProviderSubscriptionsController::class, 'checkout']);
        Route::post('/subscriptions/{subscription:uuid}/cancel', [ProviderSubscriptionsController::class, 'cancel']);
        Route::post('/subscriptions/{subscription:uuid}/resume', [ProviderSubscriptionsController::class, 'resume']);
        Route::post('/subscriptions/{subscription:uuid}/change-plan/{plan:uuid}', [ProviderSubscriptionsController::class, 'changePlan']);
        Route::get('/notifications', [ProviderNotificationsController::class, 'index']);
        Route::post('/notifications/read-all', [ProviderNotificationsController::class, 'markAllAsRead']);
        Route::post('/notifications/{notification}/read', [ProviderNotificationsController::class, 'markAsRead']);
        Route::get('/analytics', ProviderAnalyticsController::class);
        Route::get('/reviews', [MobileProviderReviewsController::class, 'index']);
        Route::get('/background-checks', [ProviderBackgroundChecksController::class, 'index']);
        Route::post('/background-checks', [ProviderBackgroundChecksController::class, 'store']);
        Route::get('/background-checks/{backgroundCheck:uuid}', [ProviderBackgroundChecksController::class, 'show']);
        Route::post('/background-checks/{backgroundCheck:uuid}/refresh', [ProviderBackgroundChecksController::class, 'refresh']);
    });

    Route::middleware('auth:sanctum')->prefix('messages')->group(function () {
        Route::get('/', [MessagesController::class, 'index']);
        Route::get('/archived', [MessagesController::class, 'archived']);
        Route::get('/unread-count', [MessagesController::class, 'unreadCount']);
        Route::get('/{conversation:uuid}', [MessagesController::class, 'show']);
        Route::post('/{conversation:uuid}', [MessagesController::class, 'send']);
        Route::post('/{conversation:uuid}/read', [MessagesController::class, 'markAsRead']);
        Route::post('/{conversation:uuid}/archive', [MessagesController::class, 'archive']);
        Route::post('/{conversation:uuid}/unarchive', [MessagesController::class, 'unarchive']);
        Route::delete('/{conversation:uuid}', [MessagesController::class, 'destroy']);
    });

    Route::middleware('auth:sanctum')->prefix('contracts')->group(function () {
        Route::get('/', [ContractsController::class, 'index']);
        Route::get('/{contract:uuid}', [ContractsController::class, 'show']);
        Route::post('/lead/{lead:uuid}/send', [ContractsController::class, 'send']);
        Route::post('/lead/{lead:uuid}/withdraw', [ContractsController::class, 'withdraw']);
        Route::post('/lead/{lead:uuid}/end', [ContractsController::class, 'end']);
        Route::post('/{contract:uuid}/accept', [ContractsController::class, 'accept']);
    });

    Route::middleware(['auth:sanctum', 'role:provider'])->prefix('provider/leads')->group(function () {
        Route::get('/', [ProviderLeadsController::class, 'index']);
        Route::get('/{lead:uuid}', [ProviderLeadsController::class, 'show']);
        Route::patch('/{lead:uuid}/status', [ProviderLeadsController::class, 'updateStatus']);
        Route::post('/{lead:uuid}/notes', [ProviderLeadsController::class, 'addNote']);
        Route::post('/{lead:uuid}/conversation', [ProviderLeadsController::class, 'createConversation']);
        Route::post('/{lead:uuid}/decline', [ProviderLeadsController::class, 'decline']);
    });
});
