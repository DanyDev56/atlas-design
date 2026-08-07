---
title: SEC-TEST — Matrice de vérification MVP
owner: Engineering + Security
last_updated: 2026-08-07
references:
  - ../fondation/security/mvp-threat-model.md
  - MVP-CLOSURE.md
  - runbooks/backup-restore.md
---

# Matrice SEC-TEST (Palier 3)

Référence menaces : [`mvp-threat-model.md`](../fondation/security/mvp-threat-model.md).

Légende : ☑ automatisé — ◐ partiel — ◻ planifié — — hors scope MVP beta.

| Test | Menace(s) | Preuve actuelle | Statut | Prochaine action |
|---|---|---|---|---|
| SEC-TEST-001 | SEC-T07 | `MvpAcceptanceCrossCuttingTest` (isolation client) | ◐ | Étendre à quotes/invoices |
| SEC-TEST-002 | SEC-T01 | — | ◻ | Fuzz login / lockout |
| SEC-TEST-003 | SEC-T02 | `VerifyEmail` + token debug test | ◐ | Token expiré / rejoué |
| SEC-TEST-004 | SEC-T03 | Sessions Postgres | ◻ | Révocation mid-session |
| SEC-TEST-005 | SEC-T04 | — | ◻ | Step-up action critique |
| SEC-TEST-006 | SEC-T05 | — | ◻ | Rate limit API |
| SEC-TEST-007 | SEC-T08 | `CrmAuthorizationTest` | ◐ | Membership révoquée mid-flow |
| SEC-TEST-008 | SEC-T09 | Public quote accept | ◐ | Token expiré / mauvais workspace |
| SEC-TEST-009 | SEC-T10 | — | ◻ | Proof opaques billing |
| SEC-TEST-010 | SEC-T11 | — | ◻ | CSRF surface web |
| SEC-TEST-011 | SEC-T12 | — | ◻ | IDOR opportunité |
| SEC-TEST-012 | SEC-T13 | — | ◻ | Mass assignment API |
| SEC-TEST-013 | SEC-T14 | Idempotence handlers | ◐ | Documenter par handler |
| SEC-TEST-014 | SEC-T15 | `MvpAcceptanceCrossCuttingTest` (revision) | ◐ | Concurrence quote accept |
| SEC-TEST-015 | SEC-T16 | — | ◻ | Payload fuzz |
| SEC-TEST-016 | SEC-T17 | — | ◻ | Outbox event forgé |
| SEC-TEST-017 | SEC-T18 | `OutboxWorkspaceSpikeTest` | ☑ | — |
| SEC-TEST-018 | SEC-T19 | `SqlModuleIsolationTest`, `ModuleBoundariesTest` | ☑ | — |
| SEC-TEST-019 | SEC-T20 | — | ◻ | Egress billing adapter |
| SEC-TEST-020 | SEC-T21 | — | ◻ | Secret scan CI |
| SEC-TEST-021 | SEC-T22 | CI `oci` SBOM | ◐ | Scan vulnérabilités |
| SEC-TEST-022 | SEC-T24 | — | ◻ | DLQ / poison message |
| SEC-TEST-023 | SEC-T25 | `verify-restore-canary.sh` | ☑ | Intégrer en rehearsal release |
| SEC-TEST-024 | SEC-T26 | — | ◻ | Supply chain pin audit |
| SEC-TEST-025 | SEC-T27 | — | ◻ | Chaos outbox consumer |
| SEC-TEST-026 | SEC-T28 | — | ◻ | Performance baseline |
| SEC-TEST-027 | SEC-T06 | — | ◻ | Privilege escalation |

## Sous-ensemble CI beta (Track A)

Exécuté à chaque `make test` :

- SEC-TEST-001 (partiel), 007 (partiel), 014 (partiel), 017, 018

Exécuté manuellement avant release :

- **SEC-TEST-023** : `make verify-restore`

## Gaps ouverts liés

| Gap | Statut Track A |
|---|---|
| SEC-GAP-004 (rétention / support) | ◻ décision Product+Security |
| SEC-GAP-006 (backup hors site) | ◐ scripts locaux ; hors site à décider |
| SEC-GAP-008 (runbooks / alertes) | ◐ backup + observabilité runbooks |
