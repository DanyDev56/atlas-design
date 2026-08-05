---
id: ADV-COMMANDS
title: Advisor Commands
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - ../aggregates.md
  - ../recommendation-lifecycle.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# Commands

Les commandes Advisor 1.0 expriment uniquement les décisions humaines sur une
Recommendation existante.

## Catalogue

- [`CompleteRecommendation`](CompleteRecommendation.md)
- [`DismissRecommendation`](DismissRecommendation.md)

## Conventions communes

- toute commande porte WorkspaceId, RecommendationId, ExpectedRevision et un
  RequestId spécialisé ;
- l'acteur doit être actif dans le même Workspace avec la capacité exacte ;
- seule une Recommendation Generated et non expirée accepte la commande ;
- une commande ne modifie ni action, rang, preuve ou politique ;
- un refus ne publie aucun événement de réussite ;
- état, Domain Event et outbox sont atomiques.

## Limite

Advisor ne possède aucune commande `ExecuteRecommendation`. Complete confirme
une action humaine ; toute mutation métier passe par la commande publique du
domaine propriétaire.
