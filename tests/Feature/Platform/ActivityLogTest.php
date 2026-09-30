<?php

namespace Tests\Feature\Platform;

use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_and_updating_a_plan_is_logged_with_the_acting_user_as_causer(): void
    {
        $user = User::factory()->create();
        Permission::create(['name' => 'activity.view', 'guard_name' => 'central']);
        $user->givePermissionTo('activity.view');

        Sanctum::actingAs($user, ['*'], 'central');

        $plan = Plan::create([
            'name' => 'Logged Plan', 'slug' => 'logged-plan',
            'price' => 15, 'billing_interval' => 'monthly', 'trial_days' => 0,
        ]);

        $plan->update(['price' => 20]);

        $response = $this->getJson('/api/v1/platform/activity?subject_type='.urlencode(Plan::class).'&subject_id='.$plan->id)
            ->assertOk();

        $events = collect($response->json('data'))->pluck('event');

        $this->assertTrue($events->contains('created'));
        $this->assertTrue($events->contains('updated'));

        $created = collect($response->json('data'))->firstWhere('event', 'created');
        $this->assertSame($user->id, $created['causer_id']);
    }
}
