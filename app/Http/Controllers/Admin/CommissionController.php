<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use App\Models\ServiceCategory;
use App\Models\WorkerProfile;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(): View
    {
        return view('admin.commission.index', [
            'versions' => CommissionSetting::query()->with(['categoryRates.category', 'workerRates.workerProfile.user'])->orderByDesc('effective_at')->orderByDesc('id')->get(),
            'active' => CommissionSetting::query()->where('effective_at', '<=', now())->orderByDesc('effective_at')->orderByDesc('id')->first(),
            'categories' => ServiceCategory::query()->where('is_active', true)->orderBy('display_order')->get(),
            'workers' => WorkerProfile::query()->with('user')->orderBy('slug')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $categoryIds = ServiceCategory::query()->where('is_active', true)->pluck('id')->all();
        $workerIds = WorkerProfile::query()->pluck('id')->all();

        $data = $request->validate([
            'effective_at' => ['required', 'date'],
            'first_booking_rate' => ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'repeat_booking_rate' => ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'basis' => ['required', Rule::in(['entire_service'])],
            'minimum_amount' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'maximum_amount' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'category_rates' => ['nullable', 'array'],
            'category_rates.*' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'worker_rates' => ['nullable', 'array'],
            'worker_rates.*' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
        ], [
            'basis.in' => 'Labor amounts are not available yet. Use the entire service amount.',
        ]);

        foreach (['first_booking_rate', 'repeat_booking_rate'] as $field) {
            $this->assertPercentage($data[$field], $field);
            $data[$field] = Money::normalize($data[$field]);
        }

        $minimum = $this->moneyOrNull($data['minimum_amount'] ?? null);
        $maximum = $this->moneyOrNull($data['maximum_amount'] ?? null);

        if ($minimum !== null && $maximum !== null && Money::toCents($maximum) < Money::toCents($minimum)) {
            throw ValidationException::withMessages([
                'maximum_amount' => 'The maximum commission must be at least the minimum.',
            ]);
        }

        $categoryRates = [];

        foreach ($data['category_rates'] ?? [] as $categoryId => $rate) {
            if ($rate === null || $rate === '') {
                continue;
            }

            if (! in_array((int) $categoryId, $categoryIds, true)) {
                throw ValidationException::withMessages([
                    'category_rates' => 'Choose a service category from the catalog.',
                ]);
            }

            $this->assertPercentage($rate, 'category_rates.'.$categoryId);
            $categoryRates[(int) $categoryId] = Money::normalize($rate);
        }

        $workerRates = [];

        foreach ($data['worker_rates'] ?? [] as $workerId => $rate) {
            if ($rate === null || $rate === '') {
                continue;
            }

            if (! in_array((int) $workerId, $workerIds, true)) {
                throw ValidationException::withMessages([
                    'worker_rates' => 'Choose a worker who has a public profile.',
                ]);
            }

            $this->assertPercentage($rate, 'worker_rates.'.$workerId);
            $workerRates[(int) $workerId] = Money::normalize($rate);
        }

        DB::transaction(function () use ($request, $data, $minimum, $maximum, $categoryRates, $workerRates) {
            $setting = CommissionSetting::query()->create([
                'effective_at' => $data['effective_at'],
                'first_booking_rate' => $data['first_booking_rate'],
                'repeat_booking_rate' => $data['repeat_booking_rate'],
                'basis' => $data['basis'],
                'minimum_amount' => $minimum,
                'maximum_amount' => $maximum,
                'created_by' => $request->user()->id,
            ]);

            foreach ($categoryRates as $categoryId => $rate) {
                $setting->categoryRates()->create([
                    'service_category_id' => $categoryId,
                    'rate' => $rate,
                ]);
            }

            foreach ($workerRates as $workerId => $rate) {
                $setting->workerRates()->create([
                    'worker_profile_id' => $workerId,
                    'rate' => $rate,
                ]);
            }
        });

        return redirect()
            ->route('admin.commission.index')
            ->with('success', 'The commission settings were saved. Bookings already made keep their original commission.');
    }

    private function assertPercentage(string $rate, string $field): void
    {
        if (Money::toCents(Money::normalize($rate)) > 10000) {
            throw ValidationException::withMessages([
                $field => 'Enter a percentage from 0.00 to 100.00.',
            ]);
        }
    }

    private function moneyOrNull(?string $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return Money::normalize($amount);
    }
}
