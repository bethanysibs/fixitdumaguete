<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerPortfolioImage;
use App\Models\WorkerProfile;
use App\Models\WorkerProject;
use App\Models\WorkerSkill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkerProfileEditorController extends Controller
{
    public function edit(Request $request, ?WorkerProfile $workerProfile = null): View|RedirectResponse
    {
        if ($request->routeIs('admin.workers.edit')) {
            return view('workers.studio', $this->studioData($this->profile($request, $workerProfile), true));
        }

        abort_unless($request->user()->role === 'worker', 403);

        $profile = $request->user()->workerProfile;

        if ($profile === null) {
            return view('workers.studio-create', $this->createData($request->user(), false));
        }

        return view('workers.studio', $this->studioData($profile, false));
    }

    public function create(Request $request, User $user): View|RedirectResponse
    {
        abort_unless($user->role === 'worker', 404);

        if ($user->workerProfile) {
            return redirect()->route('admin.workers.edit', $user->workerProfile);
        }

        return view('workers.studio-create', $this->createData($user, true));
    }

    public function store(Request $request, ?User $user = null): RedirectResponse
    {
        $worker = $this->workerWithoutProfile($request, $user);
        $data = $request->validate($this->detailRules($request->routeIs('admin.workers.store')));

        $profile = WorkerProfile::query()->create([
            'user_id' => $worker->id,
            'slug' => $this->uniqueSlug($worker->name),
            'headline' => $data['headline'],
            'bio' => $data['bio'],
            'years_experience' => $data['years_experience'] ?? null,
            'availability_note' => $data['availability_note'] ?? null,
            'verification_status' => $request->routeIs('admin.workers.store') ? $data['verification_status'] : 'pending',
            'is_sample' => false,
        ]);
        $profile->locations()->sync($data['locations']);

        return $this->redirectToEditor($request, $profile)
            ->with('success', 'The public profile was created. It appears to customers after it is approved.');
    }

    public function update(Request $request, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        $admin = $request->routeIs('admin.workers.update');
        $data = $request->validate($this->detailRules($admin));

        $profile->update([
            'headline' => $data['headline'],
            'bio' => $data['bio'],
            'years_experience' => $data['years_experience'] ?? null,
            'availability_note' => $data['availability_note'] ?? null,
            'verification_status' => $admin ? $data['verification_status'] : $profile->verification_status,
        ]);
        $profile->locations()->sync($data['locations']);

        return $this->redirectToEditor($request, $profile)->with('success', 'The profile was saved.');
    }

    public function updatePhoto(Request $request, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($profile->photo_path) {
            Storage::disk('public')->delete($profile->photo_path);
        }

        $profile->update([
            'photo_path' => $data['photo']->store('worker-photos', 'public'),
        ]);

        return $this->redirectToEditor($request, $profile)->with('success', 'The profile photo was saved.');
    }

    public function destroyPhoto(Request $request, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);

        if ($profile->photo_path) {
            Storage::disk('public')->delete($profile->photo_path);
            $profile->update(['photo_path' => null]);
        }

        return $this->redirectToEditor($request, $profile)->with('success', 'The profile photo was removed.');
    }

    public function storeSkill(Request $request, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:40']]);
        $name = preg_replace('/\s+/', ' ', trim($data['name'])) ?? '';

        if ($profile->skills()->count() >= 12) {
            return back()->with('error', 'This profile already has 12 skills.');
        }

        $exists = $profile->skills()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists();

        if (! $exists) {
            $profile->skills()->create(['name' => $name]);
        }

        return $this->redirectToEditor($request, $profile)->with('success', 'The skill was added.');
    }

    public function destroySkill(Request $request, WorkerSkill $skill, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        abort_unless($skill->worker_profile_id === $profile->id, 404);
        $skill->delete();

        return $this->redirectToEditor($request, $profile)->with('success', 'The skill was removed.');
    }

    public function storeProject(Request $request, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);

        if ($profile->projects()->count() >= 12) {
            return back()->with('error', 'This profile already has 12 projects.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:1000'],
            'completed_year' => ['nullable', 'integer', 'min:1980', 'max:'.now()->year],
        ]);

        $profile->projects()->create($data);

        return $this->redirectToEditor($request, $profile)->with('success', 'The project was added.');
    }

    public function destroyProject(Request $request, WorkerProject $project, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        abort_unless($project->worker_profile_id === $profile->id, 404);
        $project->delete();

        return $this->redirectToEditor($request, $profile)->with('success', 'The project was removed.');
    }

    public function storeImage(Request $request, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);

        if ($profile->portfolioImages()->count() >= 8) {
            return back()->with('error', 'This profile already has 8 portfolio photos.');
        }

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:160'],
        ]);

        $profile->portfolioImages()->create([
            'path' => $data['image']->store('portfolio/'.$profile->id, 'public'),
            'caption' => $data['caption'] ?? null,
        ]);

        return $this->redirectToEditor($request, $profile)->with('success', 'The portfolio photo was added.');
    }

    public function destroyImage(Request $request, WorkerPortfolioImage $image, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        abort_unless($image->worker_profile_id === $profile->id, 404);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return $this->redirectToEditor($request, $profile)->with('success', 'The portfolio photo was removed.');
    }

    public function storeService(Request $request, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        $categoryIds = ServiceCategory::query()->where('is_active', true)->pluck('id');
        $data = $request->validate([
            'service_category_id' => ['required', 'integer', Rule::in($categoryIds)],
            'summary' => ['required', 'string', 'max:500'],
            'price_amount' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
        ]);

        if ($profile->services()->where('service_category_id', $data['service_category_id'])->exists()) {
            return back()->withErrors(['service_category_id' => 'That service is already on this profile.'])->withInput();
        }

        $price = $this->money($data['price_amount']);

        if ((int) str_replace('.', '', $price) < 100) {
            return back()->withErrors(['price_amount' => 'Enter a price of at least 1.00.'])->withInput();
        }

        $category = ServiceCategory::query()->findOrFail($data['service_category_id']);
        $slug = $category->slug.'-'.$profile->slug;
        $suffix = 2;

        while (Service::query()->where('slug', $slug)->exists()) {
            $slug = $category->slug.'-'.$profile->slug.'-'.$suffix;
            $suffix++;
        }

        $profile->services()->create([
            'service_category_id' => $category->id,
            'title' => $category->name,
            'slug' => $slug,
            'summary' => $data['summary'],
            'price_amount' => $price,
            'is_active' => true,
        ]);

        return $this->redirectToEditor($request, $profile)->with('success', 'The service was added.');
    }

    public function destroyService(Request $request, Service $service, ?WorkerProfile $workerProfile = null): RedirectResponse
    {
        $profile = $this->profile($request, $workerProfile);
        abort_unless($service->worker_profile_id === $profile->id, 404);

        if ($service->bookings()->exists()) {
            return back()->with('error', 'This service has bookings, so it was kept.');
        }

        $service->delete();

        return $this->redirectToEditor($request, $profile)->with('success', 'The service was removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function studioData(WorkerProfile $profile, bool $managing): array
    {
        $profile->load([
            'user',
            'locations',
            'services.category',
            'skills',
            'projects',
            'portfolioImages',
        ]);

        return [
            'profile' => $profile,
            'managing' => $managing,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => ServiceCategory::query()->where('is_active', true)->orderBy('display_order')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createData(User $worker, bool $managing): array
    {
        return [
            'worker' => $worker,
            'managing' => $managing,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function profile(Request $request, ?WorkerProfile $workerProfile): WorkerProfile
    {
        if ($request->routeIs('admin.workers.*')) {
            abort_unless($request->user()->isAdmin() && $workerProfile, 403);

            return $workerProfile;
        }

        abort_unless($request->user()->role === 'worker', 403);
        $profile = $request->user()->workerProfile;
        abort_unless($profile, 404);

        return $profile;
    }

    private function workerWithoutProfile(Request $request, ?User $user): User
    {
        if ($request->routeIs('admin.workers.store')) {
            abort_unless($request->user()->isAdmin() && $user && $user->role === 'worker', 403);
            abort_if($user->workerProfile, 403);

            return $user;
        }

        abort_unless($request->user()->role === 'worker', 403);
        abort_if($request->user()->workerProfile, 403);

        return $request->user();
    }

    private function redirectToEditor(Request $request, WorkerProfile $profile): RedirectResponse
    {
        if ($request->routeIs('admin.workers.*')) {
            return redirect()->route('admin.workers.edit', $profile);
        }

        return redirect()->route('work.edit');
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRules(bool $admin): array
    {
        $rules = [
            'headline' => ['required', 'string', 'max:160'],
            'bio' => ['required', 'string', 'max:2000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'availability_note' => ['nullable', 'string', 'max:160'],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*' => ['integer', Rule::in(Location::query()->where('is_active', true)->pluck('id')->all())],
        ];

        if ($admin) {
            $rules['verification_status'] = ['required', Rule::in(['pending', 'approved', 'rejected'])];
        }

        return $rules;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'worker';
        $slug = $base;
        $suffix = 2;

        while (WorkerProfile::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function money(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount.'.00';
        }

        [$whole, $fraction] = explode('.', $amount, 2);

        return ((int) $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
