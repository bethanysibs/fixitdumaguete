@extends('layouts.app')

@section('title', 'Submit a payment | '.config('brand.name'))
@section('meta_description', 'Submit a payment claim for review.')

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'My bookings', 'url' => route('bookings.index')],
                ['label' => 'Payments', 'url' => route('bookings.payments.index', $booking)],
                ['label' => 'Submit'],
            ]" />

            <h1 class="mt-3">Submit a payment claim</h1>
            <p class="lede">{{ $booking->workerProfile->user->name }} · {{ $booking->service->category->name }}</p>
            <p>The receipt and reference number are evidence for review. They do not mark the payment as received.</p>

            <div class="row g-4 mt-1">
                <div class="col-lg-7">
                    @include('payments.summary')

                    @if ($canSubmit)
                        <form class="mt-4" method="POST" action="{{ route('bookings.payments.store', $booking) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="payment_type">Payment type <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span></label>
                                <select class="form-select @error('payment_type') is-invalid @enderror" id="payment_type" name="payment_type" required>
                                    @foreach ($types as $type)
                                        <option value="{{ $type['value'] }}" @selected(old('payment_type') === $type['value'])>{{ $type['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Enter the amount you sent, up to {{ $balanceRoom }}.@if (! empty($depositDue)) Include {{ $depositDue }} with the first receipt.@endif</div>
                                @error('payment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="method">Payment method <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span></label>
                                <select class="form-select @error('method') is-invalid @enderror" id="method" name="method" required>
                                    @foreach ($instructions as $instruction)
                                        <option value="{{ $instruction['method'] }}" @selected(old('method') === $instruction['method'])>{{ $instruction['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <x-form-input name="amount" label="Amount" inputmode="decimal" value="{{ old('amount') }}" hint="Enter the amount you sent. The quotation total is checked on the server." required />
                            <x-form-input name="payment_reference" label="Reference number" value="{{ old('payment_reference') }}" maxlength="40" hint="Use the reference from GCash or the bank, with 4 to 40 letters or numbers." required />
                            <x-form-textarea name="notes" label="Note" rows="3" hint="Optional. This note is visible with your claim." />

                            <div class="mb-3">
                                <label class="form-label" for="proof">Proof of payment <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">required</span></label>
                                <input class="form-control @error('proof') is-invalid @enderror" id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required>
                                <div class="form-text">JPG, PNG, or PDF, up to 5 MB. The file stays private.</div>
                                @error('proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <x-button type="submit">Submit claim for review</x-button>
                        </form>
                    @else
                        <p class="mt-4">This visit cannot take a new payment claim right now.</p>
                    @endif
                </div>
                <div class="col-lg-5">
                    @include('payments.instructions')
                </div>
            </div>

            <p class="mt-4"><a href="{{ route('bookings.payments.index', $booking) }}">Return to payment claims</a></p>
        </div>
    </section>
@endsection
