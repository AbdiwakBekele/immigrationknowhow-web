<?php

namespace App\Support;

final class AdImageUpload
{
    /** @return list<string|int> */
    public static function rules(bool $nullable = true): array
    {
        $presence = $nullable ? 'nullable' : 'sometimes';

        return [
            $presence,
            'file',
            'max:5120',
            'mimes:jpeg,jpg,png,gif,webp,bmp,heic,heif',
        ];
    }
}
