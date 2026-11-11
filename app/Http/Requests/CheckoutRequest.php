<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\CheckoutData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Idempotency-Key header is validated together with the body.
     * It always overrides any `idempotency_key` sent in the body.
     */
    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key');

        $this->merge([
            'idempotency_key' => is_string($key) && trim($key) !== '' ? trim($key) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): CheckoutData
    {
        $note = $this->validated('note');
        $key = $this->validated('idempotency_key');

        return new CheckoutData(
            email: $this->string('email')->toString(),
            note: is_string($note) ? $note : null,
            idempotencyKey: is_string($key) ? $key : null,
        );
    }
}
