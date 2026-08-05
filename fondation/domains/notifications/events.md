---
id: NTF-EVENTS
title: Notifications Domain Events
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - lifecycle.md
  - invariants.md
  - commands/README.md
  - processors/README.md
  - integrations.md
---

# Domain Events

## Enveloppe commune

| Champ | Description |
|---|---|
| `EventId` | identifiant unique de déduplication |
| `EventName` | nom canonique |
| `SchemaVersion` | version du contrat |
| `OccurredAt` | instant du fait |
| `AggregateType`, `AggregateId` | racine Notifications concernée |
| `AggregateVersion` | révision après commit |
| `WorkspaceId` | frontière d'isolation |
| `CorrelationId`, `CausationId` | chaîne depuis Advisor ou l'intention humaine |
| `ActorReference` | acteur ou workload minimal auditable |
| `Data` | charge utile minimale sans contenu rendu ni endpoint |

## Catalogue

| Événement | Producteur | Visibilité | Fait minimum |
|---|---|---|---|
| `NotificationPlanCompleted` | `ProcessAdvisorNotificationSignal` | public | Un événement Advisor a reçu une décision complète pour toute son audience. |
| `NotificationCreated` | `ProcessAdvisorNotificationSignal` | public | Un message personnel pertinent a été créé avec son plan de canaux. |
| `NotificationSuperseded` | `ProcessAdvisorNotificationSignal` | public | Une nouvelle priorité a remplacé le message actif du même thread. |
| `NotificationResolved` | `ProcessAdvisorNotificationSignal` | public | La source n'exige plus de message actif. |
| `NotificationExpired` | `ProcessAdvisorNotificationSignal`, `ExpireNotification` | public | La validité temporelle du message a pris fin. |
| `NotificationMarkedRead` | `MarkNotificationRead` | public | Le destinataire a marqué le message lu. |
| `NotificationMarkedUnread` | `MarkNotificationUnread` | public | Le destinataire a replacé le message en non lu. |
| `NotificationPreferenceChanged` | `ChangeNotificationPreferences` | public | Le User a modifié ses choix de canaux et éventuellement son opt-in Email. |
| `NotificationDeliveryRequested` | `ProcessAdvisorNotificationSignal` | interne | Une livraison Email éligible et persistée attend un dispatch. |
| `NotificationDeliveryAccepted` | `DispatchNotificationDelivery` | interne | Le fournisseur a accepté la soumission sans encore prouver sa remise. |
| `NotificationDeliveryRetryScheduled` | `DispatchNotificationDelivery` | interne | Une tentative transitoirement échouée sera rejouée avec la même clé. |
| `NotificationDeliveryConfirmed` | `RecordNotificationDeliveryOutcome` | interne | Le fournisseur a authentiquement confirmé la remise. |
| `NotificationDeliveryFailed` | `DispatchNotificationDelivery`, `RecordNotificationDeliveryOutcome` | interne | La livraison a définitivement échoué pour une raison structurée. |
| `NotificationDeliverySuppressed` | `ProcessAdvisorNotificationSignal`, `DispatchNotificationDelivery` | interne | Une règle a interdit l'effet externe avant soumission. |
| `NotificationDeliveryCancelled` | `ProcessAdvisorNotificationSignal`, `DispatchNotificationDelivery`, `ExpireNotification` | interne | Une livraison persistée est devenue non pertinente avant acceptation. |

## Contrat public de création

`NotificationCreated` contient seulement :

```text
NotificationId
RecipientUserId
NotificationTopic
RecommendationId
RecommendationPriority
AdvisorOverviewVersion
NotificationStatus
NotificationReadState
SelectedChannels[]
DisplayUntil
```

Le contenu, l'endpoint et les préférences ne sont jamais copiés dans
l'événement. Un client autorisé relit la Notification exacte.

## Contrat public de plan

`NotificationPlanCompleted` contient :

```text
NotificationPlanId
SourceEventId
SourceEventName
NotificationPolicyVersion
NotificationTopic
AdvisorOverviewVersion?
SourceOrder?
AudienceVersion?
RecipientDecisionCounts
CompletedAt
```

Les compteurs ne révèlent ni adresse, ni Membership, ni raison personnelle.

## Contrats de pertinence et lecture

Superseded, Resolved et Expired contiennent NotificationId, RecommendationId,
ancien statut, nouveau statut, instant et raison structurée. MarkedRead et
MarkedUnread contiennent NotificationId, RecipientUserId, ancien état, nouvel
état et instant. Aucun de ces faits ne modifie ou n'implique DeliveryStatus.

## Contrats de livraison

Les événements de NotificationDelivery contiennent NotificationDeliveryId,
NotificationId, canal, ancien statut, nouveau statut, numéro de tentative,
instants et raison structurée éventuelle. ProviderMessageReference reste opaque.
Ils ne contiennent jamais adresse, corps rendu, en-têtes, secret ou réponse brute.

## Publication

- état, Domain Events et outbox sont atomiques par agrégat ;
- les consommateurs dédupliquent EventId ;
- les événements suivent AggregateVersion ;
- `Accepted` ne peut jamais être nommé ou présenté comme `Delivered` ;
- Displayed, Opened, Clicked et Converted appartiennent à Product Analytics,
  pas au catalogue Notifications.
