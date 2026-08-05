---
id: WSP-ENTITIES
title: Workspace Entities
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - value-objects.md
  - invariants.md
---

# Entités

## Workspace

`Workspace` est l'unique entité du bounded context 1.0.

Il représente une activité professionnelle utilisant Atlas et conserve son
identité pendant tout son cycle de vie.

### Attributs canoniques

| Attribut | Rôle |
|---|---|
| `WorkspaceId` | identité métier stable |
| `Status` | `Provisioning`, `Active`, `Restricted` ou `Closed` |
| `Profile` | identité commerciale courante |
| `BillingIdentity` | identité déclarée de l'émetteur |
| `Preferences` | locale, fuseau, devise et pays par défaut |
| `RestrictionContext?` | origine et raison d'une restriction |
| `ClosureContext?` | origine et raison de la fermeture terminale |
| `GovernanceVersion` | version du contexte d'accès |
| `Revision` | version de concurrence de l'agrégat |
| `CreatedAt` | instant de création |
| `ActivatedAt?` | première activation |
| `ClosedAt?` | instant de fermeture terminale |

### Identité durable

Le `WorkspaceId` ne change jamais après création. Un renommage, un changement
d'adresse ou une nouvelle identité de facturation ne crée pas un nouveau
Workspace.

Une activité juridiquement ou opérationnellement distincte peut justifier un
nouveau Workspace ; cette décision appartient au workflow produit, pas à une
mutation arbitraire de l'identifiant existant.

### Comportements

L'entité protège notamment les intentions suivantes :

- activer le Workspace après une preuve de readiness ;
- remplacer son profil ou ses préférences ;
- remplacer son identité de facturation ;
- restreindre ou restaurer l'accès ;
- fermer définitivement le Workspace.

### Ce que Workspace ne contient jamais

- un `User` ou un `Membership` ;
- une liste transactionnelle de rôles ;
- un client ou une opportunité ;
- un document financier ;
- une recommandation ;
- un abonnement SaaS.

Seuls des identifiants, versions ou preuves issus de contrats publics peuvent
être utilisés pendant une commande.
