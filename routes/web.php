<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Provider;
use App\Http\Controllers\User;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', WelcomeController::class)->name('home');

// Public library access
Route::get('/library', [LibraryController::class, 'index'])->name('library.index');

// Marketplace (public)
Route::get('/providers', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/providers/{provider:slug}', [MarketplaceController::class, 'show'])->name('marketplace.show');

// Affiliate tracking
Route::get('/go/{tracking_code}', [AffiliateController::class, 'track'])->name('affiliate.track');

// DV Lottery link
Route::get('/dv-lottery', fn() => redirect('https://dvprogram.state.gov/'))->name('dv-lottery');

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [Auth\RegisterController::class, 'create'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store']);
    Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store']);
});

Route::post('/logout', [Auth\LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

// Onboarding - auth only (no email verification required)
Route::middleware(['auth'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/progress', [OnboardingController::class, 'saveProgress'])->name('onboarding.progress');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});

// Routes requiring completed onboarding
Route::middleware(['auth', 'onboarding.complete'])->group(function () {
        
        // User Dashboard
        Route::get('/dashboard', [User\DashboardController::class, 'index'])->name('dashboard');
        
        // Profile
        Route::get('/profile', [User\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [User\ProfileController::class, 'update'])->name('profile.update');
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
        });

        // Leads (for users - creating inquiries)
        Route::get('/providers/{provider:slug}/contact', [LeadController::class, 'create'])->name('leads.create');
        Route::post('/providers/{provider:slug}/contact', [LeadController::class, 'store'])->name('leads.store');

        // Digital Library
        Route::prefix('library')->name('library.')->group(function () {
            Route::get('/ebooks', [LibraryController::class, 'ebooks'])->name('ebooks');
            Route::get('/audiobooks', [LibraryController::class, 'audiobooks'])->name('audiobooks');
            Route::get('/{item:slug}', [LibraryController::class, 'show'])->name('show');
            Route::get('/{item:slug}/download', [LibraryController::class, 'download'])->name('download');
            Route::post('/{item:slug}/favorite', [LibraryController::class, 'toggleFavorite'])->name('favorite');
        });

        // Reviews
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
        
        // Profile management
        Route::get('/profile', [Provider\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [Provider\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [Provider\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        
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
        
        // Identity Verification
        Route::get('/verification', [Provider\VerificationController::class, 'index'])->name('verification.index');
        Route::post('/verification', [Provider\VerificationController::class, 'store'])->name('verification.store');
        
        // Analytics
        Route::get('/analytics', [Provider\AnalyticsController::class, 'index'])->name('analytics.index');
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
        
        Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');
        
        // Users
        Route::resource('users', Admin\UserController::class);
        
        // Providers
        Route::resource('providers', Admin\ProviderController::class);
        Route::post('/providers/{provider}/verify', [Admin\ProviderController::class, 'verify'])->name('providers.verify');
        
        // Verifications
        Route::get('/verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{verification:uuid}', [Admin\VerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{verification:uuid}/approve', [Admin\VerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{verification:uuid}/reject', [Admin\VerificationController::class, 'reject'])->name('verifications.reject');
        
        // Reviews
        Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review:uuid}/approve', [Admin\ReviewController::class, 'approve'])->name('reviews.approve');
        Route::post('/reviews/{review:uuid}/reject', [Admin\ReviewController::class, 'reject'])->name('reviews.reject');
        
        // Library
        Route::resource('library', Admin\LibraryController::class);
        Route::resource('library-categories', Admin\LibraryCategoryController::class);
        
        // Affiliates
        Route::resource('affiliates', Admin\AffiliateController::class);
        
        // Videos
        Route::resource('videos', Admin\VideoController::class);
        
        // Reports
        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/users', [Admin\ReportController::class, 'users'])->name('reports.users');
        Route::get('/reports/leads', [Admin\ReportController::class, 'leads'])->name('reports.leads');
        Route::get('/reports/revenue', [Admin\ReportController::class, 'revenue'])->name('reports.revenue');
        
        // Settings
        Route::get('/settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });
