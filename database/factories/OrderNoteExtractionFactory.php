<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NoteExtractionStatus;
use App\Models\Order;
use App\Models\OrderNoteExtraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderNoteExtraction>
 */
class OrderNoteExtractionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'delivery_date' => null,
            'po_number' => null,
            'invoice_address' => null,
            'confidence' => 0.9,
            'instructions_detected' => false,
            'status' => NoteExtractionStatus::AutoApplied,
            'review_reason' => null,
            'raw_response' => null,
            'error' => null,
        ];
    }
}
