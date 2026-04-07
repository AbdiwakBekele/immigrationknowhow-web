<?php

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case IN_PROGRESS = 'in_progress';
    case CONVERTED = 'converted';
    case CLOSED = 'closed';
    case DECLINED = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New Lead',
            self::CONTACTED => 'Contacted',
            self::IN_PROGRESS => 'In Progress',
            self::CONVERTED => 'Converted',
            self::CLOSED => 'Closed',
            self::DECLINED => 'Declined',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEW => 'blue',
            self::CONTACTED => 'indigo',
            self::IN_PROGRESS => 'yellow',
            self::CONVERTED => 'green',
            self::CLOSED => 'gray',
            self::DECLINED => 'red',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::NEW => 'bg-blue-100 text-blue-800',
            self::CONTACTED => 'bg-indigo-100 text-indigo-800',
            self::IN_PROGRESS => 'bg-amber-100 text-amber-800',
            self::CONVERTED => 'bg-emerald-100 text-emerald-800',
            self::CLOSED => 'bg-gray-100 text-gray-800',
            self::DECLINED => 'bg-red-100 text-red-800',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
