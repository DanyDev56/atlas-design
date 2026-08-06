---
id: ADV-CMD-COMPLETE-RECOMMENDATION
title: CompleteRecommendation
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
---

# CompleteRecommendation

## Objectif

Enregistrer que l'utilisateur confirme avoir accompli l'action principale.

## Agrégat concerné

`Recommendation`.

## Acteur et autorité

User actif disposant de `advisor.recommendations.complete` dans le même
Workspace.

## Données d'entrée

```text
WorkspaceId
RecommendationId
CompletionConfirmation: UserConfirmedActionCompleted
ExpectedRevision
CompleteRecommendationRequestId
ActorContext
```

## Préconditions

- Recommendation trouvée dans le même Workspace ;
- status Generated ;
- ValidUntil strictement postérieur à l'horloge serveur ;
- ExpectedRevision égale à la révision courante ;
- confirmation explicite exacte.

La commande ne vérifie et ne déduit aucune mutation CRM ou Billing.

## Invariants concernés

`ADV-INV-001`, `ADV-INV-002`, `ADV-INV-022`–`ADV-INV-028`,
`ADV-INV-032`–`ADV-INV-035`, `ADV-INV-039`, `ADV-INV-043`,
`ADV-INV-045`–`ADV-INV-055`.

## Événement produit

- `RecommendationCompleted`.

Cet événement cause `RebuildAdvisorOverview`. La completion métier reste
commise une seule fois ; la convergence reprend indépendamment jusqu'à publier
la version qui retire cette Recommendation et promeut l'alternative suivante.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`RecommendationNoLongerActive`, `Conflict`.

## Idempotence

`CompleteRecommendationRequestId` est obligatoire. Le rejeu identique retourne
la transition initiale ; une réutilisation pour une autre Recommendation ou une
autre confirmation échoue avec `Conflict`.
