<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Services\ContractAmountCalculator;
use App\Services\PaymentCalculator;
use App\Services\PaymentInstructionCatalog;
use App\Services\PaymentRecorder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CustomerPaymentController extends Controller
{
    public function __construct(
        private PaymentCalculator $payments,
        private PaymentInstructionCatalog $instructions,
        private PaymentRecorder $recorder,
        private ContractAmountCalculator $contracts,
    ) {}

    public function index(Request $request, Booking $booking): View
    {
        $this->ensureOwner($request, $booking);

        return view('payments.index', $this->page($booking));
    }

    public function create(Request $request, Booking $booking): View
    {
        $this->ensureOwner($request, $booking);

        return view('payments.create', $this->page($booking, $request->string('type')->toString()));
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureOwner($request, $booking);

        $request->validate([
            'payment_type' => ['required', Rule::in(['deposit', 'balance', 'full_payment'])],
            'method' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'payment_reference' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ], [
            'amount.regex' => 'Enter an amount with up to two decimal places.',
            'payment_reference.required' => 'Enter the reference number from the payment.',
            'proof.required' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
            'proof.mimes' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
            'proof.max' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
        ]);

        $this->recorder->submit(
            $booking,
            $request->user(),
            (string) $request->input('amount'),
            (string) $request->input('payment_type'),
            (string) $request->input('method'),
            (string) $request->input('payment_reference'),
            $request->input('notes'),
            $request->file('proof'),
        );

        return redirect()
            ->route('bookings.payments.index', $booking)
            ->with('success', 'We received your receipt. It is not paid until an administrator checks it.');
    }

    public function proof(Request $request, Booking $booking, Payment $payment, PaymentProof $proof): Response
    {
        abort_unless($payment->booking_id === $booking->id, 404);
        abort_unless($proof->payment_id === $payment->id, 404);
        abort_unless($proof->canBeViewedBy($request->user()), 403);

        $path = (string) $proof->file_path;
        abort_unless($path !== '' && ! str_contains($path, '..') && ! str_contains($path, '://'), 404);
        abort_unless(Storage::disk($proof->disk())->exists($path), 404);

        return Storage::disk($proof->disk())->download($path, $this->downloadName($proof), [
            'Content-Type' => $proof->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function page(Booking $booking, ?string $selectedType = null): array
    {
        $booking->load(['service.category', 'workerProfile.user', 'payments.proofs']);
        $summary = $this->payments->summarize($booking);
        $room = $this->payments->claimRoom($booking);
        $instructions = $this->instructions->available();
        $types = [];

        if ($room['deposit_room_cents'] > 0) {
            $types[] = ['value' => 'deposit', 'label' => 'Deposit'];
        }

        if ($room['balance_room_cents'] > 0) {
            $types[] = ['value' => 'balance', 'label' => 'Part of what I owe'];
            $types[] = ['value' => 'full_payment', 'label' => 'Everything I still owe'];
        }

        $allowed = array_column($types, 'value');
        $selected = in_array($selectedType, $allowed, true) ? $selectedType : ($allowed[0] ?? null);
        $prefill = match ($selected) {
            'deposit' => Money::fromCents($room['deposit_room_cents']),
            'full_payment' => Money::fromCents($room['balance_room_cents']),
            default => null,
        };

        return [
            'booking' => $booking,
            'contract' => $this->contracts->summarize($booking),
            'claims' => $booking->payments->sortByDesc('id')->values(),
            'instructions' => $instructions,
            'types' => $types,
            'selectedType' => $selected,
            'amountPrefill' => $prefill,
            'canSubmit' => $booking->allowsNewQuotation() && $room['balance_room_cents'] > 0 && $instructions !== [],
            'canPayDeposit' => $room['deposit_room_cents'] > 0 && $instructions !== [],
            'balanceRoom' => $this->money($room['balance_room_cents']),
            'depositDue' => $summary['deposit_outstanding_cents'] > 0 ? $this->money($summary['deposit_outstanding_cents']) : null,
            'rejectedTotal' => $summary['rejected_cents'] > 0 ? $this->money($summary['rejected_cents']) : null,
            'recordedTotal' => $summary['recorded_cents'] > 0 ? $this->money($summary['recorded_cents']) : null,
            'hasRejected' => $booking->payments->contains(fn (Payment $payment) => $payment->status === 'rejected'),
        ];
    }

    private function ensureOwner(Request $request, Booking $booking): void
    {
        abort_unless($request->user()?->id === $booking->customer_id, 403);
    }

    private function money(?int $cents): ?string
    {
        return $cents === null ? null : Money::format(Money::fromCents($cents));
    }

    private function downloadName(PaymentProof $proof): string
    {
        $name = basename(str_replace('\\', '/', (string) $proof->original_filename));
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '', $name) ?: 'receipt';

        return substr($name, 0, 160);
    }
}
