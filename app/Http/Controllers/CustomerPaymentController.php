<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Quotation;
use App\Services\DepositCalculator;
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
        private DepositCalculator $deposits,
        private PaymentInstructionCatalog $instructions,
        private PaymentRecorder $recorder,
    ) {}

    public function index(Request $request, Booking $booking): View
    {
        $this->ensureOwner($request, $booking);

        return view('payments.index', $this->page($booking));
    }

    public function create(Request $request, Booking $booking): View
    {
        $this->ensureOwner($request, $booking);

        return view('payments.create', $this->page($booking));
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
            ->with('success', 'Your payment claim was submitted for review. It is not confirmed until an administrator checks it.');
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
    private function page(Booking $booking): array
    {
        $booking->load(['service.category', 'workerProfile.user']);
        $summary = $this->payments->summarize($booking);
        $room = $this->payments->claimRoom($booking);
        $quotation = $summary['quotation_id'] !== null
            ? Quotation::query()->find($summary['quotation_id'])
            : null;
        $deposit = $quotation !== null ? $this->deposits->calculate($quotation) : null;
        $instructions = $this->instructions->available();
        $types = [];

        if ($room['deposit_room_cents'] > 0) {
            $types[] = ['value' => 'deposit', 'label' => 'Deposit'];
        }

        if ($room['balance_room_cents'] > 0) {
            $types[] = ['value' => 'balance', 'label' => 'Balance'];
            $types[] = ['value' => 'full_payment', 'label' => 'Full payment'];
        }

        return [
            'booking' => $booking,
            'quotation' => $quotation,
            'deposit' => $deposit,
            'claims' => $booking->payments()->whereNotNull('quotation_id')->with('proofs')->latest('id')->get(),
            'instructions' => $instructions,
            'types' => $types,
            'canSubmit' => $booking->allowsNewQuotation() && $room['balance_room_cents'] > 0 && $instructions !== [],
            'acceptedTotal' => $this->money($summary['accepted_amount_cents']),
            'requiredDeposit' => $this->money($summary['required_deposit_cents']),
            'verifiedPayments' => $this->money($summary['verified_cents']),
            'depositOutstanding' => $this->money($summary['deposit_outstanding_cents']),
            'balanceOutstanding' => $this->money($summary['balance_outstanding_cents']),
            'balanceRoom' => $this->money($room['balance_room_cents']),
            'depositRoom' => $this->money($room['deposit_room_cents']),
            'openClaims' => $this->money($room['open_claim_cents']),
            'finalDeposit' => $deposit !== null ? $this->money($deposit['final_deposit_cents']) : null,
            'depositRule' => $deposit['requirement'] ?? null,
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
