# Step 2 — Checkout and payment

Plan written by Claude Code. Reviewed and approved by me on 2026-11-11.
My review is at the end of this file.

## Goal

`POST /api/carts/{cart}/checkout` turns a cart into an order and starts a payment.

## Flow

1. `CheckoutRequest` validates `email` (required, email, max 255), `note` (nullable string,
   max 2000) and the optional `Idempotency-Key` header (max 255).
2. One DB transaction:
   - Lock the cart row.
   - If an `Idempotency-Key` is given and an order with that key exists:
     - same cart, same email, same note → return that order (200, `Idempotent-Replayed: true`);
     - different cart, email or note → 409.
   - If the cart already has an order → 409.
   - Empty cart → 422.
   - Lock the products (`lockForUpdate`, ordered by id), re-check stock. Any line short → 409,
     nothing written (all shortages listed in the response).
   - Decrement stock, create the order (`pending_payment`) and order items with snapshotted
     product name, sku and `unit_price_cents`. Total = sum of integer line totals.
3. After commit: `PaymentGateway::createPayment($order)`, store `payment_reference` and the
   redirect URL. The gateway is called outside the transaction so product locks are not held
   during an external call.
   - Gateway failure → order marked `payment_failed`, response 502. Stock is not released.
4. Response: `OrderResource`, 201.

Safety nets for races: `orders.cart_id` and `orders.idempotency_key` are unique. A unique
violation is caught and resolved to the same replay / 409 logic.

## Order status transitions

```
pending_payment → paid | payment_failed | cancelled
payment_failed  → pending_payment (retry) | cancelled
paid            → terminal (no paid → cancelled for now)
cancelled       → terminal
```

Invalid transition throws `InvalidOrderStatusTransitionException`.

## Decisions

1. New `StockConflictException` (409) for checkout. Step 1's `InsufficientStockException`
   (422) is unchanged.
2. Gateway failure after commit: mark `payment_failed`, return 502. Stock release is out of scope.
3. `Idempotency-Key` is optional.
4. Replay returns 200 with `Idempotent-Replayed: true`.
5. Cart item add/update/remove on a checked-out cart returns 409 — separate commit after the
   checkout commit.
6. Orders use a `uuid` column as public id.
7. Transitions as above.
8. SQLite ignores `lockForUpdate()`; documented as a known limit in the README.
9. Addition: same `Idempotency-Key` with a different email or note → 409, not the old order.

## Follow-up decisions after implementation

1. Keep `orders.payment_redirect_url` (added so a replay returns the same response).
2. Emails are trimmed and lowercased before storing and comparing for replays.
3. Replaying a `payment_failed` order returns it as is; no payment retry flow yet (README known limit).
4. Only `PaymentGatewayException` is handled; the `PaymentGateway` docblock requires
   implementations to wrap provider errors in it.
5. The stock conflict 409 returns only the short SKUs (`short_skus`), no stock levels.
6. The unique-constraint race path is not covered by tests (README known limit).
7. Keep the README note that stock is not released after a payment failure.

## Files

Create:
- `database/migrations/2026_11_11_000001_create_orders_table.php`
- `database/migrations/2026_11_11_000002_create_order_items_table.php`
- `app/Enums/OrderStatus.php`, `app/Enums/PaymentIntentStatus.php`
- `app/Exceptions/InvalidOrderStatusTransitionException.php`, `EmptyCartException.php`,
  `CartAlreadyCheckedOutException.php`, `StockConflictException.php`,
  `IdempotencyKeyConflictException.php`, `PaymentGatewayException.php`,
  `PaymentStartFailedException.php`
- `app/Models/Order.php`, `app/Models/OrderItem.php`
- `app/Data/PaymentIntent.php`, `app/Data/CheckoutData.php`, `app/Data/CheckoutResult.php`
- `app/Contracts/PaymentGateway.php`, `app/Services/Payment/FakePaymentGateway.php`
- `app/Actions/Checkout/CheckoutCart.php`
- `app/Http/Controllers/Api/CheckoutController.php`, `app/Http/Requests/CheckoutRequest.php`
- `app/Http/Resources/OrderResource.php`, `app/Http/Resources/OrderItemResource.php`
- `database/factories/OrderFactory.php`, `database/factories/OrderItemFactory.php`
- `tests/Feature/Checkout/CheckoutCartTest.php`, `tests/Unit/Enums/OrderStatusTest.php`

Change:
- `routes/api.php` (route), `app/Providers/AppServiceProvider.php` (binding, strict_types),
  `app/Models/Cart.php` (`order()` relation), `README.md` (architecture, known limits, status).

Second commit (decision 5):
- `app/Actions/Cart/AddItemToCart.php`, `UpdateCartItemQuantity.php`, `RemoveCartItem.php`
- `tests/Feature/Cart/CheckedOutCartTest.php`

## Tests

Happy path; empty cart; insufficient stock with no stock change; price snapshot; double
checkout; same Idempotency-Key (replay); same key with different cart / email / note (409);
validation; unknown cart; gateway failure (502, `payment_failed`); enum transitions (unit).

## My review

I reviewed this plan with a second Claude chat as a reviewer, then made the final decisions myself.

What I agreed with:
- Payment is called after the commit, because the payment provider can be slow.
  If we call it inside the transaction, the products stay locked and other customers have to wait.
- Products are locked in id order, because two checkouts could lock the same products
  in a different order and wait for each other forever (deadlock). The same order for everyone prevents this.

What I changed:
- The 409 for missing stock showed stock levels. I removed them, because a wholesaler
  does not want customers or competitors to see how much stock is left. Now it only shows which SKUs are short.
- Emails are now lowercased, because "Buyer@x.com" and "buyer@x.com" are the same person.
  A retry with different casing should not count as a different request.

What I added:
- Same Idempotency-Key with a different email returns 409, because the key means
  "this is the same request again". If the content is different, it is not a retry but a mistake,
  and returning the old order would hide the problem.

What I accepted as a limit:
- Stock is not released after a payment failure. I think this is OK for a demo because
  the main goal is safe checkout and payment. Releasing stock needs its own flow
  (cancel, timeout), and I wrote it in the README as a known limit.

What I learned:
- Asking the agent to list what it is unsure about found real problems, like the 409 vs 422 question
  and the untested race path. I would not have found all of them alone.
