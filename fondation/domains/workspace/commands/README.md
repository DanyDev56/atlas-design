---
id: WSP-COMMANDS
title: Workspace Commands
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - ../aggregates.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# Commands

Une commande exprime une intention unique de modifier un Workspace. Elle est
validée, autorisée et commise atomiquement avec ses événements.

## Structure normative

Chaque fiche documente au minimum :

- objectif et agrégat ;
- acteur et autorité ;
- données d'entrée ;
- préconditions et traitement ;
- invariants ;
- événements ;
- erreurs ;
- concurrence et idempotence.

Une commande refusée ne publie aucun Domain Event de réussite.

---

## Catalogue

| Cycle | Commande | Intention |
|---|---|---|
| création | [`CreateWorkspace`](CreateWorkspace.md) | créer un Workspace en provisioning |
| création | [`ActivateWorkspace`](ActivateWorkspace.md) | terminer le bootstrap |
| profil | [`UpdateWorkspaceProfile`](UpdateWorkspaceProfile.md) | modifier le profil commercial |
| profil | [`UpdateWorkspaceBillingIdentity`](UpdateWorkspaceBillingIdentity.md) | remplacer l'identité de facturation |
| préférences | [`ChangeWorkspacePreferences`](ChangeWorkspacePreferences.md) | remplacer les préférences principales |
| accès | [`RestrictWorkspace`](RestrictWorkspace.md) | suspendre l'usage ordinaire |
| accès | [`RestoreWorkspaceAccess`](RestoreWorkspaceAccess.md) | lever une restriction |
| fermeture | [`CloseWorkspace`](CloseWorkspace.md) | fermer logiquement le Workspace |

---

## Conventions communes

- `ExpectedRevision` est obligatoire hors création ;
- les clés d'idempotence sont bornées par acteur, Workspace et intention ;
- une autorisation Identity porte exactement le `WorkspaceId` ciblé ;
- les preuves inter-domaines sont versionnées et expirables ;
- les données sensibles restent hors événements publics ;
- un retry ne contourne jamais une nouvelle vérification de readiness.

---

## Évolution

Toute nouvelle commande 1.x doit :

1. représenter une intention absente du catalogue ;
2. référencer des invariants existants ou les faire évoluer explicitement ;
3. définir sa permission ou son autorité ;
4. tracer tous ses Domain Events ;
5. documenter son idempotence et sa concurrence.
