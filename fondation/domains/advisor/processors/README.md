---
id: ADV-PROCESSORS
title: Advisor Processors
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - ../aggregates.md
  - ../recommendation-policy.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# Processors

Les processeurs Advisor évaluent une source ou matérialisent une expiration. Ils
n'exécutent aucune RecommendationAction.

## Catalogue

- [`EvaluateRecommendations`](EvaluateRecommendations.md)
- [`ExpireRecommendation`](ExpireRecommendation.md)

## Conventions communes

- toute intention porte WorkspaceId et un RequestId spécialisé ;
- l'enveloppe source, ClockProof ou ExpectedRevision protège causalité et temps ;
- chaque workload exige sa capacité SystemActorOnly exacte ;
- les lectures externes utilisent uniquement des contrats publics versionnés ;
- un refus ne publie aucun événement de réussite ;
- mutations, Domain Events et outbox sont atomiques par agrégat ;
- RecommendationEvaluation reprend les étapes partielles idempotemment.
