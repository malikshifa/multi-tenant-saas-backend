<?php

namespace App\Support;

use Spatie\Permission\PermissionRegistrar;

class PermissionCacheKey
{
    public static function useForTenant(int $tenantId): string
    {
        $default = config('permission.cache.key');

        static::apply("{$default}.tenant.{$tenantId}");

        return $default;
    }

    public static function restore(string $defaultKey): void
    {
        static::apply($defaultKey);
    }

    protected static function apply(string $key): void
    {
        config(['permission.cache.key' => $key]);

        app(PermissionRegistrar::class)->initializeCache();
    }
}
