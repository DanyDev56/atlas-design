---
id: NTF-COMMANDS
title: Notifications Commands
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - ../aggregates.md
  - ../lifecycle.md
  - ../notification-policy.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# Commands

Les commandes Notifications 1.0 expriment uniquement les préférences et l'état
de lecture personnels d'un utilisateur.

## Catalogue

- [`MarkNotificationRead`](MarkNotificationRead.md)
- [`MarkNotificationUnread`](MarkNotificationUnread.md)
- [`ChangeNotificationPreferences`](ChangeNotificationPreferences.md)

## Conventions communes

- toute commande porte WorkspaceId, ExpectedRevision et un RequestId
  spécialisé ;
- l'acteur doit être le User concerné, actif dans le même Workspace et disposer
  de la capacité exacte ;
- ExpectedRevision vaut zéro lors de la première création de préférences ;
- aucune commande ne modifie source, contenu, destinataire ou état de livraison ;
- un refus ne publie aucun événement de réussite ;
- état, Domain Event et outbox sont atomiques.

## Limite

Notifications ne possède aucune commande d'exécution de RecommendationAction.
L'action ouvre un parcours du domaine propriétaire, qui autorise lui-même toute
mutation ultérieure.
