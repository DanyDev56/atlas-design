---
id: BHL-INVARIANTS
title: Business Health Invariants
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - health-policy.md
  - aggregates.md
  - value-objects.md
  - processors/README.md
---

# Invariants

## Isolation, source et politique

| ID | Règle |
|---|---|
| `BHL-INV-001` | Toute évaluation, preuve, permission et référence appartient à un seul `WorkspaceId`. |
| `BHL-INV-002` | Les identifiants Business Health sont stables, uniques et jamais réattribués. |
| `BHL-INV-003` | Il existe au plus une évaluation par `(WorkspaceId, AnalyticsSnapshotId, HealthPolicyVersion)`. |
| `BHL-INV-004` | Une BusinessHealthAssessment créée est immuable ; correction ou nouvelle règle crée une autre évaluation. |
| `BHL-INV-005` | Une HealthPolicy publiée est globale, immuable et versionnée ; un Workspace ne change ni poids ni seuil en 1.0. |
| `BHL-INV-006` | Toute évaluation référence un AnalyticsSnapshot publié, exact, du même Workspace et du profil exigé par la politique. |
| `BHL-INV-007` | Business Health utilise les MetricValue, périodes et comparaisons publiées sans recalculer ni redéfinir une MetricDefinition. |
| `BHL-INV-008` | Business Health ne lit ni stockage CRM/Billing, ni AnalyticsFact, MetricSeries ou MetricObservation privée. |
| `BHL-INV-009` | Toute AssessmentEvidence conserve la référence et la copie minimale de la SnapshotMetric effectivement utilisée ou rejetée. |
| `BHL-INV-010` | Un snapshot incompatible produit une évaluation InsufficientData explicite ; il ne réutilise jamais un score précédent. |

## Devises, disponibilité et preuves

| ID | Règle |
|---|---|
| `BHL-INV-011` | Des montants de devises différentes ne sont jamais additionnés, comparés ou convertis implicitement. |
| `BHL-INV-012` | Plus d'une devise monétaire non nulle rend l'évaluation 1.0 InsufficientData avec `MultipleCurrenciesUnsupported`. |
| `BHL-INV-013` | `NoData`, `Unavailable`, absence et zéro restent quatre situations distinctes. |
| `BHL-INV-014` | Une preuve stale, rebuilding, insuffisante ou incohérente ne devient jamais un composant disponible. |
| `BHL-INV-015` | Une donnée manquante ne reçoit jamais le score zéro et ne contribue jamais silencieusement à une moyenne. |
| `BHL-INV-016` | Un ratio exige les composantes et l'échantillon requis par la politique ; un pourcentage d'évolution exige une baseline Analytics valide. |
| `BHL-INV-017` | Toute conclusion, tendance, PrimaryAttention et HealthRisk référence les preuves et règles de politique qui la justifient. |
| `BHL-INV-018` | Une absence de preuve de risque n'est jamais présentée comme preuve d'absence de risque. |

## Scores, facteurs et fiabilité

| ID | Règle |
|---|---|
| `BHL-INV-019` | ComponentScore, FactorScore et OverallScore restent compris entre 0 et 100 et ne sont pas interchangeables. |
| `BHL-INV-020` | Les calculs utilisent des décimaux exacts et l'arrondi moitié vers le haut uniquement aux frontières définies. |
| `BHL-INV-021` | Un FactorScore n'existe que si la somme des poids originaux de composants disponibles atteint sa couverture minimale. |
| `BHL-INV-022` | L'OverallScore n'existe que si les facteurs disponibles représentent au moins 70 % des poids globaux originaux. |
| `BHL-INV-023` | Les poids disponibles sont renormalisés ; les poids absents restent visibles dans CoverageRatio. |
| `BHL-INV-024` | HealthBand découle uniquement de l'OverallScore et de la même HealthPolicyVersion. |
| `BHL-INV-025` | Une évaluation Available possède OverallScore, HealthBand et une fiabilité Reliable ou Limited. |
| `BHL-INV-026` | Une évaluation InsufficientData possède une fiabilité Insufficient et aucun OverallScore, HealthBand ou PrimaryAttention. |
| `BHL-INV-027` | Reliable exige 100 % des facteurs et composants requis complets et courants ; toute couverture moindre avec score vaut Limited. |
| `BHL-INV-028` | Chaque évaluation contient exactement les quatre FactorKey définies par HealthPolicy 1.0. |

## Tendances, attention et risques

| ID | Règle |
|---|---|
| `BHL-INV-029` | Une baseline de tendance est strictement antérieure, Available, de même Workspace, politique et devise, et distante d'au plus 45 jours. |
| `BHL-INV-030` | Sans baseline compatible, HealthTrend vaut Unknown et jamais Stable. |
| `BHL-INV-031` | PrimaryAttention maximise DeficitContribution ; les égalités suivent poids décroissant puis FactorKey lexicographique. |
| `BHL-INV-032` | PrimaryAttention est une zone d'attention, sans Action, échéance ou RecommendationPriority. |
| `BHL-INV-033` | Un HealthRisk provient uniquement d'un déclencheur explicitement défini par la même HealthPolicyVersion. |
| `BHL-INV-034` | RiskSeverity représente un seuil observé, jamais une probabilité ou une prédiction. |

## Autorité, ordre et publication

| ID | Règle |
|---|---|
| `BHL-INV-035` | Business Health ne commande ni ne modifie Analytics, CRM, Billing, Advisor ou Workspace. |
| `BHL-INV-036` | Une lecture humaine exige un acteur actif et `business-health.assessments.read` dans le même Workspace. |
| `BHL-INV-037` | L'évaluation et la consommation machine exigent leur capacité SystemActorOnly bornée. |
| `BHL-INV-038` | Chaque processeur possède un RequestId ; un rejeu identique retourne le résultat initial et une réutilisation incompatible échoue. |
| `BHL-INV-039` | Évaluation, événement et outbox sont commis atomiquement ; les consommateurs dédupliquent EventId. |
| `BHL-INV-040` | Pour un couple `(WorkspaceId, HealthPolicyVersion)`, la projection courante avance selon `(AsOf, SourcePublishedAt, BusinessHealthAssessmentId)` et ne régresse jamais face à une publication tardive. |
| `BHL-INV-041` | Une évaluation InsufficientData plus récente devient courante ; un ancien score n'est jamais conservé comme s'il était actuel. |
| `BHL-INV-042` | Business Health ne présente jamais son score comme diagnostic comptable, financier, fiscal, juridique ou sectoriel. |
| `BHL-INV-043` | La lecture courante utilise uniquement l'ActiveHealthPolicyVersion ; son activation exige une évaluation du snapshot courant selon cette version. |
