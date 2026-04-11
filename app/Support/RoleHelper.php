<?php

namespace App\Support;

use Spatie\Permission\Models\Role;

class RoleHelper
{
    public static function ensureExists(string $roleName, string $guardName = 'web'): Role
    {
        return Role::findOrCreate($roleName, $guardName);
    }
}
