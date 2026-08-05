---
id: NTF-PROC-DISPATCH-NOTIFICATION-DELIVERY
title: DispatchNotificationDelivery
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../notification-policy.md
  - ../lifecycle.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../integrations.md
---

# DispatchNotificationDelivery

## Objectif

Soumettre une livraison Email encore valide au fournisseur sans doubler un
envoi lors d'un retry ou d'une concurrence de workers.

## Agrégat concerné

`Notification` et sa `NotificationDelivery`.

## Acteur et autorité

Worker Notifications autorisé par `notifications.deliveries.dispatch`.

## Données d'entrée

```text
WorkspaceId
NotificationId
NotificationDeliveryId
DispatchLease
ExpectedRevision
DispatchNotificationDeliveryRequestId
WorkloadContext
```

## Préconditions et traitement

- NotificationDelivery Pending et échéance non atteinte ;
- Notification Active, Workspace Active et Recipient encore autorisé ;
- préférence Email toujours ImportantOnly ;
- DeliveryEndpointReference toujours vérifiée par Identity ;
- fenêtre de fréquence encore disponible ;
- FrequencyLease exclusif acquis pour EmailFrequencyKey ;
- DispatchLease acquis par compare-and-set et passage atomique à Dispatching ;
- contenu rendu depuis un template allowlisté et des données minimales ;
- appel fournisseur avec la ProviderIdempotencyKey déjà persistée.

Une invalidation constatée avant le passage à Dispatching supprime ou annule la
livraison. Une fois Dispatching, elle n'est plus déclarée Cancelled : l'outcome
fournisseur doit converger. Un timeout après l'appel conserve les deux leases et
la même ProviderIdempotencyKey au retry ; une nouvelle clé n'est jamais supposée
sûre.

## Invariants concernés

`NTF-INV-001`–`NTF-INV-003`, `NTF-INV-014`–`NTF-INV-030`,
`NTF-INV-038`–`NTF-INV-058`.

## Événements produits

- `NotificationDeliveryAccepted` si le fournisseur accepte la soumission ;
- `NotificationDeliveryRetryScheduled` après un échec transitoire borné ;
- `NotificationDeliveryFailed` après un échec terminal ;
- `NotificationDeliverySuppressed` ou `NotificationDeliveryCancelled` si la
  livraison n'est plus permise avant soumission.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`EndpointUnavailable`, `Conflict`, `TemporarilyUnavailable`.

## Idempotence

DispatchNotificationDeliveryRequestId et ProviderIdempotencyKey sont
obligatoires. Le rejeu retourne la même soumission ou le même résultat terminal.
Une réutilisation pour une autre livraison échoue avec `Conflict`.
