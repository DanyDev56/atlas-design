---
id: ANL-AGGREGATES
title: Analytics Aggregates
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - relationships.md
  - invariants.md
  - workflows.md
---

# Agrégats

## Vue d'ensemble

| Agrégat | Racine | Cohérence garantie |
|---|---|---|
| Source Fact Stream | `SourceFactStream` | ordre source, déduplication et immutabilité des faits |
| Metric Series | `MetricSeries` | unicité et calcul des cellules d'une série |
| Projection Generation | `ProjectionGeneration` | rebuild, checkpoints, validation et activation |
| Analytics Snapshot | `AnalyticsSnapshot` | publication cohérente et immuable |

`MetricDefinition` est un catalogue de référence global versionné, pas une
configuration mutable par Workspace.

## SourceFactStream

La clé est `(WorkspaceId, SourceDomain, AggregateType, AggregateId)`. La racine
accepte une révision supérieure ou un autre événement supporté de la même
transaction à révision égale. Un `SourceEventId` déjà traité ne crée aucun fait
et plusieurs événements de même révision ne doublent jamais une contribution.

Un trou de version n'est pas rempli par supposition : le stream attend, relit la
version nécessaire ou demande un rebuild borné.

## MetricSeries

La clé logique contient `WorkspaceId`, `MetricKey`, `DefinitionVersion`,
`GenerationId`, `DimensionSet` et `CurrencyCode?`. Seul le projecteur canonique
peut modifier ses observations.

Les événements concurrents sont ordonnés par leur stream source puis appliqués
idempotemment. Une cellule dérivée peut être recalculée, jamais corrigée à la
main.

## ProjectionGeneration

Une seule génération est active par Workspace et jeu de définitions. Un rebuild
travaille en parallèle dans une nouvelle génération puis bascule le pointeur
actif atomiquement après validation.

## AnalyticsSnapshot

La publication crée une nouvelle racine. Elle vérifie que toutes les
observations obligatoires proviennent de la même génération et que les
watermarks satisfont la politique de fraîcheur. Un snapshot n'est jamais mis à
jour en place. Les `SnapshotMetric` copient les valeurs publiées ; elles ne
référencent jamais une cellule de série susceptible d'être recalculée.
