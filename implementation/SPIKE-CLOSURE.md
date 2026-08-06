---
title: Spike ADR-002 — Clôture incrément 0
status: Accepted
owner: Engineering
date: 2026-08-06
references:
  - spike-checklist.md
  - ../fondation/decisions/ADR-002-mvp-implementation-stack.md
  - ../fondation/decisions/ADR-001-mvp-application-topology.md
---

# Clôture du spike incrément 0

Ce document atteste que le spike de l'incrément 0 prouve la faisabilité de la
stack proposée dans `ADR-002`. L'ADR passe à **Accepted** avec les écarts
documentés ci-dessous.

---

## Preuves par condition

| # | Condition | Preuve | Statut |
|---|---|---|---|
| 1 | PHP + Laravel 13 + Pest + PostgreSQL | Docker `php:8.4-cli`, Laravel 13.24, Pest 4.7, Postgres 18 ; 15 tests Pest | ☑ |
| 2 | Lockfiles reproductibles | `composer.lock` commité ; `npm install` en CI | ☑ |
| 3 | Tests d'architecture | `tests/Architecture/ModuleBoundariesTest.php` | ☑ |
| 4 | Outbox atomique | `tests/Integration/Messaging/OutboxWorkspaceSpikeTest.php` | ☑ |
| 5 | Inbox sans double effet | test replay + consumer spike | ☑ |
| 6 | Rôles SQL par module | `docker/postgres/init-roles.sql` + `SqlModuleIsolationTest.php` | ☑ |
| 7 | Validation HTTP stricte | `CreateWorkspaceApiTest.php` | ☑ |
| 8 | Trace HTTP → outbox | `CorrelationIdMiddleware` + test corrélation outbox | ☑ |
| 9 | CI documentation + tests | `.github/workflows/application.yml` | ☑ |
| 10 | Image OCI + SBOM | job `oci` (BuildKit sbom + provenance) | ☑ |
| 11 | Reprise outbox | `test_pending_outbox_message_survives_process_restart` | ☑ |
| 12 | Risques consignés | section ci-dessous | ☑ |

---

## Stack mesurée

| Composant | Version observée |
|---|---|
| PHP | 8.4.24 (cible ADR : 8.5 strict) |
| Laravel | 13.24.0 |
| Pest | 4.7.8 |
| PostgreSQL | 18 (Alpine) |
| Node (Vite) | 22.x |

---

## Architecture validée

```text
atlas/
  Modules/Workspace/     # spike bounded context
  Platform/Messaging/    # outbox, inbox, processor
```

- commit atomique agrégat + outbox en transaction PostgreSQL ;
- consumer idempotent via inbox ;
- schémas `platform` et `workspace` isolés ;
- rôles `atlas_platform` et `atlas_workspace` en validation.

---

## Écarts acceptés

| Écart | Impact | Plan |
|---|---|---|
| PHP 8.4 au lieu de 8.5 | Faible | Passer à `php:8.5-cli` dès disponibilité image |
| OpenTelemetry OTLP non branché | Moyen | Incrément 1 : export traces vers collector |
| Registre OCI production absent | Moyen | Résolu avec `SEC-GAP-004` avant prod |
| Rôles SQL absents du volume existant | Faible | `make down-clean && make up` ou CI fraîche |
| Code sous `atlas/` vs `src/` ADR | Faible | Renommer après `make fix-permissions` |

---

## Commandes de vérification

```bash
sudo make down-clean && sudo make up    # recréer Postgres avec rôles
sudo make migrate-fresh
sudo make test                          # 15 tests attendus
scripts/check-all.sh                    # 12 checkers documentaires
```

---

## Prochaine étape

**Incrément 1 — MVP-J1** : Identity + Workspace (remplacer le spike par les
contrats documentés du référentiel).
