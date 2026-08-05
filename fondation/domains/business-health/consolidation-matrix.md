---
id: BHL-CONSOLIDATION
title: Business Health Consolidation Matrix
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - scope.md
  - health-policy.md
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
| HealthPolicy et règles de score | Oui | Oui | Oui | Oui |
| Modèle, entités et agrégat | Oui | Oui | Oui | Oui |
| Value Objects et relations | Oui | Oui | Oui | Oui |
| Invariants et décisions | Oui | Oui | Oui | Oui |
| Permissions et processeur | Oui | Oui | Oui | Oui |
| Domain Event | Oui | Oui | Oui | Oui |
| API et intégrations | Oui | Oui | Oui | Oui |
| Workflows, glossaire et futur | Oui | Oui | Oui | Oui |

---

## Traçabilité des processeurs

| Processeur | Autorité | Capacité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `EvaluateBusinessHealth` | Workload Business Health | `business-health.assessments.evaluate` | 001–043 | `BusinessHealthAssessed` | `EvaluateBusinessHealthRequestId` |

Les numéros abrégés désignent `BHL-INV-nnn`. La fiche constitue la source
normative complète.

---

## Couverture de HealthPolicy 1.0

| FactorKey | Poids | Composants | Métriques principales |
|---|---:|---:|---|
| `CommercialMomentum` | 30 | 2 | acceptance-rate, pipeline open-amount |
| `BillingMomentum` | 20 | 2 | net-invoiced-amount, collected-amount |
| `ReceivablesDiscipline` | 35 | 2 | outstanding-amount, overdue-amount, on-time-rate |
| `ClientDiversification` | 15 | 1 | top-collection-share |
| **Total** | **100** | **7** | **8 MetricKeys** |

---

## Décisions 1.0

| Sujet | Décision |
|---|---|
| Autorité | mesures dans Analytics, interprétation dans Business Health |
| Source | un snapshot exact et cohérent par évaluation |
| Politique | globale, immuable, versionnée et non configurable |
| Facteurs | commercial, facturation, créances et diversification |
| Couverture | absence exclue puis renormalisée seulement au-dessus du seuil |
| Fiabilité | Reliable, Limited ou Insufficient explicitement exposée |
| Monnaie | une devise non nulle, aucune conversion 1.0 |
| Historique | évaluations immuables, projection courante monotone |
| Politique active | projection courante séparée par version, bascule après évaluation |
| Attention | facteur au déficit dominant, jamais une action |
| Risque | condition observée et étayée, jamais une probabilité |
| Advisor | seul propriétaire des Recommendation et priorités d'exécution |
| Utilisateur | lectures uniquement en 1.0 |

---

## Quality gates Business Health 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Les quatre facteurs, sept composants et poids total 100 sont définis.
- [x] Chaque seuil, borne, arrondi et règle d'égalité est déterministe.
- [x] Missing, NoData, Unavailable et zéro restent distincts.
- [x] Les couvertures de facteur et globale empêchent les faux scores.
- [x] La fiabilité et les raisons d'insuffisance sont contractuelles.
- [x] Les tendances comparent seulement politique et devise compatibles.
- [x] La PrimaryAttention ne contient aucune action Advisor.
- [x] Chaque HealthRisk possède preuve, règle et sévérité bornée.
- [x] L'unique processeur possède autorité, concurrence et idempotence.
- [x] Le Domain Event possède un producteur tracé.
- [x] Toutes les capacités utilisées figurent dans le catalogue.
- [x] Les contrats Analytics, Workspace et Advisor sont explicites.
- [x] Business Health ne lit aucun stockage source et ne modifie aucun domaine.
- [x] Les limites multidevise, prédictives et comptables restent explicites.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-business-health-docs.sh
```

Business Health 1.0 est `In Review` depuis le 5 août 2026. Le statut `Stable`
exige une implémentation, des jeux de snapshots de référence et des tests de
calcul conformes.
