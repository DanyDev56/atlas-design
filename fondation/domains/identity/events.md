---
id: IDN-EVENTS
title: Identity Domain Events
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - entities.md
  - aggregates.md
  - invariants.md
  - commands/README.md
---

# Domain Events

Ce document constitue le catalogue canonique des événements métier produits par
Identity 1.0.

Un événement décrit un fait déjà survenu. Il n'ordonne jamais à un consommateur
d'effectuer une action.

Tout nom absent de ce catalogue est un signal interne, une demande
d'intégration, une projection ou une proposition future ; il ne fait pas partie
des Domain Events 1.0.

---

## Enveloppe commune

Chaque événement possède au minimum :

| Champ | Description |
|---|---|
| `EventId` | Identifiant unique de l'événement. |
| `EventName` | Nom canonique du fait. |
| `SchemaVersion` | Version de son contrat. |
| `OccurredAt` | Instant métier du fait. |
| `AggregateType` | Type de l'agrégat source. |
| `AggregateId` | Identifiant de l'agrégat source. |
| `AggregateVersion` | Version après application du fait. |
| `CorrelationId` | Identifiant du processus transversal. |
| `CausationId` | Commande ou événement ayant causé ce fait. |
| `ActorReference` | Référence auditable de l'acteur ou du workflow. |
| `WorkspaceId` | Contexte concerné lorsqu'il existe. |
| `Data` | Charge utile propre au fait. |

L'enveloppe technique de transport peut ajouter des informations sans modifier
le contrat métier.

---

## Confidentialité

Les événements ne contiennent jamais :

- mot de passe ou credential brute ;
- token de session, de refresh, d'invitation ou de vérification ;
- preuve d'authentification brute ;
- code MFA ;
- donnée d'appareil ou de réseau non nécessaire ;
- secret d'intégration.

Les adresses e-mail et autres données personnelles ne sont présentes que dans
un flux interne restreint lorsqu'un cas d'usage l'exige. Les événements publics
utilisent des identifiants ou empreintes non réversibles.

---

## User

| Événement | Producteur | Fait minimum |
|---|---|---|
| `UserCreated` | `CreateUser` | Identité créée en `PendingVerification`. |
| `UserEmailVerified` | `VerifyUserEmail` | Propriété de l'adresse principale prouvée. |
| `UserActivated` | `VerifyUserEmail` | Première transition vers `Active`. |
| `UserProfileUpdated` | `UpdateUserProfile` | Métadonnées publiques modifiées. |
| `UserEmailChanged` | `ChangeUserEmail` | Adresse principale vérifiée remplacée. |
| `UserDisabled` | `DisableUser` | Accès global suspendu. |
| `UserEnabled` | `EnableUser` | Accès global rétabli. |
| `UserRemoved` | `RemoveUser` | Identité logiquement retirée de manière terminale. |

`UserActivated` est réservé à la première activation. Une sortie de
`Disabled` produit `UserEnabled`.

### Sécurité du compte

| Événement | Producteur | Fait minimum |
|---|---|---|
| `UserCredentialsChanged` | workflow de changement de credential | Matériel d'authentification remplacé et version de sécurité incrémentée. |
| `UserAccountRecovered` | workflow de récupération | Contrôle du compte rétabli après une preuve de récupération. |

Ces événements ne contiennent aucun credential, facteur ou secret. Les échecs
de récupération relèvent de l'audit de sécurité, pas d'un fait de réussite.

---

## Membership

| Événement | Producteur | Fait minimum |
|---|---|---|
| `MembershipCreated` | `CreateMembership`, `AcceptInvitation` | Appartenance active créée avec un rôle courant. |
| `MembershipRoleChanged` | `ChangeMembershipRole` | Rôle courant remplacé. |
| `MembershipRoleTransferCompleted` | `TransferMembershipRole` | Rôle transféré atomiquement entre deux memberships. |
| `MembershipSuspended` | `SuspendMembership` | Accès au workspace temporairement suspendu. |
| `MembershipReactivated` | `ReactivateMembership` | Membership suspendu redevenu actif. |
| `MembershipRemoved` | `RemoveMembership` | Appartenance retirée administrativement. |
| `MembershipRestored` | `RestoreMembership`, `AcceptInvitation` | Appartenance retirée restaurée explicitement. |
| `MembershipLeft` | `LeaveWorkspace` | Appartenance quittée volontairement. |

`MembershipRemoved` et `MembershipLeft` produisent le même statut final mais
conservent des intentions et audits distincts.

---

## Role

| Événement | Producteur | Fait minimum |
|---|---|---|
| `RoleCreated` | `CreateRole` | Rôle créé dans un workspace. |
| `RoleMetadataUpdated` | `UpdateRoleMetadata` | Métadonnées descriptives modifiées. |
| `RolePermissionGranted` | `GrantPermissionToRole` | Permission explicitement ajoutée. |
| `RolePermissionRevoked` | `RevokePermissionFromRole` | Permission explicitement retirée. |
| `RoleAssignmentPolicyChanged` | `ChangeRoleAssignmentPolicy` | Politique d'attribution remplacée. |
| `RoleTransferPolicyChanged` | `ChangeRoleTransferPolicy` | Politique de transfert remplacée. |
| `RoleDisabled` | `DisableRole` | Efficacité du rôle suspendue. |
| `RoleEnabled` | `EnableRole` | Efficacité du rôle rétablie. |
| `RoleArchived` | `ArchiveRole` | Rôle retiré définitivement de l'usage courant. |

---

## Invitation

| Événement | Producteur | Fait minimum |
|---|---|---|
| `InvitationCreated` | `CreateInvitation` | Intention d'inviter enregistrée. |
| `InvitationSendRequested` | `SendInvitation` | Transmission demandée au canal externe. |
| `InvitationSent` | confirmation d'infrastructure | Transmission prise en charge, sans garantie de livraison. |
| `InvitationResendRequested` | `ResendInvitation` | Nouvelle intention de transmission enregistrée. |
| `InvitationTokenRotated` | `ResendInvitation` | Secret précédent invalidé et nouvelle empreinte associée. |
| `InvitationAccepted` | `AcceptInvitation` | Invitation consommée par son destinataire. |
| `InvitationDeclined` | `DeclineInvitation` | Invitation refusée par son destinataire. |
| `InvitationRevoked` | `RevokeInvitation` | Invitation retirée par une autorité du workspace. |
| `InvitationExpired` | `ExpireInvitation` | Fenêtre de validité terminée. |

`InvitationTokenRotated` ne contient que les identifiants de version et
empreintes non réversibles nécessaires à l'audit.

---

## Session

| Événement | Producteur | Fait minimum |
|---|---|---|
| `SessionCreated` | `CreateSession` | Session active créée après authentification. |
| `SessionRefreshed` | `RefreshSession` | Validité renouvelée et credential de refresh tournée. |
| `SessionElevated` | `ElevateSession` | Niveau d'assurance temporairement renforcé. |
| `SessionElevationExpired` | `ExpireSessionElevation` | Élévation devenue inefficace par le temps. |
| `SessionElevationTerminated` | `TerminateSessionElevation` | Élévation terminée avant son échéance. |
| `SessionExpired` | `ExpireSession` | Session devenue terminale par le temps. |
| `SessionRevoked` | `RevokeSession`, `RevokeAllUserSessions` | Session terminée explicitement. |
| `AllUserSessionsRevoked` | `RevokeAllUserSessions` | Décision globale de révocation appliquée. |

Les rotations et compromissions de credentials peuvent produire des signaux de
sécurité internes. Elles ne changent pas le vocabulaire du cycle de vie de la
`Session`.

---

## Refus et erreurs

Une commande refusée avant commit ne produit pas d'événement métier de cycle de
vie.

Les tentatives sensibles refusées peuvent produire un enregistrement d'audit ou
un signal de sécurité restreint, par exemple :

- tentative d'impersonation refusée ;
- rejeu de credential ;
- preuve expirée ;
- violation répétée d'une politique.

Ces signaux ne doivent jamais être confondus avec la réussite de la commande.

---

## Publication

Le changement d'état et l'événement correspondant sont enregistrés dans une
même transaction ou via une garantie d'atomicité équivalente.

Les effets externes utilisent une outbox transactionnelle. Les consommateurs
sont idempotents et les producteurs ignorent qui consomme leurs événements.
