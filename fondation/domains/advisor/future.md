---
id: ADV-FUTURE
title: Advisor Future Extensions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - recommendation-policy.md
  - decision-record.md
---

# Extensions futures

Ces capacités ne modifient aucun contrat Advisor 1.0 :

- contextes d'action versionnés pour cibler une Invoice, Quote ou Opportunity ;
- mesure d'outcome séparant corrélation, adoption et effet causal ;
- règles projets, charge, capacité et calendrier ;
- préférences de recommandation explicites et désactivables ;
- personnalisation consentie selon l'historique utilisateur ;
- détection de fatigue et fréquence de notification ;
- actions préparées avec aperçu avant confirmation ;
- automatisations autorisées, réversibles et auditées ;
- conversation expliquant une Recommendation canonique sans la réinventer ;
- génération de candidats par modèle avec garde-fous déterministes ;
- prédictions calibrées avec confiance, population et dérive mesurées ;
- gains financiers estimés seulement avec baseline et méthode validées ;
- benchmarks sectoriels suffisamment représentatifs et anonymisés ;
- expérimentation de politique et comparaison contrôlée des résultats.

Toute extension doit définir source autoritaire, consentement, population,
biais, version, explicabilité, permissions, fréquence, stratégie de rollback et
mesure de résultat.

Un texte produit par IA ne devient jamais une Recommendation canonique sans
passer par une politique versionnée, une preuve suffisante et les mêmes limites
de priorité et de contrôle humain.
