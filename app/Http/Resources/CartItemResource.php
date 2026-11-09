<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->product->sku,
            'name' => $this->product->name,
            'unit_price_cents' => $this->product->price_cents,
            'quantity' => $this->quantity,
            'line_total_cents' => $this->lineTotalCents(),
        ];
    }
}
