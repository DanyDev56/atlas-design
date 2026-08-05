---
id: ADV-PUBLIC-CONTRACT
title: Advisor Public Contract
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - scope.md
  - recommendation-policy.md
  - invariants.md
  - permissions.md
  - events.md
  - commands/README.md
  - processors/README.md
---

# Contrat public Advisor

Le contrat exprime lectures, décisions humaines et intentions système
indépendantes du transport.

## Lectures utilisateur

```text
getAdvisorOverview(workspaceId)
→ AdvisorOverviewVersion + PrimaryRecommendation?
  + AlternativeRecommendations[0..2]

getRecommendation(workspaceId, recommendationId)
→ Recommendation

listRecommendations(workspaceId, status?, priority?, asOfRange?, cursor?, limit?)
→ RecommendationSummaryPage

getRecommendationExplanation(workspaceId, recommendationId,
                             evidenceRevisionNumber?)
→ RecommendationExplanation
```

Toutes exigent `advisor.recommendations.read`. Une Recommendation contient :

```text
RecommendationId
RecommendationKey
RuleKey
RecommendationPolicyVersion
RecommendationStatus
RecommendationPriority
RecommendationRankScore
ExpectedImpact
Urgency
RecommendationConfidence
EstimatedEffort
RecommendationAction
CurrentEvidenceRevision
GeneratedAt
ValidUntil
TerminalDecision?
```

L'overview ne retourne que des Recommendation Generated non expirées. Le rang
interne est fourni pour l'explication, jamais comme probabilité.

## Décisions utilisateur

```text
completeRecommendation(workspaceId, recommendationId,
                       completionConfirmation, expectedRevision,
                       completeRecommendationRequestId)

dismissRecommendation(workspaceId, recommendationId, dismissalReason,
                      expectedRevision, dismissRecommendationRequestId)
```

Les fiches dans [`commands/`](commands/README.md) sont normatives.

## Contrat fourni à Notifications

```text
getRecommendationForNotification(workspaceId, recommendationId)
→ NotificationRecommendationView

getAdvisorOverviewForNotification(workspaceId, recommendationEvaluationId)
→ NotificationAdvisorOverview
```

La lecture exige `advisor.recommendations.consume`. Les réponses minimales sont :

```text
NotificationAdvisorOverview
  RecommendationEvaluationId
  AdvisorOverviewVersion
  SourceOrder: (AsOf, SourcePublishedAt, BusinessHealthAssessmentId)
  SourceEligibility
  PrimaryRecommendation?
    RecommendationId
    RecommendationStatus
    RecommendationPriority
    NotificationTemplateKey
    NotificationTemplateVersion
    NotificationTemplateData
    RecommendationAction
    ValidUntil

NotificationRecommendationView
  RecommendationId
  RecommendationStatus
  RecommendationPriority
  NotificationTemplateKey
  NotificationTemplateVersion
  NotificationTemplateData
  RecommendationAction
  ValidUntil
  TerminalDecision?
```

NotificationTemplateData est typée, allowlistée et sans preuve détaillée. Le
contrat ne retourne ni EvidenceReference, score interne, montant, Client ou
document. RecommendationAction conserve sa RouteKey et ses capacités requises ;
Notifications ne peut ni l'altérer ni l'exécuter.

## Intentions système internes

```text
evaluateRecommendations(workspaceId, businessHealthAssessmentId,
                        businessHealthAssessedEventId,
                        recommendationPolicyVersion,
                        evaluateRecommendationsRequestId)

expireRecommendation(workspaceId, recommendationId, clockProof,
                     expectedRevision, expireRecommendationRequestId)
```

Les fiches dans [`processors/`](processors/README.md) sont normatives.

## Erreurs publiques

| Catégorie | Sens |
|---|---|
| `InvalidInput` | identifiant, raison, confirmation, plage ou limite invalide |
| `Unauthenticated` | principal ou workload absent/invalide |
| `Unauthorized` | capacité Advisor absente |
| `NotFound` | Recommendation, évaluation ou politique absente/masquée |
| `InvalidState` | transition terminale ou réaffirmation impossible |
| `RecommendationNoLongerActive` | ValidUntil atteint ou état terminal |
| `UnsupportedPolicyVersion` | politique Advisor ou Health non supportée |
| `SourceContractMismatch` | contrat Business Health incompatible |
| `Conflict` | révision, unicité ou idempotence incompatible |
| `TemporarilyUnavailable` | source ou projection nécessaire indisponible |

Une source inéligible ou zéro candidat est un résultat normal de
RecommendationEvaluation, pas une erreur API.

## Versioning

Un changement de règle, poids, seuil, template matériel ou allowlist crée une
nouvelle RecommendationPolicyVersion. Un changement de sens contractuel exige
une version d'API ou d'événement explicite.
