# Step 3 — Payment webhook

Plan written by Claude Code. Reviewed and approved by me on 2026-11-11, with the decisions below.

## Goal

`POST /api/webhooks/payment` confirms or fails a payment. It is secure and safe against
duplicate and out-of-order events.

## Payload

```json
{
  "id": "evt_123",
  "type": "payment.succeeded",
  "data": { "payment_reference": "fake_pay_...", "amount_cents": 6670, "currency": "EUR" }
}
```

Headers: `X-Timestamp` (Unix seconds), `X-Signature` (lowercase hex
`HMAC-SHA256("{timestamp}.{raw body}", PAYMENT_WEBHOOK_SECRET)`).

## Flow

1. Middleware `VerifyPaymentWebhookSignature` (route-level) calls `WebhookSignatureVerifier`:
   - missing `X-Signature` / `X-Timestamp`, or a timestamp that is not an integer → reject;
   - timestamp more than 300 s in the past **or the future** → reject;
   - `hash_equals` against the HMAC computed over the raw body (`$request->getContent()`);
   - no secret configured → reject (fail closed) and log an error.
   Any rejection → 401 JSON, nothing written. The signing format lives in one place
   (`WebhookSignature::sign()`), used by the verifier and by `FakePaymentGateway`.
2. `PaymentWebhookRequest` validates `id`, `type`, `data.payment_reference`,
   `data.amount_cents` (integer ≥ 0) and `data.currency` (3 letters). Signed but malformed
   → 422, nothing written, error logged.
3. `PaymentWebhookController` builds a `PaymentWebhookEvent` DTO, calls `HandlePaymentWebhook`,
   returns `200 {"outcome": "<outcome>"}`.
4. `HandlePaymentWebhook`, in one DB transaction:
   - insert the `webhook_events` row. A duplicate `event_id` raises
     `UniqueConstraintViolationException`; it is caught outside the transaction (same pattern
     as `CheckoutCart`), the whole transaction rolls back, and the outcome is `duplicate`;
   - unknown `type` → `unknown_type`;
   - order by `payment_reference` (`lockForUpdate`); none → `unknown_reference`, warning logged;
   - amount or currency differ from the order → `amount_mismatch`, status unchanged, error logged;
   - target status not allowed (`canTransitionTo()`) → `ignored_transition`, info logged;
   - otherwise `Order::transitionTo()` → `applied`;
   - the event row gets `outcome` and `processed_at`.
   Event row and status change commit together: if processing fails, both roll back and the
   provider's retry is processed normally.
5. Transition to `paid` → `OrderPaid` (`ShouldDispatchAfterCommit`). Listener
   `QueueOrderNoteExtraction` dispatches the queued job `ExtractOrderNote` (empty until Step 4).

## Decisions

1. `payment_failed → paid` is now allowed in `OrderStatus`. The provider is the source of truth
   for money, and stock is still reserved. Enum unit test and the Step 2 plan are updated.
2. Timestamps more than 300 s in the future are rejected too.
3. `webhook_events.outcome` is an enum: `applied`, `duplicate`, `ignored_transition`,
   `unknown_reference`, `amount_mismatch`, `unknown_type`. (`duplicate` is only ever a response
   outcome: a duplicate never gets its own row.)
4. Signed but malformed payload → 422 and an error log. Listed under Known limits (the provider
   will retry it).
5. Amount and currency are checked against the order; mismatch → `amount_mismatch`, no status
   change, error log.
6. Duplicates are detected by inserting and catching `UniqueConstraintViolationException`, not
   `insertOrIgnore` and not check-then-insert.
7. `X-Signature` is plain lowercase hex, `X-Timestamp` is Unix seconds.

## Follow-up decisions after implementation

1. A duplicate `event_id` with a different payload is still `duplicate` (the first one wins),
   and a warning is logged.
2. Payment fields (`data.payment_reference`, `data.amount_cents`, `data.currency`) are required
   only for known event types. Unknown types are stored as `unknown_type` with any `data` shape.
3. The currency is uppercased before comparing, so `"eur"` matches `"EUR"`. The stored payload
   keeps the body as sent.
4. The event row is still inserted first and updated with `outcome` / `processed_at` after.
5. Invalid signatures are logged at info level, not warning. No rate limit, because it could
   block real provider retries (README known limit). A missing secret is still an error.
6. README known limit: a future stock release step must handle `payment_failed → paid`.

## Files

Create:
- `database/migrations/2026_11_11_000003_create_webhook_events_table.php`
- `app/Models/WebhookEvent.php`
- `app/Enums/PaymentWebhookEventType.php`, `app/Enums/WebhookOutcome.php`
- `app/Data/PaymentWebhookEvent.php`, `app/Data/SignedWebhook.php`
- `app/Services/Payment/WebhookSignature.php`, `app/Services/Payment/WebhookSignatureVerifier.php`
- `app/Http/Middleware/VerifyPaymentWebhookSignature.php`
- `app/Http/Requests/PaymentWebhookRequest.php`
- `app/Http/Controllers/Api/PaymentWebhookController.php`
- `app/Actions/Payment/HandlePaymentWebhook.php`
- `app/Events/OrderPaid.php`, `app/Listeners/QueueOrderNoteExtraction.php`, `app/Jobs/ExtractOrderNote.php`
- `tests/Feature/Payment/PaymentWebhookTest.php`, `tests/Unit/Payment/WebhookSignatureVerifierTest.php`

Change:
- `routes/api.php`, `config/services.php`, `.env.example`, `phpunit.xml`
- `app/Services/Payment/FakePaymentGateway.php` (`signedWebhook()`, `signWebhookBody()`)
- `app/Providers/AppServiceProvider.php` (verifier binding)
- `app/Enums/OrderStatus.php`, `tests/Unit/Enums/OrderStatusTest.php` (decision 1)
- `docs/plans/step-2-checkout.md` (transitions), `README.md`

## Tests

Valid succeeded; valid failed; succeeded after failed; bad signature; tampered body; missing
headers; old and future timestamp; no secret configured; malformed payload (422); duplicate
`event_id`; failed after paid; unknown reference; amount and currency mismatch; unknown type;
`Event::fake` (OrderPaid exactly once) and `Queue::fake` (ExtractOrderNote exactly once);
OrderPaid runs after commit; verifier unit tests (300 s / 301 s boundaries).

## My review

I reviewed this plan with a second Claude chat as a reviewer, then made the final decisions myself.

- Agreed: the signature is checked over the raw body before anything is written.
- Changed: I asked for the same insert-and-catch pattern as in checkout, not insertOrIgnore,
  because insertOrIgnore hides other errors on MySQL.
- Added: the paid amount and currency must match the order. I also allowed payment_failed → paid,
  because the provider decides if money was taken.
- Learned: a test is only useful if it can fail. The agent proved it by breaking the code on purpose.
