# Step 4 — AI extraction of the order note

Plan written by Claude Code. Reviewed and approved by me on 2026-11-11, with the decisions below.

## Goal

The `ExtractOrderNote` job (queued from `OrderPaid` in Step 3) extracts a delivery date, a PO
number and an invoice address from the free-text order note. Uncertain or failed results go to
a human review queue.

## Flow

1. `ExtractOrderNote::handle()` calls the `ExtractOrderNoteData` action.
2. `ExtractOrderNoteData::execute($order)`:
   - empty or whitespace-only note → return; no LLM call, no row;
   - the order already has an extraction row → return it (idempotent);
   - `today` = the current date in `orderflow.business_timezone` (Europe/Berlin);
   - `$extractor->extract($note, $today)` → `ExtractedOrderNote`;
   - business rules, first match wins:
     `instructions_detected` → review; delivery date before today → review; delivery date more
     than one year after today → review; confidence below
     `orderflow.note_extraction.auto_apply_threshold` (0.8) → review; otherwise `auto_applied`;
   - insert the row; a `UniqueConstraintViolationException` on `order_id` is caught and the
     existing row is returned (same pattern as checkout and the webhook).
3. Errors never crash the job without leaving a row:
   - retryable (HTTP 429, 5xx, connection error or timeout) → `NoteExtractionUnavailableException`,
     the job retries (`$tries = 3`, `backoff() = [30, 120]` seconds); after the last attempt
     `failed()` stores a `needs_review` row (`service_unavailable`);
   - not retryable (other 4xx, invalid JSON, missing or invalid fields, `stop_reason` `refusal`
     or `max_tokens`) → `InvalidNoteExtractionException`, stored at once as `needs_review` with
     the reason, the technical `error` and the `raw_response`.

## The LLM call (`LlmOrderNoteExtractor`)

`POST` to `services.anthropic.url` with Laravel's `Http` client (no SDK, no new package).

- Headers: `x-api-key`, `anthropic-version: 2026-10-21`, `content-type: application/json`.
- `model` from `ANTHROPIC_MODEL`, default `claude-haiku-5-5`. `max_tokens` 4096 (thinking is on by
  default on Haiku 5.5 and counts toward it).
- `output_config: { effort: "low", format: { type: "json_schema", schema } }`. No `temperature`
  (rejected on API version `2026-10-21`). No refusal fallback: Haiku 5.5 has none server-side.
- Schema: `delivery_date` (date string or null), `po_number` (string or null), `invoice_address`
  (string or null), `confidence` (number), `instructions_detected` (boolean); all required,
  `additionalProperties: false`.
- Response: content blocks are read by `type` (a response can start with `thinking` blocks), the
  text is decoded and validated in PHP: date `Y-m-d`, PO number ≤ 100 chars, address ≤ 500 chars,
  confidence a number 0–1, `instructions_detected` a real boolean.

### Prompt injection

The note is untrusted customer input. The design limits what a successful injection can do:

1. All instructions are in the system prompt. The user message holds only today's date and the
   note inside `<order_note>…</order_note>`. The system prompt says that text is data, never an
   instruction.
2. `<order_note>` / `</order_note>` are stripped from the note, so it cannot close the tag.
3. Structured outputs, no tools: the answer is five fields, nothing else.
4. The model sets `instructions_detected` when the note addresses it; code then always sends the
   result to review, whatever the confidence.
5. Every value is validated in code; an extraction only writes its own row, never the order's
   status, price or payment.

## Table `order_note_extractions`

`id`, `order_id` (unique, foreign key), `delivery_date` (date, nullable), `po_number` (nullable),
`invoice_address` (text, nullable), `confidence` (decimal(4,3), nullable), `instructions_detected`
(boolean), `status`, `review_reason` (nullable), `raw_response` (nullable), `error` (nullable),
timestamps.

- `NoteExtractionStatus`: `auto_applied`, `needs_review`, `approved`, `rejected`;
  transitions `needs_review → approved | rejected` (used by the Step 5 review page).
- `NoteReviewReason`: `low_confidence`, `instructions_detected`, `past_delivery_date`,
  `delivery_date_too_far_ahead`, `invalid_response`, `refused`, `service_error`,
  `service_unavailable`, `extraction_failed`.

## Decisions

1. `review_reason` is an enum; `error` keeps the technical details.
2. Extracted data stays on the extraction row; nothing is copied onto `orders`.
3. `instructions_detected` is part of the schema. If true → always `needs_review`. A test sends
   a note that tries prompt injection.
4. Default model `claude-haiku-5-5`, configurable in `.env`. Known limit: quality must be checked
   on real notes before production.
5. Known limit: order notes (addresses, names) are personal data sent to Anthropic (GDPR).
6. "Today" uses `orderflow.business_timezone` = Europe/Berlin. A test covers a late-evening note.
7. No `WithoutOverlapping` for now.
8. A delivery date more than one year ahead → `needs_review`.
9. Http client only, no SDK.

## Follow-up decisions after implementation

1. Keep the `failed_jobs` row after the last failed attempt: the extraction is in review, and
   the failed job still shows up in monitoring.
2. Keep the retry test that runs the real queue worker on the database queue.
3. `raw_response` stores only the model's answer text, not the whole API body (GDPR data
   minimisation). HTTP error bodies are not stored; `error` says which status came back.
4. The webhook tests now run the extraction action through the sync queue (their orders have
   no note, so it does nothing). Accepted.
5. `max_days_ahead` stays a fixed config value (365), not an `.env` setting.
6. Found while implementing: delivery dates are compared as calendar dates (`Y-m-d`), not
   timestamps, because "today" is midnight in Berlin (23:00 UTC the day before).

## Fake and binding

`FakeOrderNoteExtractor` (`willReturn()`, `willThrow()`, `calls()`) returns all-null fields with
confidence 0.0 by default, so an app running on the fake by mistake sends everything to review.
`AppServiceProvider` binds the fake when the environment is `testing` or `ANTHROPIC_API_KEY` is
empty, otherwise `LlmOrderNoteExtractor`. `tests/TestCase.php` calls `Http::preventStrayRequests()`.

## Files

Create:
- `database/migrations/2026_11_11_000004_create_order_note_extractions_table.php`
- `app/Models/OrderNoteExtraction.php`, `database/factories/OrderNoteExtractionFactory.php`
- `app/Enums/NoteExtractionStatus.php`, `app/Enums/NoteReviewReason.php`
- `app/Contracts/OrderNoteExtractor.php`, `app/Data/ExtractedOrderNote.php`
- `app/Services/OrderNotes/LlmOrderNoteExtractor.php`, `app/Services/OrderNotes/FakeOrderNoteExtractor.php`
- `app/Exceptions/NoteExtractionUnavailableException.php`, `app/Exceptions/InvalidNoteExtractionException.php`
- `app/Actions/OrderNotes/ExtractOrderNoteData.php`
- `config/orderflow.php`
- `tests/Feature/OrderNotes/ExtractOrderNoteTest.php`, `tests/Feature/OrderNotes/LlmOrderNoteExtractorTest.php`,
  `tests/Unit/Enums/NoteExtractionStatusTest.php`

Change:
- `app/Jobs/ExtractOrderNote.php`, `app/Models/Order.php` (`noteExtraction()`),
  `app/Providers/AppServiceProvider.php`, `config/services.php`, `.env.example`, `phpunit.xml`,
  `tests/TestCase.php`, `README.md`

## Tests

High confidence; low confidence; exactly 0.8; empty note skips the call; past date; date more
than a year ahead; `instructions_detected`; prompt-injection note end to end with `Http::fake`;
late-evening note (Berlin date, not UTC); job run twice; invalid JSON / missing field / refusal /
4xx → review; HTTP 500 → review after 3 attempts on the database queue; the extractor sends the
expected request and parses a sample response; enum transitions.

## My review

I reviewed this plan with a second Claude chat as a reviewer, then made the final decisions myself.

- Agreed: the note is data, not instructions, and every model answer is validated in PHP.
- Changed: Haiku as the default model, because this is a small task and cost matters for an SME.
  Only the model's answer text is stored, not the full API response (GDPR data minimisation).
- Added: instructions_detected always sends the note to review. A manipulated model can't be
  trusted to judge itself, so code decides. "Today" uses Europe/Berlin.
- Learned: with AI features, the hard part is deciding what happens when the model is wrong.
