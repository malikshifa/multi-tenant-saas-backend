<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CentralAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin role
        $role = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'central',
        ]);

        // Give Super Admin every central permission
        $permissions = Permission::where(
            'guard_name',
            'central'
        )->get();

        $role->syncPermissions($permissions);

        // Create Central Admin user
        $user = User::updateOrCreate(
            [
                'email' => 'superAdmin@platform.test',
            ],
            [
                'name' => 'Central Admin',
                'password' => Hash::make('password'),
            ]
        );

        // Assign Super Admin role
        $user->syncRoles([$role]);
    }
}
