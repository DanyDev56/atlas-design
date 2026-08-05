---
id: BHL-PERMISSIONS
title: Business Health Permissions
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

Business Health définit ses capacités ; Identity les enregistre, les affecte aux
rôles et résout leur efficacité dans un Workspace.

## Capacité attribuable à un rôle

| Clé | Sensibilité | Intention |
|---|---|---|
| `business-health.assessments.read` | Standard | Consulter l'évaluation courante, l'historique, les facteurs, risques et explications. |

Cette capacité ne donne aucun accès direct aux snapshots Analytics, faits CRM,
documents Billing ou preuves privées. Le rôle owner actif la conserve selon la
politique produit du Workspace.

## Capacités SystemActorOnly

| Clé | Usage borné |
|---|---|
| `business-health.assessments.evaluate` | Consommer un signal Analytics et créer l'évaluation exacte. |
| `business-health.assessments.consume` | Lire une évaluation exacte depuis Advisor. |

Ces capacités ne peuvent pas être accordées à un rôle humain. Elles exigent une
identité de workload, une portée Workspace, une causalité et un audit.

## Refus par défaut

- un Workspace différent est toujours refusé ;
- un Workspace restreint bloque les lectures humaines ordinaires ;
- une capacité système ne peut pas être substituée par la capacité de lecture ;
- la capacité de lecture n'autorise aucun recalcul ni modification de politique ;
- une preuve source inaccessible n'est pas révélée par l'explication.
