---
id: ADV-EVENTS
title: Advisor Domain Events
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - model.md
  - recommendation-lifecycle.md
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
| `AggregateType`, `AggregateId` | racine Advisor concernée |
| `AggregateVersion` | révision après commit |
| `WorkspaceId` | frontière d'isolation |
| `CorrelationId`, `CausationId` | chaîne depuis Business Health ou l'intention humaine |
| `ActorReference` | acteur ou workload minimal auditable |
| `Data` | charge utile minimale sans preuve détaillée |

## Catalogue

| Événement | Producteur | Visibilité | Fait minimum |
|---|---|---|---|
| `RecommendationEvaluationCompleted` | `EvaluateRecommendations` | public | Les cinq règles ont convergé et référencent l'AdvisorOverviewVersion déjà appliquée. |
| `RecommendationGenerated` | `EvaluateRecommendations` | public | Une proposition classée dans le top trois est devenue active. |
| `RecommendationReaffirmed` | `EvaluateRecommendations` | interne | Une source plus récente a confirmé le même TriggerFingerprint. |
| `RecommendationCompleted` | `CompleteRecommendation` | public | Un utilisateur a confirmé avoir accompli l'action. |
| `RecommendationDismissed` | `DismissRecommendation` | public | Un utilisateur a explicitement rejeté la proposition. |
| `RecommendationExpired` | `EvaluateRecommendations`, `ExpireRecommendation` | public | La proposition n'est plus active pour une raison structurée. |
| `AdvisorOverviewChanged` | `RebuildAdvisorOverview` | public | La priorité et ses alternatives ont convergé après une cause applicable. |

## Contrat public de génération

`RecommendationGenerated` contient seulement :

```text
RecommendationId
RecommendationKey
RecommendationPolicyVersion
BusinessHealthAssessmentId
RecommendationPriority
RecommendationActionKind
TargetModule
ValidUntil
```

Un consommateur autorisé relit la Recommendation exacte avec
`advisor.recommendations.consume`. L'explication et les preuves ne sont pas
dupliquées dans l'événement.

## Contrat public d'évaluation

`RecommendationEvaluationCompleted` contient :

```text
RecommendationEvaluationId
BusinessHealthAssessmentId
RecommendationPolicyVersion
AdvisorOverviewVersion
SourceOrder: (AsOf, SourcePublishedAt, BusinessHealthAssessmentId)
SourceEligibility
PrimaryRecommendationId?
PublishedRecommendationIds[0..3]
```

Ce fait reste une preuve d'évaluation. Notifications ne l'utilise pas comme
déclencheur de diffusion.

## Contrat public de convergence

`AdvisorOverviewChanged` contient :

```text
AdvisorOverviewVersion
PreviousAdvisorOverviewVersion
OverviewConvergenceKind
CauseReference
RecommendationEvaluationId?
SourceOrder
SourceEligibility
PrimaryRecommendationId?
PublishedRecommendationIds[0..3]
```

Notifications consomme exclusivement ce fait pour créer, remplacer, résoudre
ou expirer un thread Advisor. Chaque version est publiée une seule fois après la
reconstruction complète ; les événements Recommendation isolés ne déclenchent
aucune diffusion.

## Contrats terminaux

Completed, Dismissed et Expired contiennent RecommendationId, ancien statut,
nouveau statut, instant terminal et raison ou confirmation structurée. Ils ne
contiennent ni texte libre, ni outcome supposé.

## Publication

- état, Domain Events et outbox sont atomiques par agrégat ;
- les consommateurs dédupliquent EventId ;
- les événements suivent AggregateVersion ;
- un process manager reprend les décisions non confirmées sans doubler les faits ;
- Displayed, Opened, Clicked et Ignored ne sont jamais publiés par Advisor.
