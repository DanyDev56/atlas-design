---
title: Atlas MVP — Release Candidate
status: Accepted
owner: Engineering
last_updated: 2026-08-07
closure: MVP-CLOSURE.md
references:
  - MVP-CLOSURE.md
  - ../evolution/roadmap/mvp-acceptance.md
  - ../evolution/roadmap/mvp-scope.md
  - spike-checklist.md
---

# MVP Release Candidate

> **Clôturé le 2026-08-07.** Document de synthèse ; la clôture formelle est
> attestée dans [`MVP-CLOSURE.md`](MVP-CLOSURE.md).

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
| ADR-002 spike (12 conditions) | `spike-checklist.md`, `SPIKE-CLOSURE.md` | ☑ |
| Frontières modules | `ModuleBoundariesTest` | ☑ |
| Documentation fondation | CI `documentation` + `check-all.sh` | ☑ |
| Image OCI immuable + SBOM/provenance | CI jobs `oci`, `runtime-smoke` | ☑ |
| Tests Pest + PostgreSQL | CI job `spike` (83+ tests) | ☑ |

## Palier 3 (Track A)

Clôture engineering : [`PALIER-3-CLOSURE.md`](PALIER-3-CLOSURE.md) · Checklist beta :
[`runbooks/beta-release-checklist.md`](runbooks/beta-release-checklist.md).

## Écarts acceptés (voir MVP-CLOSURE)

Les éléments suivants restent hors de cette clôture technique et sont tracés
avec owner et plan dans [`MVP-CLOSURE.md`](MVP-CLOSURE.md) :

- import historique CRM/Billing ;
- dispatch email fournisseur réel ;
- complete/dismiss Advisor ;
- accessibilité clavier et responsive ;
- sauvegarde/restauration et migrations down en production ;
- runbooks d'exploitation livrés (backup, observabilité, outbox) ; step-up SEC-T différé ;

## Démonstration locale

```bash
make up
make bootstrap   # première fois
make migrate-fresh
make test
scripts/check-mvp-reference-fixtures.sh
```

Playground : `http://localhost:8000/playground` après `make serve`.
