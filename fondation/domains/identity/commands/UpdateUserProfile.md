---
id: IDN-CMD-UPDATE-USER-PROFILE
title: UpdateUserProfile
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: User

invariants:
  - IDN-INV-017
  - IDN-INV-018

references:
  - README.md
  - ../entities.md
  - ../value-objects.md
  - ../invariants.md
  - ../events.md
---

# UpdateUserProfile

## Objectif

`UpdateUserProfile` modifie uniquement les informations publiques de
présentation d'un `User`.

Identity 1.0 autorise la modification de `DisplayName`.

La commande ne modifie jamais l'adresse e-mail, le statut, les credentials, les
memberships, les rôles ou les permissions.

---

## Acteur

Le `User` concerné, au moyen d'une session active.

---

## Données d'entrée

| Champ | Type | Obligatoire |
|---|---|---:|
| `UserId` | `UserId` | Oui |
| `DisplayName` | `DisplayName` | Oui |
| `UpdatedAt` | `Instant` | Oui |
| `UpdateUserProfileRequestId` | `RequestId` | Oui |

---

## Préconditions

- le `User` existe et est `Active` ;
- l'acteur est le sujet du `User` ;
- le nom d'affichage est valide ;
- la valeur demandée diffère de la valeur courante.

---

## Résultat

Le `DisplayName` est remplacé et `UserProfileUpdated` est enregistré dans la
même transaction.

---

## Erreurs métier

- `UserNotFound` ;
- `UserNotActive` ;
- `ActorDoesNotMatchUser` ;
- `InvalidDisplayName` ;
- `UserProfileAlreadyMatches` ;
- `UserVersionConflict` ;
- `IdempotencyConflict`.

---

## Idempotence

La clé est `UpdateUserProfileRequestId`. Seuls les changements effectifs
produisent `UserProfileUpdated`.
