---
id: ADV-CONSOLIDATION
title: Advisor Consolidation Matrix
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - scope.md
  - philosophy.md
  - recommendation-policy.md
  - recommendation-engine.md
  - recommendation-score.md
  - priorities.md
  - actions.md
  - recommendation-lifecycle.md
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
  - examples.md
  - future.md
  - glossary.md
  - commands/README.md
  - processors/README.md
---

# Matrice de consolidation

## Sources canoniques

| Source | Présente | Normalisée | Cohérente | Complète 1.0 |
|---|---:|---:|---:|---:|
| Mission, philosophie et scope | Oui | Oui | Oui | Oui |
| RecommendationPolicy et moteur | Oui | Oui | Oui | Oui |
| Rang, priorités et actions | Oui | Oui | Oui | Oui |
| Cycle de vie et exemples | Oui | Oui | Oui | Oui |
| Modèle, entités et agrégats | Oui | Oui | Oui | Oui |
| Value Objects et relations | Oui | Oui | Oui | Oui |
| Invariants et décisions | Oui | Oui | Oui | Oui |
| Permissions, commandes et processeurs | Oui | Oui | Oui | Oui |
| Domain Events | Oui | Oui | Oui | Oui |
| API, intégrations et workflows | Oui | Oui | Oui | Oui |
| Glossaire et futur | Oui | Oui | Oui | Oui |

---

## Traçabilité des commandes

| Commande | Autorité | Capacité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `CompleteRecommendation` | User | `advisor.recommendations.complete` | 001–002, 022–028, 032–035, 039, 043, 045–049 | `RecommendationCompleted` | `CompleteRecommendationRequestId` |
| `DismissRecommendation` | User | `advisor.recommendations.dismiss` | 001–002, 017–018, 032–034, 036, 039, 043, 045–049 | `RecommendationDismissed` | `DismissRecommendationRequestId` |

## Traçabilité des processeurs

| Processeur | Autorité | Capacité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `EvaluateRecommendations` | Workload Advisor | `advisor.recommendations.evaluate` | 001–050 | `RecommendationEvaluationCompleted`, `RecommendationGenerated`, `RecommendationReaffirmed`, `RecommendationExpired` | `EvaluateRecommendationsRequestId` |
| `ExpireRecommendation` | Scheduler ou workload | `advisor.recommendations.expire` | 001–002, 032–034, 037, 039, 044–049 | `RecommendationExpired` | `ExpireRecommendationRequestId` |

Les numéros abrégés désignent `ADV-INV-nnn`. Les fiches constituent les sources
normatives complètes.

---

## Couverture de RecommendationPolicy 1.0

| RuleKey | Source | Action cible |
|---|---|---|
| `CollectOverdueInvoices` | OverdueExposureRisk | Billing OverdueInvoices |
| `ReduceClientConcentration` | ClientConcentrationRisk | CRM NewOpportunity |
| `RebuildCommercialPipeline` | CommercialMomentumRisk | CRM NewOpportunity |
| `RestoreBillingMomentum` | BillingMomentumRisk | Billing RecentInvoices |
| `AddressPrimaryAttention` | PrimaryAttention sans risque équivalent | route selon FactorKey |
| **Total** | **5 règles** | **0..3 Recommendations publiées** |

---

## Décisions 1.0

| Sujet | Décision |
|---|---|
| Source | BusinessHealthAssessment exacte et courante uniquement |
| Politique | globale, immuable, versionnée et non configurable |
| Génération | cinq règles déterministes, zéro à trois propositions |
| Priorité | impact 40 %, urgence 30 %, confiance 20 %, facilité 10 % |
| Action | une RouteKey allowlistée, aucune commande source |
| Confiance | High ou Moderate, jamais probabilité |
| Impact | qualitatif, sans gain garanti |
| Preuve | révisions immuables, source et règle exactes |
| Déduplication | fingerprint réaffirmé ou nouvelle identité si changement matériel |
| Cycle | Generated vers Completed, Dismissed ou Expired |
| Completion | confirmation humaine, pas exécution ni outcome |
| Télémétrie | affichage, ouverture et clic hors du domaine |

---

## Quality gates Advisor 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Les cinq règles possèdent déclencheur, action, validité et rang explicites.
- [x] Le score de rang, ses poids, seuils et arrondi sont déterministes.
- [x] L'overview contient une priorité principale et au plus deux alternatives.
- [x] Une source insuffisante ou historique ne génère aucun fallback ancien.
- [x] Chaque Recommendation possède une action allowlistée et une preuve.
- [x] Réaffirmation, changement matériel et suppression sont distingués.
- [x] Les états terminaux sont exclusifs et jamais réactivés.
- [x] Completed ne prétend ni exécution source ni outcome.
- [x] Les deux commandes possèdent autorité, concurrence et idempotence.
- [x] Les deux processeurs possèdent causalité, reprise et idempotence.
- [x] Tous les Domain Events possèdent un producteur tracé.
- [x] Toutes les capacités utilisées figurent dans le catalogue.
- [x] Les contrats Business Health, Workspace et Notifications sont explicites.
- [x] Advisor ne lit ni Analytics, CRM ou Billing et ne modifie aucun domaine.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-advisor-docs.sh
```

Advisor 1.0 est `In Review` depuis le 5 août 2026. Le statut `Stable` exige une
implémentation, des évaluations de référence, des tests de règles et une
validation produit des formulations.
