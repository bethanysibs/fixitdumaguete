<?php

namespace App\Services;

use App\Models\QuotationItem;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class QuotationCalculator
{
    public const CURRENCY = 'PHP';

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     currency: string,
     *     subtotal: string,
     *     total_amount: string,
     *     items: list<array{
     *         item_type: string,
     *         description: string,
     *         quantity: string,
     *         unit: string,
     *         unit_price: string,
     *         line_total: string,
     *         sort_order: int
     *     }>
     * }
     */
    public function calculate(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one quotation item.',
            ]);
        }

        $lines = [];
        $errors = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                $errors["items.$index"] = 'Each quotation item must include a type, description, quantity, unit, and unit price.';

                continue;
            }

            $type = $item['item_type'] ?? null;

            if (! is_string($type) || ! in_array($type, QuotationItem::TYPES, true)) {
                $errors["items.$index.item_type"] = 'Choose labor, material, service charge, inspection fee, or other.';
            }

            $description = trim((string) ($item['description'] ?? ''));

            if ($description === '' || mb_strlen($description) > 255) {
                $errors["items.$index.description"] = 'Enter a description of 255 characters or fewer.';
            }

            $unit = trim((string) ($item['unit'] ?? ''));

            if ($unit === '' || mb_strlen($unit) > 40) {
                $errors["items.$index.unit"] = 'Enter a unit such as hour, piece, or visit.';
            }

            $quantity = $this->quantity($item['quantity'] ?? null, "items.$index.quantity", $errors);
            $unitPrice = $this->price($item['unit_price'] ?? null, "items.$index.unit_price", $errors);

            if ($errors !== [] || ! is_string($type) || $quantity === null || $unitPrice === null) {
                continue;
            }

            $lines[] = [
                'item_type' => $type,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => $unit,
                'unit_price' => $unitPrice,
                'line_total' => Money::multiply($quantity, $unitPrice),
                'sort_order' => count($lines) + 1,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $subtotal = Money::sum(array_column($lines, 'line_total'));

        return [
            'currency' => self::CURRENCY,
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'items' => $lines,
        ];
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function quantity(mixed $value, string $field, array &$errors): ?string
    {
        $normalized = $this->decimal($value, $field, 'Enter a quantity greater than zero.', 8, $errors);

        if ($normalized === null) {
            return null;
        }

        if (Money::toCents($normalized) <= 0) {
            $errors[$field] = 'Enter a quantity greater than zero.';

            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function price(mixed $value, string $field, array &$errors): ?string
    {
        $normalized = $this->decimal($value, $field, 'Enter a unit price of 0.00 or more.', 10, $errors);

        if ($normalized === null) {
            return null;
        }

        if (Money::toCents($normalized) < 0) {
            $errors[$field] = 'Enter a unit price of 0.00 or more.';

            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function decimal(mixed $value, string $field, string $message, int $wholeDigits, array &$errors): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || ! preg_match('/^\d{1,'.$wholeDigits.'}(\.\d{1,2})?$/', $value)) {
            $errors[$field] = $message;

            return null;
        }

        return Money::normalize($value);
    }
}
