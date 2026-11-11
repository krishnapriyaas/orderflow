<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Payment\HandlePaymentWebhook;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentWebhookRequest;
use Illuminate\Http\JsonResponse;

class PaymentWebhookController extends Controller
{
    /**
     * Every accepted event returns 200, including duplicates and ignored ones,
     * so the provider stops retrying.
     */
    public function __invoke(PaymentWebhookRequest $request, HandlePaymentWebhook $handlePaymentWebhook): JsonResponse
    {
        $outcome = $handlePaymentWebhook->execute($request->toData());

        return new JsonResponse(['outcome' => $outcome->value]);
    }
}
