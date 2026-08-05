---
id: ANL-PROC-INGEST-SOURCE-FACT
title: IngestSourceFact
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../integrations.md
---

# IngestSourceFact

## Objectif

Normaliser une révision CRM ou Billing authentique et déclencher sa projection.

## Agrégat concerné

`SourceFactStream` correspondant à l'agrégat source.

## Acteur et autorité

Workload autorisé par `analytics.facts.ingest`, puis par la capacité de lecture
de faits du domaine source.

## Données d'entrée

```text
WorkspaceId
SourceEventEnvelope
IngestSourceFactRequestId
WorkloadContext
```

`SourceEventEnvelope` fournit `SourceEventId`, type, agrégat, version, instant,
causalité et signature. Sa version joue le rôle de concurrence source.

## Préconditions et traitement

- événement supporté, authentique et du même Workspace ;
- EventId non associé à un autre contenu ;
- révision non régressive ou FactKind distinct à révision égale ;
- lecture exacte du fait à `AggregateVersion` ;
- payload minimal validé, hashé et enregistré avant projection.

## Invariants concernés

`ANL-INV-001`–`ANL-INV-008`, `ANL-INV-011`–`ANL-INV-020`,
`ANL-INV-021`, `ANL-INV-022`, `ANL-INV-025`, `ANL-INV-030`,
`ANL-INV-032`–`ANL-INV-034`.

## Événements produits

- `AnalyticsFactRecorded`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `UnsupportedSourceEvent`,
`SourceVersionGap`, `Conflict`, `TemporarilyUnavailable`.

## Idempotence

`IngestSourceFactRequestId` est obligatoire et dérivé de `SourceEventId`. Un
rejeu identique retourne le résultat initial ; un EventId altéré est refusé.
