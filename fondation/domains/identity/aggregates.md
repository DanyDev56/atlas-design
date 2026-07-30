---
id: IDN-AGGREGATES
title: Aggregates
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-30

references:
  - README.md
  - model.md
  - entities.md
  - value-objects.md
  - invariants.md
---

# Aggregates

Ce document définit les agrégats du domaine **Identity**.

Un agrégat délimite une frontière de cohérence métier.

Toutes les modifications d'un agrégat doivent préserver ses règles métier avant que la transaction ne soit validée.

Les agrégats communiquent entre eux uniquement par leurs identifiants ou par des événements métier.

---

# Principes

Les agrégats du domaine respectent les principes suivants.

- Un agrégat possède une unique racine (`Aggregate Root`).
- Une transaction ne modifie qu'un seul agrégat.
- Les autres agrégats sont référencés uniquement par leur identifiant.
- Les règles métier locales sont garanties à l'intérieur de l'agrégat.
- Les règles métier transversales sont assurées par des processus métier ou des événements.

---

# Vue d'ensemble

| Agrégat | Racine | Objectif |
|----------|---------|----------|
| `User` | `User` | Gérer l'identité d'une personne. |
| `Membership` | `Membership` | Gérer l'appartenance à un `Workspace`. |
| `Role` | `Role` | Gérer les autorisations d'un `Workspace`. |
| `Invitation` | `Invitation` | Gérer le processus d'invitation. |
| `Session` | `Session` | Gérer les connexions actives. |

La `Permission` ne constitue pas un agrégat.

Elle est un concept système partagé par l'ensemble de la plateforme.

---

# Agrégat User

## Racine

`User`

## Responsabilité

Garantir la cohérence de l'identité d'une personne.

## Contient

- `User`

## Référence

- `MembershipId`
- `SessionId`

Le `User` ne contient jamais directement les `Membership` ni les `Session`.

---

# Agrégat Membership

## Racine

`Membership`

## Responsabilité

Garantir la cohérence d'une appartenance entre un `User` et un `Workspace`.

## Contient

- `Membership`

## Référence

- `UserId`
- `WorkspaceId`
- `RoleId`

Le `Membership` ne contient jamais le `User`, le `Workspace` ou le `Role`.

---

# Agrégat Role

## Racine

`Role`

## Responsabilité

Garantir la cohérence d'un ensemble de `Permission`.

## Contient

- `Role`

## Référence

- `WorkspaceId`
- `PermissionKey`

Le `Role` référence des `Permission`, mais ne les possède pas.

---

# Agrégat Invitation

## Racine

`Invitation`

## Responsabilité

Garantir le cycle de vie d'une invitation.

## Contient

- `Invitation`

## Référence

- `WorkspaceId`
- `RoleId`
- `UserId`

La création d'un `Membership` est réalisée en dehors de cet agrégat.

---

# Agrégat Session

## Racine

`Session`

## Responsabilité

Garantir le cycle de vie d'une authentification.

## Contient

- `Session`

## Référence

- `UserId`

Une `Session` ne possède jamais de référence directe vers un `Workspace`.

Les autorisations sont déterminées au moment où une action est exécutée.

---

# Frontières transactionnelles

Chaque commande métier ne modifie qu'un seul agrégat.

Exemples :

| Commande | Agrégat |
|-----------|----------|
| `CreateUser` | `User` |
| `DisableUser` | `User` |
| `CreateRole` | `Role` |
| `RenameRole` | `Role` |
| `AcceptInvitation` | `Invitation` |
| `ChangeRole` | `Membership` |
| `RevokeSession` | `Session` |

Lorsqu'une opération nécessite plusieurs agrégats, elle est orchestrée par un processus métier.

---

# Communication entre agrégats

Les agrégats communiquent uniquement :

- par leurs identifiants ;
- par des événements métier.

Ils ne doivent jamais manipuler directement l'état interne d'un autre agrégat.

---

# Décisions de conception

Les agrégats du domaine `Identity` sont volontairement petits.

Cette décision permet :

- de limiter les transactions longues ;
- de réduire les conflits de concurrence ;
- de faciliter les évolutions ;
- de conserver des responsabilités clairement séparées.

Chaque agrégat protège uniquement ses propres règles métier.

Les règles impliquant plusieurs agrégats sont traitées à l'extérieur de ceux-ci.