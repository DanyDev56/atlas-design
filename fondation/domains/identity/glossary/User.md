---
id: IDN-GLS-USER
title: User
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
  - Membership.md
  - Role.md
  - Permission.md
  - Invitation.md
  - Session.md
---

# User

> Le `User` est l'identité de référence d'une personne dans Atlas.

---

# Objectif

Définir la signification officielle du concept `User` dans le langage ubiquitaire d'Atlas.

Cette définition est utilisée de manière uniforme dans la documentation, le code, les commandes, les événements, les API et les échanges fonctionnels.

---

# Définition

Le `User` représente une personne pouvant accéder à Atlas.

Son existence est indépendante de tout `Workspace`. Un `User` peut exister avant de rejoindre un espace de travail, ne jamais en rejoindre ou appartenir simultanément à plusieurs `Workspace`.

L'appartenance à un `Workspace` n'est jamais portée par le `User`. Elle est toujours représentée par un `Membership`.

Le `User` constitue uniquement l'identité de la personne dans Atlas.

Identity 1.0 représente uniquement un `HumanUser`. Son cycle de vie est :

```text
PendingVerification -> Active <-> Disabled -> Removed
Active ------------------------------------> Removed
```

`Removed` est terminal. Un verrouillage temporaire d'authentification est porté
par `AuthenticationLockStatus`, jamais par `UserStatus`.

---

# Responsabilités

Le `User` est responsable de :

- son identité ;
- son authentification ;
- son état.

Le `User` n'est jamais responsable de :

- son appartenance à un `Workspace` ;
- son `Role` ;
- ses `Permission` ;
- l'administration d'un `Workspace`.

Ces responsabilités appartiennent à d'autres concepts du domaine, principalement au `Membership`.

---

# Concepts associés

| Concept | Description |
|----------|-------------|
| `Membership` | Représente l'appartenance d'un `User` à un `Workspace`. |
| `Workspace` | Représente l'espace de travail auquel un `User` peut appartenir via un `Membership`. |
| `Role` | Définit les autorisations attribuées à un `Membership`. |
| `Permission` | Décrit une autorisation accordée par un `Role`. |
| `Invitation` | Permet à un futur ou à un `User` existant de rejoindre un `Workspace`. |
| `Session` | Représente une authentification active d'un `User`. |

---

# Confusions fréquentes

Le `User` n'est pas :

- un `Membership` ;
- un `Workspace` ;
- un `Role` ;
- un ensemble de `Permission`.

Le `User` représente uniquement une identité.

Les notions d'appartenance, de collaboration et d'autorisations sont portées par d'autres concepts du domaine.

---

# À retenir

Le `User` représente une identité, jamais une appartenance à un `Workspace`.
