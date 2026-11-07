<?php

namespace App\Services;

use App\Models\PaymentInstruction;

class PaymentInstructionCatalog
{
    /**
     * @return list<array{
     *     method: string,
     *     label: string,
     *     account_name: string|null,
     *     account_number: string|null,
     *     bank_name: string|null,
     *     instructions: string,
     *     has_qr: bool,
     *     is_placeholder: bool
     * }>
     */
    public function available(): array
    {
        $rows = PaymentInstruction::query()->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return $this->placeholders();
        }

        return $rows
            ->filter(fn (PaymentInstruction $instruction) => $instruction->is_active
                && in_array($instruction->method, PaymentInstruction::METHODS, true))
            ->map(fn (PaymentInstruction $instruction) => [
                'method' => $instruction->method,
                'label' => $this->label($instruction->method),
                'account_name' => $instruction->account_name,
                'account_number' => $instruction->account_number,
                'bank_name' => $instruction->bank_name,
                'instructions' => $instruction->instructions ?: $this->fallbackInstructions(),
                'has_qr' => filled($instruction->qr_path),
                'is_placeholder' => false,
            ])
            ->values()
            ->all();
    }

    public function allows(string $method): bool
    {
        return collect($this->available())->contains(fn (array $instruction) => $instruction['method'] === $method);
    }

    public function label(string $method): string
    {
        return match ($method) {
            'gcash' => 'GCash',
            'bank_transfer' => 'Bank transfer',
            default => 'Payment',
        };
    }

    /**
     * @return list<array{
     *     method: string,
     *     label: string,
     *     account_name: null,
     *     account_number: null,
     *     bank_name: null,
     *     instructions: string,
     *     has_qr: false,
     *     is_placeholder: true
     * }>
     */
    private function placeholders(): array
    {
        return [
            [
                'method' => 'gcash',
                'label' => 'GCash',
                'account_name' => null,
                'account_number' => null,
                'bank_name' => null,
                'instructions' => 'GCash details have not been configured yet. You can still submit a claim with your reference number and receipt. An administrator reviews it before it counts as paid.',
                'has_qr' => false,
                'is_placeholder' => true,
            ],
            [
                'method' => 'bank_transfer',
                'label' => 'Bank transfer',
                'account_name' => null,
                'account_number' => null,
                'bank_name' => null,
                'instructions' => 'Bank details have not been configured yet. You can still submit a claim with your reference number and receipt. An administrator reviews it before it counts as paid.',
                'has_qr' => false,
                'is_placeholder' => true,
            ],
        ];
    }

    private function fallbackInstructions(): string
    {
        return 'Send the payment, then submit the reference number and receipt. An administrator reviews the claim before it counts as paid.';
    }
}
