---
id: IDN-CMD-CREATE-USER
title: CreateUser
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

# CreateUser

## Objectif

`CreateUser` crée une identité humaine en attente de vérification de son adresse
e-mail principale.

La commande ne crée ni `Workspace`, ni `Membership`, ni `Session` ordinaire.

```text
no User
  ↓
PendingVerification User
```

---

## Agrégat concerné

`User`.

---

## Acteurs autorisés

- parcours public d'inscription ;
- acceptation d'invitation orchestrée ;
- workflow interne de migration explicitement autorisé.

Aucune permission de `Workspace` ne permet de créer une identité globale.

---

## Données d'entrée

| Champ | Type | Obligatoire |
|---|---|---:|
| `UserId` | `UserId` | Oui |
| `PrimaryEmailAddress` | `EmailAddress` | Oui |
| `DisplayName` | `DisplayName` | Oui |
| `IdentityType` | `IdentityType` | Oui |
| `CreatedAt` | `Instant` | Oui |
| `CreateUserRequestId` | `RequestId` | Oui |
| `CreationSource` | `ChangeSource` | Oui |

`IdentityType` doit valoir `HumanUser` en version 1.0.

---

## Préconditions

- l'adresse e-mail est syntaxiquement valide et normalisée ;
- aucun `User` non retiré ne possède cette adresse normalisée ;
- le nom d'affichage est valide ;
- la source de création est autorisée ;
- la demande est idempotente.

---

## Traitement métier

1. Normaliser l'adresse e-mail.
2. Vérifier son unicité sous contrainte persistante.
3. Construire le `User` avec `UserStatus = PendingVerification`.
4. Définir `EmailVerificationStatus = Pending`.
5. Définir `IdentityType = HumanUser`.
6. Initialiser `UserSecurityVersion`.
7. Enregistrer `UserCreated` dans la même transaction.
8. Confier la demande de vérification au workflow via l'outbox.

---

## Résultat

La commande retourne l'identité du `User` et son statut
`PendingVerification`. Elle ne retourne aucun secret de vérification.

---

## Invariants

- `IDN-INV-016` — unicité de l'adresse e-mail principale ;
- `IDN-INV-017` — un `User` actif exige une adresse vérifiée ;
- `IDN-INV-012` — séparation entre identifiants et secrets.

---

## Événement produit

`UserCreated`.

L'événement ne contient ni credential, ni token, ni preuve brute.

---

## Erreurs métier

- `EmailAddressAlreadyUsed` ;
- `InvalidEmailAddress` ;
- `InvalidDisplayName` ;
- `UnsupportedIdentityType` ;
- `UserCreationNotAllowed` ;
- `IdempotencyConflict`.

---

## Idempotence et concurrence

La clé est `CreateUserRequestId`. Une même clé avec la même empreinte
retourne le `User` initial.

Une contrainte unique sur l'adresse normalisée empêche deux inscriptions
concurrentes de créer deux identités actives pour la même adresse.

