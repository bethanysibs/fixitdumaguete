<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ContractAmountCalculator;
use App\Services\PaymentCalculator;
use App\Services\PaymentRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentCalculator $payments,
        private ContractAmountCalculator $contracts,
        private PaymentRecorder $recorder,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        if ($status === '') {
            $status = 'pending_review';
        }

        $search = trim($request->string('q')->toString());
        $query = Payment::query()->with(['booking.customer', 'booking.service.category', 'customer']);

        if ($status === 'pending_review') {
            $query->where('verification_status', 'pending_review');
        } elseif ($status !== 'all' && in_array($status, Payment::STATUSES, true)) {
            $query->where('status', $status);
        } elseif ($status !== 'all' && in_array($status, Payment::VERIFICATION_STATUSES, true)) {
            $query->where('verification_status', $status);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($payments) use ($search, $like) {
                if (ctype_digit($search)) {
                    $payments->where('booking_id', (int) $search);
                }

                $payments->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like));
            });
        }

        $claims = $query
            ->orderByRaw("case when verification_status = 'pending_review' then 0 else 1 end")
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payments.index', [
            'claims' => $claims,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['booking.customer', 'booking.service.category', 'booking.workerProfile.user', 'customer', 'proofs', 'logs.user', 'verifier', 'rejector']);
        $booking = $payment->booking;

        return view('admin.payments.show', [
            'payment' => $payment,
            'booking' => $booking,
            'contract' => $this->contracts->summarize($booking),
            'summary' => $this->payments->summarize($booking),
        ]);
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $this->recorder->verify($payment, $request->user());

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('success', 'The receipt is paid. It now counts toward the visit.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'rejection_reason.required' => 'Enter a reason of 3 to 500 characters.',
            'rejection_reason.min' => 'Enter a reason of 3 to 500 characters.',
        ]);

        $this->recorder->reject($payment, $request->user(), $data['rejection_reason']);

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('success', 'The receipt was not accepted. The customer can send another one.');
    }
}
