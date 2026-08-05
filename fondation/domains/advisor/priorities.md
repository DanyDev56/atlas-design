---
id: ADV-PRIORITIES
title: Recommendation Priorities
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - recommendation-policy.md
  - recommendation-score.md
  - philosophy.md
---

# Priorités

## Critical

Action dont la combinaison impact, urgence, confiance et effort atteint le
seuil le plus élevé. L'interface la met immédiatement en évidence, sans
déclencher l'action.

## High

Action importante à traiter rapidement, généralement aujourd'hui ou dans une
fenêtre courte.

## Medium

Action utile à planifier pendant la semaine. Elle ne doit pas être formulée
comme une urgence.

## Low

Action de faible rang relatif. Elle peut figurer dans l'historique d'évaluation,
mais n'est publiée que si elle reste dans les trois premiers candidats.

## PrimaryRecommendation

La première Recommendation de l'ordre canonique constitue la priorité
principale. Ce rôle est une projection remplaçable, pas un état de l'agrégat.

`PrimaryRecommendation` ne doit pas être confondue avec `PrimaryAttention` :
Business Health identifie le facteur fragile, Advisor choisit l'action.
