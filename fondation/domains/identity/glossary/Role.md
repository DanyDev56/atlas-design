---
id: IDN-GLS-ROLE
title: Role
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
  - Membership.md
  - Permission.md
---

# Role

> Le `Role` est un ensemble nommé de `Permission` pouvant être attribué à un `Membership`.

---

# Objectif

Définir la signification officielle du concept `Role` dans le langage ubiquitaire d'Atlas.

Cette définition est utilisée de manière uniforme dans la documentation, le code, les commandes, les événements, les API et les échanges fonctionnels.

---

# Définition

Le `Role` représente un niveau de responsabilité au sein d'un `Workspace`.

Il regroupe un ensemble cohérent de `Permission` afin de simplifier la gestion des autorisations.

Un même `Role` peut être attribué à plusieurs `Membership`.

---

# Responsabilités

Le `Role` est responsable de :

- regrouper des `Permission` ;
- représenter un niveau d'autorisation ;
- simplifier l'administration des accès.

Le `Role` n'est jamais responsable de :

- l'identité d'un `User` ;
- l'appartenance à un `Workspace` ;
- l'évaluation des autorisations.

Ces responsabilités appartiennent respectivement au `User`, au `Membership` et au système d'autorisation.

---

# Concepts associés

| Concept | Description |
|----------|-------------|
| `Membership` | Reçoit un `Role`. |
| `Permission` | Compose un `Role`. |
| `Workspace` | Utilise les `Role` pour gérer les accès de ses membres. |

---

# Confusions fréquentes

Le `Role` n'est pas :

- un utilisateur ;
- une permission ;
- une appartenance à un `Workspace`.

Le `Role` représente uniquement un ensemble cohérent de droits.

---

# À retenir

Le `Role` définit **ce qu'un `Membership` est autorisé à faire**, grâce à un ensemble de `Permission`.