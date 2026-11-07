<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['customer', 'admin', 'worker'])],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $role = $filters['role'] ?? null;

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)->orWhere('email', 'like', $like);
                });
            })
            ->when($role, fn ($query) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'title' => 'Accounts | '.config('brand.name'),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(['role' => 'customer']),
            'title' => 'Add account | '.config('brand.name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::query()->create($data);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'The account was created.');
    }

    public function show(User $user): View
    {
        $user->load('workerProfile');

        return view('admin.users.show', [
            'user' => $user,
            'title' => $user->name.' | '.config('brand.name'),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'title' => 'Edit '.$user->name.' | '.config('brand.name'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $this->guardLastAdmin($user, $data['role']);

        if ($data['password'] === null) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'The account was updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403);
        $this->guardLastAdmin($user, 'customer');

        if ($user->bookings()->exists() || $user->workerProfile?->bookings()->exists()) {
            return back()->with('error', 'This account has bookings, so it was kept.');
        }

        if ($profile = $user->workerProfile) {
            if ($profile->photo_path) {
                Storage::disk('public')->delete($profile->photo_path);
            }

            foreach ($profile->portfolioImages as $image) {
                Storage::disk('public')->delete($image->path);
            }
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'The account was deleted.');
    }

    /**
     * @return array{name: string, email: string, role: string, password: string|null}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $passwordRules = $user === null
            ? ['required', 'string', 'min:8', 'confirmed']
            : ['nullable', 'string', 'min:8', 'confirmed'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(['customer', 'admin', 'worker'])],
            'password' => $passwordRules,
        ]);

        $data['password'] = filled($data['password'] ?? null) ? $data['password'] : null;

        return $data;
    }

    private function guardLastAdmin(User $user, string $nextRole): void
    {
        if ($user->role !== 'admin' || $nextRole === 'admin') {
            return;
        }

        $anotherAdmin = User::query()
            ->where('role', 'admin')
            ->whereKeyNot($user->id)
            ->exists();

        if (! $anotherAdmin) {
            throw ValidationException::withMessages([
                'role' => 'Keep at least one admin account.',
            ]);
        }
    }
}
