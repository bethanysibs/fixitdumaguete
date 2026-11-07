@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="section-block">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <h1>Accounts</h1>
                    <p class="lede mb-0">Add a customer, an admin, or a worker, and update or remove an existing account.</p>
                </div>
                <a class="btn btn-secondary" href="{{ route('admin.users.create') }}">Add account</a>
            </div>

            <form class="admin-filters" method="GET" action="{{ route('admin.users.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="q">Search</label>
                        <input class="form-control" id="q" name="q" value="{{ $search }}" placeholder="Name or email">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="role-filter">Role</label>
                        <select class="form-select" id="role-filter" name="role">
                            <option value="">All roles</option>
                            <option value="customer" @selected($role === 'customer')>Customer</option>
                            <option value="admin" @selected($role === 'admin')>Admin</option>
                            <option value="worker" @selected($role === 'worker')>Worker</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-secondary" type="submit">Apply</button>
                        @if ($search !== '' || $role)
                            <a class="shop-clear" href="{{ route('admin.users.index') }}">Clear</a>
                        @endif
                    </div>
                </div>
            </form>

            @if ($users->isEmpty())
                <x-empty-state class="mt-4" title="No accounts match" message="Try another name, email, or role." />
            @else
                <x-data-table class="admin-table mt-4" caption="User accounts">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $account)
                            <tr>
                                <td><a href="{{ route('admin.users.show', $account) }}">{{ $account->name }}</a></td>
                                <td>{{ $account->email }}</td>
                                <td>{{ ucfirst($account->role) }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('admin.users.edit', $account) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
                <div class="mt-4">{{ $users->links() }}</div>
            @endif
        </div>
    </section>
@endsection
