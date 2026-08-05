---
id: NTF-PROC-PROCESS-ADVISOR-NOTIFICATION-SIGNAL
title: ProcessAdvisorNotificationSignal
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../notification-policy.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../integrations.md
  - ../workflows.md
---

# ProcessAdvisorNotificationSignal

## Objectif

Transformer un fait Advisor supporté en un plan durable, dédupliqué et complet
de décisions par destinataire et par canal.

## Agrégats concernés

`NotificationTopicCursor`, nouvel agrégat `NotificationPlan`, puis zéro ou
plusieurs agrégats `Notification` existants ou nouveaux.

## Acteur et autorité

Workload Notifications autorisé par `notifications.signals.process`, causé par
`RecommendationEvaluationCompleted`, `RecommendationCompleted`,
`RecommendationDismissed` ou `RecommendationExpired`.

## Données d'entrée

```text
WorkspaceId
SourceEventReference
NotificationPolicyVersion
ProcessAdvisorNotificationSignalRequestId
CorrelationId
WorkloadContext
```

Le RequestId est dérivé de SourceEventId et NotificationPolicyVersion. Chaque
décision de destinataire et de canal dérive son propre RequestId du plan.

## Préconditions et traitement

- enveloppe Advisor authentique, supportée et du même Workspace ;
- politique publiée et versions de schéma supportées ;
- lecture de l'AdvisorOverview exact par RecommendationEvaluationId puis de la
  NotificationRecommendationView courante par PrimaryRecommendationId avec
  `advisor.recommendations.consume` ;
- pour RecommendationEvaluationCompleted : état Workspace Active, contexte
  d'accès courant et audience Identity résolue pour les capacités requises ;
- acquisition du TopicProcessingLease puis comparaison
  d'AdvisorOverviewVersion au cursor avant toute mutation d'évaluation ;
- pour un événement terminal : sélection des Notification existantes par
  RecommendationId exact, sans audience nouvelle et même si le Workspace est
  restreint ;
- lecture des préférences Notifications, avec valeurs virtuelles par défaut ;
- création ou reprise de NotificationPlan ;
- application déterministe de l'éligibilité, du remplacement, de la fréquence et
  de la minimisation de contenu ;
- création, conservation, résolution, supersession ou expiration idempotente ;
- completion du plan seulement après une décision terminale pour chaque
  destinataire et canal considérés.

Un signal d'évaluation inéligible ou de version inférieure produit SourceIgnored
et ne modifie aucun thread. Un overview Eligible, monotone et vide ou une
audience vide produit un plan Completed sans nouvelle Notification et résout les
threads actifs concernés. Les threads d'anciens destinataires absents de
l'audience courante sont résolus sans diffusion.

## Invariants concernés

`NTF-INV-001`–`NTF-INV-058`.

## Événements produits

- `NotificationPlanCompleted` ;
- `NotificationCreated` ;
- `NotificationSuperseded` ;
- `NotificationResolved` ;
- `NotificationExpired` ;
- `NotificationDeliveryRequested` ;
- `NotificationDeliverySuppressed` ;
- `NotificationDeliveryCancelled`.

## Concurrence

Les Notification existantes sont mutées par ExpectedRevision. Une contrainte
unique protège la NotificationThreadKey active et une autre le
NotificationPlanKey. NotificationTopicCursor utilise un lease avec fencing token.
Après conflit, le processeur relit et réapplique seulement la décision encore
valide.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`,
`UnsupportedPolicyVersion`, `SourceContractMismatch`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

ProcessAdvisorNotificationSignalRequestId et les RequestId dérivés sont
obligatoires. Un rejeu identique reprend ou retourne le plan initial ; une
réutilisation avec une autre source ou politique échoue avec `Conflict`.
