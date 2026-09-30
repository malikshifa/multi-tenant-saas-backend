<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            'permissions.view',

            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',

            'products.view',
            'products.create',
            'products.update',
            'products.delete',

            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.cancel',

            'users.view',
            'users.invite',
            'users.assign-role',
            'users.deactivate',

            'activity.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'tenant',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'tenant',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'tenant',
        ]);

        $superAdmin->syncPermissions(
            Permission::where('guard_name', 'tenant')->get()
        );

        $admin->syncPermissions([]);
    }
}
