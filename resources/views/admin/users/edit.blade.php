@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="section-block">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <h1>Edit account</h1>
                    <p class="lede">{{ $user->name }}</p>
                    <form class="admin-panel" method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')
                        <x-form-input name="name" label="Name" :value="$user->name" required />
                        <x-form-input name="email" type="email" label="Email" :value="$user->email" required autocomplete="off" />
                        @include('admin.users._role')
                        <x-form-input name="password" type="password" label="New password" autocomplete="new-password" hint="Leave blank to keep the current password." />
                        <x-form-input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" />
                        <button class="btn btn-secondary" type="submit">Save account</button>
                        <a class="shop-clear" href="{{ route('admin.users.show', $user) }}">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
