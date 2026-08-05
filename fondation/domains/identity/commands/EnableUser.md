---
id: IDN-CMD-ENABLE-USER
title: EnableUser
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: User

references:
  - ../entities.md
  - ../invariants.md
  - ../events.md
  - ../workflows.md
---

# EnableUser

## Objectif

`EnableUser` rétablit l'accès global d'un `User` précédemment désactivé.

```text
Disabled → Active
```

La commande ne recrée aucune session et ne modifie aucun membership.

---

## Acteurs autorisés

- workflow de récupération du compte ayant validé une preuve forte ;
- workflow de sécurité ou de conformité autorisé à lever sa propre mesure.

Une permission de `Workspace` ne permet jamais de réactiver une identité
globale.

---

## Données d'entrée

- `UserId` ;
- `EnabledBy` ;
- `EnableReason` ;
- `EnableSource` ;
- `RecoveryOrApprovalProof` ;
- `EnabledAt` ;
- `EnableUserRequestId`.

---

## Préconditions

- le `User` est `Disabled` ;
- l'adresse principale reste vérifiée ;
- la cause de désactivation est résolue ;
- la preuve ou l'approbation couvre la version courante du `User` ;
- aucune interdiction permanente ne s'applique.

---

## Traitement métier

1. Vérifier la readiness de réactivation.
2. Consommer la preuve ou l'approbation.
3. Définir `UserStatus = Active`.
4. Incrémenter `UserSecurityVersion`.
5. Enregistrer `UserEnabled` atomiquement.

Une nouvelle authentification est obligatoire pour créer une `Session`.

---

## Invariants

- `IDN-INV-017` ;
- `IDN-INV-018` ;
- `IDN-INV-010`.

---

## Erreurs métier

- `UserNotFound` ;
- `UserAlreadyActive` ;
- `UserPendingVerification` ;
- `UserRemoved` ;
- `UserEnablementNotAuthorized` ;
- `UserEnablementBlocked` ;
- `RecoveryOrApprovalProofInvalid` ;
- `UserVersionConflict` ;
- `IdempotencyConflict`.

---

## Idempotence

Un retry identique retourne le résultat initial. Une activation déjà réalisée
par une autre intention est signalée sans remplacer son audit.

