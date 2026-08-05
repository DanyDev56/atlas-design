---
id: NTF-MODEL
title: Notifications Domain Model
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - notification-policy.md
  - lifecycle.md
  - entities.md
  - aggregates.md
  - value-objects.md
  - relationships.md
  - invariants.md
---

# Modèle du domaine

## Vue conceptuelle

```mermaid
classDiagram
    class NotificationPolicy {
      NotificationPolicyVersion
    }
    class NotificationPlan {
      SourceEventReference
      PlanStatus
    }
    class NotificationTopicCursor {
      LastAppliedAdvisorOverviewVersion
      TopicProcessingLease
    }
    class RecipientDecision
    class Notification {
      NotificationStatus
      NotificationReadState
    }
    class NotificationDelivery {
      NotificationChannel
      DeliveryStatus
    }
    class DeliveryAttempt
    class NotificationPreference {
      InAppMode
      EmailMode
    }

    NotificationPolicy --> NotificationPlan
    NotificationTopicCursor --> NotificationPlan
    NotificationPlan *-- RecipientDecision
    RecipientDecision --> Notification
    Notification *-- NotificationDelivery
    NotificationDelivery *-- DeliveryAttempt
    NotificationPreference --> RecipientDecision
```

## Chaîne de remise

```text
public Advisor event
        ↓ exact Advisor read
NotificationPlan + authorized Identity audience
        ↓ preferences and policy
0..n recipient Notification
        ├── in-app inbox
        └── email NotificationDelivery
```

## Quatre agrégats

- `NotificationPlan` garantit le traitement complet et idempotent d'un événement
  source pour toute son audience ;
- `NotificationTopicCursor` sérialise les plans d'évaluation d'un topic et
  empêche une régression d'AdvisorOverviewVersion ;
- `Notification` porte le contenu personnel, le thread, la lecture et les
  livraisons ;
- `NotificationPreference` porte le consentement de canal d'un User dans un
  Workspace.

## Vérités distinctes

```text
NotificationStatus       -> pertinence du message
NotificationReadState    -> décision de lecture de l'utilisateur
DeliveryStatus           -> résultat d'un canal externe
```

Aucune de ces dimensions n'est déduite silencieusement d'une autre.

## Frontière avec le fournisseur

Notifications confie une NotificationDelivery à un port Email. La réponse
synchrone peut prouver Accepted, jamais Delivered. Les callbacks sont
authentifiés, dédupliqués et appliqués à la ProviderMessageReference exacte.
