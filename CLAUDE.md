# CLAUDE.md — Rules for the AI agent

## Project
OrderFlow: a checkout and order automation service for a small B2B wholesaler (SME client scenario).
Customers check out, pay through a payment provider, and a webhook confirms the payment.
After payment, an LLM extracts structured data from the free-text order note
(delivery date, invoice address, PO number). Uncertain results go to a human review queue.

## Stack
- Laravel (latest), PHP 8.3+
- SQLite for local development and tests
- Livewire for the single back-office page
- PHPUnit for tests, Pint for code style, Larastan for static analysis

## Architecture rules
- Controllers stay thin: validate the request, call an Action, return a response.
- Business logic lives in `app/Actions` (one class, one public `execute()` / `__invoke()` method).
- Use PHP enums for statuses (`app/Enums`). Order status transitions are defined in the enum.
- Use DTOs (readonly classes) to pass structured data between layers (`app/Data`).
- External services sit behind interfaces (`app/Contracts`), bound in a service provider:
  - `PaymentGateway` → `FakePaymentGateway`
  - `OrderNoteExtractor` → LLM implementation + `FakeOrderNoteExtractor` for tests
- Side effects after payment run through events and queued jobs, never inline in controllers.
- Money is always stored as integers in cents. Never use floats for money.
- Error responses must not expose internal business data (stock levels, costs).
- Never trust LLM output: validate it in PHP, and let code, not the model, make
  safety decisions.

## Coding conventions
- `declare(strict_types=1);` in every PHP file.
- Type every parameter, property and return value.
- Follow Laravel naming conventions. Code must pass `vendor/bin/pint --test` and Larastan.
- No new Composer packages without asking me first.

## Testing rules
- Every feature comes with feature tests for the happy path AND the failure paths.
- Never call real external APIs in tests. Use the fake implementations.
- Tests must pass before a task is considered done.

## How we work
1. Before writing code, propose a short plan (files to create or change) and wait for my approval.
2. Work in small steps: one feature per task.
3. After implementing, list what you changed and anything you are unsure about.
4. Do not change unrelated files. Do not delete or weaken tests to make them pass.
