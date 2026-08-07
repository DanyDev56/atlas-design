---
title: MVP technique — Clôture incréments 0 à 8
status: Accepted
owner: Engineering
date: 2026-08-07
references:
  - MVP-RC.md
  - SPIKE-CLOSURE.md
  - spike-checklist.md
  - ../evolution/roadmap/mvp-acceptance.md
  - ../evolution/blueprint/implementation-plan.md
  - ../evolution/blueprint/roadmap.md
  - ../fondation/decisions/ADR-001-mvp-application-topology.md
  - ../fondation/decisions/ADR-002-mvp-implementation-stack.md
---

# Clôture du MVP technique

Ce document atteste que l'implémentation exécutable sous `implementation/` a
livré les huit incréments du plan d'implémentation, prouvé les trois parcours
`MVP-J1` à `MVP-J3` et stabilisé les quality gates automatisés.

La **Definition of Done de publication contrôlée** (Palier 3 de
[`roadmap.md`](../evolution/blueprint/roadmap.md)) reste ouverte. Les écarts
documentés ci-dessous sont **acceptés formellement** pour cette clôture
technique et ne bloquent pas la transition vers le Palier 3.

---

## Synthèse

| Élément | Statut |
|---|---|
| Incréments 0 à 8 | ☑ Clôturés |
| Parcours MVP-J1, MVP-J2, MVP-J3 | ☑ Prouvés par tests d'acceptation |
| Fixtures FIX-001…010 + oracle jq | ☑ |
| CI `application.yml` (docs + spike + oci) | ☑ Verte |
| Palier 3 — publication contrôlée | ◐ Track A clôturé — voir [`PALIER-3-CLOSURE.md`](PALIER-3-CLOSURE.md) |

---

## Preuves par incrément

| Incrément | Gate de sortie | Preuve principale |
|---|---|---|
| 0 — Spike ADR-002 | 12 conditions | [`SPIKE-CLOSURE.md`](SPIKE-CLOSURE.md), [`spike-checklist.md`](spike-checklist.md) |
| 1 — Identity + Workspace | MVP-J1 | `MvpJ1OnboardingTest` |
| 2 — CRM | Clients, opportunités, pipeline | `CrmClientOpportunityFlowTest`, `CrmAuthorizationTest` |
| 3 — Billing | MVP-J2 (sans import historique) | `MvpJ2BillingFlowTest` |
| 4 — Analytics | Ingest, snapshot, FIX-001…010 | `AnalyticsSnapshotTest`, tests unitaires métriques |
| 5 — Business Health | Évaluation depuis snapshot | `BusinessHealthAssessmentTest`, tests policy |
| 6 — Advisor | Overview et recommandations | `AdvisorOverviewTest`, `RecommendationPolicyEvaluatorTest` |
| 7 — Notifications + Dashboard | MVP-J3 inbox + composition | `NotificationInboxTest`, `DashboardTest` |
| 8 — Durcissement RC | Acceptation consolidée | `MvpJ3EndToEndAcceptanceTest`, `MvpAcceptanceCrossCuttingTest`, [`MVP-RC.md`](MVP-RC.md) |

---

## Preuves par parcours utilisateur

| Parcours | Test d'acceptation | Scénarios couverts |
|---|---|---|
| MVP-J1 | `MvpJ1OnboardingTest` | Inscription, vérification, session, premier Workspace actif |
| MVP-J2 | `MvpJ2BillingFlowTest` | Client → qualify → devis → acceptation → facture → paiement |
| MVP-J3 | `MvpJ3EndToEndAcceptanceTest` | Faits commerciaux → Health → Advisor → Notifications → Dashboard |

Scénarios transverses : `MvpAcceptanceCrossCuttingTest`, `OutboxWorkspaceSpikeTest`,
`SqlModuleIsolationTest`, `ModuleBoundariesTest`, `ReferenceFixturesOracleTest`.

---

## Definition of Done — statut honnête

Référence : [`mvp-acceptance.md`](../evolution/roadmap/mvp-acceptance.md).

| # | Critère | Statut | Commentaire |
|---|---|---|---|
| 1 | J1, J2, J3 en environnement proche production | ☑ | Docker Compose + CI GitHub Actions |
| 2 | Contrats, permissions et erreurs testés aux frontières | ☑ | Feature, integration et architecture |
| 3 | Retry, conflit, indisponibilité, reconstruction | ◐ | Retry/conflit/outbox prouvés ; reconstruction complète non automatisée |
| 4 | Journaux et métriques pour localiser une rupture | ◐ | OTLP + backlog monitor + runbooks ; gate Jaeger manuelle |
| 5 | Fixtures FIX-001…010 déterministes | ☑ | Oracle `scripts/check-mvp-reference-fixtures.sh` + Pest |
| 6 | Aucune exclusion nécessaire au résultat nominal | ◐ | Parcours à froid sans import ; import historique différé |
| 7 | Dashboard et frontières Blueprint respectées | ☑ | Composition par lectures publiques uniquement |
| 8 | SEC-001 validé, gaps fermés, risques High/Critical acceptés | ◐ | Subset beta SEC-TEST ☑ ; step-up et fuzz différés |

Légende : ☑ prouvé — ◐ partiel — ◻ non couvert.

---

## Scénarios transverses

| Scénario | Statut | Preuve |
|---|---|---|
| Isolation Workspace | ☑ | `MvpAcceptanceCrossCuttingTest`, `SqlModuleIsolationTest` |
| Autorisation par défaut | ☑ | `CrmAuthorizationTest`, `MvpAcceptanceCrossCuttingTest` |
| Idempotence | ☑ | J1, J3, cross-cutting |
| Conflit de révision | ☑ | `MvpAcceptanceCrossCuttingTest` |
| Reprise outbox | ☑ | `OutboxWorkspaceSpikeTest`, `MvpJ3EndToEndAcceptanceTest` |
| Import historique | ◻ | Spec [`historical-import.md`](../evolution/blueprint/historical-import.md) — non implémenté |
| Effet externe (email) | ◐ | Inbox in-app ; dispatch fournisseur réel différé |
| Reconstruction projections | ◐ | Rebuild via reprocess outbox ; pas de commande dédiée |
| Accessibilité clavier | ◻ | Playground non audité |
| SEC-T01…SEC-T28 tracés | ◐ | Subset beta automatisé — voir [`SEC-TEST-MATRIX.md`](SEC-TEST-MATRIX.md) |

---

## Stack mesurée (clôture)

| Composant | Version observée |
|---|---|
| PHP | 8.4-cli (Bookworm) |
| Laravel | 13.x |
| Pest | 4.7 |
| PostgreSQL | 18 (Alpine) |
| Tests Pest | 83+ passés (CI + local) |

---

## Écarts acceptés formellement

| Écart | Impact | Owner | Plan | Accepté le |
|---|---|---|---|---|
| Import historique CRM/Billing absent | Moyen | Product + Engineering | Palier 3 ou incrément dédié post-clôture | 2026-08-07 |
| Advisor `CompleteRecommendation` / `DismissRecommendation` | Faible | Engineering | Compléter boucle Advisor avant beta | 2026-08-07 |
| Dispatch email fournisseur réel | Moyen | Engineering | Adaptateur Notifications + consentement testé | 2026-08-07 |
| `ExpireNotification` et events outbox Notifications dédiés | Faible | Engineering | Durcissement Notifications Palier 3 | 2026-08-07 |
| OpenTelemetry OTLP non branché | Moyen | Engineering | ☑ Track A lot 2 — [`PALIER-3-CLOSURE.md`](PALIER-3-CLOSURE.md) | 2026-08-07 |
| PHP 8.4 au lieu de 8.5 | Faible | Engineering | Image Docker quand 8.5 stable | 2026-08-07 |
| Sauvegarde/restauration et migrations down | Élevé | Engineering | Scripts + canary ☑ ; migrations down ◻ | 2026-08-07 |
| Runbooks, alertes, step-up SEC-T | Élevé | Engineering + Ops | Runbooks ☑ ; webhook optionnel ; step-up ◻ | 2026-08-07 |
| Accessibilité et responsive playground | Faible | Product | Audit UX avant beta fermée | 2026-08-07 |
| Registre OCI production | Moyen | Engineering | `SEC-GAP-004` avant prod | 2026-08-07 |

Aucun écart ci-dessus n'invalide la preuve des parcours J1–J3 sur base vide ni
la cohérence des fixtures de référence.

---

## Commandes de vérification

```bash
make up
make migrate-fresh
make test                    # 83+ tests attendus
make check-docs              # 12 checkers documentaires
scripts/check-mvp-reference-fixtures.sh
```

Playground manuel : `make serve` → `http://localhost:8000/playground`.

CI : workflow `.github/workflows/application.yml` (jobs `documentation`, `spike`, `oci`).

---

## Prochaine étape

**Publication beta fermée** — voir [`PALIER-3-CLOSURE.md`](PALIER-3-CLOSURE.md) et
[`runbooks/beta-release-checklist.md`](runbooks/beta-release-checklist.md) :

1. valider SEC-GAP-004 / SEC-GAP-006 avec Product+Security ;
2. exécuter gates manuelles observabilité + release rehearsal ;
3. ouvrir beta fermée et mesurer les outcomes ;
4. Palier 4 — extensions selon usages.

Le référentiel métier (`fondation/`, `evolution/`) reste la source de vérité ;
`implementation/` prouve l'exécutabilité du MVP minimal documenté.
