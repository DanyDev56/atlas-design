---
id: IDN-CMD-REMOVE-USER
title: RemoveUser
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: User

references:
  - ../entities.md
  - ../invariants.md
  - ../events.md
  - RevokeAllUserSessions.md
  - LeaveWorkspace.md
  - RemoveMembership.md
---

# RemoveUser

## Objectif

`RemoveUser` ferme logiquement et de manière irréversible une identité Atlas.

```text
Active   ─┬─► Removed
Disabled ─┘
```

Le retrait logique est distinct de l'effacement ou de l'anonymisation des
données personnelles.

---

## Acteur

Le `User` concerné avec une authentification récente et une confirmation
explicite.

Un workflow légal exceptionnel peut retirer un `User` sous une autorité et un
audit distincts. Aucun owner de `Workspace` ne peut retirer l'identité globale
d'un autre utilisateur.

---

## Données d'entrée

- `UserId` ;
- `RemovedBy` ;
- `RemovalReason` ;
- `RemovalSource` ;
- `RemovalConfirmation` ;
- `AuthenticationProof` ;
- `RemovedAt` ;
- `RemoveUserRequestId`.

---

## Readiness obligatoire

Avant le retrait :

- aucun `Membership` actif ou suspendu ne subsiste ;
- aucun invariant de dernier owner n'est menacé ;
- les transferts de responsabilité requis sont terminés ;
- les exports ou obligations de conservation applicables sont traités ;
- la confirmation couvre exactement le retrait irréversible ;
- une politique définit le traitement de l'adresse e-mail et des données
  personnelles.

---

## Traitement métier

1. Recalculer la readiness sous verrou ou version de gouvernance.
2. Définir `UserStatus = Removed`.
3. Enregistrer la date, la source et le motif.
4. Incrémenter `UserSecurityVersion`.
5. Enregistrer `UserRemoved` atomiquement.
6. Révoquer toutes les sessions et credentials persistants.
7. Publier les demandes de traitement des données personnelles via l'outbox.

---

## Invariants

- `IDN-INV-006` — continuité du dernier owner ;
- `IDN-INV-010` — sessions inutilisables ;
- `IDN-INV-013` — absence d'accès ;
- `IDN-INV-018` — retrait terminal.

---

## Événement produit

`UserRemoved`.

L'événement conserve les identifiants d'audit nécessaires, jamais les preuves,
credentials ou données personnelles devenues inutiles.

---

## Erreurs métier

- `UserNotFound` ;
- `UserAlreadyRemoved` ;
- `UserRemovalNotAuthorized` ;
- `UserRemovalNotReady` ;
- `ActiveMembershipsRemain` ;
- `WorkspaceOwnershipTransferRequired` ;
- `RecentAuthenticationRequired` ;
- `RemovalConfirmationInvalid` ;
- `UserVersionConflict` ;
- `IdempotencyConflict`.

---

## Idempotence et irréversibilité

Un retry identique retourne le retrait initial. Aucune commande, y compris
`EnableUser`, ne peut faire sortir un `User` de l'état `Removed`.

