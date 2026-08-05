---
id: LANG-002
title: Terminology Decisions
status: Stable
owner: Product
version: 1.2
last_updated: 2026-08-05
---

# Choix de terminologie

Ce document explique pourquoi certains termes ont été retenus.

---

## Workspace

Choisi car :

- indépendant de la forme juridique ;
- compatible SaaS ;
- compréhensible.

Non retenus :

- Company
- Organisation
- Tenant

---

## Opportunity

Choisi car :

- international ;
- neutre ;
- évolutif.

Non retenus :

- Lead
- Prospect
- Deal

---

## Recommendation

Choisi car il implique une analyse préalable.

Non retenus :

- Advice
- Suggestion
- Insight

La priorité d'action se nomme `RecommendationPriority`. La première de l'ordre
canonique est `PrimaryRecommendation`.

`Completed` signifie que l'utilisateur confirme avoir accompli l'action.
`Executed` n'est pas retenu, car Advisor n'exécute aucune commande CRM ou
Billing et la completion ne prouve pas un résultat.

---

## Business Health

Choisi car il représente un concept global.

Non retenus :

- Score
- Health Score

Le bounded context produit une `BusinessHealthAssessment`. Son score global se
nomme `OverallScore` pour le distinguer des ComponentScore et FactorScore.

`PrimaryAttention` a été retenu pour la zone au déficit dominant. Le mot
Priority est réservé à Advisor, qui décide de l'ordre des Recommendation.
