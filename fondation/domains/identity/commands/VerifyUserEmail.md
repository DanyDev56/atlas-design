---
id: IDN-CMD-VERIFY-USER-EMAIL
title: VerifyUserEmail
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

# VerifyUserEmail

## Objectif

`VerifyUserEmail` confirme la propriété de l'adresse e-mail principale d'un
`User` et active son identité initiale.

```text
PendingVerification
  ↓
Active
```

---

## Acteur

Le sujet du `User`, au moyen d'une `EmailOwnershipProof` valide.

---

## Données d'entrée

| Champ | Type | Obligatoire |
|---|---|---:|
| `UserId` | `UserId` | Oui |
| `EmailOwnershipProof` | preuve secrète validée | Oui |
| `VerifiedAt` | `Instant` | Oui |
| `VerifyEmailRequestId` | `RequestId` | Oui |

La commande reçoit une référence de preuve validée. Le secret brut reste dans
le composant de vérification.

---

## Préconditions

- le `User` existe et est `PendingVerification` ;
- la preuve appartient au `User` et à son adresse principale courante ;
- la preuve est valide, non expirée, non révoquée et non consommée ;
- aucune modification concurrente n'a changé l'adresse principale.

---

## Traitement métier

1. Charger le `User` et la version attendue.
2. Valider la référence de preuve.
3. Marquer la preuve comme consommée.
4. Définir `EmailVerificationStatus = Verified`.
5. Définir `UserStatus = Active`.
6. Enregistrer `UserEmailVerified` et `UserActivated` atomiquement.

---

## Invariants

- `IDN-INV-017` ;
- `IDN-INV-012` ;
- `IDN-INV-018`.

---

## Événements produits

- `UserEmailVerified` ;
- `UserActivated`.

Les événements ne contiennent jamais la preuve ou le token brut.

---

## Erreurs métier

- `UserNotFound` ;
- `UserAlreadyActive` ;
- `UserDisabled` ;
- `UserRemoved` ;
- `EmailOwnershipProofInvalid` ;
- `EmailOwnershipProofExpired` ;
- `EmailOwnershipProofAlreadyUsed` ;
- `UserVersionConflict` ;
- `IdempotencyConflict`.

---

## Idempotence

Un retry identique retourne l'activation initiale. Une autre preuve présentée
après activation ne crée aucun nouvel événement.

