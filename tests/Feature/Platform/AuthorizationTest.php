<?php

namespace Tests\Feature\Platform;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function planPayload(): array
    {
        return [
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 10,
            'billing_interval' => 'monthly',
            'trial_days' => 0,
        ];
    }

    public function test_guests_cannot_reach_platform_endpoints(): void
    {
        $this->getJson('/api/v1/platform/plans')->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*'], 'central');

        $this->getJson('/api/v1/platform/plans')->assertForbidden();
        $this->postJson('/api/v1/platform/plans', $this->planPayload())->assertForbidden();
        $this->getJson('/api/v1/platform/payments')->assertForbidden();
        $this->getJson('/api/v1/platform/tenants/1/domains')->assertForbidden();
    }

    public function test_user_with_permission_can_create_a_plan(): void
    {
        $user = User::factory()->create();

        Permission::create(['name' => 'plans.create', 'guard_name' => 'central']);
        $user->givePermissionTo('plans.create');

        Sanctum::actingAs($user, ['*'], 'central');

        $this->postJson('/api/v1/platform/plans', $this->planPayload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'starter');

        $this->getJson('/api/v1/platform/plans')->assertForbidden();
    }

    public function test_tenant_show_route_resolves_a_real_tenant_parameter(): void
    {
        $this->assertSame('/api/v1/platform/tenants/5', route('tenants.show', ['tenant' => 5], false));
    }
}
