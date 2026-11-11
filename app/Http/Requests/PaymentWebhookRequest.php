<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\PaymentWebhookEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * Runs after VerifyPaymentWebhookSignature, so the body here is signed by the provider.
 */
class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'data.payment_reference' => ['required', 'string', 'max:255'],
            'data.amount_cents' => ['required', 'integer', 'min:0'],
            'data.currency' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * A signed but malformed payload is a problem on the provider side: log it as an error.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::error('Payment webhook payload is malformed.', [
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }

    public function toData(): PaymentWebhookEvent
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->json()->all();

        return new PaymentWebhookEvent(
            eventId: $this->string('id')->toString(),
            type: $this->string('type')->toString(),
            paymentReference: $this->string('data.payment_reference')->toString(),
            amountCents: $this->integer('data.amount_cents'),
            currency: $this->string('data.currency')->toString(),
            payload: $payload,
        );
    }
}
