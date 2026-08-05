---
id: ANL-MISSION
title: Analytics Mission
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - scope.md
  - metric-catalog.md
  - ../../vision/principles.md
  - ../../vision/anti-goals.md
  - ../../product/jobs-to-be-done/manage-business.md
---

# Mission

## Raison d'être

Un indépendant ne doit pas interpréter seul des chiffres ambigus ni se demander
si un tableau est à jour. Atlas doit pouvoir expliquer chaque mesure avant de
l'utiliser pour évaluer une situation ou recommander une action.

> Analytics fournit une base quantitative fiable et explicable aux lectures
> utilisateur, à Business Health et, indirectement, à Advisor.

## Promesse

Analytics permet :

- de mesurer le pipeline, les Quotes, la facturation et les encaissements ;
- de suivre les créances, échéances et retards ;
- de comparer des périodes cohérentes ;
- d'identifier la concentration des encaissements par Client ;
- de connaître l'échantillon, la couverture et le retard de traitement ;
- de reconstruire les projections sans changer la signification des métriques.

## Contribution à Atlas

Analytics sépare trois responsabilités :

```text
faits observés      -> CRM et Billing
mesures calculées   -> Analytics
interprétation      -> Business Health et Advisor
```

Cette séparation rend les recommandations auditables et empêche un tableau de
bord d'inventer silencieusement sa propre vérité métier.

## Critère de réussite

Pour toute valeur affichée, Atlas peut répondre :

- quelle définition et quelle version ont été appliquées ;
- quels faits sources et quelle période ont contribué ;
- pourquoi la valeur a changé ;
- si des données manquent ou sont en retard ;
- si la valeur est un stock courant, un flux de période ou un ratio.
