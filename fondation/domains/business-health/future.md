---
id: BHL-FUTURE
title: Business Health Future Extensions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - health-policy.md
  - decision-record.md
---

# Extensions futures

Ces capacités ne modifient aucun contrat Business Health 1.0 :

- facteurs projets, capacité, charge et calendrier ;
- marge après disponibilité de coûts fiables ;
- trésorerie après intégration bancaire explicite ;
- saisonnalité et comparaison année sur année ;
- support multidevise avec taux, source et instant de conversion auditables ;
- politiques adaptées à un secteur ou stade avec population représentative ;
- calibrage des poids à partir de résultats observables et sans biais caché ;
- simulations distinctes de l'évaluation observée ;
- détection d'anomalies ou prédictions avec confiance mesurée ;
- préférences d'affichage sans modification de la vérité calculée ;
- backfill contrôlé d'une nouvelle politique sur des snapshots historiques ;
- mesure de la pertinence du score et de son lien avec les Recommendation.

Toute extension doit documenter source autoritaire, population, biais, devise,
période, qualité, explicabilité, version de politique et comparabilité avec les
évaluations 1.0.

Une politique personnalisable ne sera introduite que si l'utilisateur peut
comprendre son effet et si deux évaluations de politiques différentes ne sont
jamais présentées comme directement comparables.
