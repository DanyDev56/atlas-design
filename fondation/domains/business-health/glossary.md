---
id: BHL-GLOSSARY
title: Business Health Glossary
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - health-policy.md
  - model.md
  - value-objects.md
  - ../../language/glossary.md
---

# Glossaire Business Health

| Terme | Définition |
|---|---|
| `BusinessHealthAssessment` | interprétation immuable d'un AnalyticsSnapshot selon une HealthPolicyVersion |
| `HealthPolicy` | règles, poids, seuils et exigences globales versionnées d'une évaluation |
| `ActiveHealthPolicyVersion` | version de politique sélectionnée pour les lectures courantes et les nouveaux snapshots |
| `OverallScore` | synthèse entière de 0 à 100 calculée à partir des facteurs suffisamment couverts |
| `HealthBand` | lecture qualitative déterministe d'un OverallScore |
| `HealthFactor` | dimension interprétée contribuant à l'évaluation globale |
| `FactorScore` | score entier d'un facteur suffisamment couvert |
| `FactorComponent` | règle élémentaire reliant une ou plusieurs preuves à un facteur |
| `AssessmentEvidence` | copie minimale d'une SnapshotMetric utilisée ou déclarée indisponible |
| `AssessmentReliability` | niveau Reliable, Limited ou Insufficient du résultat |
| `HealthTrend` | Improving, Stable, Declining ou Unknown face à une baseline compatible |
| `PrimaryAttention` | facteur dont la contribution au déficit global est la plus élevée |
| `HealthRisk` | condition observée déclenchée par une règle et des preuves explicites |
| `RiskSeverity` | intensité d'un seuil observé, sans probabilité implicite |
| `CurrentBusinessHealth` | projection par HealthPolicyVersion vers l'évaluation la plus récente selon l'ordre canonique |

## Termes à éviter

- `HealthScore`, ambigu entre composant, facteur et score global ;
- diagnostic ou notation financière ;
- benchmark sans population et méthodologie ;
- priorité pour désigner PrimaryAttention ;
- Recommendation, Advice ou Action dans le modèle Business Health ;
- risque avéré lorsqu'une preuve est absente ;
- temps réel sans garantie de fraîcheur mesurée ;
- stable pour une tendance sans baseline compatible.
