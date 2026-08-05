---
id: ANL-PERMISSIONS
title: Analytics Permissions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - invariants.md
  - api.md
  - processors/README.md
  - ../identity/permissions.md
---

# Permissions

Analytics définit ses capacités ; Identity les enregistre, les affecte aux rôles
et résout leur efficacité dans un Workspace.

## Capacité attribuable à un rôle

| Clé | Sensibilité | Intention |
|---|---|---|
| `analytics.metrics.read` | Standard | Consulter définitions, valeurs, séries, breakdowns et explications Analytics. |

La permission ne donne aucun accès direct aux profils CRM, documents Billing ou
faits normalisés privés. Le rôle owner actif la conserve selon la politique
produit du Workspace.

## Capacités SystemActorOnly

| Clé | Usage borné |
|---|---|
| `analytics.facts.ingest` | Lire un fait source versionné et l'enregistrer idempotemment. |
| `analytics.projections.rebuild` | Construire, valider et activer une génération de projection. |
| `analytics.snapshots.publish` | Publier un snapshot cohérent selon un profil versionné. |
| `analytics.snapshots.consume` | Lire un snapshot exact depuis Business Health. |

Ces capacités ne peuvent pas être accordées à un rôle humain. Elles exigent une
identité de workload, une portée Workspace, une causalité et un audit.

## Refus par défaut

- un Workspace différent est toujours refusé ;
- un Workspace restreint bloque les lectures humaines ordinaires ;
- une capacité système ne peut pas être substituée par `analytics.metrics.read` ;
- une dimension non prévue ne devient pas accessible par élévation de rôle.
