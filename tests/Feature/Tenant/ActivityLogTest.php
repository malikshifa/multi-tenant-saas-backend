<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Activity Co']);
        $this->tenant->domains()->create(['domain' => 'activity-co.test']);

        $this->token = $this->postJson('http://activity-co.test/api/v1/tenant/login', [
            'email' => 'superAdmin@ptenant',
            'password' => 'password',
        ])->json('data.token');
    }

    protected function tearDown(): void
    {
        app(TenantProvisioningService::class)->destroy($this->tenant);

        parent::tearDown();
    }

    public function test_creating_a_customer_is_logged_with_the_acting_user_as_causer(): void
    {
        $customerId = $this->withToken($this->token)
            ->postJson('http://activity-co.test/api/v1/tenant/customers', ['name' => 'Logged Customer'])
            ->json('data.id');

        $response = $this->withToken($this->token)
            ->getJson('http://activity-co.test/api/v1/tenant/activity?subject_type='.urlencode(Customer::class).'&subject_id='.$customerId)
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('created', $response->json('data.0.event'));
        $this->assertNotNull($response->json('data.0.causer_id'));
    }
}
