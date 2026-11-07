<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkerProfile;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_only_an_admin_can_manage_accounts(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();

        $this->get(route('admin.users.index'))->assertRedirect(route('login'));

        $this->actingAs($customer)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.home'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Accounts')
            ->assertSee('Maya Cruz')
            ->assertSee('Andres Villanueva')
            ->assertDontSee('password');
    }

    public function test_an_admin_can_create_each_role(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();

        foreach (['customer', 'admin', 'worker'] as $role) {
            $this->actingAs($admin)->post(route('admin.users.store'), [
                'name' => 'New '.ucfirst($role),
                'email' => $role.'.new@fixitdumaguete.test',
                'role' => $role,
                'password' => 'local-pass',
                'password_confirmation' => 'local-pass',
            ])->assertRedirect();

            $user = User::query()->where('email', $role.'.new@fixitdumaguete.test')->firstOrFail();
            $this->assertSame($role, $user->role);
            $this->assertTrue(password_verify('local-pass', $user->password));
        }

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Bad Role',
            'email' => 'bad@fixitdumaguete.test',
            'role' => 'owner',
            'password' => 'local-pass',
            'password_confirmation' => 'local-pass',
        ])->assertSessionHasErrors('role');
    }

    public function test_an_admin_can_update_and_delete_an_account(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.users.update', $customer), [
            'name' => 'Maya Updated',
            'email' => 'maya.updated@fixitdumaguete.test',
            'role' => 'worker',
            'password' => 'another-pass',
            'password_confirmation' => 'another-pass',
        ])->assertRedirect(route('admin.users.show', $customer));

        $customer->refresh();
        $this->assertSame('Maya Updated', $customer->name);
        $this->assertSame('worker', $customer->role);
        $this->assertTrue(password_verify('another-pass', $customer->password));

        $this->actingAs($admin)
            ->get(route('admin.users.show', $worker->user))
            ->assertOk()
            ->assertSee('View public profile');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $worker->user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($worker->user);
        $this->assertModelMissing($worker);
    }

    public function test_an_admin_cannot_remove_the_last_admin_or_their_own_account(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'customer',
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }
}
