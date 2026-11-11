<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\NoteExtractionStatus;
use App\Enums\NoteReviewReason;
use App\Enums\OrderStatus;
use App\Livewire\Admin\ReviewQueue;
use App\Models\Order;
use App\Models\OrderNoteExtraction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 2026-11-11, midday in Berlin.
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00', 'Europe/Berlin'));

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function extraction(
        NoteExtractionStatus $status = NoteExtractionStatus::NeedsReview,
        string $note = 'Please deliver on 20 November, PO 4711',
        array $attributes = [],
    ): OrderNoteExtraction {
        $order = Order::factory()->withStatus(OrderStatus::Paid)->create(['note' => $note]);

        return OrderNoteExtraction::factory()->create([
            'order_id' => $order->id,
            'delivery_date' => '2026-11-20',
            'po_number' => '4711',
            'invoice_address' => "Muster GmbH\nHauptstr. 1\n10115 Berlin",
            'confidence' => 0.55,
            'status' => $status,
            'review_reason' => $status === NoteExtractionStatus::NeedsReview ? NoteReviewReason::LowConfidence : null,
            ...$attributes,
        ]);
    }

    public function test_a_guest_is_redirected_to_the_login(): void
    {
        $this->get('/admin/reviews')->assertRedirect('/login');
    }

    public function test_a_user_who_is_not_an_admin_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/reviews')
            ->assertForbidden();
    }

    public function test_a_user_who_is_not_an_admin_cannot_call_the_actions(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs(User::factory()->create())
            ->test(ReviewQueue::class)
            ->assertForbidden();

        $this->assertSame(NoteExtractionStatus::NeedsReview, $extraction->fresh()?->status);
    }

    public function test_the_listing_shows_only_extractions_in_review(): void
    {
        $inReview = $this->extraction(note: 'Note in review');
        $autoApplied = $this->extraction(NoteExtractionStatus::AutoApplied, 'Note auto applied');
        $approved = $this->extraction(NoteExtractionStatus::Approved, 'Note approved');
        $rejected = $this->extraction(NoteExtractionStatus::Rejected, 'Note rejected');

        $this->actingAs($this->admin)
            ->get('/admin/reviews')
            ->assertOk()
            ->assertSee($inReview->order->uuid)
            ->assertSee('Note in review')
            ->assertSee('low_confidence')
            ->assertSee('0.55')
            ->assertSee('4711')
            ->assertDontSee($autoApplied->order->uuid)
            ->assertDontSee($approved->order->uuid)
            ->assertDontSee($rejected->order->uuid);
    }

    public function test_the_note_is_escaped(): void
    {
        $this->extraction(note: '<script>alert("x")</script>');

        $this->actingAs($this->admin)
            ->get('/admin/reviews')
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_approve_saves_the_edited_fields(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '2026-11-27')
            ->set("fields.{$extraction->id}.po_number", '  PO-9000  ')
            ->set("fields.{$extraction->id}.invoice_address", '')
            ->call('approve', $extraction->id)
            ->assertHasNoErrors()
            ->assertSet('notice', "Approved the note of order {$extraction->order->uuid}.")
            ->assertSee('Nothing to review.');

        $extraction->refresh();
        $this->assertSame(NoteExtractionStatus::Approved, $extraction->status);
        $this->assertSame('2026-11-27', $extraction->delivery_date?->toDateString());
        $this->assertSame('PO-9000', $extraction->po_number);
        $this->assertNull($extraction->invoice_address);
        $this->assertSame($this->admin->id, $extraction->reviewed_by_user_id);
        $this->assertTrue(now()->equalTo($extraction->reviewed_at));
    }

    public function test_approve_without_changes_keeps_the_extracted_fields(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->call('approve', $extraction->id)
            ->assertHasNoErrors();

        $extraction->refresh();
        $this->assertSame(NoteExtractionStatus::Approved, $extraction->status);
        $this->assertSame('2026-11-20', $extraction->delivery_date?->toDateString());
        $this->assertSame('4711', $extraction->po_number);
    }

    public function test_approve_with_a_past_date_fails(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '2026-11-10')
            ->call('approve', $extraction->id)
            ->assertHasErrors(["fields.{$extraction->id}.delivery_date" => 'after_or_equal']);

        $this->assertUnchanged($extraction);
    }

    public function test_approve_with_today_as_the_date_works(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '2026-11-11')
            ->call('approve', $extraction->id)
            ->assertHasNoErrors();

        $this->assertSame('2026-11-11', $extraction->fresh()?->delivery_date?->toDateString());
    }

    public function test_today_is_the_berlin_date_late_in_the_evening(): void
    {
        // 00:30 in Berlin on 2026-11-12 is still 2026-11-11 in UTC. For the wholesaler the
        // 11th is already in the past, so it must be rejected.
        $this->travelTo(CarbonImmutable::parse('2026-11-12 00:30', 'Europe/Berlin'));
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '2026-11-11')
            ->call('approve', $extraction->id)
            ->assertHasErrors(["fields.{$extraction->id}.delivery_date" => 'after_or_equal']);
    }

    public function test_approve_with_a_malformed_date_fails(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '27.11.2026')
            ->call('approve', $extraction->id)
            ->assertHasErrors(["fields.{$extraction->id}.delivery_date" => 'date_format']);

        $this->assertUnchanged($extraction);
    }

    public function test_a_reviewer_may_approve_a_date_400_days_ahead(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '2027-12-16')
            ->call('approve', $extraction->id)
            ->assertHasNoErrors();

        $extraction->refresh();
        $this->assertSame(NoteExtractionStatus::Approved, $extraction->status);
        $this->assertSame('2027-12-16', $extraction->delivery_date?->toDateString());
    }

    public function test_a_reviewer_may_approve_a_date_exactly_two_years_ahead(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '2028-11-11')
            ->call('approve', $extraction->id)
            ->assertHasNoErrors();

        $this->assertSame('2028-11-11', $extraction->fresh()?->delivery_date?->toDateString());
    }

    public function test_approve_with_a_date_more_than_two_years_ahead_fails(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.delivery_date", '2028-11-12')
            ->call('approve', $extraction->id)
            ->assertHasErrors(["fields.{$extraction->id}.delivery_date" => 'before_or_equal']);

        $this->assertUnchanged($extraction);
    }

    public function test_approve_with_a_too_long_po_number_fails(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.po_number", str_repeat('9', 101))
            ->call('approve', $extraction->id)
            ->assertHasErrors(["fields.{$extraction->id}.po_number" => 'max']);

        $this->assertUnchanged($extraction);
    }

    public function test_approve_with_a_too_long_invoice_address_fails(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.invoice_address", str_repeat('a', 501))
            ->call('approve', $extraction->id)
            ->assertHasErrors(["fields.{$extraction->id}.invoice_address" => 'max']);

        $this->assertUnchanged($extraction);
    }

    public function test_reject_sets_the_status_and_keeps_the_fields(): void
    {
        $extraction = $this->extraction();

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->set("fields.{$extraction->id}.po_number", 'ignored on reject')
            ->call('reject', $extraction->id)
            ->assertSet('notice', "Rejected the note of order {$extraction->order->uuid}.");

        $extraction->refresh();
        $this->assertSame(NoteExtractionStatus::Rejected, $extraction->status);
        $this->assertSame('4711', $extraction->po_number);
        $this->assertSame('2026-11-20', $extraction->delivery_date?->toDateString());
        $this->assertSame($this->admin->id, $extraction->reviewed_by_user_id);
        $this->assertNotNull($extraction->reviewed_at);
    }

    public function test_a_decision_on_an_extraction_no_longer_in_review_changes_nothing(): void
    {
        $extraction = $this->extraction();
        $component = Livewire::actingAs($this->admin)->test(ReviewQueue::class);

        // Another reviewer rejects it while this page is open.
        $extraction->forceFill(['status' => NoteExtractionStatus::Rejected])->save();

        $component
            ->set("fields.{$extraction->id}.po_number", 'PO-NEW')
            ->call('approve', $extraction->id)
            ->assertSet('problem', "The note of order {$extraction->order->uuid} was already rejected. Nothing was changed.");

        $extraction->refresh();
        $this->assertSame(NoteExtractionStatus::Rejected, $extraction->status);
        $this->assertSame('4711', $extraction->po_number);
        $this->assertNull($extraction->reviewed_by_user_id);
    }

    public function test_an_auto_applied_extraction_cannot_be_rejected(): void
    {
        $extraction = $this->extraction(NoteExtractionStatus::AutoApplied);

        Livewire::actingAs($this->admin)
            ->test(ReviewQueue::class)
            ->call('reject', $extraction->id)
            ->assertSet('problem', "The note of order {$extraction->order->uuid} was already auto_applied. Nothing was changed.");

        $this->assertSame(NoteExtractionStatus::AutoApplied, $extraction->fresh()?->status);
    }

    private function assertUnchanged(OrderNoteExtraction $extraction): void
    {
        $fresh = $extraction->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(NoteExtractionStatus::NeedsReview, $fresh->status);
        $this->assertSame('2026-11-20', $fresh->delivery_date?->toDateString());
        $this->assertSame('4711', $fresh->po_number);
        $this->assertNull($fresh->reviewed_by_user_id);
        $this->assertNull($fresh->reviewed_at);
    }
}
