---
id: IDN-CMD-CHANGE-USER-EMAIL
title: ChangeUserEmail
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: User

references:
  - ../entities.md
  - ../value-objects.md
  - ../invariants.md
  - ../events.md
  - ../workflows.md
---

# ChangeUserEmail

## Objectif

`ChangeUserEmail` remplace l'adresse e-mail principale par une nouvelle adresse
dont la propriété a déjà été prouvée.

La demande et la vérification de la nouvelle adresse appartiennent au workflow
de changement d'e-mail. Cette commande représente son commit métier final.

---

## Acteur

Le `User` concerné avec une authentification récente satisfaisant la politique
de sécurité.

---

## Données d'entrée

| Champ | Type | Obligatoire |
|---|---|---:|
| `UserId` | `UserId` | Oui |
| `NewEmailAddress` | `EmailAddress` | Oui |
| `EmailOwnershipProof` | preuve validée | Oui |
| `AuthenticationProof` | preuve récente | Oui |
| `ChangedAt` | `Instant` | Oui |
| `ChangeUserEmailRequestId` | `RequestId` | Oui |

---

## Préconditions

- le `User` est `Active` ;
- la nouvelle adresse diffère de l'adresse courante après normalisation ;
- aucun autre `User` non retiré ne possède la nouvelle adresse ;
- la preuve de propriété couvre cette adresse et ce `User` ;
- l'authentification est suffisamment récente ;
- les deux preuves sont valides, bornées et non rejouables.

---

## Traitement métier

1. Verrouiller l'unicité de la nouvelle adresse normalisée.
2. Consommer les preuves.
3. Remplacer `PrimaryEmailAddress`.
4. Conserver `EmailVerificationStatus = Verified`.
5. Incrémenter `UserSecurityVersion`.
6. Enregistrer `UserEmailChanged` atomiquement.
7. Orchestrer `RevokeAllUserSessions` selon la politique de sécurité.
8. Notifier l'ancienne et la nouvelle adresse via des consommateurs externes.

---

## Invariants

- `IDN-INV-016` ;
- `IDN-INV-017` ;
- `IDN-INV-012`.

---

## Événement produit

`UserEmailChanged`.

L'événement d'intégration public n'expose pas les adresses brutes. Les
consommateurs autorisés relisent l'information par un contrat protégé.

---

## Erreurs métier

- `UserNotFound` ;
- `UserNotActive` ;
- `EmailAddressAlreadyUsed` ;
- `EmailAddressAlreadyMatches` ;
- `EmailOwnershipProofInvalid` ;
- `RecentAuthenticationRequired` ;
- `UserVersionConflict` ;
- `IdempotencyConflict`.

---

## Atomicité

Le remplacement de l'adresse, la consommation de la preuve, l'incrément de
version et l'événement sont atomiques. La révocation des sessions est coordonnée
de manière à rendre les anciennes versions de sécurité immédiatement invalides.

