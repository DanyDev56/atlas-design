---
id: IDN-CMD-ARCHIVE-ROLE
title: ArchiveRole
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-31

aggregate: Role

invariants:
  - IDN-INV-004
  - IDN-INV-005
  - IDN-INV-006
  - IDN-INV-011
  - IDN-INV-014
  - IDN-INV-015
  - IDN-INV-019
  - IDN-INV-020
  - IDN-INV-021

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
  - ../events.md
  - CreateRole.md
  - EnableRole.md
  - DisableRole.md
  - ChangeMembershipRole.md
  - RemoveMembership.md
  - RevokeInvitation.md
  - ExpireInvitation.md
  - ../workflows.md
  - ../decision-record.md
---

# ArchiveRole

## Objectif

La commande `ArchiveRole` retire définitivement un `Role` de l’usage métier courant tout en conservant son identité, sa configuration et son historique à des fins d’audit et de traçabilité.

Transitions autorisées :

```text
Active
↓
Archived
```

ou :

```text
Disabled
↓
Archived
```

L’archivage représente une décision de fin de vie métier.

Un rôle archivé :

- n’accorde plus aucune permission effective ;
- ne peut plus être attribué ;
- ne peut plus être transféré ;
- ne peut plus être activé ;
- ne peut plus être modifié ;
- ne peut plus être utilisé dans de nouveaux workflows ;
- reste conservé pour l’historique.

La commande ne doit pas :

- supprimer physiquement le rôle ;
- effacer ses métadonnées ;
- retirer ses permissions ;
- modifier `RoleAssignmentPolicy` ;
- modifier `RoleTransferPolicy` ;
- modifier directement les memberships ;
- migrer automatiquement les memberships ;
- révoquer directement les invitations ;
- expirer directement les invitations ;
- annuler directement les transferts ;
- supprimer les événements historiques ;
- supprimer les références d’audit ;
- permettre une restauration implicite ;
- modifier le `Workspace`.

---

## Intention métier

La commande répond à l’intention suivante :

```text
permanently retire an existing Role
from future business use
while preserving its historical identity
and configuration
```

L’archivage indique que le rôle n’a plus vocation à revenir dans le cycle opérationnel normal.

---

## Distinction fondamentale

Il faut distinguer :

```text
DisableRole
```

de :

```text
ArchiveRole
```

---

## DisableRole

`DisableRole` représente une interruption temporaire.

```text
Active
→ Disabled
→ Active
```

Le rôle :

- peut revenir ;
- conserve ses affectations ;
- reste modifiable selon les règles du domaine ;
- peut être réactivé par `EnableRole`.

---

## ArchiveRole

`ArchiveRole` représente une sortie définitive de l’usage métier.

```text
Active or Disabled
→ Archived
```

Le rôle :

- ne doit plus revenir dans les workflows ordinaires ;
- devient immuable ;
- reste uniquement comme référence historique ;
- nécessite éventuellement une commande de restauration exceptionnelle distincte si ce besoin existe un jour.

---

## Décision de cycle de vie

Cycle recommandé :

```text
CreateRole
↓
Active
├── DisableRole
│   ↓
│ Disabled
│   ├── EnableRole
│   │   ↓
│   │ Active
│   └── ArchiveRole
│       ↓
│     Archived
└── ArchiveRole
    ↓
  Archived
```

L’archivage direct depuis `Active` est autorisé lorsque toutes les préconditions sont satisfaites.

Une désactivation préalable n’est donc pas obligatoire.

---

## RoleStatus

Le cycle de vie du rôle doit être exprimé par un concept explicite :

```text
RoleStatus
```

Valeurs :

```text
Active
Disabled
Archived
```

Cette terminologie évite de confondre :

- l’état opérationnel ;
- la fin de vie métier ;
- la disponibilité ;
- la validité de la configuration.

---

## Signification de Archived

Un rôle `Archived` :

- existe encore ;
- possède toujours son `RoleId` ;
- appartient toujours à son workspace historique ;
- conserve son nom et ses métadonnées ;
- conserve ses permissions explicites historiques ;
- conserve ses politiques ;
- conserve ses versions ;
- reste référencé dans l’audit ;
- peut apparaître dans les projections historiques ;
- ne participe plus aux décisions d’autorisation ;
- ne peut plus recevoir de nouveaux memberships ;
- ne peut plus être utilisé comme rôle de remplacement ;
- ne peut plus être cible d’un transfert ;
- ne peut plus être source d’un transfert ordinaire ;
- ne peut plus être modifié.

---

## Immutabilité après archivage

Après succès :

```text
Role.Status = Archived
```

Le rôle devient immuable pour les commandes ordinaires.

Les commandes suivantes doivent échouer :

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

Une correction exceptionnelle d’historique ne doit pas passer par ces commandes métier.

---

## Pas de suppression physique

L’archivage est une suppression logique métier.

Le rôle reste stocké afin de préserver :

- les journaux d’audit ;
- les événements historiques ;
- les références des anciens memberships ;
- les anciennes invitations ;
- les décisions d’autorisation historiques ;
- les exports ;
- les preuves de conformité ;
- les analyses de sécurité.

---

## Distinction avec une suppression technique

Une suppression physique éventuelle relève :

- de la rétention ;
- de la confidentialité ;
- de la réglementation ;
- de la purge ;
- de l’anonymisation ;
- d’un processus technique distinct.

Elle ne relève pas de `ArchiveRole`.

---

## Agrégat concerné

```text
Role
```

La commande modifie un seul agrégat `Role`.

Elle consulte également :

- le `Workspace` ;
- l’acteur ;
- les memberships référant le rôle ;
- les invitations en attente ;
- les transferts en attente ;
- les sessions actives ;
- les intégrations ;
- les workflows automatisés ;
- les références externes ;
- les modèles ;
- les règles de gouvernance ;
- les contraintes des rôles système ;
- les mécanismes de récupération ;
- les règles de rétention.

---

## Invariant principal

Un rôle archivé ne doit plus être utilisé par un membership actif.

```text
Role.Status = Archived
⇒
no Active Membership references RoleId
```

Cette règle est centrale.

---

## Formulation complète

```text
for every Membership
if Membership.RoleId = ArchivedRole.RoleId
then Membership.Status != Active
```

---

## Recommandation plus stricte

Pour simplifier le modèle, il est préférable d’exiger :

```text
no current Membership references RoleId
```

plutôt que seulement :

```text
no Active Membership references RoleId
```

Mais cette décision dépend de la stratégie de conservation des memberships supprimés ou suspendus.

---

## Politique recommandée

La règle forte proposée est :

```text
no non-removed Membership may reference an Archived Role
```

Ainsi :

```text
Active Membership     -> forbidden
Suspended Membership  -> forbidden
Removed Membership    -> historical reference allowed
```

---

## Pourquoi les Memberships suspendus doivent être traités

Un membership suspendu peut être réactivé.

S’il conserve un rôle archivé :

```text
ReactivateMembership
```

devra gérer un rôle devenu invalide.

Pour éviter cet état :

```text
Suspended Membership
must be reassigned or removed
before Role archival
```

---

## Memberships supprimés

Un membership `Removed` peut conserver la référence historique au rôle archivé.

Cette relation ne participe plus à l’autorisation.

Elle sert à :

- l’audit ;
- l’historique ;
- la reconstitution des responsabilités ;
- la conformité.

Lors d’une restauration :

```text
RestoreMembership
```

un rôle actif doit être explicitement choisi ou validé.

---

## Workflow recommandé

Avant archivage :

```text
identify current Role holders
↓
ChangeMembershipRole
or
RemoveMembership
↓
resolve pending Invitations
↓
resolve pending Transfers
↓
ArchiveRole
```

---

## Exemple avec réaffectation

```text
Role A is being retired
↓
ChangeMembershipRole
Role A → Role B
for all current holders
↓
ArchiveRole A
```

---

## Exemple avec suppression

```text
Role A is obsolete
↓
RemoveMembership
for obsolete memberships
↓
ArchiveRole A
```

---

## Pas de migration automatique

`ArchiveRole` ne doit pas recevoir un :

```text
ReplacementRoleId
```

et migrer automatiquement les memberships.

Cette orchestration serait multi-agrégats et présenterait :

- des erreurs partielles ;
- des besoins de consentement ;
- des contrôles d’attribution ;
- des risques de concurrence ;
- des besoins de compensation.

---

## Workflow de migration dédié

Si le besoin existe, utiliser une orchestration distincte :

```text
RetireRole
```

ou :

```text
MigrateRoleAssignmentsAndArchiveRole
```

Cette orchestration pourrait :

```text
validate replacement Role
↓
move Memberships
↓
resolve Invitations
↓
resolve Transfers
↓
ArchiveRole
```

Elle invoque les commandes métier existantes au lieu de les contourner.

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un administrateur de gouvernance ;
- un administrateur de sécurité ;
- un `SystemActor` ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un workflow produit ;
- un processus de migration ;
- un processus de nettoyage administratif ;
- un administrateur de plateforme dans un périmètre autorisé.

L’acteur doit être identifiable et auditable.

---

## Permission requise

Permission canonique :

```text
workspace.roles.archive
```

Cette clé est nécessaire mais ne contourne pas les protections portant sur les
rôles owner, système, externes ou issus d'un modèle. Ces protections sont des
politiques contextuelles et des exigences d'approbation, pas des permissions
alternatives.

---

## Sources d’archivage

Valeurs recommandées pour `RoleArchiveSource` :

```text
ManualAdministration
OrganizationalChange
RoleReplacement
SecurityGovernance
ComplianceGovernance
ExternalSynchronization
TemplateSynchronization
ProductConfiguration
Migration
WorkspaceClosure
AdministrativeCleanup
PermissionModelEvolution
```

---

## ManualAdministration

Un acteur autorisé décide que le rôle n’est plus nécessaire.

---

## OrganizationalChange

Le rôle disparaît en raison d’une évolution organisationnelle.

---

## RoleReplacement

Le rôle a été remplacé par un autre rôle.

---

## SecurityGovernance

Le rôle est retiré définitivement pour des raisons de sécurité.

---

## ComplianceGovernance

Le rôle ne correspond plus aux exigences de conformité.

---

## ExternalSynchronization

Une source externe autoritaire indique que le rôle n’existe plus.

---

## TemplateSynchronization

Le modèle à l’origine du rôle a été supprimé ou remplacé.

---

## ProductConfiguration

Le produit retire un rôle système obsolète.

---

## Migration

Le rôle est archivé pendant une migration de modèle d’autorisation.

---

## WorkspaceClosure

Le rôle est archivé dans le cadre de la fermeture du workspace.

---

## AdministrativeCleanup

Le rôle est un doublon, un test ou un artefact administratif devenu inutile.

---

## PermissionModelEvolution

Le rôle n’est plus compatible avec le nouveau modèle de permissions.

---

## Motifs d’archivage

Valeurs recommandées pour `RoleArchiveReason` :

```text
NoLongerNeeded
ReplacedByAnotherRole
OrganizationalChange
DuplicateRole
ObsoleteRole
SecurityRetirement
ComplianceRetirement
ExternalRoleRemoved
TemplateRemoved
ProductRoleRetired
MigrationCompleted
WorkspaceClosing
AdministrativeCleanup
Other
```

---

## Données d’entrée

### Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `RoleId` | `RoleId` | Rôle à archiver. |
| `ArchivedBy` | `UserId` ou `SystemActor` | Acteur ou workflow responsable. |
| `ArchivedAt` | Instant | Date métier de l’archivage. |
| `ArchiveReason` | `RoleArchiveReason` | Motif de l’archivage. |
| `ArchiveSource` | `RoleArchiveSource` | Origine de l’archivage. |
| `ArchiveRequestId` | Identifiant | Identifiant idempotent. |

### Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `ExpectedRoleVersion` | Version | Version attendue du rôle. |
| `ExpectedAuthorizationStateVersion` | Version | Version attendue de l’état d’autorisation. |
| `ExpectedAssignmentReferenceVersion` | Version | Version attendue de la projection des affectations. |
| `ConfirmationId` | Identifiant | Confirmation de l’archivage. |
| `ApprovalId` | Identifiant | Approbation éventuelle. |
| `SecurityReviewId` | Identifiant | Revue de sécurité. |
| `ComplianceReviewId` | Identifiant | Revue de conformité. |
| `ReplacementRoleId` | `RoleId` | Référence informative vers le rôle remplaçant. |
| `MigrationPlanId` | Identifiant | Plan de migration ayant préparé l’archivage. |
| `CaseReference` | Identifiant | Référence administrative. |
| `ExternalReference` | Identifiant | Référence externe. |
| `ExternalVersion` | Version | Version externe. |
| `TemplateId` | Identifiant | Modèle concerné. |
| `TemplateVersion` | Version | Version du modèle. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques non métier. |

---

## ReplacementRoleId

`ReplacementRoleId` est uniquement informatif dans `ArchiveRole`.

La commande :

- ne déplace aucun membership ;
- ne met à jour aucune invitation ;
- ne modifie aucun transfert ;
- n’accorde aucune permission ;
- ne garantit pas l’équivalence des rôles.

Le rôle de remplacement doit avoir été utilisé avant l’archivage par les commandes appropriées.

---

## Préconditions d’état

Transitions autorisées :

```text
Active
→ Archived
```

```text
Disabled
→ Archived
```

---

## Rôle déjà archivé

### Même ArchiveRequestId

Retourner le résultat initial.

### Nouvelle demande

Retourner :

```text
RoleAlreadyArchived
```

---

## Rôle introuvable

L'absence du rôle produit `RoleNotFound`. Identity 1.0 ne définit pas d'état
`Removed` pour le `Role`.

---

## Archivage depuis Active

L’archivage depuis `Active` est autorisé uniquement si :

- aucun membership actuel ne l’utilise ;
- aucune session ne dépend encore de ce rôle ;
- aucune invitation en attente ne le cible ;
- aucun transfert en attente ne le cible ;
- aucun workflow actif ne le requiert ;
- aucun invariant système n’est violé.

---

## Archivage depuis Disabled

L’archivage depuis `Disabled` est souvent le chemin le plus sûr.

```text
DisableRole
↓
resolve dependencies
↓
ArchiveRole
```

Cependant, la désactivation préalable ne remplace pas les validations d’archivage.

---

## Rôle avec Membership actif

L’archivage doit échouer.

Erreur :

```text
RoleStillAssignedToActiveMembership
```

---

## Rôle avec Membership suspendu

Politique recommandée : échouer.

Erreur :

```text
RoleStillAssignedToSuspendedMembership
```

Le membership doit être :

- réaffecté ;
- supprimé ;
- ou traité par une orchestration de migration.

---

## Rôle avec Membership supprimé

L’archivage est autorisé.

Le membership supprimé conserve sa référence historique.

---

## Rôle avec références inconnues

Le système doit être capable de vérifier les références métier connues.

Une projection ou un index de références peut être utilisé :

```text
RoleReferenceSummary
├── ActiveMembershipCount
├── SuspendedMembershipCount
├── RemovedMembershipCount
├── PendingInvitationCount
├── PendingTransferCount
├── ActiveSessionCount
├── ActiveIntegrationCount
├── ScheduledWorkflowCount
└── HistoricalReferenceCount
```

---

## Invitations en attente

Une invitation en état non terminal peut référencer le rôle.

Exemples :

```text
Created
Sent
Pending
```

L’archivage du rôle rendrait son acceptation impossible.

---

## Stratégies possibles

### Refus strict

L’archivage échoue tant que les invitations existent.

### Révocation automatique

La commande révoque les invitations.

### Expiration automatique

La commande expire les invitations.

---

## Décision recommandée

Utiliser le refus strict.

```text
ArchiveRole
modifies only Role
```

Erreur :

```text
PendingInvitationReferencesRole
```

L’orchestration doit d’abord invoquer :

```text
RevokeInvitation
```

ou :

```text
ExpireInvitation
```

---

## Invitations terminales

Les invitations :

```text
Accepted
Declined
Revoked
Expired
```

peuvent conserver une référence historique au rôle archivé.

---

## Transferts en attente

Un transfert peut référencer le rôle comme :

- rôle transféré ;
- rôle de remplacement ;
- rôle cible ;
- ancien rôle ;
- rôle attendu dans une approbation.

---

## Décision recommandée

L’archivage échoue si un transfert non terminal dépend du rôle.

Erreur :

```text
PendingRoleTransferReferencesRole
```

Le transfert doit être :

- exécuté ;
- annulé ;
- refusé ;
- expiré.

---

## Sessions actives

Normalement, l’absence de membership actif ou suspendu rend le nombre de sessions actives utilisant le rôle nul.

Une session obsolète peut néanmoins encore contenir des claims historiques.

---

## Politique recommandée

L’archivage peut réussir si aucune session ne possède encore une autorisation active basée sur le rôle.

Les claims historiques seuls ne bloquent pas l’archivage si :

- le moteur vérifie `RoleStatus` ;
- la version d’autorisation est invalidée ;
- aucune permission ne reste effective.

---

## Intégrations actives

Un rôle peut être référencé par :

- un compte de service ;
- une intégration ;
- une automatisation ;
- un provisioning ;
- une synchronisation ;
- un workflow planifié.

L’archivage doit échouer si une dépendance active utilise encore le rôle.

Erreur :

```text
ActiveIntegrationReferencesRole
```

---

## Workflows planifiés

Une tâche planifiée peut prévoir :

- une affectation ;
- un transfert ;
- une restauration ;
- une réactivation ;
- une synchronisation.

Ces workflows doivent être annulés ou migrés avant archivage.

Erreur :

```text
ScheduledWorkflowReferencesRole
```

---

## Permissions

`ArchiveRole` ne modifie pas :

```text
Role.PermissionAssignments
```

Les permissions restent attachées au snapshot historique du rôle.

---

## PermissionSetVersion

Comme la composition des permissions ne change pas :

```text
Role.PermissionSetVersion
```

reste inchangée.

---

## AssignmentPolicy

`RoleAssignmentPolicy` reste inchangée.

Elle est conservée comme information historique expliquant les conditions d’attribution applicables au moment de l’archivage.

---

## TransferPolicy

`RoleTransferPolicy` reste inchangée.

Elle participe également à l’audit historique.

---

## AuthorizationStateVersion

L’archivage modifie définitivement l’efficacité du rôle.

Le système doit incrémenter :

```text
Role.AuthorizationStateVersion
```

---

## Role.Version

Le cycle de vie change.

Le système incrémente :

```text
Role.Version
```

---

## ArchivedAt

Le rôle peut conserver :

```text
ArchivedAt
ArchivedBy
ArchiveReason
ArchiveSource
```

dans son état courant.

Ces données peuvent également être portées exclusivement par l’événement et une projection d’audit.

---

## Recommandation

Conserver dans l’agrégat :

```text
ArchivedAt
```

et éventuellement :

```text
ArchiveReason
```

afin de rendre l’état `Archived` autoporteur.

L’identité détaillée de l’acteur peut rester dans l’événement.

---

## Rôle Owner

Un rôle :

```text
SystemType = Owner
```

ne doit pas être archivé par `ArchiveRole`.

Erreur :

```text
OwnerRoleCannotBeArchived
```

---

## Pourquoi

Le rôle owner constitue une structure centrale du workspace.

Son remplacement ou sa migration nécessite une orchestration dédiée qui garantit :

- la continuité d’ownership ;
- le transfert des memberships ;
- la continuité administrative ;
- la disponibilité des permissions obligatoires ;
- la récupération ;
- l’unicité structurelle.

---

## Workflow owner dédié

Une évolution structurelle pourrait utiliser :

```text
ReplaceOwnerRole
```

ou :

```text
MigrateOwnershipRole
```

Ce workflow créerait ou préparerait le nouveau rôle owner avant de retirer l’ancien.

---

## Rôle DefaultMember

Un rôle :

```text
SystemType = DefaultMember
```

ne doit pas être archivé tant qu’il est configuré comme rôle par défaut.

Erreur :

```text
DefaultMemberRoleCannotBeArchived
```

---

## Remplacement du rôle par défaut

Une orchestration dédiée doit :

```text
create or select replacement Role
↓
validate assignment compatibility
↓
configure replacement as DefaultMember
↓
migrate pending Invitations when allowed
↓
archive previous Role
```

---

## Rôle ServiceAccount

Un rôle de service peut être archivé uniquement si :

- aucun compte de service actif ne l’utilise ;
- aucune intégration active ne le référence ;
- aucune tâche planifiée n’en dépend ;
- les credentials associés ont été traités ;
- les sessions techniques ont été invalidées ;
- la migration opérationnelle est terminée.

---

## Rôle Guest

Un rôle guest peut être archivé après traitement :

- des memberships invités ;
- des invitations en attente ;
- des accès temporaires ;
- des workflows externes.

---

## Rôle système sans SystemType

Un rôle `System` contrôlé par le produit ne peut être archivé localement sauf autorisation spécifique.

---

## RoleType

Règles recommandées :

```text
Custom
→ locally archivable

External
→ only by authoritative external source or permitted override

TemplateDerived
→ according to template lifecycle authority

System
→ product or recovery authority only
```

---

## Source de contrôle

Le cycle de vie peut être :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

---

## LocallyManaged

L’archivage local est permis.

---

## ExternallyManaged

L’archivage local est refusé sauf override explicite.

---

## TemplateManaged

Le modèle contrôle la fin de vie du rôle.

---

## ProductManaged

Seul le produit peut archiver le rôle.

---

## LocallyOverridable

L’archivage local peut être permis dans les limites de la politique.

---

## Source externe

Pour un rôle externe, la commande vérifie :

- `ExternalReference` ;
- `ExternalVersion` ;
- l’autorité de `ArchiveSource` ;
- l’état externe ;
- la fraîcheur de la synchronisation ;
- les références restantes ;
- le risque de recréation lors du prochain cycle.

---

## Tombstone externe

L’archivage peut conserver un marqueur :

```text
ExternalRoleTombstone
```

afin d’éviter que la prochaine synchronisation recrée immédiatement le rôle.

---

## TemplateDerived

Pour un rôle dérivé d’un modèle, la commande vérifie :

- `TemplateId` ;
- `TemplateVersion` ;
- l’état du modèle ;
- l’autorité du template ;
- les divergences locales ;
- les règles de suppression du modèle ;
- le risque de recréation.

---

## Archive Readiness

L’archivage doit être précédé d’une analyse de préparation.

Structure recommandée :

```text
RoleArchiveReadiness
├── Status
├── ActiveMembershipCount
├── SuspendedMembershipCount
├── RemovedMembershipCount
├── PendingInvitationCount
├── PendingTransferCount
├── ActiveSessionCount
├── ActiveIntegrationCount
├── ScheduledWorkflowCount
├── SystemRoleConstraintSatisfied
├── SourceAuthoritySatisfied
├── HistoricalRetentionSatisfied
├── ReplacementRoleReady
├── BlockingIssues
└── Warnings
```

---

## RoleArchiveReadinessStatus

Valeurs :

```text
Ready
ReadyWithWarnings
Blocked
RequiresMigration
RequiresExternalSynchronization
RequiresTemplateSynchronization
RequiresApproval
```

---

## Ready

Toutes les références actives ont été supprimées ou migrées.

---

## ReadyWithWarnings

L’archivage est possible, mais certaines références historiques ou informations non bloquantes subsistent.

Exemples :

- memberships supprimés ;
- invitations terminales ;
- anciens événements ;
- anciennes projections ;
- exports historiques.

---

## Blocked

Une référence active ou un invariant bloque l’archivage.

---

## RequiresMigration

Des memberships, intégrations ou workflows doivent être migrés.

---

## RequiresExternalSynchronization

La source externe doit confirmer la suppression.

---

## RequiresTemplateSynchronization

Le modèle doit être mis à jour.

---

## RequiresApproval

Une approbation spécifique est nécessaire.

---

## Impact Assessment

Structure recommandée :

```text
RoleArchiveImpact
├── PreviousLifecycleState
├── ActiveMembershipCount
├── SuspendedMembershipCount
├── RemovedMembershipCount
├── PendingInvitationCount
├── TerminalInvitationCount
├── PendingTransferCount
├── ActiveSessionCount
├── ActiveIntegrationCount
├── HistoricalReferenceCount
├── ReplacementRoleId
├── AdministrationContinuityPreserved
├── OwnershipContinuityPreserved
├── DefaultRoleContinuityPreserved
└── RetentionImpact
```

---

## Confirmation

Une confirmation explicite est recommandée car l’archivage est définitif dans les workflows ordinaires.

La confirmation doit couvrir :

```text
RoleId
WorkspaceId
CurrentLifecycleState
RoleVersion
ActiveMembershipCount
SuspendedMembershipCount
PendingInvitationCount
PendingTransferCount
ArchiveReason
ReplacementRoleId
```

---

## Approbation

Une approbation peut être exigée lorsque :

- le rôle était privilégié ou critique ;
- il s’agit d’un rôle système ;
- le rôle est externally managed ;
- le rôle est template derived ;
- il possède de nombreuses références historiques ;
- l’archivage intervient dans une migration ;
- l’acteur détient un rôle administrateur concerné ;
- la rétention ou la conformité est sensible.

---

## Réauthentification

Une réauthentification renforcée peut être requise pour :

- les rôles privilégiés ;
- les rôles critiques ;
- les rôles système ;
- les rôles de sécurité ;
- les rôles avec accès financier ;
- les rôles liés à des données sensibles.

---

## Préconditions

Avant exécution :

- le rôle existe ;
- le workspace existe ;
- le rôle appartient au workspace ;
- le rôle est `Active` ou `Disabled` ;
- le rôle n’est pas déjà archivé ;
- l’acteur ou le workflow est autorisé ;
- la source contrôle le cycle de vie ;
- le rôle n’est pas un rôle owner archivable illicitement ;
- le rôle n’est pas le rôle default member effectif ;
- aucun membership actif ne référence le rôle ;
- aucun membership suspendu ne référence le rôle ;
- aucun transfert non terminal ne référence le rôle ;
- aucune invitation non terminale ne référence le rôle ;
- aucune session active ne dépend encore du rôle ;
- aucune intégration active ne référence le rôle ;
- aucun workflow planifié ne référence le rôle ;
- les références historiques sont conservables ;
- la rétention est compatible ;
- les règles externes ou de modèle sont satisfaites ;
- les approbations requises sont valides ;
- les confirmations requises sont valides ;
- les versions attendues correspondent ;
- la demande est idempotente ;
- aucune modification concurrente incompatible n’a gagné.

---

## Traitement métier

### 1. Vérifier l’idempotence

Le système recherche une demande déjà traitée avec :

```text
RoleId + ArchiveRequestId
```

Une répétition identique retourne le résultat initial.

---

### 2. Charger le Role

Le système charge :

- `RoleId` ;
- `WorkspaceId` ;
- lifecycle state ;
- métadonnées ;
- type ;
- `SystemType` ;
- permissions ;
- policies ;
- versions ;
- source de contrôle ;
- références externes ;
- références de modèle.

---

### 3. Vérifier le lifecycle state

États acceptés :

```text
Active
Disabled
```

---

### 4. Refuser les états incompatibles

```text
Archived
→ RoleAlreadyArchived
```

### 5. Charger le Workspace

Le système vérifie :

- l’existence du workspace ;
- son état ;
- ses rôles structurels ;
- ses règles de gouvernance ;
- ses contraintes de rétention ;
- son éventuelle fermeture.

---

### 6. Charger le contexte de l’acteur

Pour un acteur humain :

- `User` ;
- `Membership` ;
- rôle ;
- permissions ;
- authentification ;
- relation avec le rôle cible.

Pour un `SystemActor` :

- identité technique ;
- autorité ;
- périmètre ;
- source ;
- référence métier.

---

### 7. Autoriser la commande

Le système vérifie :

```text
Actor may archive Roles
```

puis :

```text
Actor may archive this RoleType
```

et :

```text
Actor may archive this SystemType
```

---

### 8. Vérifier la source de contrôle

Le système détermine si `ArchiveSource` est autorisée à terminer le cycle de vie.

---

### 9. Appliquer les règles du RoleType

Le système applique les règles de :

```text
Custom
System
External
TemplateDerived
```

---

### 10. Appliquer les règles du SystemType

Le système applique les règles de :

```text
None
Owner
DefaultMember
Guest
ServiceAccount
```

---

### 11. Refuser le Owner Role

```text
SystemType = Owner
→ OwnerRoleCannotBeArchived
```

sauf orchestration structurelle explicitement distincte.

---

### 12. Refuser le DefaultMember Role effectif

```text
SystemType = DefaultMember
AND
Role is configured as current default
→ DefaultMemberRoleCannotBeArchived
```

---

### 13. Charger les Membership references

Le système calcule :

```text
ActiveMembershipCount
SuspendedMembershipCount
RemovedMembershipCount
```

---

### 14. Refuser les Memberships actifs

Si :

```text
ActiveMembershipCount > 0
```

alors :

```text
RoleStillAssignedToActiveMembership
```

---

### 15. Refuser les Memberships suspendus

Si :

```text
SuspendedMembershipCount > 0
```

alors :

```text
RoleStillAssignedToSuspendedMembership
```

---

### 16. Charger les Invitations

Le système calcule :

```text
PendingInvitationCount
TerminalInvitationCount
```

---

### 17. Refuser les Invitations non terminales

Si :

```text
PendingInvitationCount > 0
```

alors :

```text
PendingInvitationReferencesRole
```

---

### 18. Charger les Transferts

Le système recherche les transferts non terminaux référant le rôle.

---

### 19. Refuser les Transferts non terminaux

Si un transfert dépend du rôle :

```text
PendingRoleTransferReferencesRole
```

---

### 20. Charger les Sessions

Le système identifie les sessions pouvant encore dépendre du rôle.

---

### 21. Vérifier l’absence d’autorisation active

Le système s’assure qu’aucune session ne peut encore exercer des capacités via le rôle.

---

### 22. Charger les intégrations

Le système recherche :

- comptes de service ;
- automatisations ;
- synchronisations ;
- workflows ;
- jobs planifiés.

---

### 23. Refuser les intégrations actives

Si une intégration dépend encore du rôle :

```text
ActiveIntegrationReferencesRole
```

---

### 24. Refuser les workflows planifiés

Si un workflow futur cible le rôle :

```text
ScheduledWorkflowReferencesRole
```

---

### 25. Vérifier le ReplacementRoleId

Lorsque fourni, le système vérifie :

- qu’il existe ;
- qu’il appartient au même workspace ;
- qu’il est actif ;
- qu’il n’est pas identique au rôle archivé ;
- qu’il n’est pas archivé.

Cette vérification reste informative.

---

### 26. Vérifier la rétention

Le système vérifie que l’archivage ne viole pas :

- une conservation légale ;
- une politique d’audit ;
- une politique de preuve ;
- une politique de suppression.

L’archivage lui-même ne supprime aucune donnée.

---

### 27. Vérifier la confirmation

La confirmation doit correspondre à l’intention exacte.

---

### 28. Vérifier l’approbation

L’approbation doit être valide, non expirée et liée aux versions actuelles.

---

### 29. Vérifier les revues

Les revues de sécurité ou conformité sont validées lorsqu’elles sont obligatoires.

---

### 30. Vérifier ExpectedRoleVersion

```text
Role.Version = ExpectedRoleVersion
```

---

### 31. Vérifier ExpectedAuthorizationStateVersion

```text
Role.AuthorizationStateVersion
=
ExpectedAuthorizationStateVersion
```

---

### 32. Vérifier ExpectedAssignmentReferenceVersion

La projection des références doit correspondre à la version utilisée pour la décision.

---

### 33. Modifier le lifecycle state

```text
Role.Status = Archived
```

---

### 34. Enregistrer ArchivedAt

```text
Role.ArchivedAt = ArchivedAt
```

---

### 35. Conserver les Permissions

Aucune affectation de permission n’est retirée.

---

### 36. Conserver les Policies

Aucune politique n’est modifiée.

---

### 37. Incrémenter AuthorizationStateVersion

```text
Role.AuthorizationStateVersion += 1
```

---

### 38. Incrémenter Role.Version

```text
Role.Version += 1
```

---

### 39. Produire RoleArchived

L’agrégat produit :

```text
RoleArchived
```

---

### 40. Enregistrer l’idempotence

La demande et son résultat sont enregistrés.

---

### 41. Commit atomique

L’état, les versions, l’idempotence et l’événement sont persistés ensemble.

---

## Résultat attendu

Après succès :

```text
Role
├── same RoleId
├── same WorkspaceId
├── same Metadata
├── Status: Archived
├── ArchivedAt: set
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
RoleArchiveResult
├── RoleId
├── PreviousLifecycleState
├── CurrentLifecycleState
├── ActiveMembershipCount
├── SuspendedMembershipCount
├── RemovedMembershipCount
├── PendingInvitationCount
├── PendingTransferCount
├── ActiveSessionCount
├── ActiveIntegrationCount
├── HistoricalReferenceCount
├── ReplacementRoleId
├── AuthorizationStateVersion
└── RoleVersion
```

---

## Invariants concernés

### Cycle de vie valide

```text
Active or Disabled
→ Archived
```

---

### Immutabilité

```text
Archived Role
cannot be modified by ordinary Role commands
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

### Aucune affectation active

```text
Archived Role
has no Active Membership reference
```

---

### Aucune affectation suspendue

Politique recommandée :

```text
Archived Role
has no Suspended Membership reference
```

---

### Références historiques autorisées

```text
Removed Membership
may preserve archived Role reference
```

---

### Aucun nouveau workflow

Un rôle archivé ne peut plus être utilisé par :

```text
CreateMembership
RestoreMembership
ReactivateMembership
ChangeMembershipRole
TransferMembershipRole
CreateInvitation
AcceptInvitation
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

### PermissionSetVersion inchangée

La composition des permissions ne change pas.

---

### AuthorizationStateVersion modifiée

L’efficacité du rôle change définitivement.

---

### Owner Role protégé

Le rôle owner ne peut pas être archivé par cette commande.

---

### DefaultMember protégé

Le rôle par défaut effectif ne peut pas être archivé.

---

### Source d’autorité

La source contrôlant le cycle de vie doit être respectée.

---

## Événement produit

### RoleArchived

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `PreviousLifecycleState`
- `CurrentLifecycleState`
- `RoleType`
- `RoleSystemType`
- `RoleNameSnapshot`
- `RoleSensitivitySnapshot`
- `ExplicitPermissionCount`
- `EffectivePermissionCountBeforeArchival`
- `ActiveMembershipCount`
- `SuspendedMembershipCount`
- `RemovedMembershipCount`
- `PendingInvitationCount`
- `TerminalInvitationCount`
- `PendingTransferCount`
- `ActiveSessionCount`
- `ActiveIntegrationCount`
- `HistoricalReferenceCount`
- `ReplacementRoleId`
- `ArchivedBy`
- `ArchivedAt`
- `ArchiveReason`
- `ArchiveSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `MigrationPlanId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `ArchiveRequestId`
- `CorrelationId`
- `PermissionSetVersion`
- `AssignmentPolicyVersion`
- `TransferPolicyVersion`
- `AuthorizationStateVersion`
- `RoleVersion`

---

## Snapshots dans l’événement

L’événement peut contenir certains snapshots descriptifs :

```text
RoleNameSnapshot
RoleType
RoleSystemType
RoleSensitivitySnapshot
```

Ces données facilitent l’audit même si les projections évoluent.

L’identité métier reste portée par :

```text
RoleId
```

---

## Données interdites dans l’événement

L’événement ne doit pas contenir :

- de secrets ;
- de tokens ;
- de données MFA ;
- de liste nominative des anciens détenteurs ;
- de données personnelles inutiles ;
- de contenu complet de dossier ;
- de claims de session ;
- de références techniques confidentielles ;
- de justificatifs bruts.

---

## Effets et signaux secondaires possibles

Après commit, l'orchestration peut demander les effets suivants :

```text
ArchivedRoleRemovedFromAssignmentCatalog
ArchivedRoleRemovedFromTransferCatalog
ArchivedRoleRemovedFromAdministrativeListings
RoleAuthorizationPermanentlyInvalidated
RoleHistoricalProjectionUpdateRequested
ExternalRoleTombstoneRequested
TemplateRoleRetirementConfirmed
```

---

## Événements non produits

La commande ne produit pas :

```text
RoleDisabled
RolePermissionRevoked
RoleAssignmentPolicyChanged
RoleTransferPolicyChanged
MembershipRemoved
MembershipRoleChanged
InvitationRevoked
InvitationExpired
SessionRevoked
```

---

## Effet sur les Memberships

Aucun membership actuel ne doit référencer le rôle au moment du commit, à l’exception éventuelle des memberships historiques `Removed`.

La commande ne modifie aucun membership.

---

## Effet sur les Sessions

Aucune session ne doit conserver une autorisation active fondée sur le rôle.

Les claims historiques peuvent être invalidés par :

```text
AuthorizationStateVersion
```

Un handler peut également demander :

```text
RoleSessionsRevocationRequested
```

par mesure de défense en profondeur.

---

## Effet sur les Invitations

Les invitations terminales conservent leur référence historique.

Aucune invitation non terminale ne doit encore cibler le rôle.

---

## Effet sur les Transferts

Les transferts terminaux conservent leur historique.

Aucun transfert en attente ne doit référencer le rôle.

---

## Effet sur les projections

Après archivage, le rôle :

- disparaît des listes d’attribution ;
- disparaît des sélecteurs ordinaires ;
- disparaît des rôles disponibles ;
- reste visible dans les vues historiques ;
- peut apparaître dans une liste d’archives ;
- reste disponible pour l’audit.

---

## Recherche et affichage

Les requêtes ordinaires devraient exclure :

```text
Archived
```

par défaut.

Les écrans administratifs peuvent permettre :

```text
includeArchived = true
```

---

## Idempotence

Clé recommandée :

```text
RoleId + ArchiveRequestId
```

---

## Empreinte idempotente

L’empreinte inclut au minimum :

```text
RoleId
ArchivedBy
ArchivedAt
ArchiveReason
ArchiveSource
ReplacementRoleId
ConfirmationId
ApprovalId
SecurityReviewId
ComplianceReviewId
MigrationPlanId
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
```

---

## Répétition identique

Une répétition exacte retourne le résultat initial sans :

- produire un second événement ;
- modifier `ArchivedAt` ;
- incrémenter une nouvelle version ;
- répéter les notifications ;
- recréer un tombstone externe ;
- répéter les mises à jour de projection ;
- consommer une nouvelle approbation.

---

## Nouvelle demande sur un rôle archivé

Une nouvelle intention avec un autre identifiant retourne :

```text
RoleAlreadyArchived
```

---

## Conflit d’idempotence

Le même `ArchiveRequestId` utilisé avec une autre intention produit :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
ArchiveRole succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry retourne :

- le même état final ;
- le même `ArchivedAt` ;
- les mêmes versions ;
- le même événement logique ;
- le même résultat d’impact.

---

## Concurrence

### Deux archivages concurrents

Une seule commande réussit.

La seconde rencontre :

```text
RoleVersionConflict
```

ou :

```text
RoleAlreadyArchived
```

---

### Archive contre Enable

Si `EnableRole` gagne d’abord, l’archivage peut être réévalué depuis `Active`.

Si l’archivage gagne d’abord :

```text
ArchivedRoleCannotBeEnabled
```

---

### Archive contre Disable

Si `DisableRole` gagne d’abord, l’archivage peut poursuivre depuis `Disabled` après rechargement.

Si l’archivage gagne, `DisableRole` échoue.

---

### Archive contre UpdateRoleMetadata

Une modification de métadonnées concurrente doit provoquer un conflit de version.

---

### Archive contre GrantPermissionToRole

L’archivage ne doit pas ignorer un nouveau grant concurrent.

Une seule commande gagne par version.

---

### Archive contre RevokePermissionFromRole

Même principe.

---

### Archive contre ChangeMembershipRole

Un membership peut quitter ou rejoindre le rôle pendant la validation.

L’absence de références doit être protégée au moment du commit.

---

### Archive contre RestoreMembership

Un membership supprimé peut être restauré avec le rôle au même moment.

`RestoreMembership` doit revalider le lifecycle state courant du rôle.

---

### Archive contre ReactivateMembership

Un membership suspendu ne doit pas pouvoir redevenir actif avec un rôle archivé.

---

### Archive contre AcceptInvitation

L’acceptation doit vérifier que le rôle reste actif et non archivé au moment du commit.

---

### Archive contre CreateInvitation

Une invitation ne doit pas être créée avec un rôle archivé.

---

### Archive contre TransferMembershipRole

Un transfert ne doit pas cibler ou utiliser un rôle archivé.

---

### Archive contre synchronisation externe

Une synchronisation peut recréer ou modifier le rôle.

La version externe et la source d’autorité doivent protéger l’opération.

---

## Problème de référence fantôme

Une validation par projection peut indiquer :

```text
ActiveMembershipCount = 0
```

alors qu’un membership est créé simultanément.

La cohérence ne peut pas dépendre uniquement d’une projection éventuellement retardée.

---

## Coordination forte

Pour l’archivage, l’invariant :

```text
no current Membership references Role
```

doit être fortement protégé.

Stratégies possibles :

- verrou de gouvernance workspace ;
- registre transactionnel des affectations ;
- version globale des affectations ;
- contrainte persistante ;
- index synchrone ;
- transaction sérielle ;
- agrégat de gouvernance des rôles.

---

## ExpectedAssignmentReferenceVersion

Le système peut maintenir :

```text
Workspace.RoleAssignmentReferenceVersion
```

Cette version change lors de :

- création d’un membership ;
- changement de rôle ;
- restauration ;
- réactivation avec changement de rôle ;
- suppression ;
- transfert.

`ArchiveRole` vérifie qu’elle n’a pas changé depuis l’analyse.

---

## Contrainte persistante recommandée

Une contrainte ou un garde transactionnel doit empêcher :

```text
Membership.RoleId = ArchivedRoleId
```

pour tout membership non supprimé.

Cette protection complète la validation applicative.

---

## Atomicité

Le même commit doit contenir :

```text
Role.Status = Archived
+
ArchivedAt
+
AuthorizationStateVersion increment
+
Role.Version increment
+
Idempotency record
+
RoleArchived event
```

---

## États interdits

```text
Role.Status = Archived
AND
RoleArchived event missing
```

```text
RoleArchived event persisted
AND
Role remains Active or Disabled
```

```text
Archived Role referenced by Active Membership
```

```text
Archived Role referenced by Suspended Membership
```

```text
Archived Role used by pending Invitation
```

```text
Archived Role used by pending Transfer
```

```text
Archived Role used by active Integration
```

```text
Owner Role archived by ordinary command
```

```text
DefaultMember Role archived without replacement
```

```text
PermissionAssignments modified by ArchiveRole
```

```text
Policies modified by ArchiveRole
```

```text
PermissionSetVersion changed
without Permission composition change
```

```text
AuthorizationStateVersion unchanged
after archival
```

```text
ExternallyManaged Role archived
without authority
```

---

## Outbox transactionnelle

`RoleArchived` doit être enregistré dans la même transaction que le changement d’état.

Sa publication intervient après commit.

---

## Effets externes

Après succès, des handlers peuvent :

- retirer le rôle des catalogues d’attribution ;
- retirer le rôle des catalogues de transfert ;
- invalider les caches d’autorisation ;
- mettre à jour les projections historiques ;
- réindexer les vues administratives ;
- créer un tombstone externe ;
- confirmer la suppression dans un modèle ;
- mettre à jour les rapports de conformité ;
- notifier les administrateurs ;
- clôturer un plan de migration ;
- clôturer un dossier administratif ;
- mettre à jour la documentation du workspace.

---

## Notifications

Une notification est recommandée lorsque :

- le rôle était privilégié ;
- le rôle était critique ;
- le rôle remplaçait une responsabilité importante ;
- le rôle était externally managed ;
- le rôle provenait d’un modèle ;
- l’archivage termine une migration ;
- un rôle de remplacement est défini ;
- l’archivage concerne la sécurité ou la conformité.

---

## Notification aux anciens détenteurs

Les anciens détenteurs ne doivent pas nécessairement être notifiés par `ArchiveRole`, car ils ont déjà été migrés ou supprimés.

La notification appartient généralement au workflow ayant modifié leur membership.

---

## Audit

Un archivage réussi doit enregistrer :

- `RoleId`
- `WorkspaceId`
- nom du rôle
- état précédent
- état final
- `RoleType`
- `RoleSystemType`
- source de contrôle
- permissions explicites
- permissions effectives historiques
- politiques
- nombre de memberships actifs
- nombre de memberships suspendus
- nombre de memberships supprimés
- invitations non terminales
- invitations terminales
- transferts non terminaux
- sessions actives
- intégrations actives
- workflows planifiés
- références historiques
- rôle de remplacement éventuel
- `ArchivedBy`
- `ArchivedAt`
- `ArchiveReason`
- `ArchiveSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `MigrationPlanId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `ArchiveRequestId`
- `CorrelationId`
- versions précédentes
- versions finales
- résultat final.

---

## Questions auxquelles l’audit doit répondre

```text
which Role was archived
inside which Workspace
who archived it
why it was archived
which lifecycle state it had before archival
whether it had any current Membership references
whether any pending Invitation referenced it
whether any pending Transfer referenced it
whether active Sessions or Integrations depended on it
whether a replacement Role existed
which source controlled the archival
which approvals or reviews were used
which historical configuration was preserved
which authorization version became effective
```

---

## Sécurité

La commande doit garantir que :

- seul un acteur autorisé archive le rôle ;
- la source de vérité est respectée ;
- le rôle n’est pas déjà archivé ;
- aucun membership actuel ne dépend du rôle ;
- aucune invitation non terminale ne dépend du rôle ;
- aucun transfert non terminal ne dépend du rôle ;
- aucune session active ne peut encore utiliser le rôle ;
- aucune intégration active ne dépend du rôle ;
- les rôles owner et default member sont protégés ;
- le rôle devient immuable ;
- les permissions et policies restent disponibles pour l’audit ;
- le rôle ne peut plus être attribué ;
- les références historiques ne sont pas supprimées ;
- la concurrence ne crée pas une nouvelle affectation après validation ;
- l’opération est idempotente ;
- l’état et l’événement sont commités atomiquement.

---

## Confidentialité

La commande et l’événement ne doivent pas contenir :

- de secrets ;
- de tokens ;
- de mots de passe ;
- de détails MFA ;
- de données personnelles inutiles ;
- de liste nominative des anciens détenteurs ;
- de contenu complet de dossier ;
- de données d’incident sensibles ;
- de claims de session ;
- de journaux techniques complets.

---

## Erreurs métier

### RoleNotFound

Le rôle n’existe pas.

---

### WorkspaceNotFound

Le workspace n’existe pas.

---

### WorkspaceUnavailable

Le workspace n’autorise pas l’archivage.

---

### RoleAlreadyArchived

Le rôle est déjà archivé.

---

### ActorNotAuthorized

L’acteur ne peut pas archiver de rôle.

---

### RoleArchiveNotAuthorized

L’acteur ne peut pas archiver ce rôle précis.

---

### PrivilegedRoleArchiveNotAuthorized

L’acteur ne peut pas archiver un rôle privilégié.

---

### CriticalRoleArchiveNotAuthorized

L’acteur ne peut pas archiver un rôle critique.

---

### SystemRoleArchiveNotAuthorized

L’acteur ne peut pas archiver ce rôle système.

---

### OwnerRoleCannotBeArchived

Le rôle owner ne peut pas être archivé par cette commande.

---

### DefaultMemberRoleCannotBeArchived

Le rôle par défaut effectif ne peut pas être archivé.

---

### RoleStillAssigned

Le rôle possède encore une affectation actuelle.

---

### RoleStillAssignedToActiveMembership

Au moins un membership actif référence le rôle.

---

### RoleStillAssignedToSuspendedMembership

Au moins un membership suspendu référence le rôle.

---

### PendingInvitationReferencesRole

Une invitation non terminale référence le rôle.

---

### PendingRoleTransferReferencesRole

Un transfert non terminal référence le rôle.

---

### ActiveSessionReferencesRole

Une session possède encore une autorisation active fondée sur le rôle.

---

### ActiveIntegrationReferencesRole

Une intégration active dépend du rôle.

---

### ScheduledWorkflowReferencesRole

Un workflow planifié dépend du rôle.

---

### RoleLifecycleSourceNotAuthoritative

La source ne contrôle pas le cycle de vie du rôle.

---

### ExternallyManagedRoleCannotBeArchivedLocally

Le rôle externe ne peut pas être archivé localement.

---

### TemplateManagedRoleCannotBeArchivedLocally

Le modèle contrôle l’archivage.

---

### ProductManagedRoleCannotBeArchivedLocally

Le produit contrôle l’archivage.

---

### LocalLifecycleOverrideNotAllowed

Aucun override local n’est autorisé.

---

### ExternalReferenceRequired

Une référence externe est obligatoire.

---

### ExternalVersionRequired

Une version externe est obligatoire.

---

### ExternalSynchronizationRequired

La source externe doit être synchronisée avant l’archivage.

---

### TemplateReferenceRequired

Une référence de modèle est obligatoire.

---

### TemplateVersionConflict

La version du modèle ne correspond pas.

---

### TemplateSynchronizationRequired

Le modèle doit être synchronisé avant l’archivage.

---

### ReplacementRoleNotFound

Le rôle de remplacement fourni n’existe pas.

---

### ReplacementRoleWorkspaceMismatch

Le rôle de remplacement appartient à un autre workspace.

---

### ReplacementRoleNotActive

Le rôle de remplacement n’est pas actif.

---

### ReplacementRoleArchived

Le rôle de remplacement est archivé.

---

### ReplacementRoleSameAsArchivedRole

Le rôle de remplacement est identique au rôle archivé.

---

### MigrationRequired

Une migration doit être terminée avant l’archivage.

---

### MigrationPlanRequired

Un plan de migration est obligatoire.

---

### MigrationPlanIncomplete

Le plan de migration n’est pas terminé.

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

L’approbation ne couvre pas le rôle ou ses versions actuelles.

---

### SecurityReviewRequired

Une revue de sécurité est nécessaire.

---

### SecurityReviewInvalid

La revue de sécurité est invalide.

---

### ComplianceReviewRequired

Une revue de conformité est nécessaire.

---

### ComplianceReviewInvalid

La revue de conformité est invalide.

---

### HistoricalRetentionViolation

L’archivage entrerait en conflit avec une règle de conservation.

---

### HistoricalReferenceIntegrityViolation

L’archivage empêcherait la conservation correcte des références historiques.

---

### AdministrationContinuityViolation

L’archivage supprimerait une capacité administrative requise.

---

### OwnershipContinuityViolation

L’archivage violerait la continuité d’ownership.

---

### DefaultRoleContinuityViolation

L’archivage casserait la configuration du rôle par défaut.

---

### RoleVersionConflict

Le rôle a changé depuis l’analyse.

---

### AuthorizationStateVersionConflict

L’état d’autorisation a changé.

---

### AssignmentReferenceVersionConflict

Les références vers le rôle ont changé.

---

### GovernanceVersionConflict

La gouvernance du workspace a changé.

---

### RoleArchiveConflict

Une modification concurrente empêche l’archivage.

---

### IdempotencyConflict

Le même identifiant représente une autre intention.

---

## Décisions de conception

### ArchiveRole représente une fin de vie métier

L’archivage n’est pas une désactivation longue durée.

---

### L’archivage est irréversible dans le cycle ordinaire

`EnableRole` ne peut pas restaurer un rôle archivé.

---

### Une éventuelle restauration exige une commande distincte

Exemple futur :

```text
RestoreArchivedRole
```

Cette commande devrait être exceptionnelle et fortement gouvernée.

---

### Aucun Membership actuel ne peut référencer le rôle

Les memberships actifs et suspendus doivent être traités avant archivage.

---

### Les Memberships supprimés peuvent conserver la référence

Cette relation reste historique.

---

### Les Invitations non terminales bloquent l’archivage

Elles doivent être révoquées ou expirées explicitement.

---

### Les Transferts non terminaux bloquent l’archivage

Ils doivent être résolus explicitement.

---

### Les intégrations et workflows actifs bloquent l’archivage

Le rôle ne doit plus avoir de dépendance opérationnelle.

---

### ArchiveRole ne migre rien

La migration appartient à une orchestration distincte.

---

### Les Permissions sont conservées

Elles représentent la configuration historique du rôle.

---

### Les Policies sont conservées

Elles expliquent la gouvernance historique du rôle.

---

### PermissionSetVersion reste inchangée

La composition des permissions ne change pas.

---

### AuthorizationStateVersion est incrémentée

Le rôle cesse définitivement d’être une source d’autorisation.

---

### Le rôle devient immuable

Les commandes ordinaires doivent refuser toute modification.

---

### Owner et DefaultMember sont protégés

Leur remplacement nécessite une opération structurelle dédiée.

---

### Un événement métier dédié est produit

```text
RoleArchived
```

---

## Cas limites

### Rôle actif sans détenteur

L’archivage direct est autorisé si aucune autre référence active n’existe.

---

### Rôle désactivé sans détenteur

L’archivage est autorisé.

---

### Rôle sans permission

L’archivage est autorisé.

---

### Rôle avec uniquement des memberships supprimés

L’archivage est autorisé.

Les références historiques restent conservées.

---

### Rôle avec un membership suspendu

L’archivage est refusé.

Le membership doit être réaffecté ou supprimé.

---

### Rôle avec une invitation envoyée mais expirée dans le temps

Si l’état métier n’a pas encore été mis à jour vers `Expired`, l’archivage est refusé.

Le système ne doit pas déduire silencieusement l’expiration sans exécuter `ExpireInvitation`.

---

### Rôle avec transfert expiré dans le temps mais encore Pending

Même principe.

L’état métier doit être régularisé avant l’archivage.

---

### Rôle externe supprimé à la source

L’archivage peut être exécuté par `ExternalSynchronization` avec la version appropriée.

---

### Rôle externe recréé ensuite

Le système doit décider si un nouvel objet `Role` est créé avec un nouveau `RoleId` ou si un tombstone empêche la recréation.

---

### Rôle dérivé d’un modèle supprimé

Le rôle peut être archivé après résolution de ses références actives.

---

### Rôle système obsolète

L’archivage nécessite une autorité produit ou une migration.

---

### Rôle Owner sans détenteur à cause d’un état incohérent

La commande doit quand même refuser l’archivage.

La récupération de l’ownership appartient à un workflow distinct.

---

### Rôle DefaultMember inutilisé

Il reste protégé tant qu’il est configuré comme rôle par défaut.

---

### Rôle de service sans intégration visible

Une vérification des tâches planifiées et identités techniques reste nécessaire.

---

### ReplacementRoleId absent

L’archivage peut réussir si aucun remplacement n’est nécessaire.

---

### ReplacementRoleId fourni mais inutilisé

L’archivage peut réussir si toutes les migrations ont déjà été effectuées.

Le champ reste informatif.

---

### Claims de session historiques

Ils ne bloquent pas l’archivage si aucune autorisation actuelle ne peut encore être exercée.

---

### Très grand nombre de références historiques

Les références historiques ne doivent pas être chargées individuellement dans la commande.

Des compteurs ou projections dédiés sont préférables.

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
Role.Status is Active or Disabled
Role is not already Archived
Actor or SystemActor is authorized
ArchiveSource controls Role lifecycle
RoleType-specific rules are satisfied
SystemType-specific rules are satisfied
Owner Role is protected
DefaultMember Role is protected
Active Membership count is zero
Suspended Membership count is zero
Pending Invitation count is zero
Pending Role Transfer count is zero
Active Session dependency count is zero
Active Integration dependency count is zero
Scheduled Workflow dependency count is zero
Historical references can be preserved
Retention requirements are satisfied
External synchronization is valid when applicable
Template synchronization is valid when applicable
ReplacementRoleId is valid when provided
Required migration is complete
Required confirmation is valid
Required approval is valid
Required security review is valid
Required compliance review is valid
Expected Role version matches
Expected AuthorizationStateVersion matches
Expected AssignmentReferenceVersion matches
Governance version is current when required
Idempotency is verified
PermissionAssignments remain unchanged
PermissionSetVersion remains unchanged
AssignmentPolicy remains unchanged
TransferPolicy remains unchanged
AuthorizationStateVersion can be incremented
Role can become immutable
RoleArchived can be persisted atomically
```

---

## Synthèse

`ArchiveRole` retire définitivement un rôle de l’usage métier courant tout en conservant son identité et sa configuration historique.

Elle garantit que :

- le rôle existe ;
- son état permet l’archivage ;
- l’acteur ou le workflow est autorisé ;
- la source de vérité est respectée ;
- aucun membership actif ne le référence ;
- aucun membership suspendu ne le référence ;
- aucune invitation non terminale ne le cible ;
- aucun transfert non terminal ne le référence ;
- aucune session active ne dépend encore de lui ;
- aucune intégration active ne l’utilise ;
- aucun workflow planifié ne le cible ;
- les rôles owner et default member sont protégés ;
- les références historiques restent conservées ;
- les permissions ne sont pas supprimées ;
- les politiques ne sont pas modifiées ;
- aucun autre agrégat n’est modifié implicitement ;
- le rôle devient immuable ;
- il disparaît des workflows d’attribution et de transfert ;
- `PermissionSetVersion` reste inchangée ;
- `AuthorizationStateVersion` est incrémentée ;
- l’opération est idempotente ;
- la concurrence ne peut pas créer une nouvelle référence active pendant l’archivage ;
- le changement d’état et l’événement sont commités atomiquement.

Le résultat final est :

```text
Role
├── same identity
├── same Workspace
├── Status: Archived
├── ArchivedAt: set
├── same metadata
├── same PermissionAssignments
├── same AssignmentPolicy
├── same TransferPolicy
├── same PermissionSetVersion
├── new AuthorizationStateVersion
└── immutable for ordinary business commands
```
