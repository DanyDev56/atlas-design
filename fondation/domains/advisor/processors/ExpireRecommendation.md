---
id: ADV-PROC-EXPIRE-RECOMMENDATION
title: ExpireRecommendation
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - README.md
  - ../recommendation-lifecycle.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# ExpireRecommendation

## Objectif

Matérialiser la fin temporelle d'une Recommendation Generated.

## Agrégat concerné

`Recommendation`.

## Acteur et autorité

Scheduler ou workload Advisor autorisé par `advisor.recommendations.expire`.

## Données d'entrée

```text
WorkspaceId
RecommendationId
ClockProof
ExpectedRevision
ExpireRecommendationRequestId
WorkloadContext
```

## Préconditions

- Recommendation du même Workspace ;
- status Generated ;
- ClockProof authentique avec `Now >= ValidUntil` ;
- ExpectedRevision égale à la révision courante.

## Invariants concernés

`ADV-INV-001`, `ADV-INV-002`, `ADV-INV-032`–`ADV-INV-034`,
`ADV-INV-037`, `ADV-INV-039`, `ADV-INV-044`–`ADV-INV-055`.

## Événement produit

- `RecommendationExpired` avec `ExpirationReason = ValidityEnded`.

L'événement cause `RebuildAdvisorOverview`. Le scheduler ne considère
l'intention convergée que lorsque la CauseKey possède son
`AdvisorOverviewVersion` ; l'alternative suivante est alors promue si elle
existe.

## Concurrence

Si une commande humaine ou une évaluation a déjà rendu la Recommendation
terminale, le processeur relit le résultat et n'ajoute aucune transition.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`Conflict`.

## Idempotence

`ExpireRecommendationRequestId` est obligatoire. Le rejeu du même ClockProof
retourne le résultat initial ; une réutilisation incompatible échoue.
