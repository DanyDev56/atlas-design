---
id: ADV-EXAMPLES
title: Advisor Examples
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - recommendation-policy.md
  - actions.md
  - recommendation-score.md
---

# Exemples

## Créances en retard

Source : Business Health publie `OverdueExposureRisk = High`, avec une preuve
montrant que les montants échus représentent 38 % des créances restantes.

Recommendation : « Examiner et relancer les factures en retard aujourd'hui. »

Action : Billing / `OverdueInvoices`. Advisor ouvre la vue filtrée ; l'utilisateur
choisit les Invoices et Billing autorise chaque relance.

Pourquoi : règle `CollectOverdueInvoices`, impact Major, urgence Today,
confiance issue de la fiabilité de l'évaluation.

## Concentration Client

Source : `ClientConcentrationRisk = High`, car 76 % des encaissements observés
sur la période proviennent du premier Client.

Recommendation : « Créer une nouvelle opportunité pour diversifier le
portefeuille. »

Action : CRM / `NewOpportunity`. Aucun nom de Client ni chiffre d'affaires
comptable n'est inventé.

## Pipeline commercial

Source : `CommercialMomentumRisk = Medium`, avec un pipeline en baisse selon la
comparaison Analytics conservée dans Business Health.

Recommendation : « Ajouter une opportunité qualifiée au pipeline cette
semaine. »

Action : CRM / `NewOpportunity`. Advisor ne prédit ni signature ni revenu.

## Aucun candidat

Une BusinessHealthAssessment Strong, sans HealthRisk et avec des données
fiables, peut produire zéro Recommendation. Advisor ne crée pas une alerte pour
remplir l'interface.

## Cas futurs, non supportés en 1.0

- augmenter un tarif à partir d'un taux d'acceptation élevé ;
- relancer une Quote précise à partir de ses consultations ;
- annoncer un gain monétaire attendu ;
- prédire le chiffre d'affaires ou la réponse d'un Client.

Ces cas exigent des contrats, populations et modèles absents de la politique
1.0.
