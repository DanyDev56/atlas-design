---
title: Palier 3 — Clôture Track A (publication contrôlée)
status: Accepted
owner: Engineering
date: 2026-08-07
references:
  - MVP-CLOSURE.md
  - SEC-TEST-MATRIX.md
  - runbooks/beta-release-checklist.md
  - ../evolution/blueprint/roadmap.md
---

# Clôture Palier 3 — Track A engineering

Ce document atteste la livraison engineering du **Track A** (publication contrôlée
beta) sous `implementation/`. Les décisions Product+Security et la beta fermée
restent des gates distinctes.

---

## Synthèse

| Lot | Contenu | Statut |
|---|---|---|
| 1 | Backup/restore/canary, Jaeger, logs JSON | ☑ |
| 2 | OTLP PHP (traces HTTP + outbox) | ☑ |
| 3 | Backlog outbox, révocation session, runbooks incident + rétention draft | ☑ |
| 4 | RemoveMembership, rate limit auth, idempotence doc, CI release-rehearsal | ☑ |
| 5 | Rate limit public quote, webhook alerte outbox, clôture doc + checklist beta | ☑ |

| Élément | Statut |
|---|---|
| Tests Pest | ☑ 83+ (CI job `spike`) |
| Matrice SEC-TEST beta (006, 004, 007, 013, 017, 018, 023) | ☑ |
| Runbooks exploitation | ☑ backup, observabilité, outbox, beta release |
| Gate observabilité manuelle (Jaeger + logs) | ◐ checklist beta |
| SEC-GAP-004 / 006 (Product+Security) | ◐ drafts / décision hors site |

---

## Preuves par lot

### Lot 1 — Infra backup + observabilité
- Scripts : `scripts/backup-postgres.sh`, `restore-postgres.sh`, `verify-restore-canary.sh`
- Profile Docker `observability` (Jaeger + otel-collector)
- Logs JSON : `json_stderr`, `CorrelationIdMiddleware`
- Runbook : [`runbooks/backup-restore.md`](runbooks/backup-restore.md)

### Lot 2 — OTLP PHP
- `Telemetry`, `HttpTracingMiddleware`, `TraceScope` / outbox
- Config : `config/otel.php`
- Runbook : [`runbooks/observability.md`](runbooks/observability.md)

### Lot 3 — Opérations + Identity durci
- `OutboxBacklogMonitor`, `RevokeSessionHandler`, `POST /api/auth/session/revoke`
- Runbooks : [`outbox-incident.md`](runbooks/outbox-incident.md), [`data-retention-beta.md`](runbooks/data-retention-beta.md) (draft)
- Tests : `RevokeSessionTest`, `OutboxBacklogMonitorTest`

### Lot 4 — Authz + rate limit + rehearsal
- `RemoveMembershipHandler`, `POST .../memberships/{id}/remove`
- Rate limit auth : `AuthRateLimitTest`
- Catalogue : [`docs/idempotency.md`](docs/idempotency.md)
- CI : job `release-rehearsal` (workflow_dispatch)
- Tests : `MembershipRevocationTest`

### Lot 5 — Fermeture gates engineering
- Rate limit route publique quote accept : `PublicQuoteRateLimitTest`
- Webhook alerte backlog optionnel : `OUTBOX_BACKLOG_ALERT_WEBHOOK_URL`
- Checklist beta : [`runbooks/beta-release-checklist.md`](runbooks/beta-release-checklist.md)

---

## Definition of Done Palier 3 — statut honnête

| Critère roadmap | Statut | Commentaire |
|---|---|---|
| Sauvegarde / restauration / canary | ☑ | SEC-TEST-023 automatisé + CI rehearsal |
| Observabilité (logs + traces) | ◐ | OTLP branché ; gate Jaeger manuelle |
| Alertes / runbooks | ◐ | Logs + webhook optionnel ; pas de PagerDuty |
| SEC-T beta subset | ☑ | 006, 004, 007, 013, 017, 018 |
| Step-up / fuzz / scan vuln | ◻ | Hors scope Track A beta |
| Beta fermée + outcomes | ◻ | Product |

---

## Écarts acceptés (Track A)

| Écart | Statut post-Track A |
|---|---|
| OpenTelemetry OTLP | ☑ livré (profile `observability`) |
| Sauvegarde/restauration scripts | ☑ ; hors site ◐ SEC-GAP-006 |
| Runbooks / alertes | ◐ runbooks ☑ ; alerting externe via webhook optionnel |
| Step-up SEC-T04 | ◻ post-beta |
| Import historique | ◻ Product |
| Migrations down production | ◻ post-beta |

---

## Commandes de vérification

```bash
make up
make migrate-fresh
make test                    # 83+ tests
make verify-restore          # SEC-TEST-023 manuel
make up-observability        # Jaeger + OTLP
```

CI : `application.yml` — jobs `documentation`, `spike`, `oci`, `release-rehearsal` (dispatch).

---

## Prochaine étape (hors Track A engineering)

1. Valider [`data-retention-beta.md`](runbooks/data-retention-beta.md) avec Product+Security
2. Exécuter [`beta-release-checklist.md`](runbooks/beta-release-checklist.md)
3. Ouvrir beta fermée et mesurer les outcomes (Palier 3 roadmap)
4. Palier 4 — extensions selon usages observés
