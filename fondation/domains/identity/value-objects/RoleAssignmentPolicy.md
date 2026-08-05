---
id: IDN-VO-ROLE-ASSIGNMENT-POLICY
title: RoleAssignmentPolicy
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-31

type: ValueObject

references:
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../invariants.md
  - ../permissions.md
  - ../commands/CreateRole.md
  - ../commands/ChangeRoleAssignmentPolicy.md
  - ../commands/CreateMembership.md
  - ../commands/RestoreMembership.md
  - ../commands/ReactivateMembership.md
  - ../commands/ChangeMembershipRole.md
  - ../commands/TransferMembershipRole.md
---

# RoleAssignmentPolicy

## Question traitée

```text
Under which conditions may a Role
be assigned to a Membership?
```

`RoleAssignmentPolicy` décrit les règles métier qui doivent être satisfaites lorsqu’un `Membership` reçoit un `Role`.

Elle définit une politique.

Elle ne réalise aucune affectation.

---

## Responsabilité

La politique permet de déterminer :

- quels workflows peuvent attribuer le rôle ;
- quels types d’identité peuvent le recevoir ;
- quelle permission l’acteur doit posséder ;
- quelles authentifications sont requises ;
- si une approbation est obligatoire ;
- si la cible doit accepter ;
- combien de memberships actifs peuvent détenir le rôle ;
- si les affectations temporaires sont autorisées ;
- quelles exigences doivent rester satisfaites après l’attribution ;
- comment l’administration peut récupérer l’accès à un rôle critique.

---

## Non-responsabilités

`RoleAssignmentPolicy` ne détermine pas :

- les `Permission` accordées par le rôle ;
- la liste actuelle des détenteurs ;
- le statut du `Role` ;
- la politique de transfert ;
- l’exécution d’un transfert ;
- la création d’un `Membership` ;
- le cycle de vie d’une `Invitation` ;
- le cycle de vie d’une `Session` ;
- le rôle de remplacement d’un membre lors d’un transfert.

---

## Appartenance au modèle

```text
Role
└── AssignmentPolicy: RoleAssignmentPolicy
```

La politique appartient au `Role`.

Elle n’a pas d’identité propre indépendante.

Elle est comparée par valeur.

---

## Immutabilité

`RoleAssignmentPolicy` est immuable.

Une modification produit :

```text
PreviousAssignmentPolicy
↓
NewAssignmentPolicy
```

La politique courante est remplacée par une nouvelle valeur complète.

La commande correspondante est :

```text
ChangeRoleAssignmentPolicy
```

---

## Structure recommandée

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

## AllowedSources

Type recommandé :

```text
Set<RoleAssignmentSource>
```

`AllowedSources` décrit les workflows autorisés à attribuer le rôle.

Valeurs possibles :

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

---

## ManualAdministration

L’attribution est réalisée par un acteur humain autorisé.

Commandes typiques :

```text
CreateMembership
ChangeMembershipRole
RestoreMembership
ReactivateMembership
```

L’acteur doit satisfaire :

- l’autorisation générale de la commande ;
- `RequiredAssignmentPermission` lorsqu’elle est définie ;
- les éventuelles exigences d’authentification ;
- les éventuelles approbations.

---

## Invitation

L’attribution résulte de l’acceptation d’une `Invitation`.

Workflow conceptuel :

```text
CreateInvitation
↓
SendInvitation
↓
AcceptInvitation
↓
Create or Restore Membership
↓
Assign Role
```

L’acceptation d’une invitation doit toujours vérifier la politique courante.

Une invitation créée sous une ancienne version ne garantit pas que le rôle reste attribuable.

---

## WorkspaceCreation

L’attribution intervient lors du bootstrap d’un `Workspace`.

Exemple :

```text
Create Workspace
↓
Create Owner Role
↓
Create Owner Membership
```

Cette source doit être limitée aux workflows de bootstrap autorisés.

---

## SystemProvisioning

L’attribution est réalisée par un processus système interne.

Exemples :

```text
initial provisioning
automated account provisioning
internal recovery process
```

Le `SystemActor` doit être identifié et limité au périmètre autorisé.

---

## ExternalSynchronization

L’attribution provient d’une source externe autoritaire.

Exemples :

```text
SCIM
LDAP
Identity Provider
Human Resources Directory
External Governance System
```

Une attribution locale peut être interdite lorsque cette source contrôle le rôle.

---

## AdministrativeRecovery

L’attribution est réalisée pour restaurer l’administrabilité du `Workspace`.

Elle ne doit pas devenir un contournement ordinaire.

Elle peut exiger :

- une permission dédiée ;
- plusieurs approbations ;
- une authentification renforcée ;
- une référence de dossier ;
- un audit spécifique ;
- une notification de sécurité.

---

## MembershipRestoration

Le rôle est attribué ou réévalué lors de :

```text
RestoreMembership
```

Un membership restauré ne doit pas contourner la politique actuelle.

---

## MembershipReactivation

Le rôle est conservé ou réévalué lors de :

```text
ReactivateMembership
```

La réactivation doit vérifier que le membre reste éligible.

---

## RoleTransfer

Le rôle est attribué à la cible d’un :

```text
TransferMembershipRole
```

Cette source doit être présente lorsque :

```text
RoleTransferPolicy.Transferability = Transferable
```

Le transfert doit satisfaire :

```text
RoleAssignmentPolicy
AND
RoleTransferPolicy
```

---

## Migration

L’attribution est effectuée lors d’une migration contrôlée.

Cette source doit être :

- limitée dans le temps ;
- auditable ;
- versionnée ;
- désactivée après usage si nécessaire.

---

## Règle sur AllowedSources

Par défaut :

```text
AllowedSources must not be empty
```

Un rôle auquel aucune nouvelle affectation ne doit être possible devrait normalement être désactivé avec :

```text
DisableRole
```

Une politique vide ne doit pas servir implicitement de statut.

---

## RequiredAssignmentPermission

Type :

```text
PermissionId?
```

Cette propriété indique une permission supplémentaire nécessaire pour attribuer le rôle.

Exemple :

```text
workspace.roles.assign-owner
```

Condition :

```text
Actor has command Permission
AND
Actor has RequiredAssignmentPermission
```

---

## Absence de permission supplémentaire

Une valeur absente signifie :

```text
no additional Role-specific assignment Permission
```

Elle ne signifie pas :

```text
any actor may assign the Role
```

L’autorisation générale de la commande reste toujours obligatoire.

---

## Permission globale

La permission référencée appartient au catalogue global.

```text
Permission
is global
```

Son évaluation s’effectue dans le contexte du workspace de l’acteur.

```text
Actor Membership
+
Workspace
+
Role
+
Permission
```

---

## Validation de RequiredAssignmentPermission

La permission doit :

- exister ;
- être active ;
- être utilisable pour l’autorisation ;
- ne pas être supprimée ;
- ne pas être incompatible avec le rôle ;
- ne pas créer un cycle d’administration insoluble.

---

## Cycle d’autorisation

Configuration potentiellement problématique :

```text
Role A requires Permission P to assign
Permission P is granted only by Role A
No active Membership currently holds Role A
```

Le rôle devient impossible à attribuer par un workflow ordinaire.

La politique doit conserver une voie d’administration ou de récupération valide.

---

## AllowedTargetIdentityTypes

Type recommandé :

```text
Set<IdentityType>
```

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

La cible doit satisfaire au moins un type autorisé.

---

## IdentityType

`IdentityType` représente la nature métier de l’identité cible.

Il ne doit pas être déduit uniquement :

- de son adresse e-mail ;
- de son nom ;
- de son domaine ;
- d’un attribut non vérifié.

---

## Ensemble vide

Par défaut :

```text
AllowedTargetIdentityTypes must not be empty
```

Une politique n’autorisant aucune cible rendrait le rôle non attribuable.

Pour interrompre les affectations, préférer :

```text
DisableRole
```

---

## RequiresHumanAssignee

Type :

```text
Boolean
```

Lorsque :

```text
RequiresHumanAssignee = true
```

le rôle ne peut être attribué qu’à une identité humaine vérifiée.

Sont normalement exclus :

- comptes de service ;
- identités machine ;
- clients OAuth ;
- agents techniques ;
- intégrations ;
- utilisateurs synthétiques.

---

## Cohérence avec AllowedTargetIdentityTypes

Condition :

```text
RequiresHumanAssignee = true
```

implique que :

```text
AllowedTargetIdentityTypes
contains only human-compatible identity types
```

Une politique contradictoire doit être refusée.

---

## Authentification de l’acteur

Type :

```text
AuthenticationRequirement?
```

Propriété :

```text
ActorAuthenticationRequirement
```

Elle décrit le niveau d’authentification nécessaire pour l’acteur réalisant ou autorisant l’affectation.

---

## Authentification de la cible

Propriété :

```text
TargetAuthenticationRequirement
```

Elle décrit le niveau exigé pour la cible.

Les exigences de l’acteur et de la cible doivent rester séparées.

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

## MaximumAuthenticationAge

Exemple :

```text
MaximumAuthenticationAge = 10 minutes
```

Une session utilisant MFA depuis plusieurs jours ne satisfait pas nécessairement une exigence d’authentification récente.

---

## EnforcementScope

Valeurs possibles :

```text
AtAssignment
Continuous
AtAssignmentAndContinuous
```

---

## AtAssignment

La condition doit être satisfaite au moment de l’attribution.

Une perte ultérieure de conformité ne retire pas automatiquement le rôle.

---

## Continuous

La condition doit rester satisfaite pendant toute la détention du rôle.

Une perte de conformité peut déclencher :

- une alerte ;
- une réauthentification ;
- une suspension ;
- une restriction ;
- une remédiation ;
- une décision de retrait.

La politique ne réalise pas elle-même ces actions.

---

## AtAssignmentAndContinuous

La condition est vérifiée :

- au moment de l’attribution ;
- pendant toute la détention.

---

## ApprovalPolicy

Type :

```text
ApprovalPolicy?
```

L’absence signifie :

```text
no additional approval required
```

La présence signifie que l’affectation doit être couverte par une approbation valide.

---

## Structure d’ApprovalPolicy

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

---

## MinimumApprovalCount

Condition :

```text
MinimumApprovalCount >= 1
```

---

## RequiredApproverPermission

La permission d’approbation est distincte de :

- la permission de demander l’affectation ;
- la permission d’exécuter la commande ;
- les permissions accordées par le rôle cible.

---

## SelfApprovalAllowed

Pour les rôles privilégiés, la valeur recommandée est :

```text
false
```

---

## SameActorMayProvideMultipleApprovals

Par défaut :

```text
false
```

Un acteur ne doit pas satisfaire artificiellement plusieurs approbations exigées.

---

## SeparationOfDuties

La politique peut exiger que :

```text
Requester
Approver
Target
Executor
```

soient distincts selon des règles explicites.

---

## ValidityDuration

Une approbation doit avoir une durée de validité.

Exemple :

```text
ValidityDuration = 24 hours
```

Une approbation expirée doit être refusée.

---

## PolicyVersionMustMatch

La recommandation est :

```text
true
```

L’approbation doit référencer :

```text
AssignmentPolicyVersion
```

Une modification de la politique peut invalider les approbations en attente.

---

## TargetAcceptancePolicy

Type :

```text
TargetAcceptancePolicy?
```

La présence indique que la cible doit accepter le rôle.

---

## Structure de TargetAcceptancePolicy

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

## AcknowledgementType

Valeurs possibles :

```text
SimpleAcceptance
ResponsibilityAcknowledgement
TermsAcceptance
LegalAcceptance
SecurityAcknowledgement
```

---

## Portée de l’acceptation

L’acceptation doit identifier :

- le `Workspace` ;
- le `Role` ;
- la cible ;
- la version de politique ;
- la durée éventuelle ;
- les responsabilités ;
- les conséquences éventuelles.

---

## Acceptation ancienne

Une acceptation ne doit pas être réutilisée si :

- la politique a changé ;
- le rôle a changé de nature ;
- la durée a changé ;
- les responsabilités ont substantiellement changé ;
- la cible n’est plus éligible.

---

## AssignmentCapacity

Structure :

```text
AssignmentCapacity
├── MinimumActiveAssignments
└── MaximumActiveAssignments
```

---

## MinimumActiveAssignments

Type :

```text
Integer >= 0
```

Cette valeur représente le nombre minimal de memberships actifs devant détenir le rôle.

Exemple :

```text
Owner.MinimumActiveAssignments = 1
```

---

## MaximumActiveAssignments

Type :

```text
Integer >= 1
```

ou :

```text
null
```

Une valeur `null` signifie :

```text
no configured maximum
```

---

## Cohérence des limites

```text
MaximumActiveAssignments is null
OR
MinimumActiveAssignments <= MaximumActiveAssignments
```

---

## Exclusivité

Un rôle est exclusif lorsque :

```text
MaximumActiveAssignments = 1
```

`IsExclusive` ne doit pas constituer une seconde source de vérité indépendante.

Il peut être exposé comme propriété dérivée :

```text
IsExclusive :=
MaximumActiveAssignments == 1
```

---

## Comptage des affectations

Par défaut, seules les affectations suivantes sont comptées :

```text
Membership.Status = Active
```

Les memberships :

```text
Suspended
Removed
```

ne sont pas inclus.

Cette décision doit rester cohérente avec l’invariant du dernier owner.

---

## Attribution et limite maximale

Condition :

```text
ActiveAssignmentCountAfterAssignment
<=
MaximumActiveAssignments
```

La vérification doit être protégée contre la concurrence.

---

## Retrait et limite minimale

Les commandes retirant ou changeant un rôle doivent vérifier :

```text
ActiveAssignmentCountAfterChange
>=
MinimumActiveAssignments
```

Cette règle concerne notamment :

```text
ChangeMembershipRole
TransferMembershipRole
SuspendMembership
RemoveMembership
LeaveWorkspace
DisableUser
```

---

## AssignmentDurationPolicy

Type :

```text
AssignmentDurationPolicy
```

Structure recommandée :

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

## PermanentOnly

Le rôle ne peut être attribué sans limite de temps définie par une expiration.

---

## TemporaryOnly

Toute affectation doit posséder une date de fin.

---

## PermanentOrTemporary

Les deux formes sont autorisées.

---

## Modèle actuel à rôle unique

Le modèle actuel prévoit :

```text
one active Membership
has exactly one current Role
```

Une affectation temporaire remplace donc le rôle courant pendant sa période de validité.

Pour des privilèges supplémentaires temporaires, un modèle distinct peut être préférable :

```text
TemporaryPrivilegeGrant
Delegation
RoleGrant
```

---

## ContinuousRequirements

Type :

```text
Set<ContinuousAssignmentRequirement>
```

Exemples :

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

## Effet d’une perte de conformité

Une perte de conformité ne retire pas automatiquement le rôle.

Elle peut déclencher :

```text
AssignmentBecameNonCompliant
```

puis un workflow explicite.

---

## AdministrationRecoveryPolicy

Structure recommandée :

```text
AdministrationRecoveryPolicy
├── RecoveryAllowed
├── AllowedRecoveryActorTypes
├── RequiredRecoveryPermission
├── MinimumApprovalCount
├── AuthenticationRequirement
├── CaseReferenceRequired
├── NotificationRequired
└── MaximumRecoveryDuration
```

---

## Rôle critique

Une récupération administrative est particulièrement importante pour :

```text
SystemType = Owner
```

La politique owner ne doit pas rendre le `Workspace` définitivement inaccessible.

---

## RecoveryAllowed

Lorsque :

```text
RecoveryAllowed = false
```

aucun workflow de récupération administrative n’est permis par cette politique.

Cette valeur peut être interdite pour les rôles critiques.

---

## CaseReferenceRequired

Pour une récupération sensible :

```text
CaseReferenceRequired = true
```

Une référence de dossier doit être fournie.

---

## ControlPolicy

`ControlPolicy` indique qui contrôle la politique.

Valeurs :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

---

## Contrôle global ou champ par champ

Deux approches sont possibles :

### Contrôle global

Toute la politique est contrôlée par la même source.

### Contrôle champ par champ

Chaque composant possède sa propre autorité.

La seconde approche est plus flexible, mais plus complexe.

---

## ExternallyManaged

Une modification locale doit normalement être refusée.

---

## TemplateManaged

La politique provient d’un modèle.

Les overrides doivent être explicitement enregistrés.

---

## ProductManaged

La politique est imposée par le produit.

C’est une option recommandée pour certaines protections owner.

---

## LocallyOverridable

Une valeur de base existe, mais le workspace peut la surcharger dans des limites définies.

---

## Version

Type :

```text
AssignmentPolicyVersion
```

La version est incrémentée lors de chaque remplacement effectif de la politique.

Elle est référencée par :

- les invitations ;
- les approbations ;
- les acceptations ;
- les affectations planifiées ;
- les transferts ;
- les commandes concurrentes ;
- les workflows de provisioning.

---

## Version et Role.Version

Deux stratégies existent :

### Version globale seulement

```text
Role.Version
```

Toute modification du rôle invalide les décisions en attente.

### Version spécialisée

```text
Role.Version
AssignmentPolicy.Version
TransferPolicy.Version
PermissionSet.Version
Metadata.Version
```

La version spécialisée réduit les conflits inutiles.

La recommandation initiale peut être :

```text
Role.Version
+
AssignmentPolicy.Version
```

si la complexité reste maîtrisable.

---

## Validation globale

Une politique est valide lorsque toutes les conditions suivantes sont satisfaites.

### Sources

```text
AllowedSources is not empty
```

---

### Cibles

```text
AllowedTargetIdentityTypes is not empty
```

---

### Cohérence humaine

```text
RequiresHumanAssignee = true
=>
all allowed target types are human-compatible
```

---

### Permission

```text
RequiredAssignmentPermission is null
OR
Permission exists and is active
```

---

### Authentification

```text
authentication levels are supported
```

```text
MaximumAuthenticationAge > 0
when provided
```

---

### Approbation

```text
ApprovalPolicy is absent
OR
ApprovalPolicy is complete
```

---

### Acceptation

```text
TargetAcceptancePolicy is absent
OR
TargetAcceptancePolicy is complete
```

---

### Capacités

```text
MinimumActiveAssignments >= 0
```

```text
MaximumActiveAssignments is null
OR
MaximumActiveAssignments >= 1
```

```text
MaximumActiveAssignments is null
OR
MinimumActiveAssignments <= MaximumActiveAssignments
```

---

### Durée

```text
MinimumDuration <= MaximumDuration
when both are defined
```

```text
TemporaryOnly => EndDateRequired = true
```

---

### Administrabilité

```text
at least one valid assignment or recovery path exists
```

---

## Validation du Owner Role

Pour :

```text
SystemType = Owner
```

des contraintes minimales peuvent être imposées :

```text
RequiresHumanAssignee = true
```

```text
MinimumActiveAssignments >= 1
```

```text
AdministrationRecoveryPolicy.RecoveryAllowed = true
```

```text
AllowedTargetIdentityTypes
contains only human-compatible types
```

Selon le produit :

```text
ActorAuthenticationRequirement.Level >= Mfa
```

```text
TargetAuthenticationRequirement.Level >= Mfa
```

---

## Validation du ServiceAccount Role

Pour :

```text
SystemType = ServiceAccount
```

la politique devrait normalement interdire :

```text
HumanUser
```

sauf usage hybride explicitement autorisé.

---

## Consommation par CreateMembership

`CreateMembership` doit vérifier :

```text
Role is Active
Assignment source is allowed
Actor is authorized
Target identity type is allowed
Human requirement is satisfied
Authentication requirements are satisfied
Approval is valid
Target acceptance is valid when applicable
Capacity remains valid
Duration is valid
Continuous requirements are initially satisfied
AssignmentPolicyVersion is current
```

---

## Consommation par RestoreMembership

`RestoreMembership` doit vérifier la politique courante.

Un rôle historiquement attribué ne garantit pas que le membership puisse aujourd’hui le récupérer.

---

## Consommation par ReactivateMembership

`ReactivateMembership` doit vérifier :

- que le rôle est toujours actif ;
- que la cible reste éligible ;
- que les exigences continues sont satisfaites ;
- que les capacités restent cohérentes.

---

## Consommation par ChangeMembershipRole

Le rôle cible doit autoriser :

```text
ManualAdministration
```

ou la source réelle de la commande.

---

## Consommation par TransferMembershipRole

Le rôle transféré doit autoriser :

```text
RoleTransfer
```

La cible doit satisfaire :

```text
RoleAssignmentPolicy
AND
RoleTransferPolicy
```

Le rôle de remplacement de la source doit également satisfaire sa propre `RoleAssignmentPolicy`.

---

## Consommation par AcceptInvitation

L’invitation doit contenir ou référencer :

```text
RoleId
AssignmentPolicyVersion known at creation
```

Lors de l’acceptation, la politique courante est rechargée.

Si elle a changé, l’invitation peut :

- rester valide ;
- nécessiter une nouvelle acceptation ;
- nécessiter une approbation ;
- devenir invalide.

---

## Concurrence

### Limite maximale

Cas :

```text
MaximumActiveAssignments = 1
ActiveAssignmentCount = 0
```

Deux commandes concurrentes tentent d’attribuer le rôle.

Une seule doit réussir.

---

## Stratégies possibles

- verrou sur le rôle ;
- compteur transactionnel ;
- verrou de gouvernance par rôle ;
- contrainte persistante spécialisée ;
- sérialisation des commandes ;
- isolation sérialisable.

---

## Changement de politique concurrent

Une commande d’attribution validée sous une ancienne politique ne doit pas être commitée sans réévaluation.

Condition recommandée :

```text
ExpectedAssignmentPolicyVersion
=
CurrentAssignmentPolicyVersion
```

---

## Affectations existantes

Une modification de politique peut rendre des affectations existantes non conformes.

Classification possible :

```text
Compliant
CompliantWithWarning
NonCompliant
RequiresReview
RequiresRemediation
```

La politique ne modifie aucun membership.

---

## Exemples de non-conformité

### Nouveau maximum

```text
Current active assignments = 5
New maximum = 3
```

---

### Exigence humaine

```text
ServiceAccount currently holds Role
RequiresHumanAssignee becomes true
```

---

### MFA continue

```text
Target no longer has Mfa
Continuous requirement requires Mfa
```

---

### Source externe

```text
External directory membership no longer exists
```

---

## Égalité métier

Deux politiques sont égales lorsque leurs valeurs normalisées sont égales.

L’ordre des ensembles n’est pas significatif.

Exemple :

```text
{Invitation, ManualAdministration}
```

est égal à :

```text
{ManualAdministration, Invitation}
```

---

## Normalisation

La normalisation doit notamment :

- ordonner ou canoniser les ensembles ;
- normaliser les durées ;
- normaliser les permissions ;
- supprimer les doublons ;
- valider les combinaisons ;
- distinguer valeur absente et valeur explicite.

---

## Erreurs de validation

Erreurs possibles :

```text
EmptyAssignmentSources
InvalidAssignmentSource
RequiredAssignmentPermissionNotFound
RequiredAssignmentPermissionUnavailable
AssignmentAuthorizationCycleDetected
EmptyAllowedTargetIdentityTypes
InvalidTargetIdentityType
HumanAssigneePolicyConflict
InvalidActorAuthenticationRequirement
InvalidTargetAuthenticationRequirement
UnsupportedAuthenticationLevel
InvalidApprovalPolicy
InvalidApprovalCount
SelfApprovalPolicyInvalid
InvalidTargetAcceptancePolicy
InvalidAssignmentCapacity
MinimumAssignmentsExceedsMaximum
InvalidAssignmentDurationPolicy
InvalidContinuousRequirement
InvalidAdministrationRecoveryPolicy
OwnerRecoveryPathRequired
RoleWouldBecomeUnassignable
```

---

## Sécurité

La politique doit garantir que :

- les rôles privilégiés ne sont pas librement attribuables ;
- les permissions requises existent ;
- les cycles de gouvernance sont détectés ;
- les types de cible sont contrôlés ;
- les exigences d’authentification sont explicites ;
- les approbations sont versionnées ;
- les acceptations sont versionnées ;
- les limites sont protégées contre la concurrence ;
- une voie de récupération reste possible ;
- les exigences continues sont auditées ;
- aucune règle n’est déduite du nom du rôle.

---

## Confidentialité

La politique ne doit pas contenir :

- de liste nominative ;
- de secret ;
- de token ;
- de détail MFA ;
- de commentaire d’enquête ;
- de données médicales ;
- de justification individuelle ;
- de donnée contractuelle nominative.

Les exceptions individuelles doivent être modélisées ailleurs.

---

## Décisions de conception

### AssignmentMode est supprimé

Un enum combinatoire ne permet pas de représenter correctement les workflows.

Il est remplacé par :

```text
AllowedSources: Set<RoleAssignmentSource>
```

---

### La politique appartient au Role

Elle représente une propriété stable du rôle.

---

### La politique est immuable

Toute modification construit une nouvelle valeur complète.

---

### Les exigences acteur et cible sont distinctes

Elles ne doivent pas être regroupées dans une règle ambiguë.

---

### L’exclusivité est dérivée

```text
IsExclusive =
MaximumActiveAssignments == 1
```

---

### Les approbations sont versionnées

Elles doivent couvrir une version précise de la politique.

---

### Les acceptations sont versionnées

Une acceptation ancienne ne doit pas couvrir une nouvelle politique.

---

### Les exigences continues sont explicites

L’éligibilité initiale est distincte de la conformité durable.

---

### La politique ne modifie aucun Membership

Elle définit les conditions consommées par d’autres commandes.

---

### Le transfert doit satisfaire les deux politiques

```text
RoleAssignmentPolicy
AND
RoleTransferPolicy
```

---

## Checklist

```text
AllowedSources is not empty
All sources are valid
RequiredAssignmentPermission exists when provided
AllowedTargetIdentityTypes is not empty
All target identity types are valid
Human requirement is coherent
Actor authentication requirement is valid
Target authentication requirement is valid
Approval policy is complete when present
Target acceptance policy is complete when present
MinimumActiveAssignments is valid
MaximumActiveAssignments is valid
Minimum does not exceed Maximum
Assignment duration policy is valid
Continuous requirements are valid
Recovery policy is valid
Critical Role recovery path exists
Owner-specific minimum protections are satisfied
Policy Version is defined
Policy is immutable
```

---

## Synthèse

`RoleAssignmentPolicy` décrit de manière complète les conditions selon lesquelles un rôle peut être attribué à un membership.

Elle permet de déterminer :

```text
whether the Role may be assigned
through which workflow
by which authority
to which identity
under which security conditions
within which capacity limits
and with which ongoing obligations
```

Elle ne crée aucune affectation et n’accorde aucune permission.