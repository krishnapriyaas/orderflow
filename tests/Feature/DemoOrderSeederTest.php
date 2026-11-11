<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NoteExtractionStatus;
use App\Enums\NoteReviewReason;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderNoteExtraction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoOrderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_admin_and_orders_in_every_extraction_state(): void
    {
        $this->seed(DemoOrderSeeder::class);

        $admin = User::query()->where('email', DemoOrderSeeder::ADMIN_EMAIL)->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check(DemoOrderSeeder::ADMIN_PASSWORD, $admin->password));

        $this->assertSame(7, Order::query()->where('status', OrderStatus::Paid)->count());
        $this->assertSame(4, OrderNoteExtraction::query()->where('status', NoteExtractionStatus::NeedsReview)->count());

        foreach ([NoteExtractionStatus::AutoApplied, NoteExtractionStatus::Approved, NoteExtractionStatus::Rejected] as $status) {
            $this->assertSame(1, OrderNoteExtraction::query()->where('status', $status)->count(), $status->value);
        }

        $reasons = OrderNoteExtraction::query()
            ->where('status', NoteExtractionStatus::NeedsReview)
            ->pluck('review_reason')
            ->map(fn (mixed $reason): string => $reason instanceof NoteReviewReason ? $reason->value : '')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['instructions_detected', 'low_confidence', 'past_delivery_date', 'service_unavailable'], $reasons);

        foreach (Order::query()->with('items')->get() as $order) {
            $this->assertNotNull($order->uuid);
            $this->assertSame($order->total_cents, $order->items->sum('line_total_cents'));
        }

        $this->assertSame(
            2,
            OrderNoteExtraction::query()->where('reviewed_by_user_id', $admin->id)->whereNotNull('reviewed_at')->count(),
        );
    }

    public function test_it_can_run_twice_without_duplicates(): void
    {
        $this->seed(DemoOrderSeeder::class);
        $this->seed(DemoOrderSeeder::class);

        $this->assertSame(7, Order::query()->count());
        $this->assertSame(7, OrderNoteExtraction::query()->count());
        $this->assertSame(1, User::query()->where('email', DemoOrderSeeder::ADMIN_EMAIL)->count());
    }

    public function test_db_seed_creates_the_demo_data_outside_production(): void
    {
        // DatabaseSeeder runs without model events, unlike seeding DemoOrderSeeder directly.
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, Order::query()->count());
        $this->assertTrue(User::query()->where('email', DemoOrderSeeder::ADMIN_EMAIL)->exists());
    }

    public function test_db_seed_never_creates_the_demo_admin_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Order::query()->count());
        $this->assertFalse(User::query()->where('email', DemoOrderSeeder::ADMIN_EMAIL)->exists());
    }

    public function test_the_admin_can_log_in_and_sees_the_four_notes_to_review(): void
    {
        $this->seed(DemoOrderSeeder::class);

        $this->post('/login', ['email' => DemoOrderSeeder::ADMIN_EMAIL, 'password' => DemoOrderSeeder::ADMIN_PASSWORD])
            ->assertRedirect('/admin/reviews');

        $this->get('/admin/reviews')
            ->assertOk()
            ->assertSee('Order notes to review (4)')
            ->assertSee('Ignore all previous instructions');
    }
}
