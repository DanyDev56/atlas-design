---
id: ANL-VALUE-OBJECTS
title: Analytics Value Objects
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - metric-catalog.md
  - invariants.md
  - ../workspace/value-objects.md
---

# Value Objects

## Identifiants

`AnalyticsFactId`, `MetricKey`, `MetricDefinitionVersion`, `MetricSeriesId`,
`GenerationId`, `AnalyticsSnapshotId`, `SnapshotProfileKey`, `WorkspaceId` et
les RequestId spécialisés sont des types distincts.

## SourceAggregateReference

```text
SourceDomain
AggregateType
AggregateId
AggregateVersion
```

La référence est bornée au même Workspace et à une version effectivement
publiée par le domaine propriétaire.

## ObservationPeriod

```text
WindowKind
StartInclusive?
EndExclusive?
AsOf
ReportingTimeZone
ReportingCalendarVersion
```

`PointInTime` utilise `AsOf`. Les métriques de flux utilisent un intervalle
semi-ouvert non vide. Les instants canoniques restent en UTC.

## MetricValue

```text
MetricState: Available | NoData | Unavailable
Unit: Money | Count | Ratio | Duration
ExactValue?
CurrencyCode?
Numerator?
Denominator?
SampleSize
```

Money emploie des unités mineures ou un décimal exact. Ratio conserve une
précision déterministe et n'existe pas sans dénominateur positif.

## DimensionSet

Ensemble trié de dimensions autorisées par la définition. Analytics 1.0 admet :

- `CurrencyCode` pour séparer les valeurs monétaires ;
- `ClientId` uniquement pour les métriques de concentration ou breakdowns
  explicitement prévus.

Une dimension libre, un nom de Client ou une note sont interdits.

## DataCompleteness

```text
Status: Complete | Partial | Insufficient
ExpectedCount?
ObservedCount
MissingCount?
CoverageRatio?
Reasons[]
```

La complétude décrit les données nécessaires à la formule, pas la disponibilité
générale de la plateforme.

## DataFreshness

```text
Status: Current | Lagging | Rebuilding | Unavailable
CalculatedAt
LastProcessedAt?
SourceWatermarks[]
MaximumAcceptedLag
```

## ReportingCalendar

Fuseau, premier jour de semaine et version issus des préférences Workspace. Un
changement crée une nouvelle génération pour les buckets concernés ; il ne
modifie aucun fait ni snapshot publié.

## SnapshotProfile

Jeu versionné de MetricKeys, fenêtres, dimensions et seuils exigés par un
consommateur. `BusinessHealthBaselineV1` est le seul profil 1.0. Un profil ne
change jamais la formule d'une métrique ; il sélectionne des observations
cohérentes à publier ensemble.

## SnapshotMetric

Copie immuable d'une MetricObservation avec sa MetricKey, définition, valeur,
période, dimensions, fraîcheur, complétude, génération et explication. Une
référence à la cellule MetricSeries d'origine est conservée pour l'audit, jamais
pour relire la valeur publiée.

## MetricExplanation

Contient la définition lisible, la formule, la nature, les inclusions,
exclusions, limites, version et sources. Elle ne contient pas de raisonnement
opaque ni de texte généré présenté comme une règle canonique.
