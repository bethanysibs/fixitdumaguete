<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentProofStorage
{
    public function store(Payment $payment, UploadedFile $file, User $actor): PaymentProof
    {
        $path = $this->write($payment, $file);

        try {
            return PaymentProof::query()->forceCreate([
                'payment_id' => $payment->id,
                'file_path' => $path,
                'original_filename' => $this->originalFilename($file),
                'mime_type' => (string) $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->delete($path);

            throw $exception;
        }
    }

    public function write(Payment $payment, UploadedFile $file): string
    {
        $this->assertFile($file);
        $extension = $this->extension((string) $file->getMimeType());
        $path = 'payment-proofs/'.$payment->id.'/'.Str::uuid()->toString().'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages([
                'proof' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
            ]);
        }

        try {
            $written = Storage::disk('local')->writeStream($path, $stream);

            if ($written === false) {
                throw ValidationException::withMessages([
                    'proof' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
                ]);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    public function delete(string $path): void
    {
        if ($path !== '' && ! str_contains($path, '..')) {
            Storage::disk('local')->delete($path);
        }
    }

    private function assertFile(UploadedFile $file): void
    {
        $name = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $size = $file->getSize();

        if (! $file->isValid()
            || ! in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)
            || $size === false
            || $size < 1
            || $size > 5 * 1024 * 1024
            || preg_match('/\.(php|phtml|phar|exe|sh|bat|cmd|js|html|svg)(\.|$)/i', $name)
        ) {
            throw ValidationException::withMessages([
                'proof' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
            ]);
        }

        $expected = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => null,
        };

        if ($mime !== $expected) {
            throw ValidationException::withMessages([
                'proof' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
            ]);
        }

        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw ValidationException::withMessages([
                'proof' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
            ]);
        }

        if (in_array($mime, ['image/jpeg', 'image/png'], true) && getimagesize($realPath) === false) {
            throw ValidationException::withMessages([
                'proof' => 'The receipt image could not be read.',
            ]);
        }

        if ($mime === 'application/pdf') {
            $handle = fopen($realPath, 'rb');
            $header = $handle === false ? '' : (string) fread($handle, 5);

            if (is_resource($handle)) {
                fclose($handle);
            }

            if (! str_starts_with($header, '%PDF')) {
                throw ValidationException::withMessages([
                    'proof' => 'The receipt PDF could not be read.',
                ]);
            }
        }
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => throw ValidationException::withMessages([
                'proof' => 'Upload a JPG, PNG, or PDF receipt of 5 MB or less.',
            ]),
        };
    }

    private function originalFilename(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '', $name) ?: 'receipt';

        return substr($name, 0, 160);
    }
}
