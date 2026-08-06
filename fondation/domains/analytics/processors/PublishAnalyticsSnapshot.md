---
id: ANL-PROC-PUBLISH-ANALYTICS-SNAPSHOT
title: PublishAnalyticsSnapshot
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# PublishAnalyticsSnapshot

## Objectif

Publier un ensemble cohérent de métriques pour Business Health.

## Agrégat concerné

Nouvel agrégat `AnalyticsSnapshot`.

## Acteur et autorité

Scheduler ou workload autorisé par `analytics.snapshots.publish`.

## Données d'entrée

```text
WorkspaceId
AnalyticsSnapshotId
SnapshotProfileKey
SnapshotProfileVersion
AsOf
ActiveGenerationId
PublishAnalyticsSnapshotRequestId
WorkloadContext
```

## Préconditions et traitement

- génération demandée encore active ;
- profil et définitions supportés ;
- toutes les observations partagent génération, calendrier, AsOf et watermarks
  cohérents ;
- profil exact `BusinessHealthBaselineV1@1.0.0` en 1.0 ;
- complétude `Complete` pour les treize métriques obligatoires ;
- watermarks CRM et Billing présents avec un `CompleteThrough` vérifiable ;
- `SnapshotLag <= PT24H`, avec `Current` jusqu'à `PT1H` inclus et `Lagging`
  au-delà ;
- création immuable et publication atomique.

## Invariants concernés

`ANL-INV-001`, `ANL-INV-002`, `ANL-INV-009`–`ANL-INV-019`,
`ANL-INV-023`, `ANL-INV-026`–`ANL-INV-034`, `ANL-INV-037`.

## Événements produits

- `AnalyticsSnapshotPublished`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`,
`InsufficientData`, `StaleData`, `ProjectionRebuilding`, `Conflict`.

## Idempotence

`PublishAnalyticsSnapshotRequestId` est obligatoire. Le même profil, AsOf et
génération retournent le snapshot initial ; une réutilisation différente échoue.
