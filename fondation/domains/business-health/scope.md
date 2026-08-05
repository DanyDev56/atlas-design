---
id: BHL-SCOPE
title: Business Health Scope
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - health-policy.md
  - integrations.md
  - future.md
---

# Périmètre

## Inclus dans Business Health 1.0

### Évaluation

- consommation idempotente de `AnalyticsSnapshotPublished` ;
- lecture du snapshot exact `BusinessHealthBaselineV1` ;
- application déterministe d'une `HealthPolicyVersion` ;
- calcul d'un score global et de quatre facteurs ;
- attribution d'une bande de santé ;
- comparaison à l'évaluation antérieure compatible ;
- production d'une évaluation `Available` ou `InsufficientData`.

### Explication

- contribution et couverture de chaque facteur ;
- composants disponibles et indisponibles ;
- références exactes aux SnapshotMetric utilisées ;
- risques observés et seuils déclenchés ;
- `PrimaryAttention` déterminée par la contribution au déficit ;
- fiabilité `Reliable | Limited | Insufficient`.

### Restitution

- lecture de l'évaluation courante ou d'une évaluation exacte ;
- historique borné des évaluations immuables ;
- lecture machine exacte par Advisor ;
- publication de `BusinessHealthAssessed` après commit durable.

## Hors périmètre

| Responsabilité | Propriétaire ou horizon |
|---|---|
| faits Client, Opportunity, Quote, Invoice et Payment | domaines sources |
| valeurs, périodes et comparaisons de métriques | `Analytics` |
| Recommendation, action et priorité d'exécution | `Advisor` |
| diffusion proactive du résultat | `Notifications` |
| benchmark par secteur, taille ou géographie | futur avec population fiable |
| score personnalisé ou poids configurables | futur après validation produit |
| prédiction, probabilité ou détection ML | futur modèle explicable |
| trésorerie, bénéfice, marge et solvabilité | sources bancaires/comptables futures |
| diagnostic juridique, fiscal ou financier | explicitement exclu |

## Limites 1.0

- une seule devise monétaire non nulle par évaluation ;
- quatre facteurs et treize métriques du profil Analytics de référence ;
- seuils globaux identiques pour tous les Workspaces ;
- aucune saisonnalité, comparaison sectorielle ou correction régionale ;
- aucune commande humaine de recalcul ou de modification du score ;
- aucune conclusion positive à partir d'une preuve absente ;
- une évaluation historique n'est jamais recalculée en place.
