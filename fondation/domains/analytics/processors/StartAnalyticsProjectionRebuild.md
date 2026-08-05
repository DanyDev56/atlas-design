---
id: ANL-PROC-START-PROJECTION-REBUILD
title: StartAnalyticsProjectionRebuild
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# StartAnalyticsProjectionRebuild

## Objectif

Créer une génération isolée pour reconstruire un scope Analytics.

## Agrégat concerné

Nouvelle `ProjectionGeneration`.

## Acteur et autorité

Workload opérationnel autorisé par `analytics.projections.rebuild`.

## Données d'entrée

```text
WorkspaceId
GenerationId
RebuildScope
RequestedDefinitionSetVersion
ReportingCalendarVersion
SourceWatermarks
RebuildReason
StartAnalyticsProjectionRebuildRequestId
WorkloadContext
```

## Préconditions et traitement

- scope borné et définitions supportées ;
- calendrier Workspace existant ;
- watermarks sources accessibles ;
- aucune génération `Building` incompatible pour le même scope ;
- création en `Building` sans modifier l'active.

## Invariants concernés

`ANL-INV-001`, `ANL-INV-002`, `ANL-INV-009`–`ANL-INV-013`,
`ANL-INV-017`–`ANL-INV-019`, `ANL-INV-023`–`ANL-INV-025`,
`ANL-INV-030`, `ANL-INV-032`–`ANL-INV-034`.

## Événements produits

- `AnalyticsProjectionRebuildStarted`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `UnsupportedMetric`,
`Conflict`, `TemporarilyUnavailable`.

## Idempotence

`StartAnalyticsProjectionRebuildRequestId` est obligatoire. Le même scope et les
mêmes versions retournent la génération initiale ; une réutilisation différente
échoue.
