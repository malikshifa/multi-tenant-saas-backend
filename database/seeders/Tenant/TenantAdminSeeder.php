<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin role
        $role = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'tenant',
        ]);

        // Give Super Admin every tenant permission
        $permissions = Permission::where(
            'guard_name',
            'tenant'
        )->get();

        $role->syncPermissions($permissions);

        // Create Central Admin user
        $user = User::updateOrCreate(
            [
                'email' => 'superAdmin@ptenant',
            ],
            [
                'name' => 'Tenant Admin',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Assign Super Admin role
        $user->syncRoles([$role]);
    }
}
