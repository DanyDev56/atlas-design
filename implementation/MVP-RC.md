---
title: Atlas MVP — Release Candidate
status: Candidate
owner: Engineering
last_updated: 2026-08-07
references:
  - ../evolution/roadmap/mvp-acceptance.md
  - ../evolution/roadmap/mvp-scope.md
  - spike-checklist.md
---

# MVP Release Candidate

Ce document résume l'état de preuve du MVP exécutable sous `implementation/`.

## Parcours utilisateur

| Parcours | Test d'acceptation | Statut |
|---|---|---|
| MVP-J1 — Inscription → Workspace actif | `MvpJ1OnboardingTest` | ☑ |
| MVP-J2 — Client → paiement | `MvpJ2BillingFlowTest` | ☑ |
| MVP-J3 — Faits → priorité notifiée | `MvpJ3EndToEndAcceptanceTest` | ☑ |

## Scénarios transverses

| Scénario | Test | Statut |
|---|---|---|
| Isolation Workspace | `MvpAcceptanceCrossCuttingTest`, `SqlModuleIsolationTest` | ☑ |
| Autorisation par défaut | `MvpAcceptanceCrossCuttingTest`, `CrmAuthorizationTest` | ☑ |
| Idempotence commandes | `MvpJ1OnboardingTest`, `MvpJ3EndToEndAcceptanceTest`, `MvpAcceptanceCrossCuttingTest` | ☑ |
| Conflit de révision | `MvpAcceptanceCrossCuttingTest` | ☑ |
| Reprise outbox sans double effet | `OutboxWorkspaceSpikeTest`, `MvpJ3EndToEndAcceptanceTest` | ☑ |
| Fixtures déterministes FIX-001…010 | tests unitaires + `ReferenceFixturesOracleTest` | ☑ |

## Quality gates techniques

| Gate | Preuve | Statut |
|---|---|---|
| ADR-002 spike (12 conditions) | `spike-checklist.md` | ☑ |
| Frontières modules | `ModuleBoundariesTest` | ☑ |
| Documentation fondation | CI `documentation` + `check-all.sh` | ☑ |
| Image OCI + SBOM | CI job `oci` | ☑ |
| Tests Pest + PostgreSQL | CI job `spike` | ☑ |

## Hors périmètre RC (incrément 8 partiel)

Les éléments suivants restent documentés dans `mvp-acceptance.md` mais ne sont pas
automatiqués dans cette release candidate :

- import historique CRM/Billing ;
- dispatch email fournisseur réel ;
- complete/dismiss Advisor ;
- accessibilité clavier et responsive ;
- sauvegarde/restauration et migrations down en production ;
- runbooks d'exploitation et alertes.

## Démonstration locale

```bash
make up
make bootstrap   # première fois
make migrate-fresh
make test
scripts/check-mvp-reference-fixtures.sh
```

Playground : `http://localhost:8000/playground` après `make serve`.
