<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $cart_id
 * @property string $customer_email
 * @property string|null $note
 * @property OrderStatus $status
 * @property int $total_cents
 * @property string $currency
 * @property string|null $payment_reference
 * @property string|null $payment_redirect_url
 * @property string|null $idempotency_key
 * @property-read Cart $cart
 * @property-read Collection<int, OrderItem> $items
 */
#[Fillable([
    'customer_email',
    'note',
    'status',
    'total_cents',
    'currency',
    'payment_reference',
    'payment_redirect_url',
    'idempotency_key',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Only the public `uuid` column is a UUID; the primary key stays an integer.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @throws InvalidOrderStatusTransitionException
     */
    public function transitionTo(OrderStatus $to): void
    {
        $this->status = $this->status->transitionTo($to);
        $this->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_cents' => 'integer',
        ];
    }
}
