<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class DummyDatabaseNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => 'Hello from a test notification.'];
    }
}

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Notify Tenant Co']);
        $this->tenant->domains()->create(['domain' => 'notify-tenant.test']);
    }

    protected function tearDown(): void
    {
        app(TenantProvisioningService::class)->destroy($this->tenant);

        parent::tearDown();
    }

    public function test_tenant_user_can_list_and_mark_notifications_as_read(): void
    {
        app(TenantDatabaseManager::class)->connect($this->tenant);
        app()->instance('currentTenant', $this->tenant);

        $user = User::create([
            'name' => 'Staff',
            'email' => 'staff@notify-tenant.test',
            'password' => 'password',
        ]);

        $user->notify(new DummyDatabaseNotification);

        app()->forgetInstance('currentTenant');

        $token = $this->postJson('http://notify-tenant.test/api/v1/tenant/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');

        $list = $this->withToken($token)
            ->getJson('http://notify-tenant.test/api/v1/tenant/notifications')
            ->assertOk();

        $this->assertCount(1, $list->json('data'));
        $id = $list->json('data.0.id');

        $this->withToken($token)
            ->postJson("http://notify-tenant.test/api/v1/tenant/notifications/{$id}/read")
            ->assertOk();

        $this->withToken($token)
            ->getJson('http://notify-tenant.test/api/v1/tenant/notifications')
            ->assertJsonPath('data.0.read_at', fn ($value) => $value !== null);
    }
}
