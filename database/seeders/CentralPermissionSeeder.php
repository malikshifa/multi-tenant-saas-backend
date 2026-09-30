<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CentralPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'tenants.view',
            'tenants.create',
            'tenants.update',
            'tenants.delete',
            'tenants.activate',
            'tenants.suspend',

            'domains.view',
            'domains.create',
            'domains.update',
            'domains.delete',

            'plans.view',
            'plans.create',
            'plans.update',
            'plans.delete',

            'subscriptions.view',
            'subscriptions.create',
            'subscriptions.update',
            'subscriptions.cancel',

            'payments.view',
            'payments.create',
            'payments.verify',
            'payments.refund',

            'invoices.view',

            'activity.view',

            'dashboard.view',

            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            'permissions.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'central',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'central',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'central',
        ]);

        $superAdmin->syncPermissions(
            Permission::where('guard_name', 'central')->get()
        );

        $admin->syncPermissions([
            'tenants.view',
            'domains.view',
            'plans.view',
            'subscriptions.view',
            'payments.view',
            'invoices.view',
            'dashboard.view',
            'users.view',
        ]);
    }
}
