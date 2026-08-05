---
id: ANL-PROC-COMPLETE-PROJECTION-REBUILD
title: CompleteAnalyticsProjectionRebuild
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

# CompleteAnalyticsProjectionRebuild

## Objectif

Valider une génération reconstruite et l'activer atomiquement.

## Agrégat concerné

`ProjectionGeneration` et le pointeur actif du Workspace.

## Acteur et autorité

Workload opérationnel autorisé par `analytics.projections.rebuild`.

## Données d'entrée

```text
WorkspaceId
GenerationId
ValidationReport
ExpectedRevision
CompleteAnalyticsProjectionRebuildRequestId
WorkloadContext
```

## Préconditions et traitement

- génération `Building` du même Workspace ;
- checkpoints au moins égaux aux watermarks cibles ;
- définitions, devises, cardinalités et comparaisons validées ;
- activation atomique et ancienne génération marquée `Superseded` ;
- un échec de validation marque la nouvelle génération `Failed` sans bascule.

## Invariants concernés

`ANL-INV-001`, `ANL-INV-009`–`ANL-INV-013`, `ANL-INV-017`–`ANL-INV-025`,
`ANL-INV-030`, `ANL-INV-032`–`ANL-INV-034`, `ANL-INV-037`.

## Événements produits

- `AnalyticsProjectionRebuilt` après activation réussie ;
- `AnalyticsProjectionRebuildFailed` si la validation échoue et que la
  génération devient `Failed`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`,
`InsufficientData`, `SourceVersionGap`, `Conflict`.

## Idempotence

`CompleteAnalyticsProjectionRebuildRequestId` est obligatoire. Un rejeu du même
rapport retourne l'activation initiale ; un rapport incompatible est refusé.
