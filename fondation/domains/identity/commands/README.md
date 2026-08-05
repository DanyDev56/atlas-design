---
id: IDN-COMMANDS
title: Commands
status: In Review
owner: Product
version: 1.3.0
last_updated: 2026-08-05

references:
  - ../README.md
  - ../model.md
  - ../aggregates.md
  - ../relationships.md
  - ../invariants.md
---

# Commands

Ce répertoire décrit les commandes du domaine **Identity**.

Une commande représente une intention de modifier l'état du domaine.

Elle exprime ce qu'un acteur souhaite accomplir, sans décrire la manière dont cette intention est implémentée.

---

# Principes

Toutes les commandes respectent les principes suivants.

- une commande exprime une intention métier unique ;
- une commande est validée avant toute modification ;
- une commande est soit entièrement appliquée, soit entièrement refusée ;
- une commande peut produire un ou plusieurs événements métier ;
- une commande ne retourne jamais directement des données de lecture.

La majorité des commandes modifient un seul agrégat. Une intention atomique peut
toutefois coordonner plusieurs instances lorsque l'invariant métier ne peut pas
être préservé autrement. Cette exception doit documenter explicitement sa
frontière transactionnelle, sa concurrence et son mode de reprise.

---

# Cycle de vie

Chaque commande suit le cycle suivant.

```text
Command

↓

Validation

↓

Invariant checking

↓

Aggregate modification

↓

Domain Events

↓

Commit
```

Si une validation ou un invariant échoue, la commande est interrompue.

Aucun changement d'état ne doit être observable.

---

# Structure d'une commande

Chaque fichier du répertoire couvre au minimum la structure suivante.

```md
# Nom de la commande

## Objectif

## Agrégat concerné

## Acteur

## Préconditions

## Données d'entrée

## Traitement métier

## Résultat attendu

## Invariants concernés

## Événements produits

## Erreurs métier

## Idempotence

## Décisions de conception
```

Des sections supplémentaires peuvent être ajoutées lorsque la sécurité, la
concurrence ou la coordination le justifient.

---

# Convention de nommage

Les commandes utilisent :

- un verbe ;
- un nom métier ;
- un vocabulaire impératif.

Exemples :

```text
CreateUser
DisableUser

CreateMembership
ChangeMembershipRole

CreateInvitation
AcceptInvitation

CreateSession
RevokeSession
```

Les commandes ne décrivent jamais une opération technique.

Par exemple :

❌

```text
UpdateDatabase
SaveRole
PersistInvitation
```

✔️

```text
CreateRole
DisableRole
AcceptInvitation
```

---

# Responsabilités

Une commande est responsable de :

- vérifier ses préconditions ;
- demander à un agrégat d'exécuter une intention ;
- garantir les invariants concernés ;
- produire les événements métier.

Une commande n'est pas responsable :

- des projections ;
- des modèles de lecture ;
- des notifications ;
- des intégrations externes ;
- de l'interface utilisateur.

---

# Préconditions

Chaque commande doit expliciter les conditions nécessaires à son exécution.

Exemple :

Pour `AcceptInvitation` :

- l'invitation existe ;
- elle est active ;
- elle n'est pas expirée ;
- le destinataire est valide ;
- aucun `Membership` incompatible n'existe.

Une précondition ne modifie jamais le domaine.

---

# Invariants

Une commande doit préserver tous les invariants concernés.

Elle ne peut jamais produire un état invalide.

Les invariants applicables doivent être référencés explicitement.

Exemple :

```text
IDN-INV-001
IDN-INV-007
IDN-INV-009
```

---

# Événements

Une commande peut produire plusieurs événements.

Exemple :

```text
AcceptInvitation

↓

MembershipCreated

↓

InvitationAccepted
```

Les événements sont documentés séparément dans [`../events.md`](../events.md).
Ce catalogue est normatif : les noms suggérés dans une section d'effets
secondaires sont des signaux internes ou des demandes d'intégration, sauf s'ils
y figurent explicitement.

---

# Erreurs métier

Une commande doit échouer explicitement lorsqu'une règle métier ne peut pas être respectée.

Les erreurs doivent exprimer une situation métier.

Exemples :

```text
InvitationExpired

InvitationAlreadyAccepted

MembershipAlreadyExists

RoleBelongsToAnotherWorkspace

LastWorkspaceOwnerCannotBeRemoved

UnknownPermission

UserDisabled
```

Une erreur technique ne remplace jamais une erreur métier.

---

# Idempotence

Chaque commande doit préciser son comportement lorsqu'une même intention est
soumise plusieurs fois.

## Strictement idempotente

Exemple :

```text
RevokeSession
```

Réexécuter la commande ne produit aucun effet supplémentaire.

---

## Idempotente avec résultat existant

Exemple :

```text
AcceptInvitation
```

Une seconde exécution peut retourner le résultat déjà obtenu sans créer un nouveau `Membership`.

---

## Nouvelle intention

Deux demandes possédant des clés d'idempotence différentes représentent deux
intentions distinctes et peuvent produire deux résultats.

Exemple :

```text
CreateSession
```

Le retry utilisant le même `CreateSessionRequestId` retourne la session initiale.
Une nouvelle demande utilisant une autre clé peut créer une nouvelle `Session`.

La clé, l'empreinte de l'intention et la durée de conservation du résultat
doivent être documentées.

---

# Transactions

Une commande modifie par défaut un seul agrégat.

Lorsqu'une opération implique plusieurs agrégats, la coordination est réalisée par :

- un workflow ;
- un process manager ;
- un service de domaine.

Une coordination atomique multi-agrégats est admise uniquement lorsqu'elle fait
partie du contrat métier, comme le transfert simultané d'un rôle entre deux
`Membership`. La commande doit alors rendre impossible tout état intermédiaire
invalide.

---

# Autorisation

Une commande ne présume jamais qu'un acteur est autorisé.

Les contrôles d'autorisation sont réalisés avant son exécution ou par une politique dédiée.

Les préconditions peuvent supposer que l'acteur possède les permissions nécessaires.

Les permissions requises doivent être documentées lorsqu'elles sont connues.

---

# Validation

Une commande distingue trois niveaux de validation.

## Validation syntaxique

Exemple :

- format d'e-mail invalide ;
- identifiant manquant.

---

## Validation métier

Exemple :

- invitation expirée ;
- rôle supprimé.

---

## Validation d'invariants

Exemple :

- dernier propriétaire ;
- unicité du `Membership`.

---

# Concurrence

Les commandes doivent documenter leur comportement en cas d'exécution concurrente.

Exemple :

Deux appels simultanés à :

```text
AcceptInvitation
```

ne doivent jamais créer deux `Membership`.

---

# Événements compensatoires

Les commandes ne réalisent jamais elles-mêmes une compensation.

Lorsqu'un traitement transversal échoue après la modification d'un agrégat, la stratégie de compensation est définie par le workflow concerné.

---

# Répertoire

Les commandes actuellement documentées sont :

## User

- [`CreateUser`](CreateUser.md)
- [`VerifyUserEmail`](VerifyUserEmail.md)
- [`UpdateUserProfile`](UpdateUserProfile.md)
- [`ChangeUserEmail`](ChangeUserEmail.md)
- [`DisableUser`](DisableUser.md)
- [`EnableUser`](EnableUser.md)
- [`RemoveUser`](RemoveUser.md)

---

## Membership

- [`CreateMembership`](CreateMembership.md)
- [`ChangeMembershipRole`](ChangeMembershipRole.md)
- [`TransferMembershipRole`](TransferMembershipRole.md)
- [`SuspendMembership`](SuspendMembership.md)
- [`ReactivateMembership`](ReactivateMembership.md)
- [`RestoreMembership`](RestoreMembership.md)
- [`RemoveMembership`](RemoveMembership.md)
- [`LeaveWorkspace`](LeaveWorkspace.md)

---

## Role

- [`CreateRole`](CreateRole.md)
- [`UpdateRoleMetadata`](UpdateRoleMetadata.md)
- [`GrantPermissionToRole`](GrantPermissionToRole.md)
- [`RevokePermissionFromRole`](RevokePermissionFromRole.md)
- [`ChangeRoleAssignmentPolicy`](ChangeRoleAssignmentPolicy.md)
- [`ChangeRoleTransferPolicy`](ChangeRoleTransferPolicy.md)
- [`DisableRole`](DisableRole.md)
- [`EnableRole`](EnableRole.md)
- [`ArchiveRole`](ArchiveRole.md)

---

## Invitation

- [`CreateInvitation`](CreateInvitation.md)
- [`SendInvitation`](SendInvitation.md)
- [`ResendInvitation`](ResendInvitation.md)
- [`AcceptInvitation`](AcceptInvitation.md)
- [`DeclineInvitation`](DeclineInvitation.md)
- [`RevokeInvitation`](RevokeInvitation.md)
- [`ExpireInvitation`](ExpireInvitation.md)

---

## Session

- [`CreateSession`](CreateSession.md)
- [`RefreshSession`](RefreshSession.md)
- [`ElevateSession`](ElevateSession.md)
- [`ExpireSessionElevation`](ExpireSessionElevation.md)
- [`TerminateSessionElevation`](TerminateSessionElevation.md)
- [`ExpireSession`](ExpireSession.md)
- [`RevokeSession`](RevokeSession.md)
- [`RevokeAllUserSessions`](RevokeAllUserSessions.md)

---

# Évolution

L'ajout d'une nouvelle commande doit respecter les conventions de ce document.

Une nouvelle commande ne doit être introduite que lorsqu'elle représente une véritable intention métier.

Une différence purement technique ne justifie pas la création d'une nouvelle commande.

---

# Synthèse

Les commandes constituent l'unique point d'entrée pour modifier le domaine `Identity`.

Elles expriment des intentions métier explicites, préservent les invariants,
documentent leur frontière transactionnelle et publient les événements décrivant
les changements réalisés.

Une commande ne décrit jamais **comment** le domaine est implémenté, mais uniquement **ce que** le domaine doit accomplir.
