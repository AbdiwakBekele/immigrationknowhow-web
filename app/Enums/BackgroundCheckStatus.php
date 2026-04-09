<?php

namespace App\Enums;

enum BackgroundCheckStatus: string
{
    case PENDING = 'pending';
    case INVITED = 'invited';
    case COMPLETED = 'completed';
    case CLEAR = 'clear';
    case CONSIDER = 'consider';
    case SUSPENDED = 'suspended';
    case DISPUTE = 'dispute';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::INVITED => 'Invitation Sent',
            self::COMPLETED => 'Review in Progress',
            self::CLEAR => 'Cleared',
            self::CONSIDER => 'Review Required',
            self::SUSPENDED => 'Suspended',
            self::DISPUTE => 'Under Dispute',
            self::EXPIRED => 'Expired',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Background check has not been started.',
            self::INVITED => 'An invitation has been sent. Please check your email to complete the background check.',
            self::COMPLETED => 'Your background check is complete and under review.',
            self::CLEAR => 'Your background check has been cleared. You are verified.',
            self::CONSIDER => 'Your background check requires additional review.',
            self::SUSPENDED => 'Your background check has been suspended.',
            self::DISPUTE => 'You have initiated a dispute on your background check.',
            self::EXPIRED => 'Your background check has expired and needs renewal.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::INVITED => 'blue',
            self::COMPLETED => 'yellow',
            self::CLEAR => 'green',
            self::CONSIDER => 'orange',
            self::SUSPENDED => 'red',
            self::DISPUTE => 'purple',
            self::EXPIRED => 'gray',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-gray-100 text-gray-800',
            self::INVITED => 'bg-blue-100 text-blue-800',
            self::COMPLETED => 'bg-amber-100 text-amber-800',
            self::CLEAR => 'bg-emerald-100 text-emerald-800',
            self::CONSIDER => 'bg-orange-100 text-orange-800',
            self::SUSPENDED => 'bg-red-100 text-red-800',
            self::DISPUTE => 'bg-purple-100 text-purple-800',
            self::EXPIRED => 'bg-gray-100 text-gray-600',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::INVITED => 'envelope',
            self::COMPLETED => 'magnifying-glass',
            self::CLEAR => 'check-badge',
            self::CONSIDER => 'exclamation-triangle',
            self::SUSPENDED => 'x-circle',
            self::DISPUTE => 'document-text',
            self::EXPIRED => 'calendar-days',
        };
    }

    public function isPassed(): bool
    {
        return $this === self::CLEAR;
    }

    public function isActionable(): bool
    {
        return in_array($this, [self::PENDING, self::EXPIRED]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
