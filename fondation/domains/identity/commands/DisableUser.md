---
id: IDN-CMD-DISABLE-USER
title: DisableUser
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
---

# DisableUser

## Objectif

`DisableUser` suspend globalement et de manière réversible l'accès d'un `User`
à Atlas.

```text
Active → Disabled
```

La commande conserve les memberships et l'historique, mais aucun accès n'est
effectif tant que le `User` reste désactivé.

---

## Acteurs autorisés

- le `User` concerné avec une preuve récente ;
- un workflow de sécurité de confiance ;
- un workflow de conformité explicitement autorisé.

Une permission de `Workspace` ne permet jamais de désactiver l'identité globale
d'un autre utilisateur.

---

## Données d'entrée

- `UserId` ;
- `DisabledBy` ;
- `DisableReason` ;
- `DisableSource` ;
- `DisabledAt` ;
- `AuthenticationProof` lorsque l'acteur est le sujet ;
- `DisableUserRequestId`.

---

## Préconditions

- le `User` existe et est `Active` ;
- l'acteur ou le workflow est autorisé ;
- le motif et la source sont valides ;
- la demande couvre exactement l'intention de désactivation globale.

---

## Traitement métier

1. Définir `UserStatus = Disabled`.
2. Enregistrer la date, la source et le motif.
3. Incrémenter `UserSecurityVersion`.
4. Enregistrer `UserDisabled` atomiquement.
5. Déclencher `RevokeAllUserSessions` par orchestration fiable.
6. Invalider les projections d'autorisation du `User`.

---

## Invariants

- `IDN-INV-013` ;
- `IDN-INV-010` ;
- `IDN-INV-018`.

---

## Erreurs métier

- `UserNotFound` ;
- `UserAlreadyDisabled` ;
- `UserPendingVerification` ;
- `UserRemoved` ;
- `UserDisablementNotAuthorized` ;
- `InvalidDisableReason` ;
- `RecentAuthenticationRequired` ;
- `UserVersionConflict` ;
- `IdempotencyConflict`.

---

## Idempotence

Un retry identique retourne la désactivation initiale. Une nouvelle demande ne
remplace jamais silencieusement le motif ou l'acteur historique.

