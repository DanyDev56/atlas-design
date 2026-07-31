---
id: IDN-CMD-DISABLE-ROLE
title: DisableRole
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-31

aggregate: Role

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../value-objects.md
  - ../value-objects/RoleAssignmentPolicy.md
  - ../value-objects/RoleTransferPolicy.md
  - ../invariants.md
  - ../permissions.md
  - ../events/RoleDisabled.md
  - CreateRole.md
  - EnableRole.md
  - ArchiveRole.md
  - GrantPermissionToRole.md
  - RevokePermissionFromRole.md
  - ChangeMembershipRole.md
  - SuspendMembership.md
  - ../workflows.md
---

# DisableRole

## Objectif

La commande `DisableRole` désactive temporairement un `Role`.

Transition principale :

```text
Active
↓
Disabled
```

La désactivation empêche le rôle de participer normalement aux décisions d’autorisation.

Elle conserve néanmoins :

- l’identité du rôle ;
- son workspace ;
- ses métadonnées ;
- ses permissions ;
- ses politiques ;
- ses affectations aux memberships ;
- ses références externes ou de modèle ;
- son historique.

La commande ne doit pas :

- supprimer le rôle ;
- archiver le rôle ;
- retirer ses permissions ;
- modifier `RoleAssignmentPolicy` ;
- modifier `RoleTransferPolicy` ;
- suspendre directement les memberships ;
- supprimer les memberships ;
- changer le rôle des memberships ;
- révoquer systématiquement toutes les sessions ;
- modifier le workspace ;
- supprimer les invitations ;
- annuler automatiquement les transferts en attente ;
- rendre le rôle impossible à réactiver.

---

## Intention métier

La commande répond à l’intention suivante :

```text
temporarily prevent an existing Role
from granting effective capabilities
inside its Workspace
while preserving its configuration and history
```

La désactivation suspend l’efficacité du rôle sans supprimer sa définition.

---

## Agrégat concerné

```text
Role
```

La commande modifie un seul agrégat `Role`.

Elle consulte également :

- le `Workspace` ;
- l’acteur ;
- les memberships utilisant le rôle ;
- les sessions actives ;
- les permissions effectives ;
- `RoleAssignmentPolicy` ;
- `RoleTransferPolicy` ;
- les invitations et transferts en attente ;
- les règles de gouvernance ;
- les contraintes des rôles système ;
- les sources externes ;
- les modèles ;
- les mécanismes de récupération administrative.

---

## Cycle de vie du Role

Cycle recommandé :

```text
Active
↓ DisableRole
Disabled
↓ EnableRole
Active
↓ ArchiveRole
Archived
```

`DisableRole` n’est pas une suppression.

La transition reste réversible au moyen de :

```text
EnableRole
```

---

## Signification de Disabled

Un rôle désactivé :

- existe toujours ;
- reste consultable ;
- conserve ses métadonnées ;
- conserve ses permissions explicites ;
- conserve ses politiques ;
- conserve ses affectations aux memberships ;
- ne produit plus normalement de permissions effectives ;
- ne peut généralement plus être attribué ;
- ne peut généralement plus être utilisé comme cible de transfert ;
- peut être réactivé ultérieurement.

---

## Effet sur l’autorisation

La chaîne d’autorisation est :

```text
Session
→ User
→ Membership
→ Role
→ Permission
```

Après désactivation :

```text
Membership.Status = Active
AND
Role.Status = Disabled
↓
Role Permissions are ineffective
```

Le rôle ne disparaît pas de la relation.

Son efficacité est suspendue.

---

## Distinction avec SuspendMembership

`DisableRole` affecte tous les memberships utilisant le rôle.

```text
DisableRole
→ all holders affected
```

`SuspendMembership` affecte un seul membership.

```text
SuspendMembership
→ one Membership affected
```

---

## Distinction avec RevokePermissionFromRole

`RevokePermissionFromRole` retire une capacité explicite du rôle.

`DisableRole` conserve toutes les permissions, mais suspend leur efficacité.

```text
RevokePermissionFromRole
→ Permission composition changes
```

```text
DisableRole
→ Permission effectiveness changes
```

---

## Distinction avec ArchiveRole

`DisableRole` est temporaire et réversible.

`ArchiveRole` est une transition de fin d’usage plus forte.

```text
Disabled
→ may be enabled again
```

```text
Archived
→ immutable or restorable only through a dedicated command
```

---

## Effet collectif

La désactivation peut affecter simultanément :

- plusieurs memberships ;
- plusieurs sessions ;
- des intégrations ;
- des workflows ;
- des invitations ;
- des transferts ;
- des traitements automatisés ;
- des comptes de service.

Elle doit donc être considérée comme une opération de réduction collective d’autorisation.

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un administrateur de sécurité ;
- un administrateur de gouvernance ;
- un `SystemActor` ;
- un workflow de réponse à incident ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un workflow de conformité ;
- un processus de migration ;
- un processus produit ;
- un administrateur de plateforme dans un périmètre autorisé.

L’acteur doit être identifiable et auditable.

---

## Permission requise

Permission recommandée :

```text
workspace.roles.disable
```

Une permission plus générale peut être utilisée :

```text
workspace.roles.manage
```

Des permissions renforcées peuvent être nécessaires :

```text
workspace.roles.disable-privileged
workspace.roles.disable-critical
workspace.roles.disable-system
workspace.roles.disable-owner
workspace.roles.emergency-disable
workspace.roles.override-external-status
workspace.roles.override-template-status
```

---

## Emergency disable

Une voie d’urgence peut être prévue :

```text
EmergencyDisableRole
```

ou un mode spécialisé de `DisableRole`.

Elle permet une réduction rapide des capacités lors :

- d’un incident de sécurité ;
- d’une compromission ;
- d’un abus ;
- d’une erreur critique ;
- d’une violation de conformité ;
- d’une défaillance d’intégration.

---

## Recommandation

Conserver une seule commande métier :

```text
DisableRole
```

avec une source et un motif explicites.

La voie d’urgence peut utiliser :

```text
DisableSource = SecurityIncident
```

et des règles renforcées de propagation.

---

## Sources de désactivation

Valeurs recommandées pour `RoleDisableSource` :

```text
ManualAdministration
SecurityIncident
ComplianceEnforcement
SystemProvisioning
ExternalSynchronization
TemplateSynchronization
AdministrativeRecovery
Migration
ProductConfiguration
WorkspaceProtection
OperationalProtection
```

---

## ManualAdministration

Un acteur humain autorisé désactive temporairement le rôle.

---

## SecurityIncident

Le rôle est désactivé pour contenir un incident de sécurité.

---

## ComplianceEnforcement

Le rôle est désactivé parce qu’une exigence de conformité n’est plus satisfaite.

---

## SystemProvisioning

Un processus système met temporairement le rôle hors service.

---

## ExternalSynchronization

Une source externe autoritaire indique que le rôle est désactivé.

---

## TemplateSynchronization

Le modèle dont dépend le rôle impose sa désactivation.

---

## AdministrativeRecovery

La désactivation corrige une configuration incohérente ou dangereuse.

---

## Migration

Le rôle est désactivé pendant une migration.

---

## ProductConfiguration

Le produit désactive un rôle système ou une fonctionnalité.

---

## WorkspaceProtection

La désactivation protège le workspace contre une configuration dangereuse.

---

## OperationalProtection

La désactivation prévient une défaillance opérationnelle.

---

## Motifs de désactivation

Valeurs recommandées pour `RoleDisableReason` :

```text
TemporaryRestriction
SecurityIncident
SuspectedCompromise
ComplianceViolation
PolicyViolation
OrganizationalChange
FeatureSuspension
ExternalSourceDisabled
TemplateDisabled
OperationalFailure
AdministrativeCorrection
Migration
WorkspaceProtection
LeastPrivilegeReview
Other
```

---

## Données d’entrée

### Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `RoleId` | `RoleId` | Rôle à désactiver. |
| `DisabledBy` | `UserId` ou `SystemActor` | Acteur ou workflow responsable. |
| `DisabledAt` | Instant | Date métier de la désactivation. |
| `DisableReason` | `RoleDisableReason` | Motif de la désactivation. |
| `DisableSource` | `RoleDisableSource` | Origine de la désactivation. |
| `DisableRequestId` | Identifiant | Identifiant idempotent. |

### Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `ExpectedRoleVersion` | Version | Version attendue du rôle. |
| `ExpectedAuthorizationStateVersion` | Version | Version attendue de l’état d’autorisation. |
| `ConfirmationId` | Identifiant | Confirmation d’une désactivation sensible. |
| `ApprovalId` | Identifiant | Approbation éventuelle. |
| `SecurityIncidentId` | Identifiant | Incident de sécurité associé. |
| `ComplianceCaseId` | Identifiant | Dossier de conformité. |
| `CaseReference` | Identifiant | Dossier administratif. |
| `ExternalReference` | Identifiant | Référence externe. |
| `ExternalVersion` | Version | Version de la source externe. |
| `TemplateId` | Identifiant | Modèle concerné. |
| `TemplateVersion` | Version | Version du modèle. |
| `SessionHandlingPolicy` | `RoleDisableSessionPolicy` | Traitement demandé pour les sessions. |
| `PendingWorkflowPolicy` | `RoleDisablePendingWorkflowPolicy` | Traitement des workflows en attente. |
| `ScheduledReenableAt` | Instant | Réactivation planifiée éventuelle. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques non métier. |

---

## Précondition d’état

Transition valide :

```text
Role.Status = Active
↓
Role.Status = Disabled
```

---

## Rôle déjà désactivé

### Même DisableRequestId

Retourner le résultat initial.

### Nouvelle demande

Retourner :

```text
RoleAlreadyDisabled
```

Cette distinction préserve l’idempotence sans masquer une intention redondante.

---

## Rôle archivé

Un rôle archivé ne peut pas être désactivé.

Il est déjà hors usage et appartient à un état plus terminal.

Erreur :

```text
ArchivedRoleCannotBeDisabled
```

---

## Rôle supprimé

Un éventuel rôle `Removed` ne peut pas être désactivé.

---

## Désactivation immédiate

La première version de la commande effectue une désactivation immédiate après commit.

```text
Active
→ Disabled
```

---

## Désactivation planifiée

Un besoin futur peut introduire :

```text
ScheduleRoleDisablement
```

La planification ne doit pas figer les validations.

À la date d’exécution, le système doit recharger :

- le rôle ;
- ses détenteurs ;
- les politiques ;
- les permissions ;
- les sessions ;
- les contraintes owner ;
- la source de contrôle.

---

## ScheduledReenableAt

Une date de réactivation automatique peut être stockée comme intention.

Toutefois, elle ne doit pas entraîner une transition silencieuse.

À l’échéance, un scheduler doit invoquer explicitement :

```text
EnableRole
```

avec réévaluation complète.

---

## Recommandation

Dans la première version, exclure :

```text
ScheduledReenableAt
```

de l’état du rôle.

Utiliser une orchestration externe qui invoque `EnableRole`.

---

## Impact Assessment

La désactivation doit être précédée d’une analyse d’impact.

Structure recommandée :

```text
RoleDisableImpact
├── ActiveMembershipCount
├── SuspendedMembershipCount
├── ActiveSessionCount
├── EffectivePermissionCount
├── PrivilegedPermissionCount
├── CriticalPermissionCount
├── AffectedIntegrations
├── AffectedPendingInvitations
├── AffectedPendingTransfers
├── AdministrationContinuityPreserved
├── OwnershipContinuityPreserved
├── RecoveryPathAvailable
├── OperationalRisk
└── BlockingIssues
```

---

## Détenteurs actifs

Tous les memberships actifs utilisant le rôle peuvent perdre immédiatement leurs permissions effectives.

```text
AffectedActiveMemberships
=
Memberships where
Status = Active
AND
RoleId = TargetRoleId
```

---

## Détenteurs suspendus

Les memberships suspendus ne reçoivent déjà pas les permissions du rôle.

Ils sont néanmoins concernés pour une future réactivation.

---

## Aucun détenteur

Un rôle sans détenteur peut être désactivé.

L’impact est limité aux futures affectations et workflows en attente.

---

## Permissions effectives perdues

La désactivation rend inactives toutes les permissions effectives du rôle.

```text
LostEffectivePermissions
=
Role.EffectivePermissionSet
```

Cette perte est conditionnée par l’absence d’un autre mécanisme d’autorisation directe, lequel n’existe pas dans le modèle courant.

---

## Pas de modification du Permission Set

Les permissions restent attachées au rôle.

```text
PermissionAssignments before
=
PermissionAssignments after
```

`PermissionSetVersion` ne change pas.

---

## AuthorizationStateVersion

L’efficacité du rôle change.

Le système doit incrémenter :

```text
Role.AuthorizationStateVersion
```

---

## Role.Version

Le changement de statut incrémente également :

```text
Role.Version
```

---

## Memberships et version d’autorisation

Le modèle peut éviter de modifier chaque membership individuellement.

L’autorisation doit intégrer :

```text
Role.Status
Role.AuthorizationStateVersion
```

dans la décision.

---

## Sessions

La désactivation peut invalider des permissions déjà présentes dans :

- des caches ;
- des claims ;
- des tokens ;
- des projections ;
- des sessions applicatives.

---

## RoleDisableSessionPolicy

Valeurs possibles :

```text
ReevaluateAuthorization
RequireTokenRefresh
RevokeAffectedSessions
RevokePrivilegedSessionsOnly
NoAdditionalSessionAction
```

---

## ReevaluateAuthorization

Les sessions restent actives, mais toute décision future utilise l’état courant du rôle.

Option recommandée lorsque l’autorisation est dynamique.

---

## RequireTokenRefresh

Les sessions doivent renouveler leurs claims avant de poursuivre.

---

## RevokeAffectedSessions

Toutes les sessions des détenteurs actifs sont révoquées.

Option forte, adaptée à une compromission.

---

## RevokePrivilegedSessionsOnly

Seules les sessions considérées comme privilégiées sont révoquées.

---

## NoAdditionalSessionAction

Acceptable uniquement si la décision d’autorisation consulte systématiquement l’état courant du rôle.

---

## Recommandation

Par défaut :

```text
ReevaluateAuthorization
```

Pour :

```text
SecurityIncident
SuspectedCompromise
```

utiliser :

```text
RevokeAffectedSessions
```

ou une politique de sécurité équivalente.

---

## La commande révoque-t-elle elle-même les Sessions ?

Non, dans le modèle recommandé.

`DisableRole` modifie le rôle et produit un événement.

Un handler coordonné peut invoquer :

```text
RevokeSession
```

pour les sessions concernées.

---

## Cohérence de sécurité

La désactivation du rôle doit prendre effet dans la source de vérité immédiatement après commit.

La révocation éventuelle des sessions est un renforcement supplémentaire.

Une session non encore révoquée ne doit pas continuer à exercer les permissions du rôle si le moteur d’autorisation est correctement conçu.

---

## Claims statiques

Les claims statiques ne doivent pas être considérés comme autorité définitive.

Une permission sensible doit être validée contre :

```text
current Role.Status
current AuthorizationStateVersion
```

---

## Invitations en attente

Une invitation peut cibler un rôle désormais désactivé.

Après la désactivation :

```text
Invitation remains pending
```

mais son acceptation ne doit pas créer un membership actif avec un rôle inutilisable sans règle explicite.

---

## Politiques possibles pour les Invitations

```text
KeepPendingAndRevalidate
SuspendPendingInvitations
RevokePendingInvitations
```

---

## Recommandation

Utiliser :

```text
KeepPendingAndRevalidate
```

L’invitation reste inchangée.

`AcceptInvitation` doit vérifier que le rôle est actif au moment de l’acceptation.

---

## Transferts en attente

Un transfert vers le rôle désactivé ne doit pas s’exécuter sans réévaluation.

Le transfert peut :

- rester en attente ;
- devenir bloqué ;
- être annulé par un workflow ;
- expirer selon sa propre politique.

---

## Recommandation

La commande ne modifie pas les transferts en attente.

Elle produit une information suffisante pour que leurs projections les marquent comme :

```text
BlockedByRoleDisablement
```

Le transfert lui-même est réévalué à son exécution.

---

## Changements de rôle en attente

Toute commande future :

```text
ChangeMembershipRole
RestoreMembership
ReactivateMembership
TransferMembershipRole
CreateMembership
```

doit refuser l’utilisation du rôle désactivé, sauf récupération explicitement autorisée.

---

## AssignmentPolicy après désactivation

La politique d’attribution reste inchangée.

Toutefois, tant que le rôle est désactivé :

```text
new ordinary assignment forbidden
```

même si `RoleAssignmentPolicy` l’autoriserait normalement.

Le statut du rôle agit comme une condition supérieure.

---

## TransferPolicy après désactivation

La politique de transfert reste inchangée.

Tant que le rôle est désactivé :

```text
transfer into Role forbidden
```

sauf cas de récupération explicitement modélisé.

---

## Peut-on transférer depuis un rôle désactivé ?

Deux situations doivent être distinguées.

### Rôle transféré comme capacité cible

Interdit.

### Retirer un membership d’un rôle désactivé

Autorisé et souvent souhaitable.

Exemple :

```text
ChangeMembershipRole
from Disabled Role
to Active replacement Role
```

Cette opération permet la remédiation.

---

## Remediation Role

La désactivation peut nécessiter un rôle de remplacement pour les détenteurs actuels.

Deux stratégies existent.

### Désactivation pure

Les memberships conservent le rôle désactivé et perdent leurs permissions.

### Remplacement coordonné

Une orchestration déplace les memberships vers un autre rôle.

---

## Recommandation

`DisableRole` ne doit pas réattribuer les memberships.

Un workflow distinct peut orchestrer :

```text
ChangeMembershipRole
for each affected Membership
↓
DisableRole
```

ou :

```text
DisableRole
↓
ChangeMembershipRole
```

selon l’urgence.

---

## Désactivation d’urgence

Lors d’un incident, l’ordre recommandé est :

```text
DisableRole
↓
invalidate authorization
↓
revoke affected Sessions
↓
remediate Membership assignments
```

La réduction d’accès doit précéder les opérations plus lentes.

---

## Rôle Owner

La désactivation du rôle owner est particulièrement dangereuse.

Le rôle owner protège notamment :

- la continuité de l’ownership ;
- l’administration du workspace ;
- la gestion des membres ;
- la gestion des rôles ;
- la récupération ;
- le transfert de responsabilité.

---

## Décision recommandée pour Owner

Dans le modèle courant :

```text
SystemType = Owner
```

ne doit pas pouvoir être désactivé par une opération ordinaire.

---

## OwnerRoleDisablement

Deux options existent.

### Interdiction absolue

```text
Owner Role cannot be Disabled
```

### Récupération exceptionnelle

Autoriser uniquement si :

- un rôle owner de remplacement existe ;
- les memberships owner ont été transférés ;
- la continuité est garantie ;
- un workflow de récupération renforcé est utilisé.

---

## Recommandation

Retenir :

```text
Owner Role cannot be Disabled
while it is the active ownership Role of the Workspace
```

Si une transformation structurelle est nécessaire, utiliser une orchestration dédiée.

Erreur :

```text
OwnerRoleCannotBeDisabled
```

---

## Dernier chemin d’administration

Un rôle non-owner peut néanmoins être le dernier rôle possédant :

```text
workspace.roles.manage
```

ou une capacité équivalente.

La désactivation pourrait rendre le workspace non administrable.

---

## Administration continuity

Condition conceptuelle :

```text
at least one active Membership
through an Active Role
retains required administration capabilities
```

---

## Invariant multi-agrégats

Cette vérification dépend :

- de plusieurs rôles ;
- de plusieurs memberships ;
- de leurs statuts ;
- de leurs permissions effectives.

Elle dépasse l’agrégat `Role`.

---

## Coordination recommandée

Pour les rôles administratifs critiques :

- verrou de gouvernance du workspace ;
- version de gouvernance ;
- projection fortement consistante ;
- contrainte transactionnelle spécialisée ;
- orchestration sérielle.

---

## DefaultMember Role

La désactivation du rôle par défaut peut empêcher :

- la création ordinaire de memberships ;
- l’acceptation de certaines invitations ;
- l’attribution automatique ;
- les workflows de bootstrap.

---

## Politique recommandée pour DefaultMember

La désactivation peut être autorisée uniquement si :

- un autre rôle par défaut actif est défini atomiquement ;
- ou la création ordinaire de memberships est explicitement suspendue ;
- ou un workflow de migration est en cours.

Comme le modèle impose généralement un seul `DefaultMember` role, une commande simple ne peut pas résoudre seule ce remplacement.

---

## Recommandation

Refuser la désactivation ordinaire de :

```text
SystemType = DefaultMember
```

tant qu’il est configuré comme rôle par défaut effectif.

Utiliser une orchestration dédiée :

```text
ReplaceDefaultMemberRole
```

ou une opération de gouvernance équivalente.

---

## Guest Role

La désactivation peut empêcher de nouvelles affectations guest et retirer les accès existants.

Elle est généralement autorisée sous réserve de l’impact.

---

## ServiceAccount Role

La désactivation d’un rôle de compte de service peut interrompre :

- des intégrations ;
- des tâches planifiées ;
- des imports ;
- des exports ;
- des webhooks ;
- des synchronisations ;
- des processus métier.

Une confirmation opérationnelle peut être nécessaire.

---

## Rôle External

Pour :

```text
RoleType = External
```

la désactivation locale dépend de la source de vérité.

Le système vérifie :

- `ExternalReference` ;
- `ExternalVersion` ;
- autorité de la source ;
- fraîcheur de synchronisation ;
- politique d’override ;
- comportement du prochain cycle de synchronisation.

---

## Rôle TemplateDerived

Pour :

```text
RoleType = TemplateDerived
```

le système vérifie :

- le contrôle du statut ;
- les overrides autorisés ;
- la divergence locale ;
- le comportement de la prochaine synchronisation.

---

## Rôle ProductManaged

Un rôle product-managed ne peut être désactivé que par :

- le produit ;
- une migration ;
- une récupération ;
- une autorité de plateforme explicitement habilitée.

---

## Source de contrôle du statut

Le statut peut être :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

---

## LocallyManaged

La désactivation locale est permise sous réserve des règles métier.

---

## ExternallyManaged

Seule la source externe peut normalement désactiver le rôle.

---

## TemplateManaged

Le modèle contrôle le statut.

---

## ProductManaged

Le produit contrôle le statut.

---

## LocallyOverridable

Un override local peut être permis avec audit explicite.

---

## Confirmation

Une confirmation peut être nécessaire lorsque :

- des memberships actifs sont concernés ;
- des sessions actives existent ;
- le rôle est privilégié ;
- le rôle est critique ;
- le rôle alimente une intégration ;
- l’acteur détient le rôle cible ;
- la désactivation risque de bloquer l’acteur ;
- le rôle est `DefaultMember` ;
- le rôle est utilisé dans des workflows en attente.

---

## Self-disablement

L’acteur peut détenir le rôle qu’il désactive.

```text
ActorMembership.RoleId = TargetRoleId
```

Cette opération peut lui retirer immédiatement ses propres permissions.

---

## Politique de self-disablement

Elle peut être autorisée si :

- l’acteur est explicitement autorisé ;
- une confirmation forte est fournie ;
- la continuité administrative reste garantie ;
- l’acteur comprend qu’il perdra ses capacités ;
- le workflow peut terminer après commit ;
- aucun invariant owner n’est violé.

---

## Transaction et réponse

Le système doit calculer et persister le résultat avant que la nouvelle autorisation ne s’applique au prochain appel.

La réponse à la commande ne doit pas nécessiter une nouvelle autorisation après commit pour être remise au demandeur.

---

## Self-lockout

Exemple :

```text
Actor disables own Role
↓
Actor loses workspace.roles.manage
↓
Actor cannot enable Role again
```

Cette situation peut être autorisée uniquement si un autre chemin administratif existe.

---

## Approbation

Une approbation peut être requise pour :

- un rôle privilégié ;
- un rôle critique ;
- un rôle avec de nombreux détenteurs ;
- un rôle système ;
- un rôle administrateur ;
- un rôle de compte de service critique ;
- une self-disablement ;
- une désactivation sans rôle de remplacement.

---

## Séparation des devoirs

Pour une désactivation sensible :

```text
Requester != Approver
```

peut être obligatoire.

---

## Préconditions

Avant exécution :

- le rôle existe ;
- le workspace existe ;
- le rôle appartient au workspace ;
- le rôle est `Active` ;
- le rôle n’est ni archivé ni supprimé ;
- l’acteur ou le workflow est autorisé ;
- la source contrôle le statut ;
- le rôle owner n’est pas désactivé illicitement ;
- le rôle par défaut reste cohérent ;
- la continuité administrative est préservée ;
- un chemin de récupération reste disponible ;
- les détenteurs actifs sont identifiés ;
- les sessions actives sont évaluées ;
- les intégrations critiques sont évaluées ;
- la self-disablement est détectée ;
- les confirmations requises sont valides ;
- les approbations requises sont valides ;
- la politique de session est valide ;
- les versions attendues correspondent ;
- la demande est idempotente ;
- aucune modification concurrente incompatible n’a gagné.

---

## Traitement métier

### 1. Vérifier l’idempotence

Le système recherche une demande déjà traitée avec :

```text
RoleId + DisableRequestId
```

Une répétition identique retourne le résultat initial.

---

### 2. Charger le Role

Le système charge :

- identité ;
- workspace ;
- statut ;
- type ;
- `SystemType` ;
- permissions ;
- politiques ;
- versions ;
- source de contrôle ;
- références externes ou de modèle.

---

### 3. Vérifier le statut

Condition attendue :

```text
Role.Status = Active
```

---

### 4. Refuser les états incompatibles

```text
Disabled
→ RoleAlreadyDisabled
```

```text
Archived
→ ArchivedRoleCannotBeDisabled
```

```text
Removed
→ RemovedRoleCannotBeDisabled
```

---

### 5. Charger le Workspace

Le système vérifie :

- son existence ;
- son statut ;
- ses règles de gouvernance ;
- ses rôles structurels ;
- ses chemins d’administration ;
- ses politiques produit.

---

### 6. Charger le contexte de l’acteur

Pour un acteur humain :

- `User` ;
- `Membership` ;
- rôle ;
- permissions effectives ;
- niveau d’authentification ;
- relation avec le rôle cible ;
- effet de self-disablement.

Pour un `SystemActor` :

- identité technique ;
- autorité ;
- source ;
- périmètre ;
- référence externe ou de modèle.

---

### 7. Autoriser la commande

Le système vérifie :

```text
Actor may disable Roles
```

puis :

```text
Actor may disable this RoleType
```

et :

```text
Actor may disable this SystemType
```

---

### 8. Vérifier la source de contrôle

Le système vérifie que `DisableSource` est autorisée à changer le statut.

---

### 9. Calculer les permissions effectives actuelles

```text
EffectivePermissionSetBefore
```

est calculé pour mesurer l’impact.

---

### 10. Charger les détenteurs

Le système identifie :

- memberships actifs ;
- memberships suspendus ;
- comptes de service ;
- détenteurs privilégiés ;
- acteur lui-même.

---

### 11. Charger les Sessions

Le système calcule :

- nombre de sessions actives ;
- sessions privilégiées ;
- sessions utilisant des claims statiques ;
- sessions liées à des comptes de service.

---

### 12. Identifier les intégrations concernées

Le système évalue :

- comptes de service ;
- automatisations ;
- synchronisations ;
- workflows opérationnels.

---

### 13. Évaluer les invitations en attente

La commande ne les modifie pas, mais calcule l’impact.

---

### 14. Évaluer les transferts en attente

La commande ne les modifie pas, mais calcule l’impact.

---

### 15. Appliquer les règles du RoleType

Le système applique les règles de :

```text
System
Custom
External
TemplateDerived
```

---

### 16. Appliquer les règles du SystemType

Le système applique les règles de :

```text
Owner
DefaultMember
Guest
ServiceAccount
None
```

---

### 17. Protéger le Owner Role

Si le rôle est le rôle owner effectif :

```text
OwnerRoleCannotBeDisabled
```

sauf workflow exceptionnel explicitement modélisé.

---

### 18. Protéger le DefaultMember Role

Le système vérifie que la désactivation ne casse pas la configuration par défaut.

---

### 19. Vérifier la continuité administrative

Le système simule l’état :

```text
Target Role.Status = Disabled
```

puis vérifie qu’un chemin d’administration reste disponible.

---

### 20. Vérifier le chemin de récupération

Le système vérifie qu’une récupération demeure possible.

---

### 21. Détecter la self-disablement

```text
ActorMembership.RoleId = TargetRoleId
```

---

### 22. Vérifier la confirmation

Lorsque requise, la confirmation doit couvrir :

```text
RoleId
WorkspaceId
RoleVersion
CurrentActiveMembershipCount
CurrentActiveSessionCount
DisableReason
SessionHandlingPolicy
```

---

### 23. Vérifier l’approbation

L’approbation doit couvrir l’intention exacte et les versions concernées.

---

### 24. Vérifier SessionHandlingPolicy

La politique demandée doit être compatible avec :

- le motif ;
- la sensibilité ;
- les risques ;
- les capacités de l’infrastructure.

---

### 25. Vérifier ExpectedRoleVersion

```text
Role.Version = ExpectedRoleVersion
```

---

### 26. Vérifier ExpectedAuthorizationStateVersion

```text
Role.AuthorizationStateVersion
=
ExpectedAuthorizationStateVersion
```

---

### 27. Modifier le statut

```text
Role.Status = Disabled
```

---

### 28. Conserver le Permission Set

Aucune permission n’est ajoutée ou retirée.

---

### 29. Conserver les policies

Aucune politique n’est modifiée.

---

### 30. Incrémenter AuthorizationStateVersion

```text
Role.AuthorizationStateVersion += 1
```

---

### 31. Incrémenter Role.Version

```text
Role.Version += 1
```

---

### 32. Produire RoleDisabled

L’agrégat produit :

```text
RoleDisabled
```

---

### 33. Enregistrer l’idempotence

La demande et son résultat sont enregistrés.

---

### 34. Commit atomique

Le statut, les versions, l’idempotence et l’événement sont persistés ensemble.

---

## Résultat attendu

Après succès :

```text
Role
├── same RoleId
├── same WorkspaceId
├── same Metadata
├── Status: Disabled
├── same AssignmentPolicy
├── same TransferPolicy
├── same PermissionAssignments
├── same PermissionSetVersion
├── AuthorizationStateVersion: incremented
└── Role.Version: incremented
```

---

## Résultat fonctionnel

Structure recommandée :

```text
RoleDisableResult
├── RoleId
├── PreviousStatus
├── CurrentStatus
├── AffectedActiveMembershipCount
├── AffectedSuspendedMembershipCount
├── AffectedActiveSessionCount
├── LostEffectivePermissionIds
├── AffectedIntegrationCount
├── PendingInvitationCount
├── PendingTransferCount
├── SelfDisablement
├── AdministrationContinuityPreserved
├── RecoveryPathAvailable
├── SessionHandlingPolicy
├── AuthorizationStateVersion
└── RoleVersion
```

---

## Invariants concernés

### Transition valide

```text
Active
→ Disabled
```

---

### Identité stable

```text
RoleId remains unchanged
```

---

### Appartenance stable

```text
WorkspaceId remains unchanged
```

---

### Permission Set inchangé

```text
PermissionAssignments remain unchanged
```

---

### Policies inchangées

```text
AssignmentPolicy remains unchanged
TransferPolicy remains unchanged
```

---

### Permission inefficace quand Role Disabled

```text
Role.Status = Disabled
↓
Role Permissions are ineffective
```

---

### Owner continuity

Le rôle owner effectif ne peut pas être désactivé sans remplacement structurel valide.

---

### Administration continuity

Un chemin d’administration doit rester disponible lorsque l’invariant l’exige.

---

### DefaultMember continuity

La configuration du rôle par défaut doit rester cohérente.

---

### Source d’autorité

La source contrôlant le statut doit être respectée.

---

### Version d’autorisation

```text
AuthorizationStateVersion changes
after disablement
```

---

## Événement produit

### RoleDisabled

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `PreviousStatus`
- `CurrentStatus`
- `RoleType`
- `RoleSystemType`
- `RoleSensitivity`
- `ExplicitPermissionCount`
- `EffectivePermissionIdsLost`
- `AffectedActiveMembershipCount`
- `AffectedSuspendedMembershipCount`
- `AffectedActiveSessionCount`
- `AffectedIntegrationCount`
- `PendingInvitationCount`
- `PendingTransferCount`
- `SelfDisablement`
- `AdministrationContinuityPreserved`
- `RecoveryPathAvailable`
- `SessionHandlingPolicy`
- `DisabledBy`
- `DisabledAt`
- `DisableReason`
- `DisableSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityIncidentId`
- `ComplianceCaseId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `DisableRequestId`
- `CorrelationId`
- `PermissionSetVersion`
- `AuthorizationStateVersion`
- `RoleVersion`

---

## EffectivePermissionIdsLost

Toutes les permissions effectives du rôle peuvent être considérées comme perdues par ses détenteurs actifs.

Pour éviter des événements trop volumineux, deux stratégies sont possibles.

### Liste complète

Appropriée si le nombre de permissions est faible et borné.

### Résumé

```text
EffectivePermissionCountLost
PermissionSensitivitySummary
PermissionCatalogVersion
```

avec détail disponible dans une projection d’audit.

---

## Recommandation

Inclure :

```text
EffectivePermissionCountLost
CriticalPermissionCountLost
PrivilegedPermissionCountLost
```

et éventuellement les identifiants si le volume reste borné.

---

## Données interdites dans l’événement

L’événement ne doit pas contenir :

- de secrets ;
- de tokens ;
- de détails MFA ;
- de liste nominative des détenteurs ;
- de contenu confidentiel d’incident ;
- de données personnelles inutiles ;
- de claims complets ;
- de journaux de session ;
- de contenu complet de dossier.

---

## Événements secondaires possibles

```text
RoleAuthorizationDisabled
RoleHoldersAuthorizationReevaluationRequested
RoleSessionsRevocationRequested
RoleSessionsRefreshRequested
RolePendingAssignmentsBlocked
RoleIntegrationsSuspensionRequested
RoleDisabledForSecurityIncident
```

---

## Événements non produits

La commande ne produit pas :

```text
RolePermissionRevoked
MembershipSuspended
MembershipRemoved
MembershipRoleChanged
SessionRevoked
InvitationRevoked
RoleArchived
RoleAssignmentPolicyChanged
RoleTransferPolicyChanged
```

---

## Effet sur les Memberships

La commande ne modifie aucun membership.

Les memberships conservent :

```text
Membership.RoleId = disabled Role
```

mais ne reçoivent plus ses permissions.

---

## Effet sur les Sessions

Les sessions peuvent rester techniquement ouvertes.

Leurs décisions d’autorisation doivent néanmoins refléter immédiatement le nouveau statut.

Selon `SessionHandlingPolicy`, des commandes de révocation ou refresh peuvent être déclenchées.

---

## Effet sur les Invitations

Les invitations restent inchangées.

Le rôle doit être réévalué à l’acceptation.

---

## Effet sur les Transferts

Les transferts restent inchangés, mais ne peuvent pas s’exécuter vers le rôle désactivé sans réévaluation.

---

## Effet sur les caches

Après commit, les caches dépendant de :

- `Role.Status` ;
- permissions effectives ;
- capacités des memberships ;
- claims ;
- projections d’autorisation ;

doivent être invalidés ou rendus obsolètes par version.

---

## Idempotence

Clé recommandée :

```text
RoleId + DisableRequestId
```

---

## Empreinte idempotente

L’empreinte inclut au minimum :

```text
RoleId
DisabledBy
DisabledAt
DisableReason
DisableSource
ConfirmationId
ApprovalId
SecurityIncidentId
ComplianceCaseId
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
SessionHandlingPolicy
```

---

## Répétition identique

Une répétition exacte retourne le résultat initial sans :

- produire un second événement ;
- incrémenter une nouvelle version ;
- modifier `DisabledAt` ;
- répéter les invalidations ;
- répéter les notifications ;
- répéter les demandes de révocation de session ;
- consommer une nouvelle approbation.

---

## Nouvelle demande sur un rôle déjà désactivé

Une autre intention avec un autre identifiant retourne :

```text
RoleAlreadyDisabled
```

---

## Conflit d’idempotence

Le même `DisableRequestId` utilisé avec d’autres données produit :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
DisableRole succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry retourne :

- le même statut ;
- le même impact ;
- le même `DisabledAt` ;
- les mêmes versions ;
- le même événement logique.

---

## Concurrence

### Deux désactivations concurrentes

Une seule réussit.

La seconde rencontre :

```text
RoleVersionConflict
```

ou :

```text
RoleAlreadyDisabled
```

---

### Disable contre Enable

Deux intentions opposées ciblent le même rôle.

Une seule version gagne.

---

### Disable contre Archive

Si l’archivage gagne d’abord :

```text
ArchivedRoleCannotBeDisabled
```

Si la désactivation gagne d’abord, `ArchiveRole` peut éventuellement poursuivre depuis `Disabled` selon son contrat.

---

### Disable contre GrantPermissionToRole

Une permission peut être ajoutée pendant l’analyse.

La désactivation reste conceptuellement possible, mais l’impact final doit être recalculé.

---

### Disable contre RevokePermissionFromRole

Même principe.

---

### Disable contre ChangeMembershipRole

Un membership peut quitter ou rejoindre le rôle pendant l’analyse.

Les nombres d’impact peuvent devenir obsolètes.

Les invariants forts doivent être protégés.

---

### Disable contre TransferMembershipRole

Un transfert peut déplacer un membership vers le rôle au même moment.

Le rôle désactivé ne doit pas devenir cible valide après commit.

---

### Disable contre CreateMembership

Un nouveau membership peut être créé avec le rôle.

La création doit vérifier la version ou l’état actuel du rôle.

---

### Disable contre AcceptInvitation

L’acceptation doit échouer ou revalider si le rôle devient désactivé.

---

### Disable contre changement de rôle par défaut

La désactivation et le remplacement du `DefaultMember` role doivent être coordonnés.

---

### Disable de plusieurs rôles administratifs

Deux désactivations concurrentes peuvent chacune sembler préserver la continuité, mais supprimer ensemble tous les chemins d’administration.

---

## Coordination multi-rôles

Pour les capacités critiques :

```text
workspace.roles.manage
workspace.members.manage
workspace.ownership.transfer
```

la validation doit être protégée à l’échelle du workspace.

---

## Stratégies de coordination

- verrou transactionnel sur une gouvernance workspace ;
- version de gouvernance ;
- agrégat de sécurité ;
- contrainte persistante ;
- sérialisation des opérations critiques ;
- projection synchrone fortement consistante.

---

## Atomicité

Le même commit doit contenir :

```text
Role.Status = Disabled
+
AuthorizationStateVersion increment
+
Role.Version increment
+
Idempotency record
+
RoleDisabled event
```

---

## États interdits

```text
Role.Status = Disabled
AND
RoleDisabled missing
```

```text
RoleDisabled persisted
AND
Role.Status remains Active
```

```text
PermissionAssignments changed by DisableRole
```

```text
AssignmentPolicy changed by DisableRole
```

```text
TransferPolicy changed by DisableRole
```

```text
PermissionSetVersion incremented
without Permission composition change
```

```text
AuthorizationStateVersion unchanged
after disablement
```

```text
Owner Role disabled
without valid structural replacement
```

```text
last administration path removed
without recovery authority
```

```text
ExternallyManaged Role disabled locally
without override authority
```

---

## Outbox transactionnelle

`RoleDisabled` doit être enregistré dans la même transaction que le changement de statut.

La publication intervient après commit.

---

## Effets externes

Après succès, des handlers peuvent :

- invalider les caches d’autorisation ;
- recalculer les capacités des memberships ;
- réévaluer les sessions ;
- révoquer certaines sessions ;
- rafraîchir les claims ;
- bloquer les invitations concernées ;
- bloquer les transferts en attente ;
- suspendre des intégrations ;
- notifier les détenteurs ;
- notifier les owners ;
- notifier la sécurité ;
- notifier la conformité ;
- mettre à jour les projections ;
- poursuivre une réponse à incident ;
- poursuivre une migration.

---

## Notifications

Une notification est recommandée lorsque :

- des memberships actifs sont concernés ;
- le rôle est privilégié ou critique ;
- le rôle est administrateur ;
- le rôle est `DefaultMember` ;
- le rôle est associé à des comptes de service ;
- une intégration est interrompue ;
- la désactivation résulte d’un incident ;
- l’acteur se désactive lui-même ;
- une remédiation est attendue.

---

## Notification des détenteurs

Les détenteurs peuvent être informés que leur rôle a été temporairement désactivé.

Le message doit distinguer :

```text
Role disabled
```

de :

```text
Membership suspended
```

Le membership n’est pas nécessairement suspendu.

---

## Audit

Une désactivation réussie doit enregistrer :

- `RoleId`
- `WorkspaceId`
- statut précédent
- statut final
- `RoleType`
- `RoleSystemType`
- source de contrôle
- classification du rôle
- permissions explicites
- permissions effectives perdues
- nombre de memberships actifs
- nombre de memberships suspendus
- nombre de sessions actives
- intégrations affectées
- invitations en attente
- transferts en attente
- self-disablement
- continuité administrative
- continuité owner
- chemin de récupération
- politique de session
- `DisabledBy`
- `DisabledAt`
- `DisableReason`
- `DisableSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityIncidentId`
- `ComplianceCaseId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `DisableRequestId`
- `CorrelationId`
- versions précédentes
- versions finales
- résultat final.

---

## Questions auxquelles l’audit doit répondre

```text
which Role was disabled
inside which Workspace
who disabled it
why it was disabled
which source controlled the Status
how many active Memberships lost effective access
which effective capabilities became unavailable
whether active Sessions were affected
whether the actor disabled their own Role
whether administrative continuity remained possible
whether ownership continuity remained valid
which Session handling policy was applied
which approvals or incidents justified the action
which authorization version became effective
```

---

## Sécurité

La commande doit garantir que :

- seul un acteur autorisé désactive le rôle ;
- le rôle est actif ;
- un rôle archivé n’est pas modifié ;
- la source de vérité est respectée ;
- le rôle owner n’est pas désactivé illicitement ;
- le rôle par défaut reste cohérent ;
- la continuité administrative est préservée ;
- la self-disablement est contrôlée ;
- les sessions et caches sont réévaluables ;
- les invitations et transferts ne contournent pas la désactivation ;
- aucun membership n’est modifié implicitement ;
- aucune permission n’est supprimée ;
- l’opération est idempotente ;
- la concurrence ne crée pas un gap de gouvernance ;
- le statut et l’événement sont commités atomiquement.

---

## Confidentialité

La commande et son événement ne doivent pas contenir :

- de secrets ;
- de tokens ;
- de mots de passe ;
- de détails MFA ;
- de contenu complet d’incident ;
- de liste nominative des détenteurs ;
- de données personnelles inutiles ;
- de claims complets ;
- de journaux de session ;
- de justificatifs sensibles bruts.

---

## Erreurs métier

### RoleNotFound

Le rôle n’existe pas.

---

### WorkspaceNotFound

Le workspace n’existe pas.

---

### WorkspaceUnavailable

Le workspace n’autorise pas l’opération.

---

### RoleAlreadyDisabled

Le rôle est déjà désactivé.

---

### ArchivedRoleCannotBeDisabled

Le rôle archivé ne peut pas être désactivé.

---

### RemovedRoleCannotBeDisabled

Le rôle supprimé ne peut pas être désactivé.

---

### ActorNotAuthorized

L’acteur ne peut pas désactiver de rôle.

---

### RoleDisableNotAuthorized

L’acteur ne peut pas désactiver ce rôle précis.

---

### PrivilegedRoleDisableNotAuthorized

L’acteur ne peut pas désactiver un rôle privilégié.

---

### CriticalRoleDisableNotAuthorized

L’acteur ne peut pas désactiver un rôle critique.

---

### SystemRoleDisableNotAuthorized

L’acteur ne peut pas désactiver ce rôle système.

---

### OwnerRoleCannotBeDisabled

Le rôle owner effectif ne peut pas être désactivé par cette commande.

---

### DefaultMemberRoleCannotBeDisabled

Le rôle par défaut ne peut pas être désactivé sans remplacement coordonné.

---

### RoleStatusSourceNotAuthoritative

La source ne contrôle pas le statut du rôle.

---

### ExternallyManagedRoleCannotBeDisabledLocally

Le rôle externe ne peut pas être désactivé localement.

---

### TemplateManagedRoleCannotBeDisabledLocally

Le modèle contrôle le statut.

---

### ProductManagedRoleCannotBeDisabledLocally

Le produit contrôle le statut.

---

### LocalStatusOverrideNotAllowed

Aucun override local n’est autorisé.

---

### ExternalReferenceRequired

La référence externe est obligatoire.

---

### ExternalVersionRequired

La version externe est obligatoire.

---

### TemplateReferenceRequired

Le rôle dérivé doit référencer un modèle.

---

### TemplateVersionConflict

La version du modèle ne correspond pas.

---

### AdministrationContinuityViolation

La désactivation supprimerait le dernier chemin d’administration.

---

### OwnershipContinuityViolation

La désactivation violerait la continuité de l’ownership.

---

### RecoveryPathRequired

Aucun chemin de récupération valide ne resterait disponible.

---

### SelfDisablementConfirmationRequired

L’acteur doit confirmer la désactivation de son propre rôle.

---

### SelfDisablementWouldLockActorOut

L’acteur perdrait ses capacités sans autre chemin administratif.

---

### ConfirmationRequired

Une confirmation est nécessaire.

---

### ConfirmationInvalid

La confirmation ne couvre pas l’intention exacte.

---

### ApprovalRequired

Une approbation est nécessaire.

---

### ApprovalInvalid

L’approbation est invalide.

---

### ApprovalExpired

L’approbation a expiré.

---

### ApprovalScopeMismatch

L’approbation ne couvre pas le rôle, l’impact ou les versions concernés.

---

### SelfApprovalForbidden

L’acteur ne peut pas approuver sa propre désactivation sensible.

---

### SecurityIncidentReferenceRequired

Un incident doit être référencé pour cette source.

---

### ComplianceCaseRequired

Un dossier de conformité est nécessaire.

---

### SessionHandlingPolicyInvalid

La politique de traitement des sessions est invalide.

---

### SessionRevocationCapabilityUnavailable

L’infrastructure ne peut pas appliquer la politique de session demandée.

---

### OperationalImpactNotAccepted

Le risque opérationnel n’a pas été accepté.

---

### CriticalIntegrationWouldBeInterrupted

Une intégration critique serait interrompue sans procédure adaptée.

---

### PendingWorkflowPolicyInvalid

La politique relative aux workflows en attente est invalide.

---

### ProductPolicyViolation

Une règle produit interdit la désactivation.

---

### LicensePolicyViolation

La désactivation rendrait la configuration de licence incohérente.

---

### RoleVersionConflict

Le rôle a changé depuis la décision initiale.

---

### AuthorizationStateVersionConflict

L’état d’autorisation a changé.

---

### RoleDisableConflict

Une modification concurrente empêche la désactivation.

---

### GovernanceVersionConflict

La gouvernance du workspace a changé.

---

### IdempotencyConflict

Le même identifiant représente une autre intention.

---

## Décisions de conception

### DisableRole est temporaire

La transition reste réversible avec `EnableRole`.

---

### DisableRole conserve le Role

L’identité, les métadonnées, les permissions et les politiques sont préservées.

---

### Les Permissions deviennent inefficaces

Le permission set n’est pas supprimé.

---

### Aucun Membership n’est modifié

Les memberships restent affectés au rôle.

---

### Les Memberships actifs perdent leurs capacités

Cette perte résulte du statut du rôle.

---

### Les Sessions ne sont pas systématiquement révoquées

Le traitement dépend de `SessionHandlingPolicy`.

---

### Les Invitations ne sont pas révoquées

Elles sont réévaluées à l’acceptation.

---

### Les Transferts ne sont pas supprimés

Ils deviennent non exécutables tant que le rôle reste désactivé.

---

### Les policies restent inchangées

Elles seront réutilisées lors d’une future activation.

---

### PermissionSetVersion reste inchangé

La composition des permissions ne change pas.

---

### AuthorizationStateVersion est incrémentée

L’efficacité du rôle change.

---

### Owner ne peut pas être désactivé ordinairement

La continuité d’ownership exige une orchestration dédiée.

---

### DefaultMember nécessite un remplacement coordonné

La désactivation simple ne doit pas casser le bootstrap.

---

### La self-disablement est contrôlée

L’acteur peut perdre immédiatement ses propres permissions.

---

### Un événement métier dédié est produit

```text
RoleDisabled
```

---

## Cas limites

### Rôle actif sans permission

La désactivation est autorisée.

Elle bloque principalement les futures affectations et workflows.

---

### Rôle actif sans détenteur

La désactivation est autorisée.

---

### Rôle avec uniquement des memberships suspendus

La désactivation est autorisée.

Aucune permission active n’est immédiatement perdue.

---

### Rôle avec plusieurs milliers de détenteurs

La commande ne doit pas modifier chaque membership.

Elle versionne l’état du rôle et publie un événement.

---

### Rôle avec claims statiques

Une invalidation ou révocation de session renforcée peut être nécessaire.

---

### Rôle détenu par l’acteur

Une confirmation forte peut être requise.

---

### Dernier rôle administratif

La désactivation est refusée.

---

### Rôle Owner

La désactivation ordinaire est refusée.

---

### Rôle DefaultMember

La désactivation est refusée tant qu’aucun remplacement coordonné n’est établi.

---

### Rôle Guest

La désactivation peut retirer tous les accès invités.

---

### Rôle ServiceAccount

La désactivation peut interrompre une intégration.

---

### Rôle externe avec source indisponible

Une désactivation locale est refusée si la source est autoritaire, sauf override de récupération.

---

### Rôle template-derived

La désactivation dépend de la politique d’override.

---

### Invitations en attente

Elles restent présentes, mais l’acceptation devra échouer ou attendre la réactivation.

---

### Transfert vers le rôle en attente

Le transfert reste en attente ou bloqué.

---

### Transfert depuis le rôle

Le retrait d’un membership vers un rôle actif peut rester autorisé.

---

### Désactivation liée à un incident

Les sessions peuvent être révoquées immédiatement par un handler de sécurité.

---

### Réactivation planifiée

Elle doit invoquer `EnableRole` et revalider tout le contexte.

---

### Retry après succès

Le résultat initial est retourné sans nouvel événement.

---

## Checklist de validation

Avant commit :

```text
Role exists
Workspace exists
Role belongs to Workspace
Role Status is Active
Role is not Archived
Actor or SystemActor is authorized
DisableSource controls Role Status
RoleType-specific rules are satisfied
SystemType-specific rules are satisfied
Owner Role is protected
DefaultMember continuity is preserved
Active Membership impact is calculated
Suspended Membership impact is calculated
Active Session impact is calculated
Effective Permission loss is calculated
Integration impact is evaluated
Pending Invitations are evaluated
Pending Transfers are evaluated
Self-disablement is evaluated
Administration continuity is preserved
Ownership continuity is preserved
Recovery path remains available
Required confirmation is valid
Required approval is valid
Security incident reference is valid when required
Compliance case is valid when required
SessionHandlingPolicy is valid
Expected Role version matches
Expected AuthorizationStateVersion matches
Governance version is current when required
Idempotency is verified
PermissionAssignments remain unchanged
PermissionSetVersion remains unchanged
AssignmentPolicy remains unchanged
TransferPolicy remains unchanged
AuthorizationStateVersion can be incremented
No Membership is modified
No Session is directly modified by the Role aggregate
RoleDisabled can be persisted atomically
```

---

## Synthèse

`DisableRole` suspend temporairement l’efficacité d’un rôle actif dans son workspace.

Elle garantit que :

- le rôle existe ;
- la transition `Active → Disabled` est valide ;
- le rôle reste intact et réactivable ;
- l’acteur ou le workflow est autorisé ;
- la source de vérité est respectée ;
- les rôles owner et default member sont protégés ;
- la continuité administrative est préservée ;
- un chemin de récupération reste disponible ;
- les memberships actifs concernés sont identifiés ;
- les sessions et intégrations concernées sont évaluées ;
- la self-disablement est contrôlée ;
- les invitations et transferts ne contournent pas le statut ;
- aucune permission n’est retirée ;
- aucune politique n’est modifiée ;
- aucun membership n’est directement modifié ;
- `PermissionSetVersion` reste inchangé ;
- `AuthorizationStateVersion` est incrémentée ;
- les caches et sessions peuvent être réévalués ;
- l’opération est idempotente et protégée contre la concurrence ;
- le changement de statut et l’événement sont commités atomiquement.

Le résultat final est :

```text
Role
├── same identity
├── same Workspace
├── same metadata
├── Status: Disabled
├── same AssignmentPolicy
├── same TransferPolicy
├── same PermissionAssignments
├── same PermissionSetVersion
└── new AuthorizationStateVersion
```