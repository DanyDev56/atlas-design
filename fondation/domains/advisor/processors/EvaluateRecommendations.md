---
id: ADV-PROC-EVALUATE-RECOMMENDATIONS
title: EvaluateRecommendations
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - README.md
  - ../recommendation-policy.md
  - ../recommendation-engine.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# EvaluateRecommendations

## Objectif

Évaluer une BusinessHealthAssessment exacte, faire converger les Recommendation
actives et produire un rapport complet des cinq règles.

## Agrégats concernés

Nouvel agrégat `RecommendationEvaluation`, puis zéro ou plusieurs agrégats
`Recommendation` existants ou nouveaux.

## Acteur et autorité

Workload Advisor autorisé par `advisor.recommendations.evaluate`, causé par
`BusinessHealthAssessed`.

## Données d'entrée

```text
WorkspaceId
BusinessHealthAssessmentId
BusinessHealthAssessedEventId
RecommendationPolicyVersion
EvaluateRecommendationsRequestId
CorrelationId
WorkloadContext
```

EvaluateRecommendationsRequestId est dérivé de manière déterministe de la
BusinessHealthAssessment et de la RecommendationPolicyVersion. Chaque décision
de règle dérive son propre RequestId de l'évaluation et de RuleKey.

## Préconditions et traitement

- enveloppe Business Health authentique, supportée et du même Workspace ;
- politique publiée et HealthPolicyVersion supportée ;
- lecture exacte avec `business-health.assessments.consume` ;
- comparaison à la vue Business Health courante ;
- création ou reprise de RecommendationEvaluation ;
- éligibilité, cinq règles, scoring, ordre total et limite top trois ;
- génération, réaffirmation, expiration ou suppression idempotente ;
- reconstruction AdvisorOverview, avance atomique d'AdvisorOverviewVersion puis
  completion du process manager.

Une source inéligible ou historique termine l'évaluation avec une
SourceEligibility structurée sans modifier les Recommendation courantes.

## Invariants concernés

`ADV-INV-001`–`ADV-INV-050`.

## Événements produits

- `RecommendationGenerated` ;
- `RecommendationReaffirmed` ;
- `RecommendationExpired` ;
- `RecommendationEvaluationCompleted`.

Une règle peut ne produire aucun événement Recommendation. Completed n'est
publié qu'après convergence de toutes les décisions.

## Concurrence

Les Recommendation existantes sont mutées par ExpectedRevision et
compare-and-set. Une contrainte unique protège DeduplicationKey. Après conflit,
le processeur relit l'état et réapplique seulement la décision encore valide.
La publication de l'overview utilise également compare-and-set : une évaluation
qui perd contre une version issue d'un SourceOrder supérieur devient historique
et ne peut pas faire régresser la projection.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`,
`UnsupportedPolicyVersion`, `SourceContractMismatch`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`EvaluateRecommendationsRequestId` et les RequestId dérivés sont obligatoires.
Un rejeu identique reprend ou retourne le rapport initial. Une réutilisation
avec une autre source, politique ou décision échoue avec `Conflict`.
