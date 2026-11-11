<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Payment\WebhookSignatureVerifier;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentWebhookSignature
{
    public function __construct(private readonly WebhookSignatureVerifier $verifier) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $valid = $this->verifier->verify(
            $request->headers->get('X-Signature'),
            $request->headers->get('X-Timestamp'),
            $request->getContent(),
        );

        if (! $valid) {
            return new JsonResponse(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
