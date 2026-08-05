---
id: IDN-GLS-PERMISSION
title: Permission
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
  - Role.md
  - Membership.md
---

# Permission

> La `Permission` est une autorisation élémentaire accordée à un `Role`.

---

# Objectif

Définir la signification officielle du concept `Permission` dans le langage ubiquitaire d'Atlas.

Cette définition est utilisée de manière uniforme dans la documentation, le code, les commandes, les événements, les API et les échanges fonctionnels.

---

# Définition

La `Permission` représente une capacité précise qui peut devenir effective pour
un utilisateur par l'intermédiaire de son `Role` de workspace.

Chaque `Permission` décrit une seule autorisation fonctionnelle.

Les `Permission` sont regroupées dans un `Role`, qui est ensuite attribué à un `Membership`.

`PermissionId` porte l'identité stable et `PermissionKey` le contrat fonctionnel
immuable. Chaque capacité déclare son `OwningDomain` ; Identity opère le registre
global et les affectations aux rôles.

---

# Responsabilités

La `Permission` est responsable de :

- représenter une autorisation métier unique ;
- servir de brique élémentaire pour construire un `Role`.

La `Permission` n'est jamais responsable de :

- déterminer qui possède cette autorisation ;
- être attribuée directement à un `User` ;
- être attribuée directement à un `Membership`.

Ces responsabilités appartiennent au `Role`.

---

# Concepts associés

| Concept | Description |
|----------|-------------|
| `Role` | Regroupe plusieurs `Permission`. |
| `Membership` | Bénéficie des `Permission` de son `Role`. |

---

# Confusions fréquentes

La `Permission` n'est pas :

- un rôle ;
- un utilisateur ;
- une appartenance.

La `Permission` représente uniquement une autorisation élémentaire.

---

# À retenir

Les `Permission` ne sont jamais attribuées directement à un `User` ou à un `Membership`. Elles sont toujours accordées par l'intermédiaire d'un `Role`.
