---
id: NTF-VALUE-OBJECTS
title: Notifications Value Objects
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - notification-policy.md
  - lifecycle.md
  - invariants.md
  - ../advisor/value-objects.md
---

# Value Objects

## Identifiants

`NotificationId`, `NotificationPlanId`, `NotificationDeliveryId`,
`NotificationPolicyVersion`, `NotificationTemplateVersion`, `WorkspaceId`,
`UserId`, `MembershipId` et les RequestId spécialisés sont distincts.

## SourceEventReference

```text
EventId
EventName
SchemaVersion
OccurredAt
AggregateType
AggregateId
AggregateVersion
WorkspaceId
CorrelationId
```

La référence est authentique, supportée et du même Workspace.

## AdvisorSourceReference

```text
RecommendationEvaluationId
AdvisorOverviewVersion
SourceOrder: (AsOf, SourcePublishedAt, BusinessHealthAssessmentId)
PrimaryRecommendationId?
RecommendationId?
RecommendationPolicyVersion
RecommendationPriority?
RecommendationStatus?
ValidUntil?
```

Elle copie uniquement les identifiants nécessaires au message ou à sa
résolution.

AdvisorOverviewVersion est la garde monotone inter-politique. SourceOrder est
conservé pour l'audit et la cohérence avec l'évaluation exacte. Une version
inférieure au cursor vaut SourceIgnored ; une version égale avec un contenu
incompatible vaut SourceContractMismatch.

## RecipientReference

```text
RecipientUserId
MembershipId
AudienceVersion
RequiredPermissionKeys[]
```

RecipientReference ne contient ni adresse e-mail, ni Role, ni permission
effective copiée durablement au-delà de sa version de preuve.

## DeliveryEndpointReference

Handle opaque, versionné, borné à un User et au canal Email. Il prouve qu'un
endpoint était vérifié lors de la résolution mais doit être revalidé avant
dispatch. Il ne peut pas être affiché ou journalisé comme une adresse.

## NotificationStatus et NotificationReadState

```text
NotificationStatus: Active | Resolved | Superseded | Expired
NotificationReadState: Unread | Read | NotApplicable
```

## NotificationTerminalReason

```text
RecommendationCompleted
RecommendationDismissed
NoPrimaryRecommendation
RecipientUnauthorized
PrimaryRecommendationChanged
RecommendationExpired
DisplayUntilReached
```

Chaque raison est compatible avec le statut terminal exact ; elle n'est jamais
un texte libre.

## NotificationChannel et préférences

```text
NotificationChannel: InApp | Email
InAppMode: Enabled | Disabled
EmailMode: ImportantOnly | Disabled
```

## EmailConsentConfirmation

```text
ConsentPurpose: ImportantAdvisorNotifications
Confirmation: UserExplicitlyOptedIn
ConsentTextVersion
```

Le serveur ajoute EmailOptInRecordedAt et ActorReference. La confirmation n'est
ni implicite, ni précochée, ni réutilisable pour un autre purpose.

## ChannelDecision

```text
Channel
Decision: Selected | Suppressed
SuppressionReason?
PreferenceRevision?
DecisionAt
```

SuppressionReason appartient à :

```text
ChannelDisabled
EmailOptInMissing
PriorityNotImportant
EndpointUnavailable
RateLimited
RecipientUnauthorized
WorkspaceUnavailable
NotificationUnchanged
NotificationNoLongerRelevant
```

## NotificationContent

```text
NotificationTemplateKey
NotificationTemplateVersion
Locale
TitleTemplateData
BodyTemplateData
AdvisorActionDescriptor
ContentFingerprint
```

Les clés de template et données sont typées, allowlistées et bornées. Aucun
HTML, URL, texte ou identifiant personnel libre n'est accepté.

## NotificationThreadKey

Hash déterministe de WorkspaceId, RecipientUserId et NotificationTopic. Il ne
contient aucune donnée personnelle lisible.

## NotificationTopic

`AdvisorPrimaryRecommendation` est l'unique valeur 1.0.

## TopicProcessingLease

Lease durable de NotificationTopicCursor avec ProcessingPlanId, AcquiredAt,
LeaseUntil et FencingToken. Un seul plan d'évaluation peut le détenir pour un
Workspace et un topic ; un ancien token ne peut plus avancer le cursor.

## EmailFrequencyKey et FrequencyLease

EmailFrequencyKey est le hash déterministe de WorkspaceId, RecipientUserId et
NotificationTopic. FrequencyLease sérialise les dispatch Email concurrents de
cette clé ; il possède propriétaire, acquis à, échéance et token de fencing.

## DeliveryStatus

```text
Pending | Dispatching | Accepted | Delivered | Failed | Suppressed | Cancelled
```

## ProviderDeliveryProof

```text
ProviderOutcomeId
ProviderMessageReference
Outcome: Delivered | Bounced | Complained | PermanentlyFailed
OccurredAt
SignatureProofReference
```

La preuve est authentifiée par l'adaptateur ; aucune signature brute n'entre
dans le domaine.

## NotificationPlanStatus et RecipientDecisionKind

```text
NotificationPlanStatus: Processing | Completed

RecipientDecisionKind:
  Created | Unchanged | Resolved | Superseded | Expired
  | AllChannelsSuppressed | SourceIgnored
```

## DeliveryFailureReason

```text
TransientProviderFailure
PermanentProviderFailure
EndpointInvalid
Bounced
Complained
AttemptsExhausted
NotificationNoLongerRelevant
```
