---
id: ANL-CONSOLIDATION
title: Analytics Consolidation Matrix
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - scope.md
  - metric-catalog.md
  - model.md
  - entities.md
  - aggregates.md
  - value-objects.md
  - relationships.md
  - invariants.md
  - decision-record.md
  - permissions.md
  - events.md
  - api.md
  - integrations.md
  - workflows.md
  - future.md
  - glossary.md
  - processors/README.md
---

# Matrice de consolidation

## Sources canoniques

| Source | Présente | Normalisée | Cohérente | Complète 1.0 |
|---|---:|---:|---:|---:|
| Mission et scope | Oui | Oui | Oui | Oui |
| Catalogue des métriques | Oui | Oui | Oui | Oui |
| Modèle, entités et agrégats | Oui | Oui | Oui | Oui |
| Value Objects et relations | Oui | Oui | Oui | Oui |
| Invariants et décisions | Oui | Oui | Oui | Oui |
| Permissions et processeurs | Oui | Oui | Oui | Oui |
| Domain Events | Oui | Oui | Oui | Oui |
| API et intégrations | Oui | Oui | Oui | Oui |
| Workflows, glossaire et futur | Oui | Oui | Oui | Oui |

---

## Traçabilité des processeurs

| Processeur | Autorité | Capacité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `IngestSourceFact` | Workload Analytics | `analytics.facts.ingest` | 001–008, 011–022, 025, 030, 032–034 | `AnalyticsFactRecorded` | `IngestSourceFactRequestId` |
| `StartAnalyticsProjectionRebuild` | Workload opérationnel | `analytics.projections.rebuild` | 001–002, 009–013, 017–019, 023–025, 030, 032–034 | `AnalyticsProjectionRebuildStarted` | `StartAnalyticsProjectionRebuildRequestId` |
| `CompleteAnalyticsProjectionRebuild` | Workload opérationnel | `analytics.projections.rebuild` | 001, 009–025, 030, 032–034 | `AnalyticsProjectionRebuilt`, `AnalyticsProjectionRebuildFailed` | `CompleteAnalyticsProjectionRebuildRequestId` |
| `PublishAnalyticsSnapshot` | Scheduler ou workload | `analytics.snapshots.publish` | 001–002, 009–019, 023, 026–034 | `AnalyticsSnapshotPublished` | `PublishAnalyticsSnapshotRequestId` |

Les numéros abrégés désignent `ANL-INV-nnn`. Les fiches constituent la source
normative complète.

---

## Couverture des métriques

| Groupe | MetricKeys | Source autoritaire |
|---|---:|---|
| Pipeline | 1 | CRM Opportunity |
| Quotes | 3 | Billing Quote |
| Facturation et encaissement | 2 | Billing Invoice, Payment et CreditNote |
| Créances | 4 | Billing Invoice |
| Paiement et concentration | 3 | Billing Invoice et Payment |
| **Total** | **13** | CRM et Billing |

---

## Décisions 1.0

| Sujet | Décision |
|---|---|
| Autorité | faits dans CRM/Billing, mesures dans Analytics |
| Acquisition | événement minimal puis fait versionné |
| Confidentialité | faits et dimensions sans données personnelles |
| Catalogue | définitions globales versionnées, non personnalisables |
| Monnaie | séries séparées par devise, aucune conversion |
| Absence | `NoData`, zéro et indisponibilité distincts |
| Temporalité | stocks, flux, ratios et durées séparés |
| Correction | série courante recalculée, snapshot historique immuable |
| Rebuild | génération parallèle avec bascule atomique |
| Business Health | consommation par snapshot cohérent |
| Utilisateur | lectures uniquement en 1.0 |
| Prédiction | aucune probabilité ou prévision en 1.0 |
| Comptabilité | aucun revenu, bénéfice ou solde bancaire déduit |

---

## Quality gates Analytics 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Les 13 MetricKeys ont une formule, une nature et une utilité explicites.
- [x] Les dimensions, périodes, devises et arrondis sont bornés.
- [x] Les faits sources sont versionnés, minimisés et reconstructibles.
- [x] `NoData`, fraîcheur et complétude sont contractuels.
- [x] Corrections, reversals, événements en retard et rebuilds sont couverts.
- [x] Les quatre processeurs possèdent autorité, concurrence et idempotence.
- [x] Tous les Domain Events possèdent un producteur tracé.
- [x] Toutes les capacités utilisées figurent dans le catalogue.
- [x] Les contrats CRM, Billing et Workspace sont explicites et symétriques.
- [x] Business Health consomme un snapshot cohérent, pas le stockage privé.
- [x] Analytics ne modifie aucun domaine source.
- [x] Les extensions prédictives et comptables restent hors 1.0.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-analytics-docs.sh
```

Analytics 1.0 est `In Review` depuis le 5 août 2026. Le statut `Stable` exige
une implémentation, des jeux de faits de référence et des tests de calcul
conformes.
