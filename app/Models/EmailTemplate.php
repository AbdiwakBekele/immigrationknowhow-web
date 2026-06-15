<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    public const EVENT_INVITE = 'invite';

    public const EVENT_WELCOME = 'welcome';

    public const EVENT_ACCOUNT_ACTIVATION = 'account_activation';

    public const EVENT_PASSWORD_RESET = 'password_reset';

    public const EVENT_EBOOK_COUPON = 'ebook_coupon';

    protected $fillable = [
        'event_key',
        'role',
        'name',
        'subject',
        'body',
        'action_label',
        'action_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function supportedEvents(): array
    {
        return [
            self::EVENT_INVITE,
            self::EVENT_WELCOME,
            self::EVENT_ACCOUNT_ACTIVATION,
            self::EVENT_PASSWORD_RESET,
            self::EVENT_EBOOK_COUPON,
        ];
    }

    public static function supportedRoles(): array
    {
        return array_merge([null], UserRole::values());
    }
}
