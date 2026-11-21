<?php

namespace App\Http\Requests\Cms;

class PermissionRequest extends GuardedNameRequest
{
    protected function table(): string
    {
        return config('permission.table_names.permissions');
    }

    protected function resourceParam(): string
    {
        return 'permission';
    }
}