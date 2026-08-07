---
title: Draft — Rétention données beta (SEC-GAP-004)
owner: Product + Security
status: draft
last_updated: 2026-08-07
references:
  - ../SEC-TEST-MATRIX.md
  - ../../fondation/security/mvp-threat-model.md
---

# SEC-GAP-004 — Rétention et support (beta)

> **Statut : draft** — à valider par Product et Security avant publication contrôlée.

## Objectif

Définir des durées de conservation et des procédures de suppression compatibles avec
la beta B2B, sans bloquer l'exploitation (backup, observabilité, support client).

## Proposition beta

| Donnée | Rétention proposée | Justification | Suppression |
|---|---|---|---|
| Comptes utilisateurs actifs | Durée du contrat beta | Exploitation normale | Sur demande / fin beta |
| Sessions (`identity.sessions`) | 30 j après expiration ou révocation | SEC-T03 / SEC-TEST-004 | Purge planifiée (à implémenter) |
| Tokens vérification email | 24 h (existant) | SEC-T02 | Expiration automatique |
| Logs applicatifs (JSON) | 14 j | Debug + incident | Rotation infra |
| Traces Jaeger | 7 j | Observabilité Palier 3 | Rétention Jaeger |
| Backups PostgreSQL | 30 j rolling | SEC-TEST-023 | Rotation script backup |
| Outbox dispatchés | 90 j | Audit événements | Purge planifiée (à implémenter) |
| Idempotency keys | 30 j | Rejeu API | Purge planifiée (à implémenter) |

## Support client

- Export des données workspace : **hors scope beta** (documenter comme gap).
- Droit à l'effacement : procédure manuelle via runbook backup-restore + suppression compte (à rédiger).

## Décisions ouvertes

1. Durée exacte sessions révoquées vs expirées.
2. Hébergement backups hors site (SEC-GAP-006).
3. Automatisation purge vs job manuel mensuel.

## Prochaine étape

Validation Product+Security → mise à jour matrice SEC-GAP-004 en ☑ et tickets purge planifiée.
