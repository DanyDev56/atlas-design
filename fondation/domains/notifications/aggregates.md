---
id: NTF-AGGREGATES
title: Notifications Aggregates
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - entities.md
  - notification-policy.md
  - lifecycle.md
  - invariants.md
  - workflows.md
---

# Agrégats

## Vue d'ensemble

| Agrégat | Racine | Cohérence garantie |
|---|---|---|
| Notification Topic Cursor | `NotificationTopicCursor` | ordre monotone et lease de traitement par topic |
| Notification Plan | `NotificationPlan` | source, audience, décisions par destinataire et reprise |
| Notification | `Notification` | contenu, pertinence, lecture et livraison d'un destinataire |
| Notification Preference | `NotificationPreference` | consentement de canaux par User et Workspace |

`NotificationPolicy` et les templates sont des catalogues globaux versionnés.

## NotificationTopicCursor

La clé naturelle est `(WorkspaceId, NotificationTopic)`. La racine conserve
LastAppliedAdvisorOverviewVersion, LastAppliedSourceOrder, ProcessingPlanId?,
TopicProcessingLease? et Revision.

Un seul plan AdvisorOverviewChanged détient le lease à la fois. Il
compare sa version Advisor au cursor avant toute mutation, puis avance le cursor
seulement lorsque toutes ses RecipientDecision et outbox sont durables. Un token
de fencing empêche un worker au lease expiré de committer tardivement. Les
versions vides ou de remplacement restent idempotentes et ne font jamais
régresser le cursor.

Après expiration d'un lease, le repreneur relit d'abord ProcessingPlanId. Un
plan Completed fait avancer le cursor avant qu'un autre plan puisse acquérir le
lease ; un plan Processing est repris. Le cursor ne saute donc jamais un plan
ayant déjà produit des mutations durables.

## NotificationPlan

La clé naturelle est :

```text
(SourceEventId, NotificationPolicyVersion)
```

La racine fige le contrat source et l'audience résolue. Chaque RecipientDecision
possède un RequestId dérivé du plan et du RecipientUserId. Le plan devient
Completed seulement lorsque toutes les créations, résolutions, suppressions et
outbox correspondantes sont durables.

Un retry reprend les décisions manquantes. Une audience vide ou des canaux tous
supprimés terminent normalement sans Notification.

## Notification

Une contrainte unique autorise au plus une Notification Active par
NotificationThreadKey. La création fige contenu, destination, préférence,
audience et politique appliqués.

La racine accepte :

- mark read ou unread par le RecipientUserId autorisé ;
- resolve, supersede ou expire par un signal ou workload authentique ;
- création et évolution d'une NotificationDelivery Email ;
- ajout immuable de DeliveryAttempt et résultat fournisseur.

Un nouveau contenu ne réécrit jamais une Notification : il la rend Superseded
et crée une nouvelle racine.

## NotificationPreference

La clé naturelle est `(WorkspaceId, UserId)`. En l'absence de racine persistée,
les defaults de NotificationPolicy s'appliquent. La première modification crée
la racine ; les suivantes exigent ExpectedRevision.

Une préférence n'altère ni une Notification déjà créée ni une
NotificationDelivery déjà Accepted. Une NotificationDelivery encore Pending
peut être Cancelled après désactivation.

## Concurrence

Les commandes et callbacks utilisent ExpectedRevision ou ProviderOutcomeId.
Un TopicProcessingLease sérialise les plans d'évaluation d'un topic.
Un DispatchLease protège un worker sans autoriser deux ProviderIdempotencyKey.
Un FrequencyLease durable, sérialisé par EmailFrequencyKey, empêche deux
Notification concurrentes de franchir la même fenêtre avant l'appel fournisseur.
Après conflit, le traitement relit la racine et converge vers l'état terminal
déjà commis. Un timeout fournisseur ambigu conserve ce lease jusqu'à résolution.
