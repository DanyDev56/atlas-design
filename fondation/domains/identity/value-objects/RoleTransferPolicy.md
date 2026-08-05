---
id: IDN-VO-ROLE-TRANSFER-POLICY
title: RoleTransferPolicy
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
  - RoleAssignmentPolicy.md
  - ../commands/ChangeRoleTransferPolicy.md
  - ../commands/TransferMembershipRole.md
  - ../commands/ChangeMembershipRole.md
---

# RoleTransferPolicy

## Question traitée

```text
Under which conditions may a responsibility represented by a Role
be transferred from one Membership to another Membership?
```

`RoleTransferPolicy` décrit les règles applicables à un transfert atomique de rôle entre :

```text
SourceMembership
```

et :

```text
TargetMembership
```

---

## Signification du transfert

Dans le modèle actuel, un transfert signifie :

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

Le rôle ne peut pas simplement être retiré de la source, car tout `Membership` actif doit conserver exactement un rôle courant.

---

## Responsabilité

La politique détermine notamment :

- si le rôle est transférable ;
- quelles sources peuvent déclencher le transfert ;
- qui peut l’initier ;
- quelle permission est requise ;
- si la source doit confirmer ;
- si la cible doit accepter ;
- si une approbation est requise ;
- quels types d’identité peuvent recevoir le rôle ;
- quelles authentifications sont nécessaires ;
- si l’auto-transfert est permis ;
- si le rôle de remplacement de la source est valide ;
- si une période ou une fenêtre de transfert s’applique ;
- si une récupération administrative reste possible.

---

## Non-responsabilités

La politique ne détermine pas :

- les permissions accordées par le rôle ;
- la politique générale d’attribution hors transfert ;
- le rôle cible de remplacement précis ;
- les memberships participant à une opération donnée ;
- la création d’une approbation ;
- la création d’une acceptation ;
- le statut du rôle ;
- l’exécution du transfert.

---

## Appartenance au modèle

```text
Role
└── TransferPolicy: RoleTransferPolicy
```

La politique appartient au rôle transféré.

---

## Relation avec RoleAssignmentPolicy

Un transfert constitue également une affectation du rôle à la cible.

La commande doit donc satisfaire :

```text
RoleTransferPolicy
AND
RoleAssignmentPolicy for source RoleTransfer
```

La politique d’attribution doit notamment contenir :

```text
AllowedSources includes RoleTransfer
```

---

## Règle de composition

```text
Transfer allowed
IFF
TransferPolicy allows transfer
AND
AssignmentPolicy allows target assignment through RoleTransfer
```

Aucune des deux politiques ne remplace l’autre.

---

## Structure recommandée

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
├── SelfTransferPolicy
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

Les conditions détaillées ne doivent pas être encodées dans cette enum.

---

## NotTransferable

Aucun transfert ordinaire n’est autorisé.

Une récupération administrative peut toutefois exister séparément lorsque la gouvernance l’exige.

---

## Transferable

Le rôle peut être transféré sous réserve de toutes les autres propriétés de la politique.

---

## AllowedSources

Type :

```text
Set<RoleTransferSource>
```

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

## CurrentHolderInitiated

Le détenteur actuel initie le transfert.

Exemple :

```text
Owner transfers ownership before leaving
```

---

## TargetInitiated

La cible demande à recevoir la responsabilité.

Cette source exige généralement une confirmation de la source et/ou une approbation.

---

## WorkspaceAdministration

Un administrateur autorisé orchestre le transfert.

---

## ExternalSynchronization

Le transfert est imposé par une source externe autoritaire.

---

## AdministrativeRecovery

Un processus exceptionnel restaure une situation administrable.

---

## AllowedInitiators

Type :

```text
Set<RoleTransferInitiatorType>
```

Valeurs possibles :

```text
CurrentHolder
TargetMembership
WorkspaceOwner
AuthorizedMember
SystemActor
ExternalAuthority
PlatformAdministrator
```

La source et le type d’initiateur doivent être cohérents.

---

## RequiredTransferPermission

Permission additionnelle nécessaire pour initier ou autoriser le transfert.

Exemple :

```text
workspace.roles.transfer-owner
```

Elle est distincte :

- des permissions accordées par le rôle ;
- de `RequiredAssignmentPermission` ;
- des permissions d’approbation.

---

## SourceConfirmationPolicy

Structure possible :

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

## SourceConfirmation requise

Pour une responsabilité sensible, la source doit confirmer :

```text
I agree to relinquish TransferredRole
and receive SourceReplacementRole
```

La confirmation doit couvrir les deux transitions.

---

## AcknowledgementType de la source

Valeurs possibles :

```text
SimpleConfirmation
ResponsibilityRelinquishment
ConsequencesAcknowledgement
LegalConfirmation
SecurityConfirmation
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
├── ExpectedTargetRoleVersion
└── PolicyVersionMustMatch
```

La cible doit accepter :

- le rôle reçu ;
- les responsabilités ;
- la perte de son rôle précédent ;
- les éventuelles obligations continues.

---

## ApprovalPolicy

Structure analogue à celle de `RoleAssignmentPolicy`, avec éventuellement :

```text
RequiredApproverPermission
RequiredApproverSystemType
MinimumApprovalCount
SelfApprovalAllowed
SourceMayApprove
TargetMayApprove
SeparationOfDuties
AuthenticationRequirement
ValidityDuration
```

---

## Séparation des devoirs

Pour un transfert privilégié, la politique peut imposer :

```text
Initiator != Source
Initiator != Target
Approver != Source
Approver != Target
```

ou toute combinaison adaptée.

---

## AuthenticationRequirements

La politique peut distinguer :

```text
ActorAuthenticationRequirement
SourceAuthenticationRequirement
TargetAuthenticationRequirement
ApproverAuthenticationRequirement
```

Exemple pour un rôle owner :

```text
Actor  = Mfa
Source = Recent PhishingResistantMfa
Target = Mfa
Approver = Mfa
```

---

## AllowedTargetIdentityTypes

La cible doit appartenir à un type autorisé.

Exemple :

```text
Owner
AllowedTargetIdentityTypes = { HumanUser }
```

Cette règle doit rester compatible avec `RoleAssignmentPolicy`.

L’intersection des deux politiques doit être non vide.

---

## SourceReplacementRolePolicy

La source doit recevoir un autre rôle.

Structure possible :

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

## Validation du rôle de remplacement

`SourceReplacementRoleId` doit :

- exister ;
- appartenir au même `Workspace` ;
- être actif ;
- être attribuable à la source ;
- respecter sa propre `RoleAssignmentPolicy` ;
- ne pas être le rôle transféré lorsque cela rendrait le transfert sans effet ;
- ne pas violer les limites d’affectation ;
- ne pas créer une élévation de privilèges non autorisée.

---

## Échange de rôles

Le modèle peut autoriser :

```text
Source receives TargetCurrentRole
Target receives TransferredRole
```

Cela constitue un échange partiel.

La politique doit indiquer si :

```text
SourceReplacementRoleId = TargetCurrentRoleId
```

est autorisé.

---

## SelfTransferPolicy

Un transfert avec :

```text
SourceMembershipId = TargetMembershipId
```

n’a normalement aucun sens et doit être interdit.

La politique peut distinguer ce cas d’une opération où l’initiateur est également la source ou la cible.

---

## InitiatorIsParticipantPolicy

Valeurs possibles :

```text
Allowed
Forbidden
AllowedWithAdditionalApproval
```

Elle peut être définie séparément pour :

- l’initiateur-source ;
- l’initiateur-cible ;
- l’approbateur-source ;
- l’approbateur-cible.

---

## TransferWindowPolicy

Structure possible :

```text
TransferWindowPolicy
├── ImmediateOnly
├── SchedulingAllowed
├── MaximumSchedulingDelay
├── AllowedDays
├── AllowedTimeRanges
├── BlackoutPeriods
└── RevalidationRequiredAtExecution
```

---

## Transfert différé

Un transfert planifié doit toujours être réévalué au moment de l’exécution.

Les éléments suivants peuvent avoir changé :

- statut des memberships ;
- rôles courants ;
- politique ;
- approbations ;
- permissions ;
- niveau d’authentification ;
- nombre d’owners ;
- état du `Workspace`.

---

## ContinuityPolicy

Structure possible :

```text
ContinuityPolicy
├── NoTransientGapAllowed
├── MinimumActiveAssignmentsAfterTransfer
├── MaximumActiveAssignmentsAfterTransfer
├── SourceMustRemainMember
├── TargetMustAlreadyBeActive
└── SessionsMustBeInvalidatedAtomically
```

---

## Aucun état intermédiaire invalide

Pour un rôle owner :

```text
remove Owner from Source
↓
temporarily no Owner
↓
assign Owner to Target
```

est interdit.

Le transfert doit être atomique :

```text
Source role replacement
+
Target role assignment
```

---

## Minimum après transfert

Condition :

```text
ActiveAssignmentCountAfterTransfer
>=
MinimumActiveAssignments
```

---

## Maximum après transfert

Condition :

```text
MaximumActiveAssignments is null
OR
ActiveAssignmentCountAfterTransfer
<=
MaximumActiveAssignments
```

---

## AdministrationRecoveryPolicy

Même lorsqu’un rôle est non transférable, une récupération contrôlée peut être nécessaire.

Structure :

```text
AdministrationRecoveryPolicy
├── RecoveryAllowed
├── RequiredRecoveryPermission
├── RequiredApprovalCount
├── CaseReferenceRequired
├── SourceConfirmationMayBeWaived
├── TargetAcceptanceMayBeWaived
└── RequiredAuthenticationLevel
```

Une récupération n’est pas un transfert ordinaire.

Elle doit être auditée comme telle.

---

## ControlPolicy

Valeurs :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

Un rôle externe peut imposer :

```text
AllowedSources = { ExternalSynchronization }
```

---

## Version

La politique possède :

```text
TransferPolicyVersion
```

Les confirmations, acceptations et approbations doivent référencer cette version.

---

## Validation globale

Une politique est valide lorsque :

```text
Transferability = NotTransferable
OR
AllowedSources is not empty
```

```text
Transferability = NotTransferable
OR
AllowedInitiators is not empty
```

```text
SourceConfirmationPolicy is complete when required
```

```text
TargetAcceptancePolicy is complete when required
```

```text
ApprovalPolicy is complete when required
```

```text
authentication requirements are supported
```

```text
RequiredTransferPermission exists when provided
```

```text
AllowedTargetIdentityTypes is not empty
```

```text
SourceReplacementRolePolicy is coherent
```

```text
a valid recovery path exists for critical Roles
```

---

## Validation avec RoleAssignmentPolicy

La politique complète du rôle doit vérifier :

```text
Transferability = Transferable
=>
RoleAssignmentPolicy.AllowedSources contains RoleTransfer
```

et :

```text
TransferPolicy.AllowedTargetIdentityTypes
INTERSECT
AssignmentPolicy.AllowedTargetIdentityTypes
is not empty
```

---

## Validation des rôles owner

Pour :

```text
SystemType = Owner
```

des contraintes minimales peuvent être imposées :

```text
SourceConfirmationPolicy.Required = true
TargetAcceptancePolicy.Required = true
Target must be HumanUser
SourceReplacementRolePolicy.Required = true
NoTransientGapAllowed = true
MinimumActiveAssignmentsAfterTransfer >= 1
AdministrationRecoveryPolicy.RecoveryAllowed = true
```

Une approbation supplémentaire peut être requise lorsque plusieurs owners existent.

---

## Consommation par TransferMembershipRole

La commande doit vérifier au minimum :

```text
Role is Transferable
Transfer source is allowed
Initiator type is allowed
Actor has RequiredTransferPermission
Source currently holds TransferredRole
Target does not already hold TransferredRole
Source confirmation is valid
Target acceptance is valid
Approval is valid
Authentication requirements are satisfied
Target identity is eligible
AssignmentPolicy allows RoleTransfer
SourceReplacementRole is valid
Post-transfer capacity is valid
Owner continuity is preserved
TransferPolicyVersion is unchanged
AssignmentPolicyVersion is unchanged
```

---

## Concurrence

Le transfert doit se protéger contre :

- modification du rôle source ;
- modification du rôle cible ;
- changement de politique ;
- suspension d’un participant ;
- retrait d’un participant ;
- transfert concurrent ;
- modification du nombre d’owners ;
- désactivation du rôle ;
- archivage du rôle.

Les memberships doivent être verrouillés dans un ordre déterministe.

---

## Égalité

Deux politiques sont égales lorsque toutes leurs valeurs métier normalisées sont égales.

Les ensembles sont comparés sans tenir compte de leur ordre.

---

## Immutabilité

`RoleTransferPolicy` est immuable.

Une modification produit une nouvelle valeur complète :

```text
PreviousTransferPolicy
↓
NewTransferPolicy
```

---

## Décisions de conception

### TransferMode est supprimé

Un enum combinatoire ne peut pas représenter correctement toutes les contraintes.

### Le transfert et l’attribution restent distincts

Un transfert doit satisfaire les deux politiques.

### Le rôle de remplacement de la source est obligatoire

Tout membership actif conserve exactement un rôle.

### Les consentements sont versionnés

Une confirmation ou une acceptation ancienne ne doit pas couvrir une nouvelle politique.

### La continuité est atomique

Aucun état intermédiaire ne peut violer l’invariant owner.

### La récupération administrative est explicitement séparée

Elle ne doit pas contourner silencieusement la politique ordinaire.

---

## Synthèse

`RoleTransferPolicy` définit de manière complète les conditions permettant de déplacer une responsabilité entre deux memberships.

Elle permet de déterminer :

```text
whether the Role is transferable
who may initiate the transfer
which confirmations and approvals are required
which target is eligible
which replacement Role the source may receive
and whether continuity remains guaranteed
```

Elle ne réalise pas le transfert.