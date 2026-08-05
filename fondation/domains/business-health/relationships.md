---
id: BHL-RELATIONSHIPS
title: Business Health Relationships
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - integrations.md
  - ../analytics/api.md
---

# Relations

## Cardinalités internes

```text
HealthPolicy                 1 -------- 0..* BusinessHealthAssessment
BusinessHealthAssessment     1 -------- 4    HealthFactor
HealthFactor                 1 -------- 1..* FactorComponent
BusinessHealthAssessment     1 -------- 1..* AssessmentEvidence
BusinessHealthAssessment     1 -------- 0..* HealthRisk
BusinessHealthAssessment     1 -------- 0..1 PrimaryAttention
HealthPolicy                 1 -------- 0..* CurrentBusinessHealth
CurrentBusinessHealth        1 -------- 1    BusinessHealthAssessment
```

Une évaluation `InsufficientData` conserve quatre HealthFactor, même si certains
n'ont aucun score, afin d'expliquer précisément la couverture manquante.

## Analytics

```text
AnalyticsSnapshotPublished
  -> EvaluateBusinessHealth
  -> getAnalyticsSnapshot(exact id)
  -> immutable BusinessHealthAssessment
```

Une AssessmentEvidence copie une valeur publiée ; elle ne pointe jamais vers une
MetricSeries ou MetricObservation mutable. Business Health ne redéfinit aucune
MetricDefinition.

## Évaluations antérieures

Une évaluation peut référencer au plus une baseline antérieure compatible. Cette
relation sert uniquement aux tendances. La baseline ne devient pas membre de la
nouvelle racine et reste immuable indépendamment.

## Advisor

```text
BusinessHealthAssessed
  -> Advisor reads exact BusinessHealthAssessment
  -> Recommendation
```

Advisor peut s'appuyer sur facteurs, risques, tendance et PrimaryAttention. Il
ne modifie jamais l'évaluation et Business Health ne préfigure pas l'action
qu'Advisor proposera.

## Workspace et Identity

`WorkspaceId` isole les évaluations. Identity résout les capacités humaines et
système ; Business Health ne copie ni Membership ni Role. L'état d'accès du
Workspace est vérifié par contrat public, jamais par lecture de son stockage.
