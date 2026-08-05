---
id: BHL-DECISIONS
title: Business Health Decision Record
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - mission.md
  - scope.md
  - health-policy.md
  - model.md
  - invariants.md
---

# Registre des décisions

| ID | Décision | Conséquence 1.0 |
|---|---|---|
| `BHL-ADR-001` | Business Health possède l'interprétation, pas les mesures. | Analytics reste autoritaire sur toute MetricValue. |
| `BHL-ADR-002` | L'entrée unique est un AnalyticsSnapshot cohérent. | Aucun accès aux stockages CRM, Billing ou Analytics privé. |
| `BHL-ADR-003` | Une HealthPolicy est globale, immuable et versionnée. | Les résultats restent comparables et reproductibles. |
| `BHL-ADR-004` | Les seuils 1.0 sont une politique produit, pas un benchmark. | L'interface expose leurs limites sans prétention sectorielle. |
| `BHL-ADR-005` | L'évaluation est immuable. | Correction de données ou de règles crée un nouvel historique. |
| `BHL-ADR-006` | Les données insuffisantes produisent une évaluation explicite. | Atlas ne conserve ni ne fabrique un score silencieusement. |
| `BHL-ADR-007` | Les poids absents sont renormalisés seulement après un seuil de couverture. | Une donnée manquante n'équivaut jamais à zéro. |
| `BHL-ADR-008` | Quatre facteurs composent le MVP. | Commercial, facturation, créances et diversification restent lisibles. |
| `BHL-ADR-009` | `OverallScore` est le nom canonique du score global. | `HealthScore` ambigu reste évité dans le code. |
| `BHL-ADR-010` | `PrimaryAttention` décrit le déficit dominant. | Advisor reste seul propriétaire d'une action ou priorité d'exécution. |
| `BHL-ADR-011` | HealthRisk décrit une condition étayée, pas une probabilité. | Aucun modèle prédictif implicite n'est introduit. |
| `BHL-ADR-012` | Les tendances comparent des évaluations de même politique et devise. | Un changement de règle ou de monnaie ne crée pas une fausse évolution. |
| `BHL-ADR-013` | Business Health 1.0 refuse l'interprétation multidevise. | Aucune conversion ou agrégation trompeuse. |
| `BHL-ADR-014` | Une évaluation plus récente insuffisante devient courante. | La perte de qualité des données reste visible. |
| `BHL-ADR-015` | Un seul événement public résume la fin de l'évaluation. | Advisor relit le contrat exact sans duplication de tout le raisonnement. |
| `BHL-ADR-016` | Aucune commande humaine ne modifie ou relance le score en 1.0. | Le calcul reste causé par les snapshots et auditable. |
| `BHL-ADR-017` | Le score n'est ni comptable, ni bancaire, ni fiscal, ni juridique. | Atlas conserve une promesse produit honnête et bornée. |
| `BHL-ADR-018` | Chaque politique possède sa projection courante ; un pointeur global choisit l'active. | Un recalcul historique avec une nouvelle politique ne remplace pas arbitrairement la vue courante. |
