---
id: IDN-FOUNDATION-DECISION-RECORD
title: Decision Record
status: Living
owner: Product
version: 2.1.1
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - scope.md
  - model.md
  - entities.md
  - aggregates.md
  - relationships.md
  - value-objects.md
  - invariants.md
  - workflows.md
  - permissions.md
---

# Decision Record

## Objectif

Ce document enregistre les principales décisions de conception du bounded context `Identity`.

Il ne remplace pas les documents détaillés.

Il permet de comprendre :

- les choix structurants ;
- les alternatives écartées ;
- les raisons de la décision ;
- les conséquences attendues ;
- les points susceptibles d’évoluer.

---

# IDN-ADR-001 — Identity est un bounded context distinct

## Statut

```text
Accepted
```

## Contexte

L’identité, l’authentification, l’appartenance aux workspaces et l’autorisation contextuelle forment un ensemble cohérent de responsabilités.

## Décision

Créer un bounded context :

```text
Identity
```

Il possède :

- `User`
- `Membership`
- `Role`
- `Permission`
- `Invitation`
- `Session`

## Conséquences

- les règles d’identité sont centralisées ;
- les autres bounded contexts consomment des identifiants et décisions d’autorisation ;
- les concepts métier externes ne doivent pas être intégrés arbitrairement dans `Identity`.

---

# IDN-ADR-002 — Workspace appartient à un autre bounded context

## Statut

```text
Accepted
```

## Contexte

`Identity` doit contextualiser les rôles et memberships par workspace sans devenir propriétaire du cycle de vie complet du workspace.

## Décision

`Workspace` appartient à un autre bounded context.

`Identity` conserve uniquement :

```text
WorkspaceId
```

et les informations strictement nécessaires à ses invariants.

## Conséquences

- la création ou fermeture d’un workspace nécessite une intégration ;
- `Identity` ne possède pas les données métier du workspace ;
- certaines opérations nécessitent une orchestration inter-contextes.

---

# IDN-ADR-003 — Membership est l’agrégat central de l’autorisation contextuelle

## Statut

```text
Accepted
```

## Contexte

Un `User` peut appartenir à plusieurs workspaces et disposer d’un rôle différent dans chacun.

## Décision

Utiliser `Membership` comme lien métier entre :

```text
User
Workspace
Role
```

Chaîne d’autorisation :

```text
Session
↓
User
↓
Membership
↓
Role
↓
Permission
```

## Conséquences

- l’autorisation est évaluée dans un workspace ;
- un `User` ne possède pas directement un rôle global ;
- les changements de rôle s’appliquent au `Membership`.

---

# IDN-ADR-004 — Un seul Membership par User et Workspace

## Statut

```text
Accepted
```

## Décision

Garantir :

```text
UNIQUE(UserId, WorkspaceId)
```

## Conséquences

- une réintégration restaure le membership existant ;
- aucun doublon de membership ne doit être créé ;
- l’historique reste attaché à la même identité de membership.

---

# IDN-ADR-005 — Un Membership actif possède exactement un Role courant

## Statut

```text
Accepted
```

## Contexte

Le modèle initial cherche une autorisation simple et lisible.

## Décision

Un membership actif possède exactement un rôle courant.

```text
Membership.RoleId
```

## Conséquences

- changer de rôle remplace le rôle précédent ;
- un transfert doit donner un rôle de remplacement à la source ;
- les privilèges supplémentaires temporaires devront être modélisés autrement ;
- un futur modèle multi-rôles nécessiterait une décision distincte.

---

# IDN-ADR-006 — Les Permissions sont globales

## Statut

```text
Accepted
```

## Contexte

Les capacités telles que :

```text
workspace.members.change-role
workspace.roles.create
billing.invoices.read
```

doivent avoir une sémantique stable dans toute la plateforme.

## Décision

Les `Permission` appartiennent à un catalogue global.

Les `Role` leur sont associés dans un workspace.

## Conséquences

- une permission possède une identité globale ;
- un workspace ne crée pas librement de nouvelles permissions métier ;
- les rôles restent spécifiques aux workspaces ;
- l’évaluation est contextualisée par le membership.

---

# IDN-ADR-007 — Les Roles appartiennent à un seul Workspace

## Statut

```text
Accepted
```

## Décision

Chaque rôle possède :

```text
WorkspaceId
```

et ne peut pas être déplacé.

## Conséquences

- deux workspaces peuvent utiliser des rôles portant le même nom ;
- les contraintes d’unicité sont contextualisées ;
- les affectations doivent vérifier l’égalité des workspaces.

---

# IDN-ADR-008 — RoleId définit l’identité, pas le Name

## Statut

```text
Accepted
```

## Décision

Le rôle est identifié par :

```text
RoleId
```

Le nom est mutable.

## Conséquences

- un renommage ne crée pas un nouveau rôle ;
- les memberships restent associés au même rôle ;
- les règles d’autorisation ne dépendent jamais du nom ;
- les noms peuvent être localisés ou personnalisés.

---

# IDN-ADR-009 — SystemType définit les rôles structurels

## Statut

```text
Accepted
```

## Contexte

Le nom `Owner` ne doit pas être utilisé pour reconnaître le rôle propriétaire.

## Décision

Introduire :

```text
RoleSystemType
```

avec les valeurs 1.0 :

```text
None
Owner
DefaultMember
Guest
```

`ServiceAccount` est réservé à l'extension des identités non humaines décrite
dans `future.md`.

L’ownership dépend de :

```text
SystemType = Owner
```

## Conséquences

- un owner role peut être renommé ;
- un rôle custom nommé `Owner` ne devient pas owner ;
- les invariants structurels utilisent `SystemType`.

---

# IDN-ADR-010 — Un Workspace doit toujours conserver un owner actif

## Statut

```text
Accepted
```

## Décision

Garantir :

```text
ActiveOwnerCountAfterOperation >= 1
```

pour toute opération susceptible de réduire le nombre d’owners actifs.

## Commandes concernées

```text
ChangeMembershipRole
TransferMembershipRole
SuspendMembership
RemoveMembership
LeaveWorkspace
DisableUser
```

## Erreur commune

```text
WorkspaceMustHaveActiveOwner
```

## Conséquences

- les opérations concurrentes doivent être coordonnées ;
- la vérification doit être transactionnellement protégée ;
- une voie de récupération administrative doit exister.

---

# IDN-ADR-011 — Membership supprimé logiquement, jamais physiquement

## Statut

```text
Accepted
```

## Décision

La fin d’appartenance produit :

```text
Membership.Status = Removed
```

Le membership conserve :

- son identité ;
- son historique ;
- son utilisateur ;
- son workspace ;
- son dernier rôle connu.

## Conséquences

- une réintégration utilise `RestoreMembership` ;
- les audits restent cohérents ;
- les suppressions physiques relèvent de la rétention, pas du domaine courant.

---

# IDN-ADR-012 — RestoreMembership est distinct de ReactivateMembership

## Statut

```text
Accepted
```

## Décision

```text
Removed
↓
Active
```

relève de :

```text
RestoreMembership
```

Alors que :

```text
Suspended
↓
Active
```

relève de :

```text
ReactivateMembership
```

## Conséquences

- les intentions métier restent explicites ;
- les audits distinguent retour après départ et fin de suspension ;
- les validations peuvent différer.

---

# IDN-ADR-013 — LeaveWorkspace est distinct de RemoveMembership

## Statut

```text
Accepted
```

## Décision

`LeaveWorkspace` représente une décision volontaire du membre.

`RemoveMembership` représente une décision administrative ou système.

## Événements distincts

```text
MembershipLeft
MembershipRemoved
```

## Conséquences

- les audits distinguent les responsabilités ;
- les confirmations et authentifications peuvent différer ;
- la continuité owner reste vérifiée dans les deux cas.

---

# IDN-ADR-014 — Le transfert porte sur les rôles des Memberships

## Statut

```text
Accepted
```

## Contexte

Une commande spécialisée `TransferWorkspaceOwnership` avait été envisagée.

## Décision

Utiliser une commande générique :

```text
TransferMembershipRole
```

Le transfert peut concerner :

- owner ;
- billing administrator ;
- security administrator ;
- toute responsabilité transférable.

## Structure

```text
SourceMembership:
TransferredRole
↓
SourceReplacementRole

TargetMembership:
CurrentRole
↓
TransferredRole
```

## Conséquences

- l’ownership n’est pas un modèle séparé ;
- le transfert modifie deux memberships ;
- l’opération doit être atomique ;
- la source doit conserver un rôle.

---

# IDN-ADR-015 — Le transfert produit un événement sémantique unique

## Statut

```text
Accepted
```

## Décision

Produire :

```text
MembershipRoleTransferCompleted
```

plutôt que deux événements indépendants non coordonnés.

## Conséquences

- l’intention métier du transfert reste visible ;
- les consommateurs peuvent reconstruire les deux changements ;
- l’audit conserve une causalité unique.

---

# IDN-ADR-016 — La création d’une Invitation est séparée de son envoi

## Statut

```text
Accepted
```

## Décision

Séparer :

```text
CreateInvitation
```

et :

```text
SendInvitation
```

## Workflow

```text
CreateInvitation
↓
SendInvitation
↓
InvitationSendRequested
↓
Infrastructure
↓
InvitationSent
```

## Conséquences

- l’intention métier est distincte de l’intégration ;
- les retries d’infrastructure ne sont pas des renvois métier ;
- `ResendInvitation` reste une décision explicite.

---

# IDN-ADR-017 — Une Invitation ne peut être acceptée qu’une fois

## Statut

```text
Accepted
```

## Décision

Les états suivants sont terminaux :

```text
Accepted
Declined
Revoked
Expired
```

Une invitation acceptée ne peut plus être réutilisée.

## Conséquences

- l’acceptation est idempotente ;
- les tokens ne doivent pas permettre plusieurs créations ;
- les états terminaux sont protégés contre les transitions ultérieures.

---

# IDN-ADR-018 — Les tokens sont séparés des identifiants

## Statut

```text
Accepted
```

## Décision

Distinguer :

```text
InvitationId
InvitationToken

SessionId
SessionToken
```

## Conséquences

- les identifiants peuvent apparaître dans les événements ;
- les tokens ne doivent jamais apparaître en clair dans l’audit ou les événements ;
- seules des empreintes non réversibles sont persistées.

---

# IDN-ADR-019 — Les Commands expriment une intention métier

## Statut

```text
Accepted
```

## Décision

Une commande :

- exprime une intention ;
- valide les préconditions ;
- applique les invariants ;
- modifie un agrégat ou une coordination explicitement justifiée ;
- produit des événements ;
- ne retourne pas un read model.

Cycle :

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

## Conséquences

- éviter les commandes CRUD génériques ;
- préférer des noms tels que `SuspendMembership` ou `GrantPermissionToRole` ;
- documenter l’idempotence et la concurrence.

---

# IDN-ADR-020 — Les commandes cosmétiques de Role sont regroupées

## Statut

```text
Accepted
```

## Contexte

Des commandes séparées avaient été envisagées :

```text
RenameRole
UpdateRoleDescription
UpdateRoleDisplay
```

## Décision

Regrouper les propriétés descriptives dans :

```text
UpdateRoleMetadata
```

Champs concernés :

```text
Name
Description
DisplayColor
Icon
DisplayOrder
DocumentationUrl
```

## Conséquences

- moins de micro-commandes ;
- aucun impact sur l’autorisation ;
- aucun changement de permission ;
- aucun changement de membership ;
- un événement principal `RoleMetadataUpdated`.

---

# IDN-ADR-021 — Les changements comportementaux de Role restent explicites

## Statut

```text
Accepted
```

## Décision

Ne pas créer :

```text
UpdateRole
```

Utiliser des commandes explicites :

```text
UpdateRoleMetadata
ChangeRoleAssignmentPolicy
ChangeRoleTransferPolicy
GrantPermissionToRole
RevokePermissionFromRole
EnableRole
DisableRole
ArchiveRole
```

## Conséquences

- autorisation plus précise ;
- audit lisible ;
- événements spécialisés ;
- concurrence mieux maîtrisée ;
- réduction des modifications accidentelles.

---

# IDN-ADR-022 — AssignmentMode est remplacé par RoleAssignmentPolicy

## Statut

```text
Accepted
```

## Contexte

Le modèle initial utilisait :

```text
AssignmentMode
```

avec des valeurs combinées :

```text
Manual
InvitationOnly
SystemOnly
ManualOrInvitation
ManualOrSynchronization
```

Cette approche créait une explosion combinatoire dès que de nouveaux workflows apparaissaient.

Elle ne permettait pas non plus de représenter correctement :

- les types de cibles ;
- les permissions requises ;
- les niveaux d’authentification ;
- les approbations ;
- les acceptations ;
- les limites ;
- les exigences continues ;
- la récupération administrative.

## Décision

Remplacer `AssignmentMode` par :

```text
RoleAssignmentPolicy
```

La liste des workflows autorisés est représentée par :

```text
AllowedSources: Set<RoleAssignmentSource>
```

## Structure

```text
RoleAssignmentPolicy
├── AllowedSources
├── RequiredAssignmentPermission
├── AllowedTargetIdentityTypes
├── RequiresHumanAssignee
├── ActorAuthenticationRequirement
├── TargetAuthenticationRequirement
├── ApprovalPolicy
├── TargetAcceptancePolicy
├── AssignmentCapacity
├── AssignmentDurationPolicy
├── ContinuousRequirements
├── AdministrationRecoveryPolicy
├── ControlPolicy
└── Version
```

## Conséquences

- les règles d’attribution sont centralisées ;
- les commandes consommatrices chargent la politique courante ;
- les approbations et acceptations sont liées à une version ;
- les affectations existantes peuvent être évaluées après un changement ;
- la commande `ChangeRoleAssignmentPolicy` remplace la politique complète.

---

# IDN-ADR-023 — TransferMode est remplacé par RoleTransferPolicy

## Statut

```text
Accepted
```

## Contexte

Le modèle initial utilisait :

```text
TransferMode
```

avec des valeurs telles que :

```text
NotTransferable
Transferable
TransferableWithAcceptance
TransferableWithApproval
TransferableOnlyByOwner
TransferableOnlyBySystem
```

Cette représentation ne pouvait pas combiner correctement :

- confirmation de la source ;
- acceptation de la cible ;
- plusieurs approbations ;
- restrictions d’initiateur ;
- exigences d’authentification ;
- types de cible ;
- rôle de remplacement ;
- continuité ;
- récupération administrative.

## Décision

Remplacer `TransferMode` par :

```text
RoleTransferPolicy
```

## Structure

```text
RoleTransferPolicy
├── Transferability
├── AllowedSources
├── AllowedInitiators
├── RequiredTransferPermission
├── SourceConfirmationPolicy
├── TargetAcceptancePolicy
├── ApprovalPolicy
├── ActorAuthenticationRequirement
├── SourceAuthenticationRequirement
├── TargetAuthenticationRequirement
├── AllowedTargetIdentityTypes
├── SourceReplacementRolePolicy
├── InitiatorParticipationPolicy
├── TransferWindowPolicy
├── ContinuityPolicy
├── AdministrationRecoveryPolicy
├── ControlPolicy
└── Version
```

## Conséquences

- les conditions de transfert deviennent explicites ;
- `TransferMembershipRole` doit charger la politique courante ;
- les preuves sont liées à une version ;
- le rôle de remplacement de la source est validé ;
- la continuité owner est protégée atomiquement.

---

# IDN-ADR-024 — Attribution et transfert utilisent deux politiques distinctes

## Statut

```text
Accepted
```

## Contexte

Un transfert attribue bien le rôle à une cible, mais il possède des contraintes spécifiques liées à la source et à la continuité.

## Décision

Conserver deux politiques :

```text
RoleAssignmentPolicy
RoleTransferPolicy
```

Un transfert est autorisé si et seulement si :

```text
RoleTransferPolicy allows the transfer
AND
RoleAssignmentPolicy allows assignment through RoleTransfer
```

## Conséquences

- aucune politique ne remplace l’autre ;
- `AllowedSources` doit contenir `RoleTransfer` pour un rôle transférable ;
- les types de cible autorisés par les deux politiques doivent être compatibles ;
- la commande doit vérifier les deux versions.

---

# IDN-ADR-025 — Les politiques de Role sont des Value Objects immuables

## Statut

```text
Accepted
```

## Décision

Modéliser :

```text
RoleAssignmentPolicy
RoleTransferPolicy
```

comme des `Value Objects` immuables.

Une modification produit :

```text
PreviousPolicy
↓
NewPolicy
```

## Conséquences

- validation globale de la valeur finale ;
- égalité par valeur ;
- comparaison claire des anciennes et nouvelles politiques ;
- aucun patch partiel n’entre directement dans l’agrégat ;
- l’application peut transformer un patch d’API en politique complète.

---

# IDN-ADR-026 — Les politiques sont versionnées

## Statut

```text
Accepted
```

## Décision

Introduire :

```text
AssignmentPolicyVersion
TransferPolicyVersion
```

## Conséquences

Les éléments suivants doivent référencer les versions pertinentes :

- invitation ;
- approbation ;
- acceptation ;
- confirmation source ;
- transfert planifié ;
- affectation planifiée ;
- commande concurrente.

Une preuve émise sous une ancienne politique peut être invalidée.

---

# IDN-ADR-027 — IsExclusive est dérivé du maximum

## Statut

```text
Accepted
```

## Contexte

Stocker à la fois :

```text
IsExclusive
MaximumActiveAssignments
```

crée deux sources de vérité.

## Décision

Dériver :

```text
IsExclusive :=
MaximumActiveAssignments == 1
```

## Conséquences

- suppression des incohérences ;
- l’exclusivité reste facilement exposable ;
- les validations utilisent la capacité maximale.

---

# IDN-ADR-028 — Les policies sont obligatoires à la création du Role

## Statut

```text
Accepted
```

## Décision

`CreateRole` exige :

```text
AssignmentPolicy
TransferPolicy
```

Un rôle ne peut pas être créé avec une politique implicite ou absente.

## Conséquences

- le comportement initial est explicite ;
- aucune valeur par défaut cachée ;
- les rôles système appliquent immédiatement leurs protections ;
- `RoleCreated` contient les politiques initiales.

---

# IDN-ADR-029 — Un nouveau Role ne reçoit aucune Permission implicitement

## Statut

```text
Accepted
```

## Décision

Après `CreateRole` :

```text
Permission assignments = empty
```

Les permissions sont ajoutées avec :

```text
GrantPermissionToRole
```

## Conséquences

- création et autorisation restent séparées ;
- audit précis ;
- aucun privilège implicite ;
- le bootstrap doit orchestrer plusieurs commandes.

---

# IDN-ADR-030 — Un nouveau Role n’est assigné à aucun Membership

## Statut

```text
Accepted
```

## Décision

Après `CreateRole` :

```text
Membership assignments = empty
```

## Conséquences

- l’affectation reste une intention séparée ;
- les politiques sont vérifiées par les commandes d’affectation ;
- le bootstrap owner nécessite une orchestration explicite.

---

# IDN-ADR-031 — Les exigences d’authentification distinguent acteur et cible

## Statut

```text
Accepted
```

## Contexte

Une valeur unique telle que :

```text
RequiredAuthenticationLevel
```

ne permet pas de savoir à qui elle s’applique.

## Décision

Utiliser au minimum :

```text
ActorAuthenticationRequirement
TargetAuthenticationRequirement
```

Dans les transferts, distinguer également :

```text
SourceAuthenticationRequirement
ApproverAuthenticationRequirement
```

## Conséquences

- les règles sont non ambiguës ;
- les rôles sensibles peuvent exiger des niveaux différents ;
- les preuves d’authentification sont vérifiées par participant.

---

# IDN-ADR-032 — Les exigences ponctuelles et continues sont distinctes

## Statut

```text
Accepted
```

## Décision

Une exigence d’authentification ou de conformité précise sa portée :

```text
AtAssignment
Continuous
AtAssignmentAndContinuous
```

## Conséquences

- une condition vérifiée lors de l’attribution n’est pas automatiquement continue ;
- les pertes de conformité peuvent déclencher des workflows dédiés ;
- aucun rôle n’est retiré silencieusement par la politique elle-même.

---

# IDN-ADR-033 — Les affectations existantes ne sont jamais corrigées silencieusement

## Statut

```text
Accepted
```

## Contexte

Une nouvelle politique peut rendre des memberships existants non conformes.

## Décision

`ChangeRoleAssignmentPolicy` :

- analyse les affectations existantes ;
- produit un résumé de conformité ;
- peut exiger un plan de remédiation ;
- ne modifie aucun membership.

## Conséquences

Les corrections utilisent des commandes explicites telles que :

```text
ChangeMembershipRole
SuspendMembership
RemoveMembership
TransferMembershipRole
```

---

# IDN-ADR-034 — Les preuves sensibles couvrent exactement l’intention

## Statut

```text
Accepted
```

## Décision

Une confirmation, approbation ou acceptation doit référencer les éléments précis qu’elle couvre.

Pour un transfert :

```text
SourceMembershipId
TargetMembershipId
TransferredRoleId
SourceReplacementRoleId
AssignmentPolicyVersion
TransferPolicyVersion
```

## Conséquences

- une preuve ne peut pas être réutilisée pour une autre cible ;
- un changement de rôle de remplacement invalide la preuve ;
- un changement de politique peut exiger une nouvelle preuve.

---

# IDN-ADR-035 — Les commandes consommatrices rechargent la politique courante

## Statut

```text
Accepted
```

## Décision

Toute commande d’attribution ou de transfert recharge :

```text
Current RoleAssignmentPolicy
Current RoleTransferPolicy when applicable
```

## Conséquences

- une invitation ne garantit pas définitivement l’éligibilité ;
- une approbation ancienne peut devenir invalide ;
- une opération planifiée est réévaluée à l’exécution ;
- les versions attendues protègent la concurrence.

---

# IDN-ADR-036 — Le bootstrap est une orchestration, pas une commande monolithique

## Statut

```text
Accepted
```

## Décision

Le bootstrap d’un workspace orchestre plusieurs intentions :

```text
Create Workspace
Create Owner Role
Grant Owner Permissions
Create Default Member Role
Grant Default Member Permissions
Create Owner Membership
```

## Conséquences

- les commandes restent cohérentes ;
- la visibilité du workspace doit attendre les invariants minimaux ;
- une transaction ou saga peut coordonner le workflow ;
- les compensations doivent être explicites.

---

# IDN-ADR-037 — Les événements ne contiennent jamais les tokens bruts

## Statut

```text
Accepted
```

## Décision

Les événements et audits peuvent contenir :

```text
InvitationId
SessionId
TokenFingerprint
```

mais jamais :

```text
RawInvitationToken
RawSessionToken
Password
MfaSecret
```

## Conséquences

- réduction du risque de fuite ;
- les intégrations utilisent des références sûres ;
- les tokens ne peuvent pas être reconstruits depuis les événements.

---

# IDN-ADR-038 — Les effets externes utilisent une outbox transactionnelle

## Statut

```text
Accepted
```

## Décision

Les événements de domaine sont enregistrés dans la même transaction que l’état de l’agrégat.

La publication intervient après commit.

## Conséquences

- pas d’événement perdu après succès ;
- pas d’événement publié si la transaction échoue ;
- les handlers doivent être idempotents.

---

# IDN-ADR-039 — L’idempotence fait partie du contrat des Commands

## Statut

```text
Accepted
```

## Décision

Chaque commande modifiante définit :

- une clé idempotente ;
- une empreinte d’intention ;
- le comportement d’un retry ;
- l’erreur en cas de réutilisation conflictuelle.

## Conséquences

- les retries réseau sont sûrs ;
- les événements ne sont pas dupliqués ;
- les dates métier initiales sont conservées ;
- les effets externes ne sont pas répétés.

---

# IDN-ADR-040 — La concurrence optimiste est appliquée aux agrégats

## Statut

```text
Accepted
```

## Décision

Les commandes sensibles utilisent :

```text
ExpectedAggregateVersion
```

ou des versions spécialisées lorsque nécessaire.

## Conséquences

- prévention des écritures perdues ;
- réévaluation après conflit ;
- protection contre les politiques obsolètes ;
- coordination renforcée pour les invariants multi-agrégats.

---

# IDN-ADR-041 — Le cycle de vie User 1.0 possède quatre états

## Statut

```text
Accepted
```

## Décision

```text
PendingVerification -> Active <-> Disabled -> Removed
Active ------------------------------------> Removed
```

`Removed` est terminal. Aucun état `Suspended`, `Locked`, `Deleted` ou
`PendingDeletion` n'appartient à `UserStatus` 1.0.

## Conséquences

- toutes les commandes utilisent le même vocabulaire ;
- l'activation initiale est distincte de la réactivation ;
- le retrait ne peut pas être annulé.

---

# IDN-ADR-042 — Le verrouillage d'authentification est séparé du statut User

## Statut

```text
Accepted
```

## Décision

`AuthenticationLockStatus = Unlocked | TemporarilyLocked` protège le point
d'authentification sans modifier `UserStatus`.

## Conséquences

- un verrou temporaire ne déclenche pas un faux cycle de vie métier ;
- la désactivation globale reste portée par `DisableUser` ;
- les sessions peuvent être révoquées sans inventer un statut de compte.

---

# IDN-ADR-043 — L'adresse principale est unique et vérifiée avant activation

## Statut

```text
Accepted
```

## Décision

Une adresse normalisée identifie au maximum un `User` non retiré. Un `User`
`Active` possède obligatoirement une adresse principale `Verified`.

## Conséquences

- `CreateUser` commence en `PendingVerification` ;
- `VerifyUserEmail` réalise la première activation ;
- `ChangeUserEmail` ne commit qu'une nouvelle adresse déjà prouvée ;
- la concurrence est protégée par une contrainte d'unicité.

---

# IDN-ADR-044 — Identity 1.0 crée uniquement des HumanUser

## Statut

```text
Accepted
```

## Décision

`IdentityType = HumanUser` est la seule valeur créable en 1.0. Les comptes de
service, machines, intégrations et agents sont réservés dans `future.md`.

## Conséquences

- aucune identité technique n'hérite implicitement des sessions humaines ;
- `ServiceAccount` n'est pas un acteur créable par les commandes 1.0 ;
- une extension future devra définir son propre cycle de vie et ses preuves.

---

# IDN-ADR-045 — RemoveUser est logique, terminal et distinct de l'effacement

## Statut

```text
Accepted
```

## Décision

`RemoveUser` retire logiquement l'identité après suppression de ses accès
courants. L'anonymisation, la rétention et l'effacement des données personnelles
relèvent d'un workflow légal séparé.

## Conséquences

- les références d'audit restent stables ;
- aucune restauration du `User` n'est possible ;
- le retrait révoque toutes les sessions ;
- l'effacement ne doit pas casser l'intégrité des événements historiques.

---

# IDN-ADR-046 — Archived est l'unique état terminal du Role

## Statut

```text
Accepted
```

## Décision

`RoleStatus = Active | Disabled | Archived`. Identity 1.0 ne définit pas
`Deleted` ou `Removed` pour un rôle.

## Conséquences

- un rôle archivé est immuable et ne fournit plus de permission effective ;
- les références historiques sont conservées ;
- une recréation produit un nouveau `RoleId`.

---

# IDN-ADR-047 — Le catalogue 1.0 utilise des permissions fines

## Statut

```text
Accepted
```

## Décision

Les commandes sensibles possèdent une clé canonique distincte. Les clés
génériques `workspace.members.manage` et `workspace.roles.manage` ne font pas
partie du catalogue 1.0.

Les variantes owner, système, externes ou privilégiées sont traitées par des
politiques contextuelles et des approbations, pas par une explosion de clés.

## Conséquences

- moindre privilège explicite ;
- catalogue stable et testable ;
- une seule clé requise par intention ;
- le rôle owner conserve le socle complet des permissions Identity de workspace.

---

# IDN-ADR-048 — Identity dépend d'un contrat public minimal de Workspace

## Statut

```text
Accepted
```

## Décision

Identity ne lit pas le modèle interne de `Workspace`. Il consomme uniquement le
contrat versionné `getWorkspaceAccessContext` et le fait public
`WorkspaceAccessStateChanged` définis dans `api.md`.

## Conséquences

- les références sont validées sans couplage de modèle ;
- l'autorisation refuse par défaut un contexte absent ou inutilisable ;
- la fermeture et les changements de gouvernance invalident les décisions en
  cache.

---

# IDN-ADR-049 — Un refus de commande n'est pas un Domain Event de réussite

## Statut

```text
Accepted
```

## Décision

Une commande refusée avant commit ne publie aucun événement métier de cycle de
vie. Une tentative sensible peut produire un signal d'audit ou de sécurité
restreint.

## Conséquences

- les consommateurs ne confondent pas tentative et changement d'état ;
- les signaux de sécurité restent séparés du catalogue `events.md` ;
- les erreurs publiques ne divulguent aucun secret.

---

# Décisions dépréciées

## AssignmentMode

Statut :

```text
Deprecated
```

Remplacé par :

```text
RoleAssignmentPolicy.AllowedSources
```

---

## TransferMode

Statut :

```text
Deprecated
```

Remplacé par :

```text
RoleTransferPolicy
```

---

## IsExclusive comme propriété persistée

Statut :

```text
Deprecated
```

Remplacé par :

```text
MaximumActiveAssignments = 1
```

---

## RenameRole

Statut :

```text
Superseded
```

Remplacé par :

```text
UpdateRoleMetadata
```

---

## UpdateRoleDescription

Statut :

```text
Superseded
```

Remplacé par :

```text
UpdateRoleMetadata
```

---

## TransferWorkspaceOwnership

Statut :

```text
Rejected
```

Remplacé par :

```text
TransferMembershipRole
```

---

## UpdateRole générique

Statut :

```text
Rejected
```

Remplacé par des commandes explicites.

---

# Évolutions reportées

Les questions non arbitrées ne font pas partie du contrat 1.0. Elles sont
classées avec leurs contraintes dans [`future.md`](future.md).

---

# Checklist de mise à jour

Lorsqu’une nouvelle décision est ajoutée :

```text
Decision has a unique ID
Context is documented
Decision is explicit
Consequences are documented
Rejected alternatives are identified when useful
Related documents are updated
Deprecated concepts are marked
Commands and events remain coherent
Invariants are updated
Glossary is updated when terminology changes
```

---

## Synthèse actuelle

Le modèle `Identity` repose sur les décisions structurantes suivantes :

```text
Membership is the central contextual authorization aggregate
Role belongs to one Workspace
Permission is global
Membership has one current Role
Role identity is RoleId
SystemType defines structural meaning
Workspace always keeps an active Owner
Role assignment rules live in RoleAssignmentPolicy
Role transfer rules live in RoleTransferPolicy
Policies are immutable and versioned
Role creation grants no Permission
Role creation assigns no Membership
Commands express explicit business intentions
Events and state commit atomically
User lifecycle is PendingVerification, Active, Disabled, Removed
Authentication lock is separate from UserStatus
Identity 1.0 creates HumanUser only
Role Archived state is terminal
Fine-grained permission keys are canonical
Workspace is consumed through a minimal public contract
```
