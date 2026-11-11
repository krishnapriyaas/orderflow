# Step 5 — Back-office review page and final documentation

Plan written by Claude Code. Reviewed and approved by me on 2026-11-11, with the decisions below.

## Goal

Extractions in `needs_review` (Step 4) are decided by a person on a Livewire page at
`/admin/reviews`. The page is protected by a simple admin login. A demo seeder fills the page
right after `db:seed`. The README gets its final version.

## Package

`livewire/livewire` `^4.4` (v4.4.8 at the time of writing). Supports Laravel 13 and PHP ^8.1,
no npm build needed. Approved.

## Admin login

Laravel's built-in session auth (`web` guard, `Auth::attempt()`), no Breeze or Fortify: those are
new packages and add registration, password reset and email verification that a page for one or
two staff members does not need.

- `LoginController`: show form, log in, log out. `throttle:5,1` on the login POST, session id
  regenerated after login and invalidated on logout.
- `users.is_admin` (boolean, default false) and a Gate `review-order-notes`. The route uses
  `auth` and `can:review-order-notes`, so a logged-in non-admin gets 403.
- Demo admin `admin@example.com` / `password`, created by the demo seeder.

## Review page

- Class-based Livewire component `App\Livewire\Admin\ReviewQueue` (not a single-file component,
  so strict types and Larastan work as usual), full-page route `GET /admin/reviews`.
- Lists only `needs_review` rows, oldest first, 20 per page: order uuid, original note,
  `review_reason`, `instructions_detected`, `error`, confidence (read only), and the three
  fields as inputs keyed by extraction id.
- The note is untrusted customer text and is only printed escaped (`{{ }}`).
- Approve validates the edited fields, then calls `ApproveNoteExtraction` with a
  `ReviewedOrderNote` DTO. Reject calls `RejectNoteExtraction`.
- Status changes go through `NoteExtractionStatus::transitionTo()` (throws
  `InvalidNoteExtractionStatusTransitionException`, like `OrderStatus`).
- The update runs `WHERE status = 'needs_review'`: a second click or a stale tab gets an error
  and nothing is written.

### Validation on approve

- `delivery_date`: nullable, `Y-m-d`, not before today (the business date in Europe/Berlin)
  and not more than 2 years after today.
- `po_number`: nullable, max 100 characters. `invoice_address`: nullable, max 500 characters.
  Blank inputs become `null`.
- The limits are shared constants used by both `LlmOrderNoteExtractor` and the review form.

## Table change

`order_note_extractions` gets `reviewed_by_user_id` (nullable foreign key to `users`, null on
delete) and `reviewed_at` (nullable timestamp), set on approve and on reject.

## Demo seeder

`DemoOrderSeeder` creates the admin user and paid orders with notes, each with its own cart:
four `needs_review` rows (`low_confidence`, `instructions_detected`, `past_delivery_date`,
`service_unavailable`), one `auto_applied`, one `approved`, one `rejected`. Dates are relative
to the day it runs. Fixed idempotency keys make it safe to run twice. No factories (Faker is a
dev dependency).

## Decisions

1. `is_admin` column with a Gate.
2. A reviewer may approve a delivery date more than 365 days ahead. The 365-day rule exists
   because the model can misread a date; a person who checked the note is the check. Past dates
   stay blocked. A test approves a date 400 days ahead.
3. `reviewed_by_user_id` and `reviewed_at`, set on approve and reject.
4. The length limits move into shared constants.
5. Rejected rows keep their extracted fields.
6. (Agent's recommendation, not asked separately) `DatabaseSeeder` only runs the demo seeder in
   the `local` and `testing` environments, so the demo admin with a known password is never
   created on a real server.

## Follow-up decisions after implementation

1. The Gate only allows `is_admin === true`: a user created in code has `null` until it is
   reloaded. Fixed in its own commit.
2. `DecideNoteExtraction` holds the shared conditional update used by approve and reject.
3. "Today in Berlin" lives in one helper, `App\Support\BusinessDate::today()`, used by the
   extraction action, the review page and the demo seeder. Separate commit.
4. Sanity limit for reviewers: a delivery date more than 2 years ahead is rejected with a
   validation error (`ReviewedOrderNote::MAX_YEARS_AHEAD`), against typos such as 2062 for
   2026. Exactly 2 years ahead is allowed. Decision 2 still holds for anything in between.
5. Accepted: `strict_types` added to the files touched anyway, the `redirectUsersTo` line in
   `bootstrap/app.php`, and demo orders do not decrement stock.
6. Not tested by the agent, left to me: clicking approve and reject in a real browser, and
   `composer setup` from a fresh clone.

## Files

Create:
- `app/Livewire/Admin/ReviewQueue.php`, `resources/views/livewire/admin/review-queue.blade.php`
- `app/Http/Controllers/Auth/LoginController.php`, `resources/views/auth/login.blade.php`
- `resources/views/layouts/admin.blade.php`
- `app/Actions/OrderNotes/ApproveNoteExtraction.php`, `app/Actions/OrderNotes/RejectNoteExtraction.php`
- `app/Data/ReviewedOrderNote.php`
- `app/Exceptions/InvalidNoteExtractionStatusTransitionException.php`
- `database/migrations/2026_11_11_000005_add_is_admin_to_users_table.php`
- `database/migrations/2026_11_11_000006_add_review_columns_to_order_note_extractions_table.php`
- `database/seeders/DemoOrderSeeder.php`
- `tests/Feature/Admin/ReviewQueueTest.php`, `tests/Feature/Admin/AdminLoginTest.php`,
  `tests/Feature/DemoOrderSeederTest.php`

Change:
- `composer.json`, `composer.lock`, `routes/web.php`, `app/Providers/AppServiceProvider.php`,
  `app/Models/User.php`, `app/Models/OrderNoteExtraction.php`, `app/Enums/NoteExtractionStatus.php`,
  `app/Data/ExtractedOrderNote.php`, `app/Services/OrderNotes/LlmOrderNoteExtractor.php`,
  `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`,
  `tests/Unit/Enums/NoteExtractionStatusTest.php`, `README.md`

## Tests

Guest redirected to login; non-admin gets 403; the listing shows only `needs_review`; approve
with edited fields; approve with a past date fails; approve 400 days ahead works; exactly 2 years
ahead works, one day more fails; PO number too
long fails; reject; approve or reject a row no longer in review fails; login, wrong password,
logout; seeder seeds every state and runs twice; enum `transitionTo()`.

## Commits

1. Livewire install. 2. Admin login. 3. Review page with actions. 4. Demo seeder. 5. README.
