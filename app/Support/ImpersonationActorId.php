<?php

namespace App\Support;

final class ImpersonationActorId
{
    /**
     * Normalize the session value stored when an admin impersonates another user.
     * Arrays or corrupted values are collapsed so Eloquent never treats the id as a list.
     */
    public static function fromSession(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value === false || $value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
