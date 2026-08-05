---
id: NTF-ENTITIES
title: Notifications Entities
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - value-objects.md
  - invariants.md
---

# Entités

## NotificationTopicCursor

Racine de sérialisation par Workspace et NotificationTopic. Elle contient
LastAppliedAdvisorOverviewVersion, LastAppliedSourceOrder,
TopicProcessingLease?, ProcessingPlanId?, Revision et les instants d'acquisition
ou de progression. Elle ne contient aucun Recipient ni contenu.

## Notification

Message durable destiné à un seul User dans un seul Workspace.

| Attribut | Rôle |
|---|---|
| `NotificationId`, `WorkspaceId` | identité et isolation |
| `RecipientReference` | UserId, MembershipId et AudienceVersion |
| `NotificationThreadKey` | unicité du topic actif par destinataire |
| `SourceReference` | événement et Recommendation exacts |
| `AppliedAdvisorOverviewVersion` | version Advisor ayant produit le message |
| `AppliedAdvisorSourceOrder` | ordre source conservé pour audit |
| `NotificationPolicyVersion` | politique appliquée |
| `ContentFingerprint` | déduplication matérielle |
| `NotificationContent` | template, locale et données minimisées |
| `ChannelPlan` | décision InApp et Email |
| `NotificationStatus` | Active, Resolved, Superseded ou Expired |
| `NotificationReadState` | Unread, Read ou NotApplicable |
| `TerminalReason?` | raison structurée compatible avec le statut terminal |
| `DisplayUntil` | fin de pertinence visible |
| `CreatedAt`, `ReadAt?`, `ResolvedAt?` | instants métier |
| `Revision` | concurrence optimiste |

## NotificationDelivery

Enfant de Notification pour un canal externe.

| Attribut | Rôle |
|---|---|
| `NotificationDeliveryId` | identité stable |
| `NotificationChannel` | Email en 1.0 |
| `DeliveryEndpointReference` | handle opaque et versionné |
| `ProviderIdempotencyKey` | déduplication externe stable |
| `DeliveryStatus` | cycle de remise |
| `ProviderMessageReference?` | corrélation opaque fournisseur |
| `Attempts[]` | tentatives immuables |
| `RequestedAt`, `AcceptedAt?`, `DeliveredAt?` | instants distincts |
| `FailureReason?` | raison structurée minimale |

## DeliveryAttempt

Une tentative contient numéro, lease, RequestedAt, résultat, ProviderResponseCode
allowlisté, NextAttemptAt éventuel et latence. Elle ne contient ni adresse brute,
ni corps complet, ni secret fournisseur.

## NotificationPlan

Process manager durable par événement source et politique.

| Attribut | Rôle |
|---|---|
| `NotificationPlanId`, `WorkspaceId` | identité |
| `SourceEventReference` | EventId, nom, schéma et ordre |
| `AdvisorOverviewVersion`, `SourceOrder` | ordre Advisor exact du plan |
| `NotificationTopic` | cursor de sérialisation concerné |
| `NotificationPolicyVersion` | règles appliquées |
| `NotificationPlanStatus` | Processing ou Completed |
| `AudienceSnapshotReference` | version de résolution Identity |
| `RecipientDecisions[]` | Created, Unchanged, Resolved, Superseded, Expired, Suppressed ou SourceIgnored |
| `ProcessAdvisorNotificationSignalRequestId` | idempotence |
| `StartedAt`, `CompletedAt?` | reprise fiable |

## NotificationPreference

Préférence par `(WorkspaceId, UserId)` : InAppMode, EmailMode,
EmailOptInRecordedAt?, version et audit de changement. Elle ne contient aucun
endpoint de livraison.
