@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Accounts', 'url' => route('admin.users.index')],
                ['label' => $user->name],
            ]" />
            <div class="row g-4 mt-1">
                <div class="col-lg-7">
                    <h1>{{ $user->name }}</h1>
                    <dl class="account-details">
                        <div>
                            <dt>Email</dt>
                            <dd>{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt>Role</dt>
                            <dd>{{ ucfirst($user->role) }}</dd>
                        </div>
                    </dl>
                    @if ($user->workerProfile)
                        <p class="mt-3 mb-1"><a href="{{ route('workers.show', $user->workerProfile) }}">View public profile</a></p>
                        <p class="mb-0"><a href="{{ route('admin.workers.edit', $user->workerProfile) }}">Edit profile and photo</a></p>
                    @elseif ($user->role === 'worker')
                        <p class="mt-3 mb-0"><a href="{{ route('admin.workers.create', $user) }}">Set up public profile</a></p>
                    @endif
                </div>
                <div class="col-lg-5">
                    <div class="admin-panel">
                        <a class="btn btn-secondary" href="{{ route('admin.users.edit', $user) }}">Edit account</a>
                        @if ($user->is(auth()->user()))
                            <p class="mt-3 mb-0">You cannot delete the account you are using.</p>
                        @else
                            <form class="mt-3" method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger" type="submit">Delete account</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
