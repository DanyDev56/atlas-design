---
id: LANG-002
title: Terminology Decisions
status: Stable
owner: Product
version: 1.3
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

---

## Notifications

`Notification` est retenu pour un message produit durable dérivé d'un fait déjà
décidé. Alert n'est pas retenu comme synonyme générique, car il impliquerait un
niveau d'urgence absent de nombreuses Notifications.

Trois dimensions sont nommées séparément :

- `NotificationStatus` pour la pertinence ;
- `NotificationReadState` pour la lecture personnelle ;
- `DeliveryStatus` pour le transport externe.

`Accepted` signifie que le fournisseur a pris en charge une soumission.
`Delivered` exige une preuve authentique ultérieure. Le terme `Sent` n'est pas
retenu car il ne permet pas de savoir lequel de ces faits est réellement prouvé.
