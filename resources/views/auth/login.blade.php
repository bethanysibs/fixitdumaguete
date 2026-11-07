@extends('layouts.app')

@section('title', $title)
@section('meta_description', $description)
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="section-block">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-5">
                    <h1>Log in</h1>
                    <p class="lede">Customers and admins can sign in to set a star rating.</p>
                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf
                        <x-form-input name="email" type="email" label="Email" required autocomplete="username" />
                        <x-form-input name="password" type="password" label="Password" required autocomplete="current-password" />
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                            <label class="form-check-label" for="remember">Remember this browser</label>
                        </div>
                        <button class="btn btn-secondary" type="submit">Log in</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
