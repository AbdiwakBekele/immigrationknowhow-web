<?php

namespace App\Support;

class LanguageOptions
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'en' => 'English',
            'es' => 'Spanish',
            'zh' => 'Chinese (Mandarin)',
            'hi' => 'Hindi',
            'ar' => 'Arabic',
            'pt' => 'Portuguese',
            'fr' => 'French',
            'de' => 'German',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'vi' => 'Vietnamese',
            'tl' => 'Tagalog',
            'ru' => 'Russian',
            'it' => 'Italian',
            'pl' => 'Polish',
            'uk' => 'Ukrainian',
            'fa' => 'Persian',
            'tr' => 'Turkish',
            'th' => 'Thai',
            'he' => 'Hebrew',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $code) => ['value' => $code, 'label' => $label])
            ->values()
            ->all();
    }
}
