---
id: BHL-VALUE-OBJECTS
title: Business Health Value Objects
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - health-policy.md
  - invariants.md
  - ../analytics/value-objects.md
---

# Value Objects

## Identifiants

`BusinessHealthAssessmentId`, `HealthPolicyVersion`, `FactorKey`, `RiskKey`,
`WorkspaceId`, `AnalyticsSnapshotId` et `EvaluateBusinessHealthRequestId` sont
des types distincts.

## ActiveHealthPolicyVersion

Pointeur global vers la HealthPolicyVersion utilisée par les lectures courantes
et les nouveaux snapshots. Il ne bascule qu'après création réussie d'une
évaluation du snapshot courant avec cette version. Son changement n'altère
aucune politique ni évaluation existante.

## AnalyticsSnapshotReference

```text
AnalyticsSnapshotId
SnapshotProfileKey
SnapshotProfileVersion
AsOf
SourcePublishedAt
GenerationId
DefinitionSetVersion
ReportingCalendarVersion
```

La référence désigne un snapshot publié, immuable et appartenant au même
Workspace.

## Score

`ComponentScore`, `FactorScore` et `OverallScore` sont des entiers de 0 à 100.
Ils ne sont pas interchangeables. Les calculs intermédiaires restent exacts et
l'arrondi moitié vers le haut n'intervient qu'à la frontière définie.

## HealthBand

```text
Strong | Stable | Watch | AtRisk | Critical
```

Une bande n'existe qu'avec un OverallScore et découle exclusivement de la même
HealthPolicyVersion.

## AssessmentStatus et AssessmentReliability

```text
AssessmentStatus: Available | InsufficientData
AssessmentReliability: Reliable | Limited | Insufficient
```

`Available` exige une fiabilité Reliable ou Limited. `InsufficientData` exige
Insufficient et interdit score, bande et PrimaryAttention.

## FactorState et FactorComponent

```text
FactorState: Available | InsufficientData

FactorComponent
  ComponentKey
  OriginalWeight
  ComponentState: Available | InsufficientData
  ComponentScore?
  EvidenceReferences[]
  PolicyRuleReference
  Explanation
```

## HealthTrend

```text
Improving | Stable | Declining | Unknown
```

La tendance contient `BaselineAssessmentId?`, `CurrentScore?`, `BaselineScore?`,
`ScoreDelta?` et la règle de comparaison. `Unknown` n'est jamais rendu comme
Stable.

## PrimaryAttention

```text
FactorKey
DeficitContribution
FactorScore
OriginalFactorWeight
PolicyRuleReference
Explanation
```

PrimaryAttention décrit une zone de fragilité relative. Il ne contient ni
Action, ni due date, ni RecommendationPriority.

## RiskSeverity

```text
Low | Medium | High | Critical
```

La sévérité traduit un seuil de politique observé. Elle ne représente pas une
probabilité statistique.

## EvidenceReference

```text
AnalyticsSnapshotId
MetricKey
DefinitionVersion
ObservationPeriod
DimensionSet
CurrencyCode?
EvidenceRole
```

La valeur référencée est aussi figée dans `AssessmentEvidence` afin que
l'explication reste reproductible sans lire une projection Analytics mutable.

## AssessmentCurrency

Devise unique des preuves monétaires non nulles. Elle est absente uniquement si
aucune preuve monétaire utilisable n'existe. Business Health 1.0 ne convertit et
n'agrège jamais plusieurs devises.

## InsufficiencyReason

Enum structurée :

```text
SnapshotProfileMismatch
SnapshotUnavailable
StaleOrIncompleteSnapshot
MultipleCurrenciesUnsupported
MissingRequiredMetric
MetricUnavailable
MetricNotComparable
InsufficientSample
InsufficientFactorCoverage
InsufficientOverallCoverage
InconsistentEvidence
NoMonetaryEvidence
```

Une explication humaine accompagne la raison sans changer sa sémantique.
