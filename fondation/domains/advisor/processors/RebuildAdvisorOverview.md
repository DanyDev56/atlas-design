---
id: ADV-PROC-REBUILD-ADVISOR-OVERVIEW
title: RebuildAdvisorOverview
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - ../model.md
  - ../aggregates.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# RebuildAdvisorOverview

## Objectif

Faire converger l'unique `AdvisorOverview` d'un Workspace après une évaluation
appliquée ou toute mutation terminale d'une Recommendation.

## Projection concernée

Nouvelle `AdvisorOverviewRevision`, pointeur `AdvisorOverview` courant et
`AdvisorOverviewVersion` monotone. Les agrégats `RecommendationEvaluation` et
`Recommendation` sont relus sans être modifiés.

## Acteur et autorité

Workload Advisor autorisé par `advisor.overview.rebuild`.

## Données d'entrée

```text
WorkspaceId
OverviewCause
  CauseKind: EvaluationApplied | SourceInvalidated
             | RecommendationCompleted | RecommendationDismissed
             | RecommendationExpired | PolicyReplaced
  CauseReference
  CauseEventId?
  RecommendationEvaluationId?
ExpectedAdvisorOverviewVersion
RebuildAdvisorOverviewRequestId
CorrelationId
WorkloadContext
```

## Préconditions et traitement

- cause authentique, supportée, non déjà appliquée et du même Workspace ;
- pour une évaluation, toutes ses CandidateDecision sont durablement appliquées ;
- pour une mutation terminale, événement et AggregateVersion exacts relus ;
- politique active et dernier `SourceOrder` non régressifs ;
- sélection des Recommendation `Generated`, non expirées, appartenant au top
  trois de la dernière évaluation appliquée ;
- tri par l'ordre canonique de la RecommendationPolicy ;
- la première survivante devient Primary et les deux suivantes alternatives ;
- une source courante `InsufficientAssessment` ou `StaleAssessment` produit un
  overview vide après expiration contrôlée des Recommendation actives ;
- compare-and-set sur `ExpectedAdvisorOverviewVersion`, incrément exact de un,
  création de la révision immuable, enregistrement de la CauseKey, bascule du
  pointeur courant puis publication atomique.

Une completion, un dismissal ou une expiration de la priorité promeut donc
immédiatement l'alternative Generated suivante. Le processeur ne publie jamais
le quatrième candidat et ne régénère aucune Recommendation terminale.

Une source historique, un contrat incompatible ou une politique non supportée
ne constitue pas une cause applicable : elle conserve l'overview et sa version.

## Invariants concernés

`ADV-INV-001`, `ADV-INV-002`, `ADV-INV-013`–`ADV-INV-015`,
`ADV-INV-032`–`ADV-INV-034`, `ADV-INV-040`, `ADV-INV-044`–`ADV-INV-055`.

## Événement produit

- `AdvisorOverviewChanged`.

## Concurrence

La projection est sérialisée par Workspace. Après conflit, le processeur relit
Recommendations, dernière évaluation et version courante, puis recalcule depuis
les sources autoritaires. Une CauseKey déjà appliquée retourne sa version
initiale ; aucun événement livré tardivement ne restaure une entrée terminale.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`,
`SourceContractMismatch`, `Conflict`, `TemporarilyUnavailable`.

## Idempotence

`RebuildAdvisorOverviewRequestId` est dérivé de la CauseKey et obligatoire. Un
rejeu identique retourne l'`AdvisorOverviewVersion` produite ; une cause ou une
empreinte différente sous la même clé échoue avec `Conflict`.
