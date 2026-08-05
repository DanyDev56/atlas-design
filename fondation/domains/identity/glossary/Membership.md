---
id: IDN-GLS-MEMBERSHIP
title: Membership
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - ../README.md
  - ../mission.md
  - ../scope.md
  - ../model.md
  - ../entities.md
  - User.md
  - ../../../language/glossary.md
  - Role.md
  - Permission.md
---

# Membership

> Le `Membership` représente l'appartenance d'un `User` à un `Workspace`.

---

# Objectif

Définir la signification officielle du concept `Membership` dans le langage ubiquitaire d'Atlas.

Cette définition est utilisée de manière uniforme dans la documentation, le code, les commandes, les événements, les API et les échanges fonctionnels.

---

# Définition

Le `Membership` matérialise la relation entre un `User` et un `Workspace`.

Il représente la présence d'un `User` au sein d'un espace de travail et porte toutes les informations liées à cette appartenance.

Un `User` possède un `Membership` différent pour chaque `Workspace` auquel il appartient.

Un `Membership` actif porte exactement un `Role` courant dans le même
`Workspace`. Ses statuts 1.0 sont `Active`, `Suspended` et `Removed`. Une sortie
de `Removed` exige `RestoreMembership` ; elle n'est jamais implicite.

Le `Membership` constitue le point central de la collaboration entre les utilisateurs d'un même `Workspace`.

---

# Responsabilités

Le `Membership` est responsable de :

- l'appartenance d'un `User` à un `Workspace` ;
- son `Role` ;
- son état.

Le `Membership` n'est jamais responsable de :

- l'identité du `User` ;
- des informations du `Workspace` ;
- des `Permission` elles-mêmes.

Les `Permission` sont définies par le `Role` attribué au `Membership`.

---

# Concepts associés

| Concept | Description |
|----------|-------------|
| `User` | Possède un ou plusieurs `Membership`. |
| `Workspace` | Contient un ou plusieurs `Membership`. |
| `Role` | Définit les autorisations attribuées au `Membership`. |
| `Permission` | Est accordée au `Membership` par l'intermédiaire de son `Role`. |
| `Invitation` | Donne naissance à un `Membership` lorsqu'elle est acceptée. |

---

# Confusions fréquentes

Le `Membership` n'est pas :

- un `User` ;
- un `Workspace` ;
- un `Role`.

Le `Membership` représente uniquement l'appartenance d'un `User` à un `Workspace`.

---

# À retenir

Le `Membership` représente une appartenance, jamais une personne.
