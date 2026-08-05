---
id: ANL-ENTITIES
title: Analytics Entities
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

## AnalyticsFact

Observation normalisée et immuable d'une révision source.

| Attribut | Rôle |
|---|---|
| `AnalyticsFactId`, `WorkspaceId` | identité et isolation |
| `SourceEventId` | déduplication et causalité |
| `SourceDomain` | `CRM` ou `Billing` en 1.0 |
| `SourceAggregateReference` | type, identifiant et version |
| `FactKind` | Opportunity, Quote, Invoice, Payment ou CreditNote |
| `EffectiveAt` | instant métier du fait |
| `RecordedAt` | instant d'ingestion |
| `Payload` | valeur analytique minimale et typée |
| `PayloadHash` | preuve de stabilité |

Un AnalyticsFact ne contient ni nom, ni adresse, ni e-mail, ni texte libre.

## MetricDefinition

Définition de référence déployée par Atlas.

| Attribut | Rôle |
|---|---|
| `MetricKey`, `DefinitionVersion` | identité sémantique |
| `Nature` | `Stock`, `Flow`, `Ratio`, `AverageDuration` |
| `Unit` | Money, Count, Ratio ou Duration |
| `Formula` | calcul déterministe |
| `SourceFactKinds` | faits autorisés |
| `SupportedWindows` | périodes valides |
| `AllowedDimensions` | dimensions bornées |
| `CompletenessPolicy` | seuils et couverture |
| `Status` | `Active` ou `Deprecated` |

Une définition publiée n'est jamais éditée avec un nouveau sens.

## MetricSeries et MetricObservation

Une `MetricSeries` regroupe les observations d'une même clé, définition,
génération, dimension et devise.

Une `MetricObservation` contient :

- la période ou `AsOf` ;
- la valeur exacte et son unité ;
- numérateur et dénominateur éventuels ;
- taille d'échantillon et couverture ;
- source watermarks et instant de calcul ;
- état de disponibilité et explication des exclusions.

## ProjectionGeneration

| Attribut | Rôle |
|---|---|
| `GenerationId`, `WorkspaceId` | identité |
| `Scope` | métriques et périodes reconstruites |
| `DefinitionSetVersion` | catalogue appliqué |
| `ReportingCalendarVersion` | calendrier appliqué |
| `Status` | `Building`, `Active`, `Failed`, `Superseded` |
| `Checkpoints` | progression par source |
| `ValidationReport` | contrôles avant activation |

## AnalyticsSnapshot

Ensemble immuable de `SnapshotMetric` partageant :

- un `WorkspaceId` ;
- un `AsOf` ;
- une génération active ;
- un calendrier de reporting ;
- un jeu de watermarks cohérent ;
- une politique de complétude satisfaite.

Chaque SnapshotMetric copie la valeur, la période, la devise, la complétude, la
fraîcheur et la définition de l'observation publiée. Il ne pointe pas vers une
cellule mutable de MetricSeries. Le snapshot conserve des références de
causalité vers les faits et définitions, jamais leur contenu personnel ou
documentaire.
