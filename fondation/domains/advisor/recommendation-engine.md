---
id: ADV-RECOMMENDATION-ENGINE
title: Recommendation Engine
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - recommendation-policy.md
  - model.md
  - invariants.md
  - processors/EvaluateRecommendations.md
  - ../business-health/api.md
---

# Recommendation Engine

## Source unique 1.0

Après `BusinessHealthAssessed`, Advisor relit :

```text
getBusinessHealthAssessment(workspaceId, businessHealthAssessmentId)
```

avec `business-health.assessments.consume`, puis confirme qu'il s'agit de la vue
courante. Il ne lit ni AnalyticsSnapshot, ni CRM, ni Billing.

## Pipeline déterministe

```text
exact BusinessHealthAssessment
        ↓ source eligibility
five versioned rules
        ↓ candidate scoring
total deterministic order
        ↓ publication limit
zero to three Recommendation
```

Chaque CandidateDecision vaut :

```text
Generated
Reaffirmed
ExpiredAndReplaced
SuppressedTerminalFingerprint
BelowPublicationLimit
NotApplicable
RejectedSource
```

Le rapport conserve aussi les règles non publiées afin d'expliquer pourquoi une
action n'a pas été présentée.

## Ordre des évaluations

Une RecommendationEvaluation compare l'ordre source
`(AsOf, SourcePublishedAt, BusinessHealthAssessmentId)`. Une évaluation tardive
se termine avec `SourceEligibility = HistoricalSource` et ne change ni les
recommandations actives ni la priorité courante.

## Révisions de preuve

Une réaffirmation ajoute une `RecommendationEvidenceRevision` immuable avec la
nouvelle source, les observations copiées et l'explication rendue. Elle peut
prolonger ValidUntil uniquement selon `SourceAsOf + RuleValidity`.

Une modification de sévérité, bande, action, impact, urgence, confiance ou
effort change le TriggerFingerprint : l'ancienne Recommendation expire et une
nouvelle est générée.

## Défaillance et reprise

RecommendationEvaluation est un process manager durable. Chaque décision
possède un RequestId dérivé de l'évaluation et de la RuleKey. Un retry reprend
les décisions manquantes ; l'évaluation devient Completed seulement lorsque
toutes ont convergé, que l'AdvisorOverviewRevision applicable est durable et que
l'outbox est durable.
