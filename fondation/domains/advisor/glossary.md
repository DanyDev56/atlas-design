---
id: ADV-GLOSSARY
title: Advisor Glossary
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - README.md
  - recommendation-policy.md
  - model.md
  - value-objects.md
  - ../../language/glossary.md
---

# Glossaire Advisor

| Terme | Définition |
|---|---|
| `Recommendation` | proposition d'action durable, priorisée, expliquée et laissée au contrôle utilisateur |
| `RecommendationPolicy` | règles, rangs, actions, templates et limites globales versionnées |
| `ActiveRecommendationPolicyVersion` | politique utilisée pour les évaluations et lectures courantes |
| `RecommendationEvaluation` | rapport durable des cinq règles appliquées à une source exacte |
| `CandidateDecision` | résultat d'une règle avant ou sans création de Recommendation |
| `RecommendationKey` | identité sémantique stable d'un type de proposition |
| `RuleKey` | identité de la règle ayant produit ou supprimé un candidat |
| `RecommendationStatus` | Generated, Completed, Dismissed ou Expired |
| `RecommendationPriority` | Critical, High, Medium ou Low dérivé du rang |
| `RecommendationRankScore` | score interne déterministe d'ordre entre 0 et 100 |
| `PrimaryRecommendation` | première Recommendation de l'AdvisorOverview courant |
| `RecommendationAction` | parcours allowlisté constituant l'unique action principale |
| `ExpectedImpact` | objectif et niveau qualitatifs sans gain garanti |
| `RecommendationConfidence` | qualité de la justification, sans probabilité implicite |
| `EstimatedEffort` | effort Small, Medium, Large ou Unknown utilisé dans le rang |
| `RecommendationEvidenceRevision` | preuve et explication immuables d'une génération ou réaffirmation |
| `TriggerFingerprint` | empreinte des éléments matériels déterminant l'identité courante du déclencheur |
| `DeduplicationKey` | clé empêchant plusieurs Recommendations actives du même type |
| `AdvisorOverview` | projection de la priorité principale et de deux alternatives au maximum |
| `AdvisorOverviewVersion` | ordre monotone des évaluations, invalidations et mutations terminales appliquées dans un Workspace |
| `AdvisorOverviewRevision` | composition immuable relisible pour une AdvisorOverviewVersion exacte |

## Termes à éviter

- Advice, Suggestion ou Insight pour une Recommendation ;
- Executed lorsque l'utilisateur a seulement confirmé l'action ;
- Accepted pour Completed ou Dismissed ;
- Probability pour RecommendationConfidence ;
- ROI, gain ou revenu attendu sans modèle calibré ;
- AI Recommendation pour une règle déterministe 1.0 ;
- Action automatique lorsque RecommendationAction ouvre seulement un parcours ;
- Priority pour désigner PrimaryAttention Business Health.
