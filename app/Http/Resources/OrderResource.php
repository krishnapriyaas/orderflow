<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'customer_email' => $this->customer_email,
            'note' => $this->note,
            'currency' => $this->currency,
            'total_cents' => $this->total_cents,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => [
                'reference' => $this->payment_reference,
                'redirect_url' => $this->payment_redirect_url,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
