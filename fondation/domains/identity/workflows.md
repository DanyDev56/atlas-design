---
id: IDN-WORKFLOWS
title: Identity Workflows
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - commands/README.md
  - events.md
  - permissions.md
  - invariants.md
  - api.md
---

# Workflows

Ce document décrit les processus Identity 1.0 qui coordonnent plusieurs commandes,
agrégats ou effets externes.

Un workflow ne contourne jamais les préconditions d'une commande. Il porte un
`CorrelationId`, conserve son état de reprise et traite chaque effet externe de
manière idempotente.

---

## Inscription et activation

```text
CreateUser
  ↓
UserCreated (PendingVerification)
  ↓
verification delivery requested
  ↓
EmailOwnershipProof validated
  ↓
VerifyUserEmail
  ↓
UserEmailVerified + UserActivated
  ↓
CreateSession
```

Règles :

- la réponse d'inscription ne révèle pas si une adresse est déjà utilisée dans
  un contexte exposé à l'énumération ;
- le token brut de vérification n'entre jamais dans les événements ;
- une preuve couvre le `User`, l'adresse courante, une finalité et une expiration ;
- l'activation et la consommation de la preuve sont atomiques ;
- la création d'un premier `Workspace` est une orchestration externe postérieure
  à l'activation.

---

## Authentification et création de session

```text
credentials presented to authentication component
  ↓
credentials validated
  ↓
AuthenticationProof issued
  ↓
CreateSession
  ↓
SessionCreated
  ↓
credentials returned by secure delivery layer
```

Le composant qui compare les credentials appartient à l'infrastructure de
sécurité d'Identity. Le domaine reçoit une `AuthenticationProof`, jamais un mot de
passe brut.

Une réponse d'échec reste volontairement peu descriptive afin d'éviter
l'énumération des comptes.

---

## Rafraîchissement de session

```text
active Session + one-time refresh credential
  ↓
RefreshSession
  ├── consume previous credential
  ├── rotate refresh family
  ├── verify UserSecurityVersion
  └── SessionRefreshed
```

Le rejeu d'une credential consommée compromet la famille concernée et peut
déclencher `RevokeAllUserSessions` selon la politique de risque.

---

## Élévation de session

```text
recent strong AuthenticationProof
  ↓
ElevateSession
  ↓
SessionElevated
  ├── time reached → ExpireSessionElevation → SessionElevationExpired
  └── early end    → TerminateSessionElevation → SessionElevationTerminated
```

L'élévation ne crée aucune permission. Son périmètre, son échéance, son nombre
d'actions et ses versions de sécurité sont vérifiés lors de chaque autorisation.

---

## Récupération de compte

```text
recovery requested
  ↓
opaque response
  ↓
RecoveryProof delivered and validated
  ↓
new credential registered
  ↓
UserSecurityVersion incremented
  ↓
UserCredentialsChanged
  ↓
EnableUser when the recovery authorizes it
  ↓
RevokeAllUserSessions
  ↓
UserAccountRecovered
```

Règles :

- la demande ne révèle jamais l'existence du compte ;
- la preuve est bornée, à usage unique et liée à une finalité ;
- le remplacement de credential et l'incrément de version sont atomiques ;
- toutes les anciennes sessions et familles de refresh deviennent invalides ;
- la récupération ne fait jamais sortir un `User` de `Removed` ;
- une notification de sécurité est envoyée par un consommateur externe.

---

## Changement d'adresse e-mail

```text
recent AuthenticationProof
  +
new EmailOwnershipProof
  ↓
ChangeUserEmail
  ├── replace verified primary email
  ├── increment UserSecurityVersion
  └── UserEmailChanged
  ↓
RevokeAllUserSessions according to policy
```

L'ancienne adresse reste inchangée tant que la nouvelle n'est pas prouvée. Les
deux adresses reçoivent une notification de sécurité sans apparaître dans un
événement public.

---

## Invitation d'un membre

```text
CreateInvitation
  ↓
InvitationCreated
  ↓
SendInvitation
  ↓
InvitationSendRequested
  ↓
delivery adapter
  ↓
InvitationSent
```

La création et l'envoi restent deux intentions distinctes. Un échec de livraison
ne modifie pas silencieusement le statut métier de l'invitation.

---

## Acceptation d'une invitation

```text
valid Invitation + legitimate User
  ↓
validate Workspace and Role
  ↓
create or restore exactly one Membership
  ↓
mark Invitation Accepted
  ↓
commit state and events atomically
```

Le résultat valide est toujours :

```text
Accepted Invitation
AND
exactly one compatible active Membership
```

Si le destinataire ne possède pas encore de `User`, l'orchestration exécute
d'abord l'inscription et la preuve de l'adresse. `AcceptInvitation` ne crée pas
une identité non vérifiée implicitement.

---

## Suspension et réactivation d'un Membership

```text
SuspendMembership
  ↓
MembershipSuspended
  ↓
authorization invalidated

MembershipReactivationReadiness
  ↓
ReactivateMembership
  ↓
MembershipReactivated
```

Le rôle est conservé pendant la suspension. Aucune permission n'est effective et
aucune ancienne session ne récupère implicitement l'accès lors de la réactivation.

---

## Retrait, départ et restauration d'un Membership

`RemoveMembership` représente une décision administrative.

`LeaveWorkspace` représente la décision volontaire du membre.

Les deux conduisent à un membership `Removed`, mais produisent des événements
distincts. `RestoreMembership` est la seule voie de retour depuis cet état.

Avant toute perte du rôle owner, la continuité d'un owner actif est vérifiée sous
une version de gouvernance cohérente.

---

## Transfert de rôle

`TransferMembershipRole` modifie simultanément le membership source et le
membership cible.

Le workflow :

1. valide les consentements, approbations et politiques courantes ;
2. verrouille les deux memberships dans un ordre stable ;
3. vérifie la version de gouvernance du workspace ;
4. attribue le rôle de remplacement à la source ;
5. attribue le rôle transféré à la cible ;
6. incrémente les deux versions d'autorisation ;
7. enregistre un seul `MembershipRoleTransferCompleted` ;
8. rend les deux changements visibles dans un même commit.

---

## Désactivation et réactivation d'un User

`DisableUser` rend immédiatement toutes les autorisations du sujet inefficaces,
incrémente `UserSecurityVersion` et orchestre la révocation des sessions.

`EnableUser` exige une preuve de readiness et une nouvelle authentification. Il
ne recrée aucune session ou autorisation mise en cache.

---

## Retrait d'un User

Avant `RemoveUser`, tous les memberships actifs ou suspendus doivent avoir été
quittés ou retirés, et les responsabilités owner transférées.

Le retrait est terminal. Les traitements de rétention, d'anonymisation et
d'effacement sont déclenchés après le fait métier sans effacer les identifiants
d'audit indispensables.

---

## Changement d'état du Workspace

Identity consomme le contrat public minimal du domaine Workspace.

- `Active` permet les opérations ordinaires ;
- `Restricted` refuse l'accès ordinaire mais autorise les workflows de sécurité
  et de remédiation explicitement listés ;
- `Closed` rend toutes les autorisations contextuelles inefficaces et interdit
  les nouvelles invitations et affectations. Une session globale peut rester
  valide sans donner accès à ce workspace.

Identity conserve les memberships et rôles historiques. Il ne modifie jamais
l'état du `Workspace`.
