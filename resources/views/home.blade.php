@extends('layouts.app')

@section('title', config('brand.seo.title'))
@section('meta_description', config('brand.seo.description'))
@section('robots', 'index, follow')

@push('head')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    @include('home.hero')
    @include('home.services')
    @include('home.steps')
    @include('home.locations')
@endsection
