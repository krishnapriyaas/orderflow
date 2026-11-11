# AI_LOG — How I worked with the AI agent

Short entries, written on the day the work happened.
For each feature: what I asked, what the agent proposed, what I changed and why.

---

## Step 1 — Setup, products and cart

**Asked:** Plan products, cart API, Action classes and tests, plan first, no code.

**Agent proposed:** A clear plan with 6 test classes, including a check that an item from another cart returns 404. It also noticed three empty files (copy, findstr, mkdir) left in the repo from Windows commands, and flagged them instead of deleting them silently.

**What I changed :** Asked it to delete the leftover files in a separate commit. It listed 6 open points after implementing; I accepted 4 (stock and price snapshot belong to checkout, DTOs only at boundaries), and asked it to fix the seeder so db:seed can run twice.

**Lesson:** Asking the agent to list what it is unsure about surfaced real design questions (stock reservation, SQLite ignores lockForUpdate) that I would otherwise have had to find myself.

---
## Step 2 — Checkout and payment

**Asked:** Plan checkout, orders and a fake payment gateway, plan first, no code. I asked for a price snapshot, stock checks in one transaction, an Idempotency-Key and an order status enum.

**Agent proposed:** A good plan. It called the payment gateway after the commit, so the products are not locked during a slow external call, and it locked products in id order to avoid deadlocks.
It asked 8 open questions, for example whether to use 409 or 422 for missing stock, because
Step 1 already used 422.

**What I changed:** I added a rule: the same Idempotency-Key with a different email or note
returns 409, not the old order. After implementing, I removed the stock levels from the 409
response, because a wholesaler doesn't want customers to see its stock. I also asked for
lowercased emails and a separate commit to block cart changes after checkout.

**Lesson:** The agent was honest about what it could not test (the race between two checkouts on SQLite). I put this in the README as a known limit instead of hiding it.

---
## Step 3 — Payment webhook

**Asked:** Plan a signed payment webhook, plan first, no code. HMAC signature over the raw body,
old timestamps rejected, each event processed only once, and an OrderPaid event after the commit.

**Agent proposed:** A careful plan. It noticed by itself that postJson() in tests would re-encode
the JSON and break the signature, so the tests send the exact raw body. It also proved its
after-commit test works: it removed ShouldDispatchAfterCommit, saw the test fail, and put it back.

**What I changed:** I added a check that the paid amount and currency match the order. If not,
the order is not marked paid. I allowed payment_failed → paid, because if the provider says the
money was taken, the order must be paid. I also asked for the same insert-and-catch pattern as
in checkout instead of insertOrIgnore, which hides other errors on MySQL.

**Lesson:** A test is only useful if it can fail. Breaking the code on purpose is a quick way
to check that.
