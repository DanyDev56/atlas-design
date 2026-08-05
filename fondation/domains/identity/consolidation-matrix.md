---
id: IDN-CONSOLIDATION-MATRIX
title: Identity Consolidation Matrix
status: Living Document
owner: Product
version: 1.3.0
last_updated: 2026-08-05

references:
  - README.md
  - scope.md
  - model.md
  - invariants.md
  - decision-record.md
  - future.md
  - ../../../scripts/check-identity-docs.sh
---

# Matrice de consolidation Identity

Ce document définit le travail nécessaire pour déclarer le contrat `Identity`
1.0 cohérent, implémentable et vérifiable.

Il ne mesure pas le volume de documentation. Il mesure la présence d'une source
canonique, l'absence de contradiction et la traçabilité entre les contrats.

---

## Périmètre Identity 1.0

Identity 1.0 couvre :

- l'identité humaine `User` ;
- la propriété et la vérification de l'adresse e-mail principale ;
- le cycle de vie du compte ;
- les preuves d'authentification et la récupération du compte ;
- les `Session` et leur révocation ;
- l'appartenance à un `Workspace` par `Membership` ;
- un `Role` courant par `Membership` actif ;
- le catalogue global des `Permission` ;
- les rôles système et personnalisés d'un `Workspace` ;
- les invitations ;
- la résolution d'une autorisation contextualisée ;
- l'audit des changements sensibles ;
- les contrats publics consommés par les autres domaines.

Les sujets reportés sont recensés dans [`future.md`](future.md). Ils ne bloquent
pas Identity 1.0 et ne doivent pas introduire de comportement implicite.

---

## Documents canoniques

| Contrat | Source canonique | État | Critère de sortie |
|---|---|---|---|
| Mission | `mission.md` | Présent | Compatible avec le périmètre 1.0 |
| Périmètre | `scope.md` | Présent | Frontière Workspace explicite |
| Modèle | `model.md` | Présent | Concepts et source de vérité uniques |
| Entités | `entities.md` | Présent | Cycles de vie alignés sur les commandes |
| Agrégats | `aggregates.md` | Présent | Frontières transactionnelles sans contradiction |
| Relations | `relationships.md` | Présent | Cardinalités et propriété validées |
| Value Objects | `value-objects.md` | Présent | Valeurs 1.0 décidées, futures isolées |
| Invariants | `invariants.md` | Présent | Identifiants stables et référencés correctement |
| Commands | `commands/README.md` | Présent | Chaque intention 1.0 possède un contrat |
| Events | `events.md` | Présent | Un catalogue unique décrit tous les faits publics |
| Permissions | `permissions.md` | Présent | Clés et règles d'évaluation canoniques |
| Workflows | `workflows.md` | Présent | Parcours transversaux et atomicité définis |
| Contrat public | `api.md` | Présent | Lectures, commandes et erreurs exposées |
| Futur | `future.md` | Présent | Chaque extension hors 1.0 est explicitement isolée |
| Décisions | `decision-record.md` | Présent | Questions 1.0 arbitrées ou reportées |

---

## Couverture des agrégats

| Agrégat | Cycle de vie | Commands | Events | Invariants | Permissions |
|---|---:|---:|---:|---:|---:|
| `User` | Présent | Présent | Canonique | Présent | Canonique |
| `Membership` | Présent | Présent | Canonique | Présent | Canonique |
| `Role` | Présent | Présent | Canonique | Présent | Canonique |
| `Invitation` | Présent | Présent | Canonique | Présent | Canonique |
| `Session` | Présent | Présent | Canonique | Présent | Canonique |

`Permission` appartient à un catalogue global. Les affectations de permissions
sont modifiées par l'agrégat `Role`.

---

## Traçabilité des commandes

Les invariants indiqués ci-dessous sont les invariants principaux. Les fiches de
commande peuvent en référencer d'autres selon leur contexte.

### User

| Commande | Autorité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|
| `CreateUser` | Inscription ou workflow de confiance | 012, 016, 017 | `UserCreated` | `CreateUserRequestId` |
| `VerifyUserEmail` | Preuve bornée du sujet | 012, 017, 018 | `UserEmailVerified`, `UserActivated` | `VerifyEmailRequestId` |
| `UpdateUserProfile` | Intrinsèque au sujet | 017, 018 | `UserProfileUpdated` | `UpdateUserProfileRequestId` |
| `ChangeUserEmail` | Intrinsèque + authentification récente | 012, 016, 017 | `UserEmailChanged` | `ChangeUserEmailRequestId` |
| `DisableUser` | Sujet ou workflow sécurité/conformité | 010, 013, 018 | `UserDisabled` | `DisableUserRequestId` |
| `EnableUser` | Workflow de récupération ou autorité d'origine | 010, 017, 018 | `UserEnabled` | `EnableUserRequestId` |
| `RemoveUser` | Sujet ou workflow légal distinct | 006, 010, 013, 018 | `UserRemoved` | `RemoveUserRequestId` |

### Membership

| Commande | Permission ou autorité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|
| `CreateMembership` | `workspace.members.create` ou bootstrap de confiance | 001–006, 014, 015 | `MembershipCreated` | `CreationRequestId` |
| `ChangeMembershipRole` | `workspace.members.change-role` | 001–006, 011, 014, 015 | `MembershipRoleChanged` | `ChangeRequestId` |
| `TransferMembershipRole` | `workspace.members.transfer-role` | 001, 003, 005, 006, 011, 014 | `MembershipRoleTransferCompleted` | `TransferRequestId` |
| `SuspendMembership` | `workspace.members.suspend` | 001–006, 011, 014 | `MembershipSuspended` | `SuspensionRequestId` |
| `ReactivateMembership` | `workspace.members.reactivate` | 001–006, 011, 014, 015 | `MembershipReactivated` | `ReactivationRequestId` |
| `RestoreMembership` | `workspace.members.restore` | 001–006, 011, 014, 015 | `MembershipRestored` | `RestorationRequestId` |
| `RemoveMembership` | `workspace.members.remove` | 001, 002, 004–006, 011, 014 | `MembershipRemoved` | `RemovalRequestId` |
| `LeaveWorkspace` | Intrinsèque au membre actif | 001, 002, 004, 006, 011, 014 | `MembershipLeft` | `LeaveRequestId` |

### Role

| Commande | Permission | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|
| `CreateRole` | `workspace.roles.create` | 004–006, 014, 015, 020, 021 | `RoleCreated` | `CreationRequestId` |
| `UpdateRoleMetadata` | `workspace.roles.update-metadata` | 004, 005, 011, 019, 021 | `RoleMetadataUpdated` | `UpdateRequestId` |
| `GrantPermissionToRole` | `workspace.roles.grant-permission` | 004–006, 011, 014, 015, 019–021 | `RolePermissionGranted` | `GrantRequestId` |
| `RevokePermissionFromRole` | `workspace.roles.revoke-permission` | 004–006, 011, 014, 015, 019–021 | `RolePermissionRevoked` | `RevocationRequestId` |
| `ChangeRoleAssignmentPolicy` | `workspace.roles.change-assignment-policy` | 005, 006, 014, 015, 019, 021 | `RoleAssignmentPolicyChanged` | `ChangeRequestId` |
| `ChangeRoleTransferPolicy` | `workspace.roles.change-transfer-policy` | 005, 006, 014, 015, 019, 021 | `RoleTransferPolicyChanged` | `ChangeRequestId` |
| `DisableRole` | `workspace.roles.disable` | 005, 006, 014, 015, 019–021 | `RoleDisabled` | `DisableRequestId` |
| `EnableRole` | `workspace.roles.enable` | 005, 006, 014, 015, 019–021 | `RoleEnabled` | `EnableRequestId` |
| `ArchiveRole` | `workspace.roles.archive` | 004–006, 011, 014, 015, 019–021 | `RoleArchived` | `ArchiveRequestId` |

### Invitation

| Commande | Permission ou autorité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|
| `CreateInvitation` | `workspace.members.invite` | 001, 005, 008, 009, 012, 014, 015 | `InvitationCreated` | `CreateInvitationRequestId` |
| `SendInvitation` | `workspace.members.invite` ou workflow validé | 005, 008, 009, 012, 014, 015 | `InvitationSendRequested`, puis `InvitationSent` | `SendRequestId` |
| `ResendInvitation` | `workspace.members.invite` ou workflow validé | 001, 005, 008, 009, 012, 014, 015 | `InvitationResendRequested`, `InvitationTokenRotated`, puis `InvitationSent` | `ResendRequestId` |
| `AcceptInvitation` | Preuve bornée du destinataire | 001–003, 005, 007–009, 012, 015 | `MembershipCreated` ou `MembershipRestored`, puis `InvitationAccepted` | `AcceptanceRequestId` |
| `DeclineInvitation` | Preuve bornée du destinataire | 007–009, 012 | `InvitationDeclined` | `DeclineRequestId` |
| `RevokeInvitation` | `workspace.members.invite` | 007–009, 012, 014, 015 | `InvitationRevoked` | `RevocationRequestId` |
| `ExpireInvitation` | `identity.invitations.expire` | 007, 009, 012, 015 | `InvitationExpired` | `ExpirationRequestId` |

### Session

| Commande | Permission ou autorité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|
| `CreateSession` | Preuve d'authentification ; capacités système pour autrui | 010, 012, 013, 017, 018, 022 | `SessionCreated` | `CreateSessionRequestId` |
| `RefreshSession` | Credential de refresh du sujet | 010, 012, 013, 017, 018, 022 | `SessionRefreshed` | `RefreshRequestId` |
| `ElevateSession` | Preuve forte du sujet | 010, 012, 013, 014, 022 | `SessionElevated` | `ElevateSessionRequestId` |
| `ExpireSessionElevation` | Horloge ou `SystemActor` | 010, 012, 022 | `SessionElevationExpired` | `ExpireSessionElevationRequestId` |
| `TerminateSessionElevation` | Sujet ou workflow sécurité borné | 010, 012, 022 | `SessionElevationTerminated` | `TerminateSessionElevationRequestId` |
| `ExpireSession` | Horloge ou `SystemActor` | 010, 012, 022 | `SessionExpired` | `ExpireSessionRequestId` |
| `RevokeSession` | Intrinsèque au sujet ou `identity.sessions.revoke` | 010, 012, 022 | `SessionRevoked` | `RevokeSessionRequestId` |
| `RevokeAllUserSessions` | Intrinsèque au sujet, workflow sécurité ou `identity.sessions.revoke-all` | 010, 012, 013, 018, 022 | `SessionRevoked`, `AllUserSessionsRevoked` | `RevokeAllSessionsRequestId` |

### Événements produits par un workflow

| Workflow | Domain Events |
|---|---|
| Changement de credential | `UserCredentialsChanged` |
| Récupération de compte finalisée | `UserAccountRecovered` |

---

## Commandes User requises

Le cycle de vie `User` est complet lorsque les intentions suivantes possèdent un
contrat normatif :

- `CreateUser` ;
- `VerifyUserEmail` ;
- `UpdateUserProfile` ;
- `ChangeUserEmail` ;
- `DisableUser` ;
- `EnableUser` ;
- `RemoveUser`.

La récupération du compte est un workflow Identity coordonnant une preuve de
récupération, la modification des credentials et `RevokeAllUserSessions`.

---

## Décisions 1.0

| Sujet | Décision 1.0 |
|---|---|
| Statuts User | `PendingVerification`, `Active`, `Disabled`, `Removed` |
| Verrouillage de sécurité | État d'authentification distinct de `UserStatus` |
| Suspension globale | Représentée par `Disabled` avec une raison structurée |
| Identité prise en charge | `HumanUser` |
| Identités techniques | Reportées ; aucune création implicite en 1.0 |
| Adresse e-mail | Unique après normalisation ; propriété prouvée avant activation |
| Suppression User | Logique, terminale et distincte de l'effacement de données personnelles |
| Rôles par Membership | Exactement un rôle courant pour un Membership actif |
| Permissions directes | Interdites sur User et Membership |
| Autorisation | Toujours résolue dans un Workspace actif et pour un Membership actif |
| Workspace | Référence externe validée par contrat public minimal |
| Audience Notifications | Résolution bornée et revalidation avec endpoint opaque |

---

## Quality gates Identity 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Toutes les commandes 1.0 figurent dans le catalogue.
- [x] Tous les événements produits figurent dans le catalogue.
- [x] Toutes les permissions requises figurent dans le catalogue.
- [x] Chaque commande référence des invariants existants.
- [x] Chaque commande possède une clé ou une politique d'idempotence.
- [x] Les transitions terminales sont irréversibles.
- [x] Les secrets sont absents des événements et journaux.
- [x] Les effets externes utilisent une outbox ou un contrat de reprise explicite.
- [x] Le contrat minimal de Workspace est documenté.
- [x] Le contrat d'audience Notifications n'expose aucune adresse brute.
- [x] Aucun nom déprécié n'est utilisé comme concept courant.
- [x] Aucun lien ou `reference` ne cible une ressource absente.
- [x] Les questions ouvertes restantes sont toutes classées dans `future.md`.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-identity-docs.sh
```

Identity 1.0 est donc `In Review` depuis le 5 août 2026. Ce statut signifie que
le contrat documentaire est fermé et prêt pour revue contradictoire ; il ne
signifie pas encore que l'implémentation est validée.

---

## Règle de clôture

Identity passe de `Draft` à `In Review` uniquement lorsque toutes les quality
gates sont cochées.

Le statut `Stable` n'est attribué qu'après confrontation du contrat à une
implémentation et à ses tests de domaine.
