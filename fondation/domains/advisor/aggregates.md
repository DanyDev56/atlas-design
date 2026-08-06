---
id: ADV-AGGREGATES
title: Advisor Aggregates
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - model.md
  - entities.md
  - recommendation-policy.md
  - recommendation-lifecycle.md
  - invariants.md
  - workflows.md
---

# Agrégats

## Vue d'ensemble

| Agrégat | Racine | Cohérence garantie |
|---|---|---|
| Recommendation Evaluation | `RecommendationEvaluation` | source, politique, cinq décisions et progression idempotente |
| Recommendation | `Recommendation` | action, preuve, rang et transition terminale cohérents |

`RecommendationPolicy` est un catalogue global versionné.
`RecommendationRuleState`, les `AdvisorOverviewRevision` immuables et le pointeur
`AdvisorOverview` courant sont des projections reconstructibles.

## RecommendationEvaluation

La clé naturelle est :

```text
(WorkspaceId, BusinessHealthAssessmentId, RecommendationPolicyVersion)
```

La racine fige la source et les cinq CandidateDecision. Le processeur applique
chaque décision avec un RequestId dérivé puis marque la racine `Completed`
seulement lorsque les mutations de Recommendation, la reconstruction de
l'AdvisorOverview et leurs événements sont durables.

Un traitement interrompu reste `Processing` et reprend à la première décision
non confirmée. Une source inéligible atteint `Completed` avec sa raison et cinq
décisions `RejectedSource`, sans RecommendationGenerated.

## Recommendation

La création exige une CandidateDecision publiée dans le top trois. La racine
garantit :

- une action principale allowlistée ;
- un TriggerFingerprint et une DeduplicationKey ;
- un rang calculé par la politique ;
- au moins une RecommendationEvidenceRevision ;
- un ValidUntil strictement postérieur au SourceAsOf et au moment de création.

Depuis Generated, elle accepte :

- `reaffirm` avec le même fingerprint et une source plus récente ;
- `complete` par un acteur humain autorisé ;
- `dismiss` par un acteur humain autorisé ;
- `expire` par un workload ou une nouvelle évaluation.

Completed, Dismissed et Expired n'acceptent plus aucune transition.

## Concurrence

Les commandes humaines utilisent `ExpectedRevision`. Une réaffirmation ou
expiration concurrente gagne uniquement par compare-and-set ; le perdant relit
l'état et retourne le résultat compatible ou un conflit.

Une contrainte unique empêche deux Recommendation Generated pour la même
DeduplicationKey. `RebuildAdvisorOverview` remplace atomiquement la projection et
sa version après chaque CauseKey applicable. Une évaluation Applied, une source
courante invalidante ou une mutation terminale avance la version ; une source
historique conserve celle déjà publiée.

## Nouvelle politique

Une nouvelle RecommendationPolicyVersion crée des évaluations distinctes. Son
activation exige l'évaluation de la BusinessHealthAssessment courante et
l'expiration explicite des recommandations actives de la politique remplacée.
Les politiques et historiques antérieurs restent immuables.
