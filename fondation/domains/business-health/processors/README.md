---
id: BHL-PROCESSORS
title: Business Health Processors
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - ../aggregates.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# Processors

Business Health 1.0 ne possède aucune commande métier humaine. Son unique
intention interne est exécutée par un workload autorisé après publication d'un
snapshot Analytics.

## Catalogue

- [`EvaluateBusinessHealth`](EvaluateBusinessHealth.md)

## Conventions communes

- toute intention porte `WorkspaceId` et un RequestId spécialisé ;
- l'enveloppe source protège causalité et déduplication ;
- chaque workload exige sa capacité SystemActorOnly exacte ;
- les lectures externes utilisent uniquement des contrats publics versionnés ;
- les calculs emploient la HealthPolicyVersion demandée et immuable ;
- un refus ne produit aucun événement de réussite ;
- évaluation, événement et outbox sont commis atomiquement.

## Évolution

Un nouveau processeur doit représenter une intention absente du catalogue et
documenter agrégat, autorité, données, invariants, événements, concurrence,
idempotence et erreurs. Un simple changement de seuil crée une nouvelle
HealthPolicyVersion, pas un processeur.
