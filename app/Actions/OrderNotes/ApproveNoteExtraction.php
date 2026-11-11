<?php

declare(strict_types=1);

namespace App\Actions\OrderNotes;

use App\Data\ReviewedOrderNote;
use App\Enums\NoteExtractionStatus;
use App\Exceptions\InvalidNoteExtractionStatusTransitionException;
use App\Models\OrderNoteExtraction;
use App\Models\User;

class ApproveNoteExtraction
{
    public function __construct(private readonly DecideNoteExtraction $decide) {}

    /**
     * Saves the reviewer's fields and marks the extraction approved.
     *
     * @throws InvalidNoteExtractionStatusTransitionException when it is no longer in review
     */
    public function execute(OrderNoteExtraction $extraction, ReviewedOrderNote $data, User $reviewer): OrderNoteExtraction
    {
        return $this->decide->execute($extraction, NoteExtractionStatus::Approved, $reviewer, [
            'delivery_date' => $data->deliveryDate?->toDateString(),
            'po_number' => $data->poNumber,
            'invoice_address' => $data->invoiceAddress,
        ]);
    }
}
