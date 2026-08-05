---
id: ADV-ENTITIES
title: Advisor Entities
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

## Recommendation

Proposition durable possédant une identité et un cycle de vie.

| Attribut | Rôle |
|---|---|
| `RecommendationId`, `WorkspaceId` | identité et isolation |
| `RecommendationKey`, `RuleKey` | sens stable et règle productrice |
| `RecommendationPolicyVersion` | politique effectivement appliquée |
| `TriggerFingerprint` | identité matérielle du déclencheur |
| `RecommendationStatus` | Generated, Completed, Dismissed ou Expired |
| `RecommendationPriority` | Critical, High, Medium ou Low |
| `RecommendationRankScore` | ordre déterministe de 0 à 100 |
| `ExpectedImpact` | objectif et niveau qualitatifs |
| `Urgency`, `RecommendationConfidence`, `EstimatedEffort` | composantes du rang |
| `RecommendationAction` | action principale unique |
| `EvidenceRevisions[]` | preuves et explications historisées |
| `CurrentEvidenceRevision` | révision présentée actuellement |
| `GeneratedAt`, `ValidUntil` | création et fin de validité |
| `CompletedAt?`, `DismissedAt?`, `ExpiredAt?` | instant terminal exclusif |
| `DismissalReason?`, `ExpirationReason?` | justification structurée |
| `PreviousRecommendationId?` | continuité sans réactivation |
| `Revision` | concurrence optimiste |

## RecommendationEvidenceRevision

Enfant immuable et séquentiel de Recommendation.

| Attribut | Rôle |
|---|---|
| `EvidenceRevisionNumber` | ordre strictement croissant |
| `BusinessHealthAssessmentReference` | source exacte et AsOf |
| `SourceObservations[]` | facteur, risque, bande et valeurs nécessaires copiés |
| `EvidenceReferences[]` | références Business Health et Analytics transitives |
| `RecommendationExplanation` | texte déterministe et règles rendues |
| `ValidatedAt` | instant de réaffirmation |
| `ProposedValidUntil` | validité calculée depuis SourceAsOf |

Une révision ne modifie jamais les preuves précédentes.

## RecommendationEvaluation

Process manager durable par source et politique.

| Attribut | Rôle |
|---|---|
| `RecommendationEvaluationId`, `WorkspaceId` | identité |
| `BusinessHealthAssessmentReference` | source exacte |
| `RecommendationPolicyVersion` | règles appliquées |
| `EvaluateRecommendationsRequestId` | idempotence globale |
| `EvaluationStatus` | Processing ou Completed |
| `SourceEligibility` | éligible ou raison structurée |
| `CandidateDecisions[]` | décision pour chaque RuleKey |
| `StartedAt`, `CompletedAt?` | suivi de reprise |

## CandidateDecision

Enfant de RecommendationEvaluation représentant une règle : score, rang,
décision, RequestId dérivé et RecommendationId éventuel. Il ne devient jamais
une Recommendation sans décision `Generated`.

## RecommendationRuleState et AdvisorOverview

Read models reconstructibles. Le premier protège déduplication et épisodes de
prédicat ; le second sert les lectures de priorité. Aucun n'est une source de
vérité supérieure aux agrégats.
