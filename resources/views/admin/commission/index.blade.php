@extends('layouts.app')

@section('title', 'Commission | '.config('brand.name'))
@section('meta_description', 'Commission settings for marketplace bookings.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>Commission</h1>
            <p class="lede">Save a new version when the rates change. A booking keeps the commission calculated when it was created.</p>

            <form class="admin-panel mt-4" method="POST" action="{{ route('admin.commission.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <x-form-input name="effective_at" type="datetime-local" label="Effective" :value="old('effective_at', now()->format('Y-m-d\TH:i'))" required />
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="basis">Basis</label>
                        <select class="form-select" id="basis" name="basis">
                            <option value="entire_service" selected>Entire service amount</option>
                            <option value="labor" disabled>Labor amount — not available yet</option>
                            <option value="labor_and_charges" disabled>Labor plus service charges — not available yet</option>
                        </select>
                        <div class="form-text">Labor and service charges cannot be selected yet. Every booking uses the service price, and the snapshot records that basis.</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <x-form-input name="first_booking_rate" label="First booking percentage" :value="old('first_booking_rate', '10.00')" required />
                    </div>
                    <div class="col-md-4">
                        <x-form-input name="repeat_booking_rate" label="Repeat booking percentage" :value="old('repeat_booking_rate', '5.00')" hint="Used after a completed, paid booking with the same worker." required />
                    </div>
                    <div class="col-md-4">
                        <x-form-input name="minimum_amount" label="Minimum commission" :value="old('minimum_amount')" placeholder="Optional" />
                    </div>
                    <div class="col-md-4">
                        <x-form-input name="maximum_amount" label="Maximum commission" :value="old('maximum_amount')" placeholder="Optional" />
                    </div>
                </div>

                <h2 class="h5 mt-4">Category rates</h2>
                <p class="form-text">Leave a category blank to use the first or repeat percentage.</p>
                <div class="row g-3">
                    @foreach ($categories as $category)
                        <div class="col-md-4">
                            <x-form-input name="category_rates[{{ $category->id }}]" :label="$category->name" :value="old('category_rates.'.$category->id)" placeholder="Optional" />
                        </div>
                    @endforeach
                </div>

                <h2 class="h5 mt-2">Worker rates</h2>
                <p class="form-text">A worker rate replaces the category rate and the first or repeat percentage.</p>
                <div class="row g-3">
                    @foreach ($workers as $worker)
                        <div class="col-md-4">
                            <x-form-input name="worker_rates[{{ $worker->id }}]" :label="$worker->user->name" :value="old('worker_rates.'.$worker->id)" placeholder="Optional" />
                        </div>
                    @endforeach
                </div>

                <button class="btn btn-primary mt-3" type="submit">Save new version</button>
            </form>

            <h2 class="h5 mt-5">Saved versions</h2>
            <div class="table-responsive admin-table mt-3">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Effective</th>
                            <th>First</th>
                            <th>Repeat</th>
                            <th>Basis</th>
                            <th>Limits</th>
                            <th>Overrides</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($versions as $version)
                            <tr>
                                <td>
                                    {{ $version->effective_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                    @if ($active && $active->is($version))
                                        <div class="small">In effect</div>
                                    @endif
                                </td>
                                <td>{{ $version->first_booking_rate }}%</td>
                                <td>{{ $version->repeat_booking_rate }}%</td>
                                <td>
                                    {{ $version->basisLabel() }}
                                    @if ($version->basis !== 'entire_service')
                                        <div class="small">Not applied. Bookings use the service price.</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $version->minimum_amount ? \App\Support\Money::format((string) $version->minimum_amount) : 'No minimum' }}
                                    /
                                    {{ $version->maximum_amount ? \App\Support\Money::format((string) $version->maximum_amount) : 'No maximum' }}
                                </td>
                                <td>{{ $version->categoryRates->count() }} categories, {{ $version->workerRates->count() }} workers</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
