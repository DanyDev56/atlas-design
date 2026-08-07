---
title: Rétention données beta (SEC-GAP-004)
owner: Product + Security
status: validated-beta
last_updated: 2026-08-07
references:
  - ../SEC-TEST-MATRIX.md
  - ../../fondation/security/mvp-threat-model.md
---

# SEC-GAP-004 — Rétention et support (beta)

> **Statut : validé beta** — tableau de rétention accepté par Product et Security.
> Purge automatisée livrée (`atlas:retention:purge`).

## Objectif

Définir des durées de conservation et des procédures de suppression compatibles avec
la beta B2B, sans bloquer l'exploitation (backup, observabilité, support client).

## Proposition beta

| Donnée | Rétention proposée | Justification | Suppression |
|---|---|---|---|
| Comptes utilisateurs actifs | Durée du contrat beta | Exploitation normale | Sur demande / fin beta |
| Sessions (`identity.sessions`) | 30 j après expiration ou révocation | SEC-T03 / SEC-TEST-004 | `atlas:retention:purge` (quotidien 03:00 UTC) |
| Tokens vérification email | 24 h (existant) | SEC-T02 | Expiration automatique |
| Logs applicatifs (JSON) | 14 j | Debug + incident | Rotation infra |
| Traces Jaeger | 7 j | Observabilité Palier 3 | Rétention Jaeger |
| Backups PostgreSQL | 30 j rolling | SEC-TEST-023 | Rotation script backup |
| Outbox dispatchés | 90 j | Audit événements | `atlas:retention:purge` |
| Idempotency keys | 30 j | Rejeu API | `atlas:retention:purge` |

## Purge automatisée

Commande : `php artisan atlas:retention:purge` (alias Makefile : `make retention-purge`).

- Planification Laravel : quotidien à **03:00** (`bootstrap/app.php`).
- En production / beta : cron `* * * * * php artisan schedule:run` ou équivalent orchestrateur.
- Simulation : `make retention-purge DRY_RUN=1` ou `--dry-run`.
- Config : `config/platform.php` → `retention.*` (env `RETENTION_*_DAYS`).

Logs structurés : message `Retention purge completed` avec compteurs par catégorie.

## Support client

- Canal beta : **email** `beta@atlas-design.fr` *(placeholder)*.
- Périmètre utilisateurs : **beta fermée interne** (équipe Atlas + proches).
- Export des données workspace : **hors scope beta** (documenter comme gap).
- Droit à l'effacement : procédure manuelle via runbook backup-restore + suppression compte (à rédiger).

## Décisions ouvertes

1. ~~Durée exacte sessions révoquées vs expirées.~~ **30 j unifié** (expiration ou révocation).
2. ~~Hébergement backups hors site (SEC-GAP-006).~~ **Beta : risque accepté** — dumps locaux uniquement, rotation 30 j ; hors site reporté post-beta.
3. ~~Automatisation purge vs job manuel mensuel.~~ **Automatisé** — `atlas:retention:purge`, schedule quotidien.

## Prochaine étape

Post-beta : purge `platform.inbox_receipts`, export données workspace, procédure effacement compte.
