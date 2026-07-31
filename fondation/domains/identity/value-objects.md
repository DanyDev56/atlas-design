---
id: IDN-FOUNDATION-VALUE-OBJECTS
title: Value Objects
status: Draft
owner: Product
version: 2.0.0
last_updated: 2026-07-31

references:
  - README.md
  - entities.md
  - aggregates.md
  - relationships.md
  - invariants.md
  - permissions.md
  - value-objects/RoleAssignmentPolicy.md
  - value-objects/RoleTransferPolicy.md
---

# Value Objects

## Objectif

Ce document inventorie les principaux `Value Objects` du bounded context `Identity`.

Un `Value Object` :

- n’a pas d’identité métier propre ;
- est défini par ses valeurs ;
- est immuable ;
- applique ses propres validations ;
- peut être remplacé, mais non muté arbitrairement ;
- protège le domaine contre les représentations primitives ambiguës.

---

## Principes

### Égalité par valeur

Deux instances sont égales lorsque leurs valeurs métier normalisées sont égales.

---

### Immutabilité

Toute modification produit une nouvelle valeur.

```text
PreviousValue
↓
NewValue
```

---

### Validation à la construction

Un `Value Object` invalide ne doit pas pouvoir être créé.

---

### Normalisation

La représentation interne doit être canonique lorsque plusieurs entrées équivalentes sont possibles.

---

### Absence de dépendance infrastructurelle

Un `Value Object` ne dépend pas directement :

- d’une base de données ;
- d’un transport HTTP ;
- d’une file de messages ;
- d’un service de notification ;
- d’une projection ;
- d’un framework.

---

# Identifiants

Les identifiants métier sont représentés par des types distincts.

Ils ne doivent pas être échangés librement.

---

## UserId

Identifie un `User`.

```text
UserId != MembershipId
UserId != SessionId
UserId != InvitationId
```

---

## MembershipId

Identifie un `Membership`.

---

## RoleId

Identifie un `Role`.

Le nom du rôle ne constitue pas son identité.

---

## PermissionId

Identifie une `Permission` globale.

---

## InvitationId

Identifie une `Invitation`.

Il est distinct du token d’invitation.

---

## SessionId

Identifie une `Session`.

Il est distinct du token ou du cookie de session.

---

## WorkspaceId

Identifie un `Workspace` appartenant à un autre bounded context.

`Identity` utilise cet identifiant comme référence externe.

---

## CorrelationId

Relie plusieurs commandes et événements appartenant à un même workflow.

---

## RequestId

Identifie une demande idempotente.

Des types spécialisés peuvent être utilisés :

```text
CreationRequestId
RemovalRequestId
TransferRequestId
PolicyChangeRequestId
```

---

# Tokens et preuves secrètes

Les tokens doivent rester distincts des identifiants métier.

---

## InvitationToken

Secret présenté lors de l’acceptation d’une invitation.

Il ne doit pas apparaître :

- en clair dans les événements ;
- en clair dans l’audit ;
- dans les logs ;
- dans les messages d’erreur ;
- dans les URLs conservées durablement.

Une empreinte cryptographique ou une valeur dérivée doit être stockée.

---

## SessionToken

Secret permettant de retrouver ou valider une session.

Il est distinct de `SessionId`.

---

## TokenHash

Représentation persistable et non réversible d’un token.

---

## AuthenticationProof

Preuve structurée d’une authentification ou réauthentification.

Elle peut contenir :

```text
AuthenticationProof
├── AuthenticationContextId
├── AuthenticatedAt
├── AuthenticationLevel
├── Factors
├── ValidUntil
└── SubjectId
```

Elle ne contient jamais les secrets des facteurs.

---

# Temps et périodes

---

## Instant

Représente un instant absolu.

Il doit être stocké dans une représentation non ambiguë, généralement UTC.

---

## DateRange

Structure :

```text
DateRange
├── Start
└── End
```

Condition :

```text
Start <= End
```

---

## ValidityPeriod

Période pendant laquelle une preuve, une approbation ou une affectation est valide.

---

## Duration

Durée normalisée.

Exemples :

```text
10 minutes
24 hours
30 days
```

---

## ExpirationDate

Date après laquelle une ressource n’est plus valide.

L’expiration métier est distincte de la suppression ou de la rétention.

---

# User

---

## EmailAddress

Adresse e-mail normalisée et validée.

La politique de comparaison doit être explicite.

---

## DisplayName

Nom de présentation.

Il ne constitue pas une identité juridique ou technique.

---

## UserStatus

Valeurs recommandées :

```text
Active
Disabled
Removed
```

La valeur exacte dépend du modèle de cycle de vie retenu.

---

## IdentityType

Valeurs possibles :

```text
HumanUser
ServiceAccount
MachineIdentity
ExternalUser
GuestUser
FederatedUser
InternalUser
```

`IdentityType` est utilisé notamment par `RoleAssignmentPolicy`.

---

## AuthenticationLevel

Valeurs possibles :

```text
Standard
RecentAuthentication
Mfa
PhishingResistantMfa
HardwareBacked
```

---

## AuthenticationRequirement

Structure recommandée :

```text
AuthenticationRequirement
├── Level
├── MaximumAuthenticationAge
├── EnforcementScope
└── RevalidationRequired
```

---

## AuthenticationEnforcementScope

Valeurs :

```text
AtAssignment
Continuous
AtAssignmentAndContinuous
```

Le terme `Assignment` peut être généralisé lorsque le même objet est utilisé dans d’autres workflows.

---

# Membership

---

## MembershipStatus

Valeurs :

```text
Active
Suspended
Removed
```

---

## MembershipCreationSource

Valeurs recommandées :

```text
Invitation
WorkspaceCreation
ManualAdministration
SystemProvisioning
ExternalSynchronization
AdministrativeRecovery
Migration
```

---

## MembershipRemovalSource

Valeurs recommandées :

```text
ManualAdministration
WorkspaceGovernance
SecurityWorkflow
ComplianceWorkflow
ExternalSynchronization
SystemProvisioning
ContractTermination
AdministrativeRecovery
WorkspaceClosure
```

---

## MembershipRemovalReason

Valeurs recommandées :

```text
AccessNoLongerRequired
EmploymentEnded
ContractEnded
OrganizationLeft
PolicyViolation
SecurityDecision
ComplianceDecision
ExternalDirectoryRemoved
AdministrativeCorrection
DuplicateResolution
WorkspaceReorganization
WorkspaceClosure
Other
```

---

## MembershipSuspensionReason

Valeurs possibles :

```text
SecurityReview
ComplianceReview
TemporaryLeave
AdministrativeDecision
ExternalSynchronization
AccessInvestigation
Other
```

---

## LeaveConfirmation

Structure recommandée :

```text
LeaveConfirmation
├── ConfirmationId
├── ConfirmedAt
├── AuthenticationContext
├── ExpectedMembershipVersion
└── AcknowledgedConsequences
```

---

## LeaveReadiness

Valeurs :

```text
Ready
ReadyWithWarnings
Blocked
RequiresTransfer
RequiresNoticePeriod
```

---

## MembershipRoleAssignment

Si l’historique des rôles est modélisé comme valeur :

```text
MembershipRoleAssignment
├── RoleId
├── AssignedAt
├── AssignedBy
├── AssignmentSource
├── AssignmentPolicyVersion
└── EndedAt
```

Le rôle courant reste porté par le `Membership`.

---

# Role

---

## RoleName

Nom métier du rôle.

Il doit être :

- non vide ;
- normalisable ;
- unique dans le `Workspace` après normalisation ;
- non trompeur ;
- compatible avec les noms réservés.

---

## NormalizedRoleName

Forme canonique utilisée pour les comparaisons et l’unicité.

---

## RoleDescription

Description facultative du rôle.

Elle ne doit jamais être utilisée comme règle d’autorisation.

---

## RoleDisplayColor

Couleur de présentation contrôlée.

Elle ne porte aucune sémantique d’autorisation.

---

## RoleIcon

Clé d’icône appartenant à un catalogue autorisé.

---

## RoleType

Valeurs :

```text
System
Custom
External
TemplateDerived
```

---

## RoleSystemType

Valeurs :

```text
None
Owner
DefaultMember
Guest
ServiceAccount
```

`RoleSystemType` définit la fonction structurelle.

L’ownership ne doit jamais être déduit du nom.

---

## RoleStatus

Valeurs recommandées :

```text
Active
Disabled
Archived
```

Un éventuel état `Removed` doit être distingué de l’archivage si le modèle le nécessite.

---

## RoleCreationSource

Valeurs :

```text
ManualAdministration
WorkspaceCreation
SystemProvisioning
ExternalSynchronization
TemplateInstantiation
Migration
AdministrativeRecovery
ProductBootstrap
```

---

## RoleMetadata

Structure possible :

```text
RoleMetadata
├── Name
├── Description
├── DisplayColor
├── Icon
├── DisplayOrder
└── DocumentationUrl
```

Les métadonnées ne modifient pas l’autorisation.

---

## RoleMetadataControl

Valeurs :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

---

# RoleAssignmentPolicy

`RoleAssignmentPolicy` décrit les conditions d’attribution d’un `Role` à un `Membership`.

Document détaillé :

```text
value-objects/RoleAssignmentPolicy.md
```

Structure :

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

---

## RoleAssignmentSource

Valeurs :

```text
ManualAdministration
Invitation
WorkspaceCreation
SystemProvisioning
ExternalSynchronization
AdministrativeRecovery
MembershipRestoration
MembershipReactivation
RoleTransfer
Migration
```

`AssignmentMode` est supprimé.

Les sources autorisées sont représentées par :

```text
Set<RoleAssignmentSource>
```

---

## AssignmentCapacity

Structure :

```text
AssignmentCapacity
├── MinimumActiveAssignments
└── MaximumActiveAssignments
```

---

## Exclusivité

`IsExclusive` n’est pas une valeur indépendante.

Elle est dérivée de :

```text
MaximumActiveAssignments = 1
```

---

## AssignmentDurationPolicy

Structure possible :

```text
AssignmentDurationPolicy
├── Mode
├── MinimumDuration
├── MaximumDuration
├── EndDateRequired
├── ReviewBeforeExpiration
├── RenewalAllowed
└── MaximumRenewalCount
```

---

## AssignmentDurationMode

Valeurs :

```text
PermanentOnly
TemporaryOnly
PermanentOrTemporary
```

---

## ContinuousAssignmentRequirement

Valeurs possibles :

```text
MfaEnabled
HumanIdentityMaintained
EmploymentRelationshipActive
ExternalDirectoryMembershipPresent
ComplianceTrainingValid
AccountNotCompromised
AccountNotDisabled
```

---

## AssignmentPolicyVersion

Version logique de la politique.

Elle doit être référencée par les décisions sensibles et les workflows différés.

---

# RoleTransferPolicy

`RoleTransferPolicy` décrit les conditions permettant de transférer un rôle entre deux memberships.

Document détaillé :

```text
value-objects/RoleTransferPolicy.md
```

Structure :

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

---

## Transferability

Valeurs :

```text
NotTransferable
Transferable
```

`TransferMode` est supprimé.

Les conditions détaillées appartiennent à `RoleTransferPolicy`.

---

## RoleTransferSource

Valeurs possibles :

```text
CurrentHolderInitiated
TargetInitiated
WorkspaceAdministration
WorkspaceGovernance
SecurityWorkflow
ExternalSynchronization
SystemProvisioning
AdministrativeRecovery
WorkspaceClosure
Migration
```

---

## RoleTransferInitiatorType

Valeurs :

```text
CurrentHolder
TargetMembership
WorkspaceOwner
AuthorizedMember
SystemActor
ExternalAuthority
PlatformAdministrator
```

---

## SourceReplacementRolePolicy

Structure :

```text
SourceReplacementRolePolicy
├── Required
├── AllowedRoleIds
├── AllowedSystemTypes
├── ForbiddenRoleIds
├── MustBeAssignableToSource
├── MustRemainActive
└── MayEqualTargetPreviousRole
```

---

## TransferContinuityPolicy

Structure :

```text
TransferContinuityPolicy
├── NoTransientGapAllowed
├── MinimumActiveAssignmentsAfterTransfer
├── MaximumActiveAssignmentsAfterTransfer
├── SourceMustRemainMember
├── TargetMustAlreadyBeActive
└── SessionsMustBeInvalidatedAtomically
```

---

## TransferPolicyVersion

Version logique de la politique de transfert.

Elle doit être référencée par :

- les confirmations ;
- les acceptations ;
- les approbations ;
- les transferts planifiés ;
- `TransferMembershipRole`.

---

# Approbations et consentements

---

## ApprovalPolicy

Structure générique :

```text
ApprovalPolicy
├── MinimumApprovalCount
├── RequiredApproverPermission
├── RequiredApproverSystemType
├── SelfApprovalAllowed
├── SameActorMayProvideMultipleApprovals
├── RequesterMayApprove
├── TargetMayApprove
├── AuthenticationRequirement
├── ValidityDuration
├── SeparationOfDuties
└── PolicyVersionMustMatch
```

Elle peut être utilisée par :

- `RoleAssignmentPolicy` ;
- `RoleTransferPolicy` ;
- des workflows de sécurité ;
- des workflows de récupération.

---

## ApprovalProof

Structure :

```text
ApprovalProof
├── ApprovalId
├── ApprovedBy
├── ApprovedAt
├── ValidUntil
├── Subject
├── PolicyVersion
├── AuthenticationContext
└── Scope
```

---

## TargetAcceptancePolicy

Structure :

```text
TargetAcceptancePolicy
├── Required
├── AuthenticationRequirement
├── ValidityDuration
├── AcknowledgementType
├── PolicyVersionMustMatch
└── ConsequencesMustBeAcknowledged
```

---

## AcceptanceProof

Structure :

```text
AcceptanceProof
├── AcceptanceId
├── AcceptedBy
├── AcceptedAt
├── ValidUntil
├── Subject
├── PolicyVersion
├── AuthenticationContext
└── AcknowledgementType
```

---

## SourceConfirmationPolicy

Utilisée principalement par `RoleTransferPolicy`.

Structure :

```text
SourceConfirmationPolicy
├── Required
├── AuthenticationRequirement
├── ValidityDuration
├── ExpectedSourceRoleVersion
├── AcknowledgementType
└── WaivableByRecovery
```

---

## SourceConfirmationProof

Structure :

```text
SourceConfirmationProof
├── ConfirmationId
├── ConfirmedBy
├── ConfirmedAt
├── ValidUntil
├── SourceMembershipId
├── TargetMembershipId
├── TransferredRoleId
├── SourceReplacementRoleId
├── AssignmentPolicyVersion
├── TransferPolicyVersion
└── AuthenticationContext
```

---

# Invitation

---

## InvitationStatus

Valeurs recommandées :

```text
Draft
Sent
Accepted
Declined
Revoked
Expired
```

Les états suivants sont terminaux :

```text
Accepted
Declined
Revoked
Expired
```

---

## InvitationEmail

Adresse e-mail destinataire de l’invitation.

Elle peut être distincte de l’adresse définitive du `User`, selon le workflow retenu.

---

## InvitationExpiration

Structure ou date indiquant la limite d’acceptation.

Condition :

```text
CurrentTime >= ExpirationDate
```

entraîne l’expiration métier.

---

## InvitationAcceptanceProof

Structure :

```text
InvitationAcceptanceProof
├── InvitationId
├── AcceptedBy
├── AcceptedAt
├── AuthenticationContext
├── ExpectedInvitationVersion
└── AcknowledgedWorkspace
```

---

# Session

---

## SessionStatus

Valeurs possibles :

```text
Active
Revoked
Expired
```

---

## SessionValidity

Structure :

```text
SessionValidity
├── IssuedAt
├── ExpiresAt
├── LastActivityAt
├── AbsoluteExpiration
└── IdleExpiration
```

---

## AuthorizationVersion

Version permettant d’invalider les décisions d’autorisation déjà mises en cache.

Elle peut être portée par :

- le `User` ;
- le `Membership` ;
- la `Session` ;
- une combinaison des trois.

---

## AuthenticationContext

Structure :

```text
AuthenticationContext
├── AuthenticationContextId
├── AuthenticatedAt
├── AuthenticationLevel
├── Factors
├── DeviceContext
└── ValidUntil
```

Aucun secret de facteur ne doit être stocké dans ce `Value Object`.

---

# Raisons et sources

Les raisons et sources sont des `Value Objects` ou enums structurés.

Ils doivent éviter les chaînes arbitraires lorsque les décisions métier doivent être analysées.

---

## ChangeReason

Exemples :

```text
SecurityHardening
OrganizationalChange
ComplianceRequirement
AdministrativeCorrection
ExternalSynchronization
Migration
Other
```

---

## ChangeSource

Exemples :

```text
ManualAdministration
WorkspaceGovernance
SecurityPolicy
CompliancePolicy
ExternalSynchronization
TemplateSynchronization
SystemProvisioning
Migration
AdministrativeRecovery
```

---

## CaseReference

Référence un dossier administratif, de sécurité ou de conformité.

Elle ne doit pas contenir le contenu confidentiel du dossier.

---

# Versions

---

## AggregateVersion

Version optimiste d’un agrégat.

Exemples :

```text
Role.Version
Membership.Version
Invitation.Version
```

---

## PolicyVersion

Version logique d’une politique.

Exemples :

```text
AssignmentPolicyVersion
TransferPolicyVersion
```

---

## ExternalVersion

Version fournie par une source externe.

Elle permet de rejeter les mises à jour obsolètes.

---

## TemplateVersion

Version du modèle ayant produit ou synchronisé une valeur.

---

# Idempotence

---

## IdempotencyKey

Clé logique utilisée pour reconnaître une demande déjà traitée.

Exemples :

```text
WorkspaceId + CreationRequestId
RoleId + PolicyChangeRequestId
MembershipId + RemovalRequestId
```

---

## IdempotencyFingerprint

Empreinte normalisée de l’intention.

Elle doit permettre de distinguer :

```text
same request retried
```

de :

```text
same key reused for another intention
```

---

# Décisions de modélisation

## Les identifiants sont séparés

Chaque concept possède son propre type d’identifiant.

---

## Les tokens ne sont pas des identifiants

Les tokens sont secrets et ne doivent pas apparaître dans les événements.

---

## Les politiques de rôle sont des Value Objects

```text
RoleAssignmentPolicy
RoleTransferPolicy
```

---

## AssignmentMode est supprimé

Il est remplacé par :

```text
AllowedSources
```

---

## TransferMode est supprimé

Il est remplacé par :

```text
RoleTransferPolicy
```

---

## IsExclusive est dérivé

```text
MaximumActiveAssignments = 1
```

---

## Les politiques sont immuables et versionnées

Les approbations, acceptations et opérations différées doivent référencer une version.

---

## Les exigences d’authentification sont structurées

Une simple valeur booléenne `RequiresMfa` serait trop limitée.

---

## Les raisons sont structurées

Les raisons importantes ne doivent pas être uniquement du texte libre.

---

## Les références externes sont séparées de l’identité interne

```text
RoleId != ExternalReference
UserId != ExternalDirectoryId
```

---

# Checklist générale

```text
Value Object has no independent identity
Value Object is immutable
Equality is based on normalized values
Construction validates all invariants
Primitive values are wrapped when ambiguity exists
Secrets are not exposed
Identifiers are strongly separated
External references remain distinct
Policy versions are explicit
Empty and absent values are distinguished
Collections are normalized
Order is ignored when not meaningful
```

---

## Synthèse

Les `Value Objects` protègent le modèle `Identity` contre :

- les primitives ambiguës ;
- les états invalides ;
- les comparaisons incohérentes ;
- les mélanges d’identifiants ;
- les politiques non structurées ;
- les tokens exposés ;
- les décisions non versionnées.

Les deux politiques centrales du `Role` sont désormais :

```text
RoleAssignmentPolicy
RoleTransferPolicy
```

Elles remplacent les anciens concepts trop limités :

```text
AssignmentMode
TransferMode
IsExclusive
```