---
id: NTF-PUBLIC-CONTRACT
title: Notifications Public Contract
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - notification-policy.md
  - lifecycle.md
  - invariants.md
  - permissions.md
  - events.md
  - commands/README.md
  - processors/README.md
---

# Contrat public Notifications

Le contrat exprime inbox, préférences et intentions système indépendamment du
transport HTTP, du fournisseur Email et de l'interface Atlas.

## Lectures utilisateur

```text
getNotification(workspaceId, notificationId)
→ Notification

listNotifications(workspaceId, status?, readState?, cursor?, limit?)
→ NotificationSummaryPage

getUnreadNotificationCount(workspaceId)
→ UnreadNotificationCount

getNotificationPreferences(workspaceId)
→ EffectiveNotificationPreferences
```

Les trois premières exigent `notifications.inbox.read` et ne retournent que les
Notification dont RecipientUserId est l'utilisateur effectif. List et compteur
ne considèrent que les messages dont le canal InApp a été sélectionné ; une
lecture exacte peut néanmoins ouvrir une Notification Email personnelle depuis
son lien authentifié. La dernière exige `notifications.preferences.read` et
retourne la préférence du même User.

Une Notification utilisateur contient :

```text
NotificationId
NotificationTopic
RecommendationId
RecommendationPriority
NotificationStatus
NotificationReadState
NotificationContent
SelectedChannels[]
CreatedAt
DisplayUntil
Revision
```

NotificationContent contient un ActionDescriptor allowlisté. L'ouverture de
l'action exige une authentification et les capacités du domaine cible.

`getUnreadNotificationCount` compte uniquement les messages accessibles avec
ReadState Unread ; le filtre de statut est un choix d'interface explicite et ne
change jamais ReadState.

## Décisions utilisateur

```text
markNotificationRead(workspaceId, notificationId, expectedRevision,
                     markNotificationReadRequestId)

markNotificationUnread(workspaceId, notificationId, expectedRevision,
                       markNotificationUnreadRequestId)

changeNotificationPreferences(workspaceId, inAppPreference, emailPreference,
                              emailConsentConfirmation?, expectedRevision,
                              changeNotificationPreferencesRequestId)
```

Les fiches dans [`commands/`](commands/README.md) sont normatives.

## Intentions système internes

```text
processAdvisorNotificationSignal(workspaceId, sourceEventReference,
                                 notificationPolicyVersion,
                                 processAdvisorNotificationSignalRequestId)

dispatchNotificationDelivery(workspaceId, notificationId,
                             notificationDeliveryId, dispatchLease,
                             expectedRevision,
                             dispatchNotificationDeliveryRequestId)

recordNotificationDeliveryOutcome(workspaceId, notificationDeliveryId,
                                  providerOutcomeId, providerDeliveryProof,
                                  expectedRevision,
                                  recordNotificationDeliveryOutcomeRequestId)

expireNotification(workspaceId, notificationId, clockProof, expectedRevision,
                   expireNotificationRequestId)
```

Les fiches dans [`processors/`](processors/README.md) sont normatives.

## Callback fournisseur

Le transport expose un endpoint authentifié qui traduit seulement les outcomes
allowlistés en ProviderDeliveryProof. Un payload invalide est rejeté avant le
domaine ; un ProviderOutcomeId déjà appliqué est idempotent.

## Erreurs publiques

| Catégorie | Sens |
|---|---|
| `InvalidInput` | identifiant, filtre, préférence, preuve ou limite invalide |
| `Unauthenticated` | principal, workload ou callback absent/invalide |
| `Unauthorized` | capacité absente ou User différent du destinataire |
| `NotFound` | ressource absente ou masquée par l'isolation personnelle |
| `InvalidState` | transition de lecture, pertinence ou livraison impossible |
| `NotificationNoLongerActive` | pertinence ou validité atteinte avant l'effet |
| `UnsupportedChannel` | canal ou préférence non supporté en 1.0 |
| `ConsentRequired` | activation Email sans opt-in explicite |
| `EndpointUnavailable` | endpoint vérifié absent ou devenu invalide |
| `UnsupportedPolicyVersion` | politique Notifications non supportée |
| `SourceContractMismatch` | signal Advisor et lecture exacte incompatibles |
| `ProviderProofInvalid` | callback ou preuve fournisseur non authentique |
| `Conflict` | révision, unicité ou idempotence incompatible |
| `TemporarilyUnavailable` | source, Identity, Workspace ou fournisseur indisponible |

Audience vide, notification inchangée, canal désactivé ou fréquence atteinte
sont des décisions normales de NotificationPlan, pas des erreurs API.

## Versioning

Un changement matériel de source, audience, canal, fréquence, contenu ou
template crée une nouvelle NotificationPolicyVersion. Un changement de sens
contractuel exige une version d'API ou d'événement explicite.
