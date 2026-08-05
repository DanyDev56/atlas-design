---
id: NTF-RELATIONSHIPS
title: Notifications Relationships
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - integrations.md
  - ../advisor/api.md
  - ../identity/api.md
---

# Relations

## Cardinalités internes

```text
NotificationPolicy      1 -------- 0..* NotificationPlan
NotificationTopicCursor 1 -------- 0..* NotificationPlan serialized
NotificationPlan        1 -------- 0..* RecipientDecision
RecipientDecision       1 -------- 0..1 Notification
NotificationPreference  1 -------- 0..* RecipientDecision
Notification            1 -------- 0..1 Email NotificationDelivery
NotificationDelivery    1 -------- 0..* DeliveryAttempt
NotificationThreadKey   1 -------- 0..1 Active Notification
```

## Advisor

```text
RecommendationEvaluationCompleted
  -> ProcessAdvisorNotificationSignal
  -> getAdvisorOverviewForNotification(exact evaluation id)
  -> NotificationTopicCursor(version + processing lease)
  -> NotificationPlan
```

Les événements terminaux Advisor résolvent ou expirent les Notification liées.
Notifications ne modifie ni Recommendation ni AdvisorOverview.

## Identity

Identity fournit une AudienceSnapshot et des DeliveryEndpointReference opaques.
Notifications conserve les identifiants minimaux mais ne devient propriétaire
ni du User, Membership, Role, permission ou endpoint.

## Workspace

WorkspaceId isole plans, préférences et messages. WorkspaceAccessContext décide
si la planification ordinaire est permise ; Locale est figée depuis les
WorkspacePreferences au moment de la création.

## Fournisseur Email

L'adaptateur reçoit une enveloppe minimisée avec ProviderIdempotencyKey et
DeliveryEndpointReference, résout confidentiellement l'endpoint puis transmet
au fournisseur uniquement les données nécessaires. Son identifiant de message
et ses outcomes restent des références externes, pas des identités Notification.
