---
id: ANL-PUBLIC-CONTRACT
title: Analytics Public Contract
status: In Review
owner: Product
version: 1.3.0
last_updated: 2026-08-06

references:
  - scope.md
  - metric-catalog.md
  - invariants.md
  - permissions.md
  - events.md
  - processors/README.md
---

# Contrat public Analytics

Le contrat exprime des lectures et intentions système indépendantes du
transport. Analytics 1.0 n'expose aucune commande métier humaine.

## Lectures utilisateur

```text
listMetricDefinitions(workspaceId)

getMetric(workspaceId, metricKey, definitionVersion?, observationPeriod,
          dimensionSet?)

getMetricSeries(workspaceId, metricKey, definitionVersion?, periodRange,
                bucketKind, dimensionSet?)

getMetricBreakdown(workspaceId, metricKey, definitionVersion?,
                   observationPeriod, allowedDimension)

getMetricExplanation(workspaceId, metricKey, definitionVersion)

getAnalyticsOverview(workspaceId, overviewProfileKey, observationContext)
```

Toutes exigent `analytics.metrics.read`. Une réponse de valeur contient :

```text
MetricKey
DefinitionVersion
MetricValue
ObservationPeriod
PeriodComparison?
DimensionSet
DataCompleteness
DataFreshness
CalculatedAt
GenerationId
MetricExplanationReference
```

Une série ne mélange jamais versions, devises, calendriers ou générations.

## Contrat fourni à Business Health

```text
getAnalyticsSnapshot(workspaceId, analyticsSnapshotId)
→ AnalyticsSnapshot

getLatestAnalyticsSnapshot(workspaceId, snapshotProfileKey,
                           snapshotProfileVersion,
                           minimumAsOf?, maximumAcceptedLagOverride?)
→ AnalyticsSnapshot | SnapshotUnavailable
```

La lecture exige `analytics.snapshots.consume`. Le consommateur vérifie le
profil, la complétude et la fraîcheur au lieu d'inférer qu'un snapshot ancien
est courant. Un `maximumAcceptedLagOverride` ne peut qu'être inférieur ou égal
au maximum du profil ; il ne peut jamais élargir la fenêtre de publication.

## Intentions système internes

```text
ingestSourceFact(workspaceId, sourceEventEnvelopeOrHistoricalManifestEntry,
                 ingestSourceFactRequestId)

startAnalyticsProjectionRebuild(workspaceId, generationId, rebuildScope,
                                requestedDefinitionSetVersion,
                                reportingCalendarVersion, sourceWatermarks,
                                startRebuildRequestId)

completeAnalyticsProjectionRebuild(workspaceId, generationId,
                                   validationReport, expectedRevision,
                                   completeRebuildRequestId)

publishAnalyticsSnapshot(workspaceId, analyticsSnapshotId,
                         snapshotProfile, asOf, activeGenerationId,
                         publishSnapshotRequestId)
```

Les fiches dans [`processors/`](processors/README.md) sont normatives.

## Erreurs publiques

| Catégorie | Sens |
|---|---|
| `InvalidInput` | clé, période, dimension ou valeur syntaxiquement invalide |
| `Unauthenticated` | principal ou workload absent/invalide |
| `Unauthorized` | permission ou capacité absente |
| `NotFound` | définition, série, génération ou snapshot absent/masqué |
| `UnsupportedMetric` | métrique ou version non supportée |
| `UnsupportedSourceEvent` | événement ou FactKind absent du contrat 1.0 |
| `UnsupportedDimension` | dimension absente de l'allowlist de la définition |
| `CurrencyConflict` | tentative d'agrégation entre devises |
| `InsufficientData` | couverture sous le seuil requis |
| `StaleData` | retard supérieur au maximum accepté |
| `ProjectionRebuilding` | génération courante indisponible pour cette lecture |
| `SourceVersionGap` | révision source manquante ou désordonnée |
| `Conflict` | révision ou idempotence incompatible |
| `TemporarilyUnavailable` | source ou projection nécessaire indisponible |

`NoData` est un `MetricState` normal retourné avec sa complétude et son
explication. Il n'est jamais transporté comme une erreur.

## Versioning

Une nouvelle définition de métrique n'est pas une nouvelle version d'API. Un
changement de sens d'un champ, d'une enum, d'une permission ou d'un événement
exige une version contractuelle explicite.
