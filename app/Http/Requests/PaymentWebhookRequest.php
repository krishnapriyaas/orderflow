<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\PaymentWebhookEvent;
use App\Enums\PaymentWebhookEventType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
        // Event types we do not handle may have a different `data` shape: they are
        // stored as unknown_type, so their payment fields are not validated.
        $skipPaymentFields = Rule::excludeIf(fn (): bool => $this->eventType() === null);

        return [
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'data' => [$skipPaymentFields, 'required', 'array'],
            'data.payment_reference' => [$skipPaymentFields, 'required', 'string', 'max:255'],
            'data.amount_cents' => [$skipPaymentFields, 'required', 'integer', 'min:0'],
            'data.currency' => [$skipPaymentFields, 'required', 'string', 'size:3'],
        ];
    }

    private function eventType(): ?PaymentWebhookEventType
    {
        $type = $this->input('type');

        return is_string($type) ? PaymentWebhookEventType::tryFrom($type) : null;
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

        $known = $this->eventType() !== null;

        return new PaymentWebhookEvent(
            eventId: $this->string('id')->toString(),
            type: $this->string('type')->toString(),
            paymentReference: $known ? $this->string('data.payment_reference')->toString() : null,
            amountCents: $known ? $this->integer('data.amount_cents') : null,
            // Uppercased, so "eur" matches the order's "EUR".
            currency: $known ? $this->string('data.currency')->upper()->toString() : null,
            payload: $payload,
        );
    }
}
