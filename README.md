# OrderFlow

![CI](https://github.com/krishnapriyaas/orderflow/actions/workflows/ci.yml/badge.svg)

OrderFlow is a small checkout and order service for a B2B wholesaler.

Customers order and pay online. A signed payment webhook confirms the payment, and it is safe against duplicate events. Customers often write special requests in the order note, like a delivery date or a PO number. An LLM turns these notes into structured data, and when it is not sure, a person reviews it.

I'm building this with Laravel and Claude Code as my AI agent. I plan every feature with the agent first, review the plan, and only then let it write code.

## Status

Work in progress. Full documentation comes when the app is complete.

- [x] Products and cart API (with tests)
- [ ] Checkout and payment
- [ ] Payment webhook
- [ ] AI note extraction and review page
- [ ] Full documentation

## How I work with the agent

- `CLAUDE.md` has the rules the agent follows in this project.
- `AI_LOG.md` is my daily log: what I asked, what the agent got right or wrong, and what I decided.