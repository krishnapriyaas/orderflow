# OrderFlow

![CI](https://github.com/krishnapriyaas/orderflow/actions/workflows/ci.yml/badge.svg)

OrderFlow is a small checkout and order service for a B2B wholesaler.

Customers order and pay online. A signed payment webhook confirms the payment, and it is safe against duplicate events. Customers often write special requests in the order note, like a delivery date or a PO number. An LLM turns these notes into structured data, and when it is not sure, a person reviews it.

I'm building this with Laravel and Claude Code as my AI agent. I plan every feature with the agent first, review the plan, and only then let it write code.

## Status

Work in progress. Full documentation comes when the app is complete.

- [x] Products and cart API (with tests)
- [x] Checkout and payment
- [x] Payment webhook
- [x] AI note extraction
- [ ] Review page
- [ ] Full documentation

## Architecture

- **Thin controllers, Action classes.** Controllers validate input with a FormRequest, call one Action (for example `CheckoutCart`) and return an API Resource. Business rules live in `app/Actions`.
- **Checkout is all-or-nothing.** Locking products, re-checking stock, decrementing stock and creating the order happen in one DB transaction. Unique constraints on `cart_id` and `idempotency_key` make double checkouts and retried requests safe. A repeated `Idempotency-Key` returns the original order only if the request is the same; otherwise it is rejected with 409.
- **Orders are snapshots.** Order items copy the product name, SKU and unit price at checkout, so later catalog changes never change a past order. Money is always integer cents.
- **Explicit order lifecycle.** `OrderStatus` is an enum that owns its allowed transitions. An invalid transition throws a domain exception instead of quietly writing a bad state.
- **External services behind interfaces.** `PaymentGateway` is a contract bound in the service provider. Today it resolves to `FakePaymentGateway`, and tests never call a real provider.
- **Webhooks are verified before anything else.** Middleware checks an HMAC-SHA256 signature over `"{timestamp}.{raw body}"` with `hash_equals` and rejects timestamps more than 5 minutes in the past or future. An invalid request gets 401 before any database write.
- **Each event is processed once.** `webhook_events.event_id` is unique. A redelivered event fails the insert, `HandlePaymentWebhook` catches the unique violation and returns 200 `duplicate` with no second side effect, also when two deliveries race.
- **Out-of-order events are safe.** The order status only changes through `Order::transitionTo()`. An event that is not an allowed transition (for example `failed` after `paid`), an unknown payment reference or a wrong amount or currency is stored with its `outcome` for audit, but the status stays the same. A late `succeeded` after `failed` does mark the order paid, because the provider is the source of truth for money.
- **Side effects run after commit.** `OrderPaid` is dispatched only after the transaction commits, and a listener queues `ExtractOrderNote`. A rollback can never trigger note extraction for an order that is not paid.
- **Event row and status change are atomic.** If processing fails, both roll back, so the provider's retry is processed normally instead of being treated as a duplicate.

## AI in the app

- **One narrow job.** After payment, Claude (`claude-haiku-5-5` by default, set with `ANTHROPIC_MODEL`) extracts three fields from the free-text order note: delivery date, PO number and invoice address. The answer is held to a fixed JSON schema (structured outputs), and the API is called with Laravel's Http client.
- **The note is data, not instructions.** All instructions are in the system prompt. The note is sent inside `<order_note>` tags it cannot close, the model has no tools, and it must flag `instructions_detected` when the note tries to instruct it. A flagged note always goes to review, whatever confidence it claims. An extraction only writes its own row, never the order's status, price or payment.
- **Nothing the model says is trusted blindly.** PHP validates every answer (types, lengths, a real date, confidence 0–1), and business rules decide the status: a past date, a date more than a year ahead, or confidence below 0.8 sends the extraction to `needs_review` with a `review_reason`. "Today" is the wholesaler's date in Europe/Berlin, not the server's UTC date.
- **Failures never lose an order.** Rate limits, server errors and timeouts are retried with backoff (3 attempts). Invalid answers, refusals and other API errors go to review at once, with the technical `error` and the `raw_response` kept. The job is idempotent: one row per order, enforced by a unique key.
- **Tests never call the real API.** Tests use `FakeOrderNoteExtractor` or `Http::fake`, and `Http::preventStrayRequests()` fails any unfaked call. Without an API key the app uses the fake, which sends every note to review.

## Known limits

- **SQLite ignores row locks.** `lockForUpdate()` is a no-op on SQLite. SQLite serialises write transactions, so the tests are still valid, but they do not prove the locking under concurrent requests the way MySQL or PostgreSQL would.
- **No stock release yet.** When starting the payment fails, the order is marked `payment_failed` (HTTP 502), but its stock stays decremented. Releasing stock for failed or cancelled orders comes in a later step.
- **No payment retry flow.** Replaying a checkout with the same `Idempotency-Key` returns the existing order, even when it is `payment_failed`. There is no endpoint yet to start a new payment for a failed order.
- **Untested race path.** When two checkouts race, the losing request hits a unique constraint (`cart_id` or `idempotency_key`), and `CheckoutCart` catches it and returns the existing order or a 409. SQLite runs write transactions one at a time, so the test suite cannot produce this race and the path is not covered by tests.
- **Malformed signed webhooks get 422.** A correctly signed payload that is missing fields is rejected with 422 and an error log, and nothing is stored. The provider will keep retrying it until it gives up, so these errors need a person to look at them.
- **Amount mismatches need manual handling.** A webhook whose amount or currency does not match the order is stored as `amount_mismatch` and logged as an error, but nothing else happens. There is no alert or review flow for it yet.
- **No rate limit on the webhook.** A limit could block real provider retries after an outage, so there is none. Requests with a bad signature are cheap (rejected before any database query) and logged at info level only.
- **Stock release must handle late payments.** A late `payment.succeeded` moves a `payment_failed` order to `paid`. A future step that releases stock for failed orders must handle this, or an order could be paid after its stock went back on sale.
- **Extraction quality is not measured yet.** The tests prove the plumbing (request, validation, review rules), not how well the model reads real notes. Before production, run `claude-haiku-5-5` on a sample of real, anonymised order notes and check the results and the 0.8 threshold by hand; switch `ANTHROPIC_MODEL` to a larger model if Haiku falls short.
- **Order notes are personal data.** Notes can contain names and addresses, and they are sent to Anthropic for extraction. A real deployment needs this in the privacy notice and a data processing agreement (GDPR), and should check the provider's data retention settings. To keep stored data to a minimum, `raw_response` holds only the model's answer text (the extracted fields), not the full API response; API error bodies are not stored at all.
- **Prompt injection is limited, not impossible.** A note can still try to mislead the model. The model's `instructions_detected` flag and its confidence are self-reported, so a clever note could get past both. What it can reach is only the three extracted fields on its own row, which a customer could have typed into the note anyway.
- **Concurrent duplicate jobs.** If the same job ran twice at the same moment, both runs would call the LLM; the unique key keeps only one row. Jobs are queued once per paid order, so there is no `WithoutOverlapping` lock for now.
- **Fixed retry backoff.** HTTP 429 is retried after 30 s and 120 s; the API's `retry-after` header is not read.

## How I work with the agent

- `CLAUDE.md` has the rules the agent follows in this project.
- `AI_LOG.md` is my daily log: what I asked, what the agent got right or wrong, and what I decided.