# AI_LOG — How I worked with the AI agent

Short entries, written on the day the work happened.
For each feature: what I asked, what the agent proposed, what I changed and why.

---

## Day 1 — Setup, products and cart

**Asked:** Plan products, cart API, Action classes and tests, plan first, no code.

**Agent proposed:** A clear plan with 6 test classes, including a check that an item from another cart returns 404. It also noticed three empty files (copy, findstr, mkdir) left in the repo from Windows commands, and flagged them instead of deleting them silently.

**What I changed / decided:** Asked it to delete the leftover files in a separate commit. It listed 6 open points after implementing; I accepted 4 (stock and price snapshot belong to checkout, DTOs only at boundaries), and asked it to fix the seeder so db:seed can run twice.

**Lesson:** Asking the agent to list what it is unsure about surfaced real design questions (stock reservation, SQLite ignores lockForUpdate) that I would otherwise have had to find myself.

---
