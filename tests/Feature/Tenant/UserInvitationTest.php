<?php

namespace Tests\Feature\Tenant;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Notifications\Tenant\UserInvited;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Invite Co']);
        $this->tenant->domains()->create(['domain' => 'invite-co.test']);

        $this->adminToken = $this->postJson('http://invite-co.test/api/v1/tenant/login', [
            'email' => 'superAdmin@ptenant',
            'password' => 'password',
        ])->json('data.token');
    }

    protected function tearDown(): void
    {
        app(TenantProvisioningService::class)->destroy($this->tenant);

        parent::tearDown();
    }

    public function test_full_invite_lifecycle(): void
    {
        Notification::fake();

        $this->withToken($this->adminToken)
            ->postJson('http://invite-co.test/api/v1/tenant/users/invite', [
                'name' => 'New Person',
                'email' => 'new@invite-co.test',
            ])
            ->assertCreated();

        $acceptUrl = null;

        Notification::assertSentTo(
            User::where('email', 'new@invite-co.test')->firstOrFail(),
            UserInvited::class,
            function (UserInvited $notification) use (&$acceptUrl) {
                $acceptUrl = $notification->acceptUrl;

                return true;
            }
        );

        $this->assertNotNull($acceptUrl);
        parse_str(parse_url($acceptUrl, PHP_URL_QUERY), $query);

        $this->postJson('http://invite-co.test/api/v1/tenant/accept-invite', [
            'email' => 'new@invite-co.test',
            'token' => $query['token'],
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->postJson('http://invite-co.test/api/v1/tenant/login', [
            'email' => 'new@invite-co.test',
            'password' => 'newpassword123',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_invite_is_blocked_once_plan_user_limit_is_reached(): void
    {
        $plan = Plan::create([
            'name' => 'Solo', 'slug' => 'solo-invite-'.uniqid(),
            'price' => 5, 'billing_interval' => 'monthly',
            'trial_days' => 0, 'max_users' => 1,
        ]);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
        ]);

        $this->withToken($this->adminToken)
            ->postJson('http://invite-co.test/api/v1/tenant/users/invite', [
                'name' => 'Blocked Person',
                'email' => 'blocked@invite-co.test',
            ])
            ->assertStatus(422);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        app(TenantDatabaseManager::class)->connect($this->tenant);
        app()->instance('currentTenant', $this->tenant);

        $user = User::create([
            'name' => 'Bench Warmer',
            'email' => 'bench@invite-co.test',
            'password' => 'password',
        ]);

        Permission::firstOrCreate(['name' => 'users.deactivate', 'guard_name' => 'tenant']);

        app()->forgetInstance('currentTenant');

        $this->withToken($this->adminToken)
            ->postJson("http://invite-co.test/api/v1/tenant/users/{$user->id}/deactivate")
            ->assertOk();

        $this->postJson('http://invite-co.test/api/v1/tenant/login', [
            'email' => 'bench@invite-co.test',
            'password' => 'password',
        ])->assertForbidden();
    }
}
