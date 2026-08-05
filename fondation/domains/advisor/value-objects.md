---
id: ADV-VALUE-OBJECTS
title: Advisor Value Objects
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - recommendation-policy.md
  - recommendation-score.md
  - actions.md
  - invariants.md
  - ../business-health/value-objects.md
---

# Value Objects

## Identifiants

`RecommendationId`, `RecommendationEvaluationId`, `RecommendationKey`,
`RuleKey`, `RecommendationPolicyVersion`, `WorkspaceId`,
`BusinessHealthAssessmentId` et les RequestId spécialisés sont distincts.

## ActiveRecommendationPolicyVersion

Pointeur global vers la politique appliquée aux nouvelles évaluations et aux
lectures courantes. Son activation attend une évaluation de la source courante
et l'expiration contrôlée des Recommendations de l'ancienne version.

## BusinessHealthAssessmentReference

```text
BusinessHealthAssessmentId
HealthPolicyVersion
AsOf
SourcePublishedAt
AssessmentStatus
AssessmentReliability
```

La référence appartient au même Workspace et désigne la réponse exacte relue
après `BusinessHealthAssessed`.

## RecommendationStatus

```text
Generated | Completed | Dismissed | Expired
```

Generated est le seul état actif. Les autres sont terminaux.

## RecommendationPriority

```text
Critical | High | Medium | Low
```

Elle découle exclusivement du RecommendationRankScore de la même politique.

## ExpectedImpact

```text
ImpactCategory:
  ProtectCollections | BuildPipeline | RestoreBillingMomentum
  | ReduceClientConcentration | AddressPrimaryAttention

ExpectedImpactLevel: Major | Significant | Moderate | Minor
StatementTemplateKey
```

ExpectedImpact est qualitatif en 1.0 et n'exprime aucun montant garanti.

## Urgency, Confidence et Effort

```text
Urgency: Immediate | Today | ThisWeek | NoDeadline
RecommendationConfidence: High | Moderate | Low
EstimatedEffort: Small | Medium | Large | Unknown
```

Confidence décrit la qualité de la justification, pas une probabilité de succès.

## RecommendationAction

```text
ActionKind
Module
RouteKey
RouteParameters
RequiredCapabilities[]
LabelTemplateKey
```

Module, RouteKey, paramètres et capacités sont allowlistés. Aucun URI, script,
commande ou payload métier libre n'est accepté.

## RecommendationExplanation

```text
WhyNow
SourceObservations[]
PolicyRuleReference
RankingExplanation
ConfidenceRationale
ExpectedImpactStatement
Limitations[]
```

Tous les textes proviennent de templates versionnés et de valeurs typées. Une
formulation générative n'est pas une règle canonique 1.0.

## TriggerFingerprint et DeduplicationKey

Hashes déterministes d'entrées canoniques décrites par RecommendationPolicy.
Ils ne contiennent ni texte rendu, ni nom de Client, ni timestamp de traitement.

## CandidateDecisionKind

```text
Generated
Reaffirmed
ExpiredAndReplaced
SuppressedTerminalFingerprint
BelowPublicationLimit
NotApplicable
RejectedSource
HistoricalSource
```

## SourceEligibility

```text
Eligible
InsufficientAssessment
StaleAssessment
UnsupportedHealthPolicy
HistoricalSource
SourceContractMismatch
```

Une valeur autre que Eligible termine l'évaluation avec zéro candidat publié ;
elle ne constitue pas automatiquement une défaillance technique.

## DismissalReason

```text
NotRelevant | AlreadyDone | NotNow | IncorrectContext | Other
```

`Other` n'accepte pas de texte libre en 1.0.

## ExpirationReason

```text
ValidityEnded
PredicateCleared
NoLongerPrioritized
ContextChanged
PolicyReplaced
```

## CompletionConfirmation

Valeur explicite `UserConfirmedActionCompleted`. Elle ne constitue ni preuve de
mutation source, ni RecommendationOutcome.
