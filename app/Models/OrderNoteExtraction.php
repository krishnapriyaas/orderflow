<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NoteExtractionStatus;
use App\Enums\NoteReviewReason;
use Carbon\CarbonImmutable;
use Database\Factories\OrderNoteExtractionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property CarbonImmutable|null $delivery_date
 * @property string|null $po_number
 * @property string|null $invoice_address
 * @property float|null $confidence
 * @property bool $instructions_detected
 * @property NoteExtractionStatus $status
 * @property NoteReviewReason|null $review_reason
 * @property string|null $raw_response
 * @property string|null $error
 * @property-read Order $order
 */
#[Fillable([
    'delivery_date',
    'po_number',
    'invoice_address',
    'confidence',
    'instructions_detected',
    'status',
    'review_reason',
    'raw_response',
    'error',
])]
class OrderNoteExtraction extends Model
{
    /** @use HasFactory<OrderNoteExtractionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_date' => 'immutable_date',
            'confidence' => 'float',
            'instructions_detected' => 'boolean',
            'status' => NoteExtractionStatus::class,
            'review_reason' => NoteReviewReason::class,
        ];
    }
}
