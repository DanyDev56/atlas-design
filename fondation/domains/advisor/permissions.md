---
id: ADV-PERMISSIONS
title: Advisor Permissions
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - scope.md
  - invariants.md
  - api.md
  - commands/README.md
  - processors/README.md
  - ../identity/permissions.md
---

# Permissions

Advisor définit ses capacités ; Identity les enregistre, les affecte aux rôles
et résout leur efficacité dans un Workspace.

## Capacités attribuables à un rôle

| Clé | Sensibilité | Intention |
|---|---|---|
| `advisor.recommendations.read` | Standard | Consulter priorité, recommandations actives, historique et explications. |
| `advisor.recommendations.complete` | Standard | Confirmer l'accomplissement de l'action principale. |
| `advisor.recommendations.dismiss` | Standard | Rejeter explicitement une Recommendation avec un motif structuré. |

Complete et dismiss impliquent read. Le rôle owner actif conserve ces capacités
selon la politique produit du Workspace.

Une capacité Advisor ne donne jamais la capacité requise par la
RecommendationAction. CRM, Billing ou Business Health autorise séparément
l'acteur au moment de l'ouverture et de toute commande.

## Capacités SystemActorOnly

| Clé | Usage borné |
|---|---|
| `advisor.recommendations.evaluate` | Consommer BusinessHealthAssessed et appliquer RecommendationPolicy. |
| `advisor.recommendations.expire` | Matérialiser une fin de validité prouvée par l'horloge. |
| `advisor.overview.rebuild` | Reconstruire et publier l'AdvisorOverview après une cause authentique. |
| `advisor.recommendations.consume` | Lire une Recommendation exacte depuis Notifications. |

Ces capacités ne peuvent pas être accordées à un rôle humain. Elles exigent une
identité de workload, une portée Workspace, une causalité et un audit.

## Refus par défaut

- un Workspace différent est toujours refusé ;
- un Workspace restreint bloque les intentions humaines ordinaires ;
- une capacité système ne peut pas être substituée par une capacité humaine ;
- complete ou dismiss ne permet aucune modification de preuve, rang ou action ;
- Advisor ne relaie jamais l'autorité de l'acteur au domaine cible.
