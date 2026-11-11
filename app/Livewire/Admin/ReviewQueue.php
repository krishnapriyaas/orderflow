<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\OrderNotes\ApproveNoteExtraction;
use App\Actions\OrderNotes\RejectNoteExtraction;
use App\Data\ReviewedOrderNote;
use App\Enums\NoteExtractionStatus;
use App\Exceptions\InvalidNoteExtractionStatusTransitionException;
use App\Models\OrderNoteExtraction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Back-office queue of order note extractions that need a human decision.
 */
#[Layout('components.layouts.admin')]
#[Title('Order note reviews')]
class ReviewQueue extends Component
{
    use WithPagination;

    /**
     * The editable fields per extraction id.
     *
     * @var array<int|string, array{delivery_date: string, po_number: string, invoice_address: string}>
     */
    public array $fields = [];

    public ?string $notice = null;

    public ?string $problem = null;

    public function mount(): void
    {
        Gate::authorize('review-order-notes');
    }

    public function approve(int $id, ApproveNoteExtraction $approve): void
    {
        Gate::authorize('review-order-notes');
        $this->resetMessages();

        $extraction = OrderNoteExtraction::query()->with('order')->findOrFail($id);

        $this->validate(
            ReviewedOrderNote::rules($this->today(), "fields.{$id}."),
            attributes: [
                "fields.{$id}.delivery_date" => 'delivery date',
                "fields.{$id}.po_number" => 'PO number',
                "fields.{$id}.invoice_address" => 'invoice address',
            ],
        );

        $data = ReviewedOrderNote::fromValidated($this->fields[$id] ?? []);

        try {
            $approve->execute($extraction, $data, $this->reviewer());
        } catch (InvalidNoteExtractionStatusTransitionException $e) {
            $this->decisionFailed($id, $extraction, $e);

            return;
        }

        unset($this->fields[$id]);
        $this->notice = "Approved the note of order {$extraction->order->uuid}.";
    }

    public function reject(int $id, RejectNoteExtraction $reject): void
    {
        Gate::authorize('review-order-notes');
        $this->resetMessages();

        $extraction = OrderNoteExtraction::query()->with('order')->findOrFail($id);

        try {
            $reject->execute($extraction, $this->reviewer());
        } catch (InvalidNoteExtractionStatusTransitionException $e) {
            $this->decisionFailed($id, $extraction, $e);

            return;
        }

        unset($this->fields[$id]);
        $this->notice = "Rejected the note of order {$extraction->order->uuid}.";
    }

    public function render(): View
    {
        $extractions = OrderNoteExtraction::query()
            ->with('order')
            ->where('status', NoteExtractionStatus::NeedsReview)
            ->oldest()
            ->orderBy('id')
            ->paginate(20);

        // Fill the inputs once per row; after that they hold what the reviewer typed.
        foreach ($extractions->items() as $extraction) {
            $this->fields[$extraction->id] ??= [
                'delivery_date' => $extraction->delivery_date?->toDateString() ?? '',
                'po_number' => $extraction->po_number ?? '',
                'invoice_address' => $extraction->invoice_address ?? '',
            ];
        }

        return view('livewire.admin.review-queue', ['extractions' => $extractions]);
    }

    private function decisionFailed(int $id, OrderNoteExtraction $extraction, InvalidNoteExtractionStatusTransitionException $e): void
    {
        unset($this->fields[$id]);
        $this->problem = "The note of order {$extraction->order->uuid} was already {$e->from->value}. Nothing was changed.";
    }

    private function resetMessages(): void
    {
        $this->notice = null;
        $this->problem = null;
    }

    private function reviewer(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * The business date: a delivery date is "in the past" by the wholesaler's calendar.
     */
    private function today(): CarbonImmutable
    {
        $timezone = config('orderflow.business_timezone');

        return CarbonImmutable::now(is_string($timezone) ? $timezone : 'Europe/Berlin')->startOfDay();
    }
}
