---
id: ANL-SCOPE
title: Analytics Scope
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - metric-catalog.md
  - integrations.md
  - future.md
---

# Périmètre

## Inclus dans Analytics 1.0

### Acquisition des faits

- consommation idempotente des événements CRM Opportunity ;
- consommation idempotente des événements Billing Quote, Invoice, Payment et
  CreditNote ;
- lecture d'un fait analytique minimal à la révision signalée ;
- normalisation sans profil Client, adresse, note libre ou preuve publique ;
- gestion des événements en retard, dupliqués ou temporairement incomplets.

### Calcul

- métriques canoniques décrites dans [`metric-catalog.md`](metric-catalog.md) ;
- séries temporelles par période civile ou glissante supportée ;
- mesures courantes à un instant `AsOf` ;
- dimensions `CurrencyCode` et, uniquement lorsque prévu, `ClientId` ;
- recalcul après correction, reversal ou rebuild ;
- comparaison avec une période précédente de même définition.

### Restitution

- valeur, unité, définition et formule lisible ;
- période, fuseau, dimensions et devise ;
- taille d'échantillon, couverture et état `Available | NoData | Unavailable` ;
- fraîcheur `Current | Lagging | Rebuilding | Unavailable` ;
- snapshots cohérents et immuables pour Business Health.

## Hors périmètre

| Responsabilité | Propriétaire ou horizon |
|---|---|
| faits Client, Opportunity et Pipeline | `CRM` |
| documents, soldes et règlements | `Billing` |
| score global, facteurs, risques et tendances interprétées | `Business Health` |
| recommandations et priorités d'action | `Advisor` |
| notification d'un changement | `Notifications` |
| prédiction de signature ou de revenu | futur modèle explicable |
| trésorerie réelle et solde bancaire | future intégration bancaire |
| revenu comptable, marge et fiscalité | future intégration comptable |
| métriques produit, NPS et télémétrie technique | Product Analytics / Observability |
| projets, capacité et calendrier | domaines futurs |

## Limites 1.0

- aucune conversion de devise ;
- aucun segment arbitraire créé par l'utilisateur ;
- aucune formule personnalisée ;
- aucune donnée personnelle dans les dimensions ;
- aucun export analytique en masse ;
- aucune prédiction ou benchmark sectoriel ;
- une correction peut réviser une série courante historique, jamais un snapshot
  déjà publié.
