---
id: IDN-GLS-INVITATION
title: Invitation
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-30

references:
  - ../README.md
  - ../mission.md
  - ../scope.md
  - ../model.md
  - ../entities.md
  - User.md
  - Membership.md
  - Workspace.md
---

# Invitation

> L'`Invitation` est une proposition de rejoindre un `Workspace`.

---

# Objectif

Définir la signification officielle du concept `Invitation` dans le langage ubiquitaire d'Atlas.

Cette définition est utilisée de manière uniforme dans la documentation, le code, les commandes, les événements, les API et les échanges fonctionnels.

---

# Définition

L'`Invitation` représente une demande adressée à une personne afin qu'elle rejoigne un `Workspace`.

Une `Invitation` peut être destinée à un `User` existant ou à une personne qui créera son compte lors de son acceptation.

Lorsqu'une `Invitation` est acceptée, elle donne naissance à un `Membership`.

---

# Responsabilités

L'`Invitation` est responsable de :

- proposer l'accès à un `Workspace` ;
- définir le `Role` qui sera attribué lors de l'adhésion ;
- représenter l'état d'une invitation.

L'`Invitation` n'est jamais responsable de :

- créer un `User` ;
- authentifier une personne ;
- représenter l'appartenance à un `Workspace`.

Ces responsabilités appartiennent respectivement au `User`, à la `Session` et au `Membership`.

---

# Concepts associés

| Concept | Description |
|----------|-------------|
| `Workspace` | Est le destinataire de l'adhésion proposée. |
| `Membership` | Est créé lorsque l'`Invitation` est acceptée. |
| `User` | Devient membre du `Workspace` après acceptation. |
| `Role` | Est attribué au futur `Membership`. |

---

# Confusions fréquentes

L'`Invitation` n'est pas :

- un `Membership` ;
- un `User` ;
- une authentification.

L'`Invitation` représente uniquement une proposition de rejoindre un `Workspace`.

---

# À retenir

Une `Invitation` ne donne aucun accès tant qu'elle n'a pas été acceptée.