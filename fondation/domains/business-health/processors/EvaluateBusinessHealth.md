---
id: BHL-PROC-EVALUATE-BUSINESS-HEALTH
title: EvaluateBusinessHealth
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../health-policy.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# EvaluateBusinessHealth

## Objectif

Interpréter un AnalyticsSnapshot exact avec une HealthPolicyVersion et créer une
BusinessHealthAssessment immuable et explicable.

## Agrégat concerné

Nouvel agrégat `BusinessHealthAssessment`.

## Acteur et autorité

Workload Business Health autorisé par
`business-health.assessments.evaluate`, causé par
`AnalyticsSnapshotPublished`.

## Données d'entrée

```text
WorkspaceId
AnalyticsSnapshotId
AnalyticsSnapshotPublishedEventId
HealthPolicyVersion
EvaluateBusinessHealthRequestId
CorrelationId
WorkloadContext
```

`EvaluateBusinessHealthRequestId` est dérivé de manière déterministe de
`AnalyticsSnapshotId` et `HealthPolicyVersion`.

## Préconditions

- enveloppe Analytics authentique, supportée et du même Workspace ;
- HealthPolicyVersion publiée et profil demandé compatible ;
- snapshot exact accessible avec `analytics.snapshots.consume` ;
- cohérence des identifiants, AsOf et version entre événement et snapshot ;
- clé naturelle snapshot/politique non occupée par un contenu incompatible.

Une couverture insuffisante, une comparaison absente ou plusieurs devises ne
refusent pas une source authentique : elles produisent une évaluation
`InsufficientData` conformément à la politique.

## Traitement

1. dédupliquer EventId et RequestId ;
2. relire et valider le snapshot exact ;
3. sélectionner et figer les AssessmentEvidence ;
4. évaluer les ComponentScore et couvertures ;
5. calculer les quatre HealthFactor ;
6. calculer OverallScore, HealthBand et AssessmentReliability ;
7. choisir la baseline compatible et calculer les HealthTrend ;
8. calculer PrimaryAttention et les HealthRisk étayés ;
9. créer l'agrégat et `BusinessHealthAssessed` atomiquement ;
10. avancer CurrentBusinessHealth pour cette HealthPolicyVersion par comparaison
    atomique du tuple d'ordre.

## Invariants concernés

`BHL-INV-001`–`BHL-INV-043`.

## Événement produit

- `BusinessHealthAssessed`.

## Concurrence

L'unicité sur `(WorkspaceId, AnalyticsSnapshotId, HealthPolicyVersion)` fait
converger deux exécutions. La projection courante de la HealthPolicyVersion
applique un compare-and-set sur
`(AsOf, SourcePublishedAt, BusinessHealthAssessmentId)` ; une livraison tardive
ne la fait pas régresser et une autre politique utilise une projection distincte.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`,
`UnsupportedSnapshotProfile`, `UnsupportedPolicyVersion`,
`SourceContractMismatch`, `Conflict`, `TemporarilyUnavailable`.

## Idempotence

`EvaluateBusinessHealthRequestId` est obligatoire. Le rejeu du même snapshot,
de la même politique et du même événement retourne l'évaluation initiale. Une
réutilisation avec un contenu différent échoue avec `Conflict`.
