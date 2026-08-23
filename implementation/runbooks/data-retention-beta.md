---
title: Rétention données beta (SEC-GAP-004)
owner: Product + Security
status: validated-beta
last_updated: 2026-08-23
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
- Périmètre validé historiquement : **beta fermée interne** (équipe Atlas + proches).
- Cible externe : cinq professionnels nommément invités, uniquement après le
  `Go` de la checklist de release.
- Export des données Workspace : assistance manuelle documentée, sans
  libre-service annoncé.
- Droit à l'effacement : parcours support documenté, mais suppression Workspace
  atomique encore à implémenter et tester avant données externes.
- Procédure : [`beta-support-offboarding.md`](beta-support-offboarding.md).

## Décisions ouvertes

1. ~~Durée exacte sessions révoquées vs expirées.~~ **30 j unifié** (expiration ou révocation).
2. ~~Hébergement backups hors site (SEC-GAP-006).~~ **Beta interne : risque
   accepté** — dumps locaux uniquement, rotation 30 j. Cette acceptation ne
   couvre pas la beta externe : une sauvegarde chiffrée hors site y est requise.
3. ~~Automatisation purge vs job manuel mensuel.~~ **Automatisé** — `atlas:retention:purge`, schedule quotidien.

## Prochaine étape

Avant beta externe : valider export assisté et suppression Workspace atomique.
Post-beta : purge `platform.inbox_receipts` et export en libre-service.
