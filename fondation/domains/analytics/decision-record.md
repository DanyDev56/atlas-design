---
id: ANL-DECISIONS
title: Analytics Decision Record
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - mission.md
  - scope.md
  - metric-catalog.md
  - model.md
  - invariants.md
---

# Registre des décisions

| ID | Décision | Conséquence 1.0 |
|---|---|---|
| `ANL-ADR-001` | Analytics possède les mesures, jamais les faits métier sources. | CRM et Billing restent autoritaires. |
| `ANL-ADR-002` | Un événement signale le changement et une lecture versionnée fournit le fait exact. | Les événements restent minimaux sans rendre les calculs ambigus. |
| `ANL-ADR-003` | Les faits normalisés sont immuables et sans données personnelles. | Les projections sont reconstructibles avec une surface sensible réduite. |
| `ANL-ADR-004` | Le catalogue des métriques est versionné et non configurable par Workspace. | Une même clé/version garde le même sens pour tous. |
| `ANL-ADR-005` | Les métriques monétaires sont séparées par devise. | Aucune conversion ou total trompeur n'est produit. |
| `ANL-ADR-006` | Stock, flux, ratio et durée sont des natures distinctes. | Les agrégations invalides sont refusées par construction. |
| `ANL-ADR-007` | `NoData` est distinct de zéro et d'une indisponibilité. | L'interface ne fabrique pas un résultat à partir d'une absence. |
| `ANL-ADR-008` | Toute valeur expose fraîcheur et complétude. | Business Health peut réduire sa confiance ou refuser un calcul. |
| `ANL-ADR-009` | Les corrections révisent la série courante, pas les snapshots publiés. | L'audit passé reste reproductible et la vue courante reste exacte. |
| `ANL-ADR-010` | Les rebuilds utilisent une génération parallèle. | Une reconstruction n'expose pas de série partiellement recalculée. |
| `ANL-ADR-011` | Business Health consomme des snapshots cohérents. | Il ne dépend pas du stockage privé ni de cellules prises à des instants différents. |
| `ANL-ADR-012` | Analytics ne publie pas un événement par variation de cellule. | `AnalyticsSnapshotPublished` est le contrat stable et limite le bruit. |
| `ANL-ADR-013` | Les lectures humaines sont read-only. | Analytics 1.0 ne possède aucune commande métier utilisateur. |
| `ANL-ADR-014` | Les métriques produit et la télémétrie ne sont pas le domaine Analytics métier. | NPS, rétention et performance restent dans leurs systèmes dédiés. |
| `ANL-ADR-015` | Aucune prédiction en 1.0. | PipelineAmount reste une estimation déclarée, sans probabilité inventée. |
| `ANL-ADR-016` | Les encaissements et montants facturés ne sont pas renommés revenu ou trésorerie. | Atlas évite toute promesse comptable ou bancaire implicite. |
| `ANL-ADR-017` | Une évolution compare uniquement des périodes de même définition, dimension, devise et calendrier. | Business Health ne reçoit aucun pourcentage construit sur une baseline arbitraire. |
| `ANL-ADR-018` | `BusinessHealthBaselineV1@1.0.0` distingue fraîcheur courante à une heure et retard publiable borné à vingt-quatre heures. | Business Health peut rendre `InsufficientData` sur un snapshot `Lagging`, tandis qu'Analytics refuse une source plus ancienne. |
