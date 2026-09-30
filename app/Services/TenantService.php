<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantService
{
    public function create(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'status' => TenantStatus::PROVISIONING,
            'database_name' => 'tenant_'.Str::lower(Str::random(16)),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);

        ProvisionTenantJob::dispatch($tenant->id);

        return $tenant;
    }

    public function suspend(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => TenantStatus::SUSPENDED]);

        return $tenant->fresh();
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => TenantStatus::ACTIVE]);

        return $tenant->fresh();
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $suffix = 2;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
