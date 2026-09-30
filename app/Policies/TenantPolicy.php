<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tenants.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tenants.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.update');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.delete');
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.suspend');
    }

    public function activate(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.activate');
    }
}
