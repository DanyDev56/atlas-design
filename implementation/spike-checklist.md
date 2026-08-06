---
title: Spike ADR-002 — Incrément 0
status: Accepted
owner: Engineering
last_updated: 2026-08-06
references:
  - SPIKE-CLOSURE.md
  - ../../fondation/decisions/ADR-002-mvp-implementation-stack.md
  - ../../evolution/blueprint/implementation-plan.md
---

# Spike ADR-002 — Checklist des 12 conditions

**Statut : clôturé le 2026-08-06.** Voir [`SPIKE-CLOSURE.md`](SPIKE-CLOSURE.md).

| # | Condition | Preuve | Statut |
|---|---|---|---|
| 1 | PHP 8.5 exécute Laravel 13, Pest et PostgreSQL | Docker + CI | ☑ |
| 2 | Lockfiles reproductibles | `composer.lock` + CI npm | ☑ |
| 3 | Tests d'architecture | Pest arch | ☑ |
| 4 | Outbox atomique | Integration PostgreSQL | ☑ |
| 5 | Inbox sans double effet | Replay test | ☑ |
| 6 | Rôles SQL par module | init-roles + test | ☑ |
| 7 | Validation HTTP stricte | Feature API | ☑ |
| 8 | Trace HTTP → outbox | Correlation middleware | ☑ |
| 9 | CI docs + tests | `application.yml` | ☑ |
| 10 | Image OCI + SBOM | job CI `oci` | ☑ |
| 11 | Reprise outbox | Integration test | ☑ |
| 12 | Risques consignés | SPIKE-CLOSURE.md | ☑ |

`ADR-002` est **Accepted**.
