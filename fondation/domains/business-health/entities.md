---
id: BHL-ENTITIES
title: Business Health Entities
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

## BusinessHealthAssessment

Évaluation immuable d'un snapshot par une politique déterminée.

| Attribut | Rôle |
|---|---|
| `BusinessHealthAssessmentId`, `WorkspaceId` | identité et isolation |
| `AnalyticsSnapshotReference` | source exacte, profil, AsOf et publication |
| `HealthPolicyVersion` | règles effectivement appliquées |
| `AssessmentCurrency?` | unique devise monétaire interprétée |
| `AssessmentStatus` | `Available` ou `InsufficientData` |
| `AssessmentReliability` | `Reliable`, `Limited` ou `Insufficient` |
| `OverallScore?`, `HealthBand?` | synthèse bornée |
| `HealthTrend` | évolution face à la baseline compatible |
| `BaselineAssessmentId?` | comparaison exacte éventuelle |
| `PrimaryAttention?` | facteur au déficit dominant |
| `InsufficiencyReasons[]` | causes structurées sans valeur inventée |
| `AssessedAt` | instant du calcul durable |

## HealthFactor

Composant nommé de l'évaluation.

| Attribut | Rôle |
|---|---|
| `FactorKey` | identité stable du facteur |
| `OriginalWeight` | poids défini par la politique |
| `FactorState` | `Available` ou `InsufficientData` |
| `FactorScore?` | entier entre 0 et 100 |
| `CoverageRatio` | poids de composants réellement utilisables |
| `EffectiveWeight?` | contribution renormalisée au score global |
| `HealthTrend` | évolution face au même facteur antérieur |
| `Components[]` | résultats de règles constitutives |
| `Explanation` | synthèse déterministe et limites |

## AssessmentEvidence

Copie minimale et immuable d'une preuve Analytics utilisée ou rejetée :

- `EvidenceReference` vers le SnapshotMetric exact ;
- valeur, unité, période, dimensions et devise publiées ;
- comparaison de période éventuelle ;
- fraîcheur, complétude et taille d'échantillon ;
- rôle dans le calcul et cause d'indisponibilité éventuelle.

Elle ne contient ni profil Client, ni document Billing, ni fait source privé.

## HealthRisk

Condition interprétée directement par une règle de HealthPolicy.

| Attribut | Rôle |
|---|---|
| `RiskKey` | type stable du risque |
| `RiskSeverity` | `Low`, `Medium`, `High` ou `Critical` |
| `FactorKey` | facteur concerné |
| `EvidenceReferences[]` | preuves exactes du déclenchement |
| `PolicyRuleReference` | règle et seuil appliqués |
| `Explanation` | raison déterministe et limites |

Un HealthRisk n'est ni une probabilité, ni une prédiction, ni une action.

## CurrentBusinessHealth

Read model remplaçable contenant le pointeur vers l'évaluation courante d'un
couple `(WorkspaceId, HealthPolicyVersion)`. Il peut évoluer ; l'évaluation
pointée reste immuable. La vue utilisateur emploie la version de politique
active déployée par Atlas.
