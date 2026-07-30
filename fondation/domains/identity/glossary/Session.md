---
id: IDN-GLS-SESSION
title: Session
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
---

# Session

> La `Session` représente une authentification active d'un `User`.

---

# Objectif

Définir la signification officielle du concept `Session` dans le langage ubiquitaire d'Atlas.

Cette définition est utilisée de manière uniforme dans la documentation, le code, les commandes, les événements, les API et les échanges fonctionnels.

---

# Définition

La `Session` représente une authentification valide d'un `User`.

Elle matérialise une connexion active à Atlas et permet d'identifier le `User` lors de ses interactions avec le système.

Une même personne peut disposer de plusieurs `Session` simultanément, par exemple depuis plusieurs appareils.

---

# Responsabilités

La `Session` est responsable de :

- représenter une authentification active ;
- identifier le `User` pendant sa connexion ;
- permettre la révocation d'un accès.

La `Session` n'est jamais responsable de :

- représenter l'identité d'un `User` ;
- gérer les autorisations ;
- représenter l'appartenance à un `Workspace`.

Ces responsabilités appartiennent respectivement au `User`, au `Role` et au `Membership`.

---

# Concepts associés

| Concept | Description |
|----------|-------------|
| `User` | Possède une ou plusieurs `Session`. |
| `Membership` | Détermine les droits du `User` une fois authentifié. |
| `Role` | Définit les autorisations accessibles pendant une `Session`. |

---

# Confusions fréquentes

La `Session` n'est pas :

- un `User` ;
- un `Membership` ;
- une autorisation.

La `Session` représente uniquement une authentification active.

---

# À retenir

La `Session` prouve l'identité du `User`, mais ne définit jamais ses autorisations.