@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="section-block">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <h1>Add account</h1>
                    <p class="lede">Choose whether this person is a customer, an admin, or a worker.</p>
                    <form class="admin-panel" method="POST" action="{{ route('admin.users.store') }}">
                        @csrf
                        <x-form-input name="name" label="Name" :value="$user->name" required />
                        <x-form-input name="email" type="email" label="Email" :value="$user->email" required autocomplete="off" />
                        @include('admin.users._role')
                        <x-form-input name="password" type="password" label="Password" required autocomplete="new-password" hint="At least 8 characters." />
                        <x-form-input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
                        <button class="btn btn-secondary" type="submit">Create account</button>
                        <a class="shop-clear" href="{{ route('admin.users.index') }}">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
