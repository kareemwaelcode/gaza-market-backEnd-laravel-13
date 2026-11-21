<?php

namespace App\Http\Requests\Cms;

class RoleRequest extends GuardedNameRequest
{
    protected function table(): string
    {
        return config('permission.table_names.roles');
    }

    protected function resourceParam(): string
    {
        return 'role';
    }
}