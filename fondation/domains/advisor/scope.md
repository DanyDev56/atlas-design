---
id: ADV-SCOPE
title: Advisor Scope
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - README.md
  - mission.md
  - recommendation-policy.md
  - recommendation-lifecycle.md
  - integrations.md
  - future.md
---

# Périmètre

## Inclus dans Advisor 1.0

### Génération déterministe

- consommation idempotente de `BusinessHealthAssessed` ;
- lecture de la BusinessHealthAssessment exacte et courante ;
- application d'une `RecommendationPolicyVersion` ;
- évaluation de cinq règles explicites ;
- génération de zéro à trois Recommendation actives ;
- sélection déterministe d'une priorité principale ;
- réaffirmation ou expiration face à une nouvelle évaluation.
- convergence versionnée de l'AdvisorOverview après toute évaluation appliquée
  ou mutation terminale.

### Explication et action

- cause, objectif et observation source ;
- impact et confiance qualitatifs ;
- urgence, effort et score de rang interne ;
- action principale unique issue d'une allowlist ;
- module, RouteKey et capacités requises ;
- limites et révisions de preuve historisées.

### Cycle de vie

- consultation de la priorité, des recommandations actives et de l'historique ;
- confirmation humaine d'accomplissement ;
- rejet avec motif structuré ;
- expiration par changement de contexte ou fin de validité ;
- événement public pour chaque transition métier significative.

## Hors périmètre

| Responsabilité | Propriétaire ou horizon |
|---|---|
| score, facteurs, tendances et risques d'activité | `Business Health` |
| métriques et séries | `Analytics` |
| modification d'une Opportunity, Quote, Invoice ou Payment | domaine source |
| diffusion in-app ou e-mail | `Notifications` |
| exécution sans confirmation humaine | `Automation`, futur |
| affichage, ouverture et clic | Product Analytics |
| résultat causal et impact réellement obtenu | future mesure d'outcome |
| recommandation conversationnelle ou générative | futur explicable |
| prédiction, gain monétaire ou probabilité | futur modèle calibré |
| personnalisation fondée sur le comportement | futur avec consentement |

## Limites 1.0

- Business Health est l'unique source métier de génération ;
- cinq règles globales, identiques pour tous les Workspaces ;
- une seule action principale par Recommendation ;
- trois Recommendation `Generated` au maximum par Workspace ;
- aucune cible Client ou document individuel déduite des agrégats ;
- aucune commande source relayée ou exécutée par Advisor ;
- aucun texte libre dans les motifs de rejet ;
- aucun apprentissage automatique ou score personnalisé.
