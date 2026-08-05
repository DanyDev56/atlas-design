---
id: NTF-PROCESSORS
title: Notifications Processors
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - ../aggregates.md
  - ../notification-policy.md
  - ../lifecycle.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# Processors

Les processeurs Notifications planifient une diffusion, pilotent son transport
ou matérialisent son échéance. Aucun n'exécute l'action métier proposée.

## Catalogue

- [`ProcessAdvisorNotificationSignal`](ProcessAdvisorNotificationSignal.md)
- [`DispatchNotificationDelivery`](DispatchNotificationDelivery.md)
- [`RecordNotificationDeliveryOutcome`](RecordNotificationDeliveryOutcome.md)
- [`ExpireNotification`](ExpireNotification.md)

## Conventions communes

- toute intention porte WorkspaceId et un RequestId spécialisé ;
- l'enveloppe source, ExpectedRevision, ProviderOutcomeId ou ClockProof protège
  causalité, concurrence et temps ;
- chaque workload exige sa capacité SystemActorOnly exacte ;
- les lectures externes utilisent uniquement des contrats publics versionnés ;
- un refus ne publie aucun événement de réussite ;
- mutations, Domain Events et outbox sont atomiques par agrégat ;
- les fournisseurs reçoivent toujours une ProviderIdempotencyKey stable.
