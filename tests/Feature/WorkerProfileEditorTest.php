<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkerProfile;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkerProfileEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_a_worker_photo_appears_on_the_professionals_list_and_profile(): void
    {
        Storage::fake('public');

        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $profile = $worker->workerProfile;
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($customer)
            ->post(route('work.photo.update'), [
                'photo' => UploadedFile::fake()->image('face.jpg'),
            ])
            ->assertForbidden();

        $this->actingAs($worker)
            ->post(route('work.photo.update'), [
                'photo' => UploadedFile::fake()->image('face.jpg', 400, 400),
            ])
            ->assertRedirect(route('work.edit'));

        $profile->refresh();
        $this->assertNotNull($profile->photo_path);
        Storage::disk('public')->assertExists($profile->photo_path);

        $this->get(route('workers.index'))
            ->assertOk()
            ->assertSee($profile->photoUrl(), false)
            ->assertSee('Photo of Andres Villanueva', false);

        $this->get(route('workers.show', $profile))
            ->assertOk()
            ->assertSee($profile->photoUrl(), false);

        $path = $profile->photo_path;

        $this->actingAs($worker)
            ->delete(route('work.photo.destroy'))
            ->assertRedirect(route('work.edit'));

        $this->assertNull($profile->fresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_admin_can_add_a_photo_skill_and_project_for_a_worker(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $profile = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.workers.photo.update', $profile), [
                'photo' => UploadedFile::fake()->image('andres.png'),
            ])
            ->assertRedirect(route('admin.workers.edit', $profile));

        $this->actingAs($admin)
            ->post(route('admin.workers.skills.store', $profile), ['name' => 'Leak repair'])
            ->assertRedirect(route('admin.workers.edit', $profile));

        $this->actingAs($admin)
            ->post(route('admin.workers.projects.store', $profile), [
                'title' => 'Kitchen sink',
                'summary' => 'Replaced a worn trap under a kitchen sink.',
                'completed_year' => '2024',
            ])
            ->assertRedirect(route('admin.workers.edit', $profile));

        $this->actingAs($admin)
            ->post(route('admin.workers.portfolio.store', $profile), [
                'image' => UploadedFile::fake()->image('sink.webp'),
                'caption' => 'Finished trap',
            ])
            ->assertRedirect(route('admin.workers.edit', $profile));

        $profile->refresh();

        $this->get(route('workers.show', $profile))
            ->assertOk()
            ->assertSee('Leak repair')
            ->assertSee('Kitchen sink')
            ->assertSee('Finished trap')
            ->assertSee($profile->photoUrl(), false);
    }
}
