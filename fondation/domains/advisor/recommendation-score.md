---
id: ADV-RECOMMENDATION-SCORE
title: Recommendation Rank Score
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - recommendation-policy.md
  - priorities.md
  - value-objects.md
  - invariants.md
---

# Recommendation Rank Score

`RecommendationRankScore` ordonne les candidats d'une même évaluation. C'est un
outil interne de tri, pas une probabilité de réussite ni une valeur métier
affichée sans explication.

## Valeurs normalisées

| ExpectedImpactLevel | Valeur |
|---|---:|
| `Major` | 100 |
| `Significant` | 65 |
| `Moderate` | 35 |
| `Minor` | 10 |

| Urgency | Valeur |
|---|---:|
| `Immediate` | 100 |
| `Today` | 75 |
| `ThisWeek` | 50 |
| `NoDeadline` | 20 |

| RecommendationConfidence | Valeur |
|---|---:|
| `High` | 100 |
| `Moderate` | 60 |
| `Low` | 20 |

| EstimatedEffort | EaseValue |
|---|---:|
| `Small` | 100 |
| `Medium` | 60 |
| `Large` | 20 |
| `Unknown` | 0 |

## Formule

```text
RecommendationRankScore = roundHalfUp(
    0.40 * ImpactValue
  + 0.30 * UrgencyValue
  + 0.20 * ConfidenceValue
  + 0.10 * EaseValue
)
```

Les calculs intermédiaires utilisent des décimaux exacts. Le résultat final est
un entier de 0 à 100.

## Priority

| RecommendationRankScore | RecommendationPriority |
|---:|---|
| 85–100 | `Critical` |
| 65–84 | `High` |
| 40–64 | `Medium` |
| 0–39 | `Low` |

La Priority exprime l'ordre conseillé de l'action, pas la gravité absolue d'une
entreprise. Le détail d'impact, urgence, confiance et effort reste visible.

## Historique utilisateur

Le comportement passé de l'utilisateur ne modifie pas le score 1.0. Une future
personnalisation exigera consentement, explication, possibilité de désactivation
et version de politique distincte.
