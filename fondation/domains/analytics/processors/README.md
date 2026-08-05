---
id: ANL-PROCESSORS
title: Analytics Processors
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

Analytics 1.0 ne possède aucune commande métier humaine. Ses quatre intentions
internes sont exécutées par des workloads autorisés.

## Catalogue

- [`IngestSourceFact`](IngestSourceFact.md)
- [`StartAnalyticsProjectionRebuild`](StartAnalyticsProjectionRebuild.md)
- [`CompleteAnalyticsProjectionRebuild`](CompleteAnalyticsProjectionRebuild.md)
- [`PublishAnalyticsSnapshot`](PublishAnalyticsSnapshot.md)

## Conventions communes

- toute intention porte `WorkspaceId` et un RequestId spécialisé ;
- l'enveloppe source ou `ExpectedRevision` protège la concurrence ;
- chaque workload exige sa capacité SystemActorOnly exacte ;
- les lectures externes utilisent uniquement des contrats publics versionnés ;
- un refus ne produit aucun événement de réussite ;
- état, événements, checkpoints et outbox sont commis atomiquement.

## Évolution

Un nouveau processeur doit représenter une intention absente du catalogue et
documenter agrégat, autorité, données, invariants, événements, concurrence,
idempotence et erreurs.
