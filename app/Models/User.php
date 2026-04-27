<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'phone_verified_at',
        'avatar',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'languages',
        'preferred_language',
        'timezone',
        'onboarding_completed',
        'onboarding_data',
        'onboarding_completed_at',
        'referred_by_affiliate_id',
        'affiliate_referral_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'languages' => 'array',
            'onboarding_data' => 'array',
            'onboarding_completed' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    protected $appends = ['full_name', 'initials', 'avatar_url'];

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name, 0, 1).substr($this->last_name, 0, 1)
        );
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return str_starts_with($this->avatar, 'http')
                ? $this->avatar
                : asset('storage/'.$this->avatar);
        }

        return null;
    }

    // Relationships
    public function serviceProvider(): HasOne
    {
        return $this->hasOne(ServiceProvider::class);
    }

    public function affiliateProfile(): HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function affiliateReferrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class, 'referred_user_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function identityVerifications(): HasMany
    {
        return $this->hasMany(IdentityVerification::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function libraryAccess(): HasMany
    {
        return $this->hasMany(LibraryUserAccess::class);
    }

    public function videoAccess(): HasMany
    {
        return $this->hasMany(VideoUserAccess::class);
    }

    public function referredByAffiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'referred_by_affiliate_id');
    }

    public function affiliateReferral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'affiliate_referral_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeProviders($query)
    {
        return $query->role(UserRole::PROVIDER->value);
    }

    public function scopeGeneralUsers($query)
    {
        return $query->role(UserRole::USER->value);
    }

    public function scopeAffiliates($query)
    {
        return $query->role(UserRole::AFFILIATE->value);
    }

    public function scopeAdvertisers($query)
    {
        return $query->role(UserRole::ADVERTISER->value);
    }

    // Helper Methods
    public function isProvider(): bool
    {
        return $this->hasRole(UserRole::PROVIDER->value);
    }

    public function hasProviderRegistration(): bool
    {
        $registrationRole = data_get($this->onboarding_data, 'registration.role');
        if (is_string($registrationRole) && strtolower($registrationRole) === UserRole::PROVIDER->value) {
            return true;
        }

        $legacyRole = data_get($this->onboarding_data, 'role');
        if (is_string($legacyRole) && strtolower($legacyRole) === UserRole::PROVIDER->value) {
            return true;
        }

        $serviceType = data_get($this->onboarding_data, 'registration.service_type')
            ?? data_get($this->onboarding_data, 'service_type');

        if (is_string($serviceType) && trim($serviceType) !== '') {
            return true;
        }

        $coverageArea = data_get($this->onboarding_data, 'coverage_area');

        return is_array($coverageArea) && (
            filled($coverageArea['country'] ?? null)
            || filled($coverageArea['state'] ?? null)
            || filled($coverageArea['postal_code'] ?? null)
        );
    }

    public function followsProviderOnboarding(): bool
    {
        return $this->isProvider() || $this->hasProviderRegistration();
    }

    /**
     * Step 2 of signup is saved (coverage for providers, address for clients).
     */
    public function hasCompletedSignupAddressStep(): bool
    {
        if ($this->followsProviderOnboarding()) {
            $c = $this->onboarding_data['coverage_area'] ?? [];
            if (empty($c['country']) || $c['state'] === null || $c['state'] === '') {
                return false;
            }
            if (strtoupper((string) ($c['country'] ?? '')) === 'US' && ! filled($c['postal_code'] ?? null)) {
                return false;
            }

            return true;
        }

        if (! $this->city || ! $this->state || ! $this->country) {
            return false;
        }

        if (strtoupper((string) $this->country) === 'US' && ! filled($this->postal_code)) {
            return false;
        }

        return true;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value]);
    }

    public function isAffiliate(): bool
    {
        return $this->hasRole(UserRole::AFFILIATE->value);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SUPER_ADMIN->value);
    }

    public function isAdvertiser(): bool
    {
        return $this->hasRole(UserRole::ADVERTISER->value);
    }

    /**
     * Signup phone verification is valid only when timestamp exists and phone is stored.
     */
    public function hasCompletedSignupPhoneStep(): bool
    {
        return filled($this->phone) && ! is_null($this->phone_verified_at);
    }

    /**
     * First page this user should land on after login (or admin impersonation).
     */
    public function defaultAuthenticatedHomeUrl(): string
    {
        if ($this->isAffiliate() && ! $this->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if ($this->isAffiliate()) {
            return route('affiliate.dashboard');
        }

        if (! $this->isAffiliate() && ! $this->hasCompletedSignupPhoneStep() && ! $this->isAdmin()) {
            if ($this->isAdvertiser()) {
                return $this->hasCompletedSignupAddressStep()
                    ? route('onboarding.advertiser', ['step' => 3])
                    : route('onboarding.advertiser', ['step' => 2]);
            }

            if ($this->followsProviderOnboarding()) {
                return $this->hasCompletedSignupAddressStep()
                    ? route('onboarding.index', ['step' => 3])
                    : route('address-detail');
            }

            return route('onboarding.index', ['step' => 2]);
        }

        if ($this->isAdvertiser() && ! $this->hasCompletedOnboarding()) {
            return route('onboarding.advertiser');
        }

        if (! $this->hasCompletedOnboarding()) {
            return route('onboarding.index');
        }

        if ($this->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($this->isProvider()) {
            return route('provider.dashboard');
        }

        if ($this->isAdvertiser()) {
            return route('advertiser.dashboard');
        }

        return route('dashboard');
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed;
    }

    public function canAccessAffiliatePortal(): bool
    {
        return $this->isAffiliate()
            && $this->affiliateProfile
            && $this->affiliateProfile->isPortalAccessible();
    }

    public function getLatestVerification()
    {
        return $this->identityVerifications()
            ->latest()
            ->first();
    }

    public function isVerified(): bool
    {
        $verification = $this->getLatestVerification();

        return $verification && $verification->status === 'approved';
    }

    public function updateLastLogin(): void
    {
        $this->updateQuietly(['last_login_at' => now()]);
    }
}
