# OrderFlow

![CI](https://github.com/krishnapriyaas/orderflow/actions/workflows/ci.yml/badge.svg)

OrderFlow is a small checkout and order service for a B2B wholesaler.

Customers order and pay online. A signed payment webhook confirms the payment, and it is safe against duplicate events. Customers often write special requests in the order note, like a delivery date or a PO number. An LLM turns these notes into structured data, and when it is not sure, a person reviews it.

I'm building this with Laravel and Claude Code as my AI agent. I plan every feature with the agent first, review the plan, and only then let it write code.

## Status

Work in progress. Full documentation comes when the app is complete.

- [x] Products and cart API (with tests)
- [x] Checkout and payment
- [ ] Payment webhook
- [ ] AI note extraction and review page
- [ ] Full documentation

## Architecture

- **Thin controllers, Action classes.** Controllers validate input with a FormRequest, call one Action (for example `CheckoutCart`) and return an API Resource. Business rules live in `app/Actions`.
- **Checkout is all-or-nothing.** Locking products, re-checking stock, decrementing stock and creating the order happen in one DB transaction. Unique constraints on `cart_id` and `idempotency_key` make double checkouts and retried requests safe. A repeated `Idempotency-Key` returns the original order only if the request is the same; otherwise it is rejected with 409.
- **Orders are snapshots.** Order items copy the product name, SKU and unit price at checkout, so later catalog changes never change a past order. Money is always integer cents.
- **Explicit order lifecycle.** `OrderStatus` is an enum that owns its allowed transitions. An invalid transition throws a domain exception instead of quietly writing a bad state.
- **External services behind interfaces.** `PaymentGateway` is a contract bound in the service provider. Today it resolves to `FakePaymentGateway`, and tests never call a real provider.

## Known limits

- **SQLite ignores row locks.** `lockForUpdate()` is a no-op on SQLite. SQLite serialises write transactions, so the tests are still valid, but they do not prove the locking under concurrent requests the way MySQL or PostgreSQL would.
- **No stock release yet.** When starting the payment fails, the order is marked `payment_failed` (HTTP 502), but its stock stays decremented. Releasing stock for failed or cancelled orders comes in a later step.
- **No payment retry flow.** Replaying a checkout with the same `Idempotency-Key` returns the existing order, even when it is `payment_failed`. There is no endpoint yet to start a new payment for a failed order.
- **Untested race path.** When two checkouts race, the losing request hits a unique constraint (`cart_id` or `idempotency_key`), and `CheckoutCart` catches it and returns the existing order or a 409. SQLite runs write transactions one at a time, so the test suite cannot produce this race and the path is not covered by tests.

## How I work with the agent

- `CLAUDE.md` has the rules the agent follows in this project.
- `AI_LOG.md` is my daily log: what I asked, what the agent got right or wrong, and what I decided.