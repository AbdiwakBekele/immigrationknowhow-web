<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use App\Models\IdentityVerification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
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
            substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1)
        );
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return str_starts_with($this->avatar, 'http') 
                ? $this->avatar 
                : asset('storage/' . $this->avatar);
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

    // Helper Methods
    public function isProvider(): bool
    {
        return $this->hasRole(UserRole::PROVIDER->value);
    }

    public function hasProviderRegistration(): bool
    {
        return ! empty(data_get($this->onboarding_data, 'registration.service_type'));
    }

    public function followsProviderOnboarding(): bool
    {
        return $this->isProvider() || $this->hasProviderRegistration();
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
