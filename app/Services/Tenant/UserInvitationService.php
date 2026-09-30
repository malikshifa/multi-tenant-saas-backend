<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Notifications\Tenant\UserInvited;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class UserInvitationService
{
    public function __construct(
        protected TenantUserQuotaService $quota
    ) {}

    public function invite(Tenant $tenant, string $name, string $email, ?string $role = null): User
    {
        $this->quota->assertCapacityAvailable($tenant);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            // No usable password yet; the invitee sets one via acceptInvite().
            'password' => Str::random(40),
            'invited_at' => now(),
        ]);

        if ($role) {
            $user->assignRole($role);
        }

        $token = Password::broker('tenant_users')->createToken($user);

        $acceptUrl = rtrim(config('app.frontend_url'), '/')
            .'/tenant/accept-invite?token='.$token
            .'&email='.urlencode($user->email);

        $user->notify(new UserInvited($acceptUrl));

        return $user;
    }

    public function acceptInvite(string $email, string $token, string $password): User
    {
        $status = Password::broker('tenant_users')->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'invited_at' => null,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new RuntimeException(__($status));
        }

        return User::where('email', $email)->firstOrFail();
    }

    public function listUsers()
    {
        return User::latest()->paginate(20);
    }

    public function assignRole(User $user, string $role): User
    {
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    public function deactivate(User $user): User
    {
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return $user->fresh();
    }
}
