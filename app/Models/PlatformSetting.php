<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'support_email',
        'support_phone',
        'support_address',
        'site_logo_path',
        'admin_logo_path',
        'site_tagline',
        'footer_tagline',
        'maintenance_mode',
        'reviews_auto_approve',
        'email_notifications',
        'new_provider_alerts',
        'dv_lottery_page_title',
        'dv_lottery_page_subtitle',
        'dv_lottery_description',
        'dv_lottery_official_url',
        'dv_lottery_cta_label',
        'dv_lottery_warning_text',
        'dv_lottery_open_from',
        'dv_lottery_open_to',
        'dv_lottery_show_in_menu_after_close',
    ];

    protected $appends = [
        'site_logo_url',
        'admin_logo_url',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'reviews_auto_approve' => 'boolean',
            'email_notifications' => 'boolean',
            'new_provider_alerts' => 'boolean',
            'dv_lottery_open_from' => 'date',
            'dv_lottery_open_to' => 'date',
            'dv_lottery_show_in_menu_after_close' => 'boolean',
        ];
    }

    public static function defaults(): array
    {
        return [
            'company_name' => config('app.name', 'ImmigrationKnowHow'),
            'support_email' => env('MAIL_FROM_ADDRESS'),
            'support_phone' => null,
            'support_address' => null,
            'site_logo_path' => null,
            'admin_logo_path' => null,
            'site_tagline' => 'Connect with trusted attorneys, tax experts, translators, and more to navigate your immigration journey with confidence.',
            'footer_tagline' => 'Connecting immigrants with trusted service providers since 2024.',
            'maintenance_mode' => false,
            'reviews_auto_approve' => false,
            'email_notifications' => true,
            'new_provider_alerts' => true,
            'dv_lottery_page_title' => 'DV Lottery',
            'dv_lottery_page_subtitle' => 'Official Diversity Visa information and trusted guidance.',
            'dv_lottery_description' => 'Always submit applications through the official U.S. Department of State portal.',
            'dv_lottery_official_url' => 'https://dvprogram.state.gov/',
            'dv_lottery_cta_label' => 'Open Official DV Lottery Website',
            'dv_lottery_warning_text' => 'Never use unofficial third-party links for DV submissions. Use only the official government website.',
            'dv_lottery_open_from' => null,
            'dv_lottery_open_to' => null,
            'dv_lottery_show_in_menu_after_close' => true,
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], static::defaults());
    }

    /**
     * Canonical default logo (replace public/images/logo.svg to update branding app-wide).
     * Other optional filenames are checked if logo.svg is missing.
     */
    public static function publicBrandLogoCandidates(): array
    {
        return [
            'images/logo.svg',
            'images/logo.png',
            'images/logo.webp',
            'images/logo.jpg',
            'images/logo.jpeg',
            'images/brand-logo.svg',
            'images/brand-logo.png',
            'images/site-logo.svg',
            'images/site-logo.png',
            'images/immigrants-know-how-logo.svg',
        ];
    }

    public static function defaultBrandLogoUrl(): string
    {
        foreach (static::publicBrandLogoCandidates() as $relative) {
            if (is_file(public_path($relative))) {
                return asset($relative);
            }
        }

        return asset('images/provider-portal-mark.svg');
    }

    public static function branding(): array
    {
        $defaults = static::defaults();

        if (! Schema::hasTable('platform_settings')) {
            return [
                'company_name' => $defaults['company_name'],
                'site_logo_url' => static::defaultBrandLogoUrl(),
                'admin_logo_url' => static::defaultBrandLogoUrl(),
                'support_email' => $defaults['support_email'],
                'support_phone' => $defaults['support_phone'],
                'support_address' => $defaults['support_address'],
                'site_tagline' => $defaults['site_tagline'],
                'footer_tagline' => $defaults['footer_tagline'],
            ];
        }

        $settings = static::query()->first();

        return [
            'company_name' => $settings?->company_name ?: $defaults['company_name'],
            'site_logo_url' => $settings?->site_logo_url ?? static::defaultBrandLogoUrl(),
            'admin_logo_url' => $settings?->admin_logo_url ?? static::defaultBrandLogoUrl(),
            'support_email' => $settings?->support_email ?: $defaults['support_email'],
            'support_phone' => $settings?->support_phone ?: $defaults['support_phone'],
            'support_address' => $settings?->support_address ?: $defaults['support_address'],
            'site_tagline' => $settings?->site_tagline ?: $defaults['site_tagline'],
            'footer_tagline' => $settings?->footer_tagline ?: $defaults['footer_tagline'],
        ];
    }

    public function dvLotteryContent(): array
    {
        $defaults = static::defaults();
        $today = Carbon::today();
        $openFrom = $this->dv_lottery_open_from;
        $openTo = $this->dv_lottery_open_to;
        $hasValidDateRange = $openFrom && $openTo && $openFrom->lessThanOrEqualTo($openTo);
        $isOpen = $hasValidDateRange
            && $today->greaterThanOrEqualTo($openFrom)
            && $today->lessThanOrEqualTo($openTo);
        $isClosed = $hasValidDateRange && $today->greaterThan($openTo);
        $daysUntilClose = $hasValidDateRange ? $today->diffInDays($openTo, false) : null;
        $isClosingSoon = $hasValidDateRange
            && $isOpen
            && $daysUntilClose !== null
            && $daysUntilClose >= 0
            && $daysUntilClose <= 7;
        $showAfterClose = $this->dv_lottery_show_in_menu_after_close;
        if ($showAfterClose === null) {
            $showAfterClose = true;
        }
        $isBeforeOpen = $hasValidDateRange && $today->lessThan($openFrom);
        $showInMenu = ! $hasValidDateRange
            || $isBeforeOpen
            || $isOpen
            || ($isClosed && (bool) $showAfterClose);

        return [
            'title' => $this->dv_lottery_page_title ?: $defaults['dv_lottery_page_title'],
            'short_description' => $this->dv_lottery_page_subtitle ?: $defaults['dv_lottery_page_subtitle'],
            'description' => $this->dv_lottery_description ?: $defaults['dv_lottery_description'],
            'official_url' => $this->dv_lottery_official_url ?: $defaults['dv_lottery_official_url'],
            'cta_label' => $this->dv_lottery_cta_label ?: $defaults['dv_lottery_cta_label'],
            'warning_text' => $this->dv_lottery_warning_text ?: $defaults['dv_lottery_warning_text'],
            'open_from' => optional($openFrom)->toDateString(),
            'open_to' => optional($openTo)->toDateString(),
            'show_in_menu_after_close' => (bool) $showAfterClose,
            'is_open' => $isOpen,
            'is_closed' => $isClosed,
            'is_closing_soon' => $isClosingSoon,
            'show_in_menu' => $showInMenu,
            'status_message' => $isClosed
                ? 'The DV Lottery is now closed'
                : ($isClosingSoon ? 'Hurry up! DV Lottery sign-up will be closed soon' : null),
        ];
    }

    public function getSiteLogoUrlAttribute(): string
    {
        return $this->site_logo_path
            ? asset('storage/'.$this->site_logo_path)
            : static::defaultBrandLogoUrl();
    }

    public function getAdminLogoUrlAttribute(): string
    {
        return $this->admin_logo_path
            ? asset('storage/'.$this->admin_logo_path)
            : static::defaultBrandLogoUrl();
    }
}