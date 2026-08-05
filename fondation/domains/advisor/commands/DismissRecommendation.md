---
id: ADV-CMD-DISMISS-RECOMMENDATION
title: DismissRecommendation
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../recommendation-lifecycle.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# DismissRecommendation

## Objectif

Enregistrer le rejet explicite d'une Recommendation avec un motif structuré.

## Agrégat concerné

`Recommendation`.

## Acteur et autorité

User actif disposant de `advisor.recommendations.dismiss` dans le même
Workspace.

## Données d'entrée

```text
WorkspaceId
RecommendationId
DismissalReason
ExpectedRevision
DismissRecommendationRequestId
ActorContext
```

## Préconditions

- Recommendation trouvée dans le même Workspace ;
- status Generated ;
- ValidUntil strictement postérieur à l'horloge serveur ;
- DismissalReason appartenant à l'enum 1.0 ;
- aucun texte libre ;
- ExpectedRevision égale à la révision courante.

## Invariants concernés

`ADV-INV-001`, `ADV-INV-002`, `ADV-INV-017`, `ADV-INV-018`,
`ADV-INV-032`–`ADV-INV-034`, `ADV-INV-036`, `ADV-INV-039`,
`ADV-INV-043`, `ADV-INV-045`–`ADV-INV-049`.

## Événement produit

- `RecommendationDismissed`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`RecommendationNoLongerActive`, `Conflict`.

## Idempotence

`DismissRecommendationRequestId` est obligatoire. Le rejeu identique retourne
la transition initiale ; une réutilisation avec un autre motif ou une autre
Recommendation échoue avec `Conflict`.
