<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $token
 * @property-read Collection<int, CartItem> $items
 * @property-read Order|null $order
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Cart $cart): void {
            if (empty($cart->token)) {
                $cart->token = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function totalCents(): int
    {
        return $this->items->sum(fn (CartItem $item): int => $item->lineTotalCents());
    }
}
