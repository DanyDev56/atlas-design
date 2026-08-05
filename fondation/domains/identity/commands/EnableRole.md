---
id: IDN-CMD-ENABLE-ROLE
title: EnableRole
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-31

aggregate: Role

invariants:
  - IDN-INV-005
  - IDN-INV-006
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
  - DisableRole.md
  - ArchiveRole.md
  - GrantPermissionToRole.md
  - RevokePermissionFromRole.md
  - ../workflows.md
---

# EnableRole

## Objectif

La commande `EnableRole` réactive un `Role` précédemment désactivé.

Transition principale :

```text
Disabled
↓
Active
```

L’activation rend de nouveau le rôle utilisable dans les décisions d’autorisation et dans les workflows qui autorisent son attribution.

La commande ne doit pas :

- créer un rôle ;
- restaurer un rôle archivé ;
- modifier les métadonnées du rôle ;
- ajouter ou retirer une permission ;
- modifier `RoleAssignmentPolicy` ;
- modifier `RoleTransferPolicy` ;
- attribuer le rôle à un membership ;
- réactiver un membership ;
- restaurer un membership ;
- révoquer ou recréer une session ;
- corriger automatiquement les non-conformités du rôle ;
- réactiver implicitement une source externe ;
- modifier le `Workspace`.

---

## Intention métier

La commande répond à l’intention suivante :

```text
make an existing disabled Role
usable again
inside its Workspace
```

L’activation signifie que le rôle peut de nouveau participer à la chaîne d’autorisation :

```text
Session
→ User
→ Membership
→ Role
→ Permission
```

à condition que les autres éléments soient eux-mêmes valides.

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
- les permissions du rôle ;
- le catalogue global des permissions ;
- `RoleAssignmentPolicy` ;
- `RoleTransferPolicy` ;
- les règles de gouvernance ;
- les règles de sécurité ;
- les sources externes ;
- les modèles ;
- les limitations produit ;
- les éventuelles non-conformités existantes.

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

Un rôle archivé ne peut pas être activé avec `EnableRole`.

La réouverture éventuelle d’un rôle archivé nécessiterait une décision distincte, telle que :

```text
RestoreArchivedRole
```

si ce besoin est retenu.

---

## Signification de Active

Un rôle `Active` peut être :

- utilisé pour calculer les permissions effectives ;
- attribué par les workflows autorisés ;
- utilisé comme rôle cible d’un changement ;
- utilisé comme rôle de remplacement ;
- transféré si sa politique l’autorise ;
- associé à de nouveaux memberships ;
- utilisé par les memberships actifs qui le détiennent déjà.

L’état `Active` ne garantit pas à lui seul :

- que le rôle possède des permissions ;
- qu’il possède des détenteurs ;
- que toute identité peut le recevoir ;
- que tout acteur peut l’attribuer ;
- que les détenteurs sont conformes ;
- que toutes les fonctionnalités sont disponibles.

---

## Signification de Disabled

Un rôle `Disabled` existe toujours, mais n’est pas utilisable comme rôle actif d’autorisation.

Il conserve :

- son identité ;
- son workspace ;
- ses métadonnées ;
- ses permissions ;
- ses politiques ;
- son historique ;
- ses affectations historiques ;
- ses références externes ou de modèle ;
- ses versions.

---

## Effet de la désactivation sur les Memberships

Le modèle recommandé considère qu’un membership actif détenant un rôle désactivé ne reçoit plus les permissions de ce rôle.

```text
Membership.Status = Active
AND
Role.Status = Disabled
↓
Role Permissions are not effective
```

Le membership lui-même reste actif.

Il peut néanmoins devenir incapable d’exercer toute action métier.

---

## Effet de l’activation sur les Memberships

Lorsque le rôle redevient actif, ses permissions peuvent redevenir effectives pour tous les memberships actifs qui le détiennent encore.

```text
Membership.Status = Active
AND
Membership.RoleId = RoleId
AND
Role.Status changes to Active
↓
Role Permissions may become effective again
```

Cette réactivation peut concerner simultanément plusieurs utilisateurs.

---

## Pas de réattribution

`EnableRole` ne réattribue pas le rôle.

Les memberships possédant déjà `RoleId` conservent cette relation pendant la désactivation.

L’activation rétablit l’efficacité du rôle, pas son affectation.

---

## Pas de réactivation des Memberships

Un membership suspendu reste suspendu.

```text
Membership.Status = Suspended
AND
Role becomes Active
↓
Membership remains Suspended
```

Un membership supprimé reste supprimé.

---

## Pas de réactivation automatique des Sessions

Une session révoquée ou expirée reste révoquée ou expirée.

L’activation du rôle ne recrée aucune session.

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un administrateur de sécurité ;
- un administrateur de gouvernance ;
- un `SystemActor` ;
- un workflow de récupération ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un processus de migration ;
- un processus produit ;
- un administrateur de plateforme dans un périmètre autorisé.

L’acteur doit être identifiable et auditable.

---

## Permission requise

Permission canonique :

```text
workspace.roles.enable
```

La sensibilité du rôle et son origine sont évaluées par des politiques
contextuelles et peuvent exiger une approbation ou une réauthentification. Elles
ne créent pas de permissions alternatives en 1.0.

---

## Sources d’activation

Valeurs recommandées pour `RoleEnableSource` :

```text
ManualAdministration
SecurityRecovery
ComplianceRecovery
SystemProvisioning
ExternalSynchronization
TemplateSynchronization
AdministrativeRecovery
Migration
ProductConfiguration
WorkspaceRecovery
```

---

## ManualAdministration

Un acteur humain autorisé réactive le rôle.

---

## SecurityRecovery

Le rôle est réactivé après résolution d’un incident de sécurité.

---

## ComplianceRecovery

Le rôle est réactivé après résolution d’un blocage de conformité.

---

## SystemProvisioning

Un processus interne remet le rôle en service.

---

## ExternalSynchronization

Une source externe autoritaire indique que le rôle doit redevenir actif.

---

## TemplateSynchronization

Un modèle réactive le rôle dérivé.

---

## AdministrativeRecovery

Le rôle est activé pour restaurer l’administrabilité du workspace.

---

## Migration

La transition intervient dans le cadre d’une migration contrôlée.

---

## ProductConfiguration

Le produit réactive un rôle système.

---

## WorkspaceRecovery

L’activation participe à un workflow de récupération du workspace.

---

## Motifs d’activation

Valeurs recommandées pour `RoleEnableReason` :

```text
TemporaryRestrictionResolved
SecurityReviewCompleted
ComplianceReviewCompleted
OperationalRecovery
OrganizationalNeedRestored
FeatureReenabled
ExternalSourceRestored
TemplateRestored
AdministrativeCorrection
WorkspaceRecovery
Migration
Other
```

---

## Données d’entrée

### Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `RoleId` | `RoleId` | Rôle à activer. |
| `EnabledBy` | `UserId` ou `SystemActor` | Acteur ou workflow responsable. |
| `EnabledAt` | Instant | Date métier de l’activation. |
| `EnableReason` | `RoleEnableReason` | Motif de l’activation. |
| `EnableSource` | `RoleEnableSource` | Origine de l’activation. |
| `EnableRequestId` | Identifiant | Identifiant idempotent. |

### Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `ExpectedRoleVersion` | Version | Version attendue du rôle. |
| `ExpectedPermissionSetVersion` | Version | Version attendue des permissions. |
| `ExpectedAssignmentPolicyVersion` | Version | Version attendue de la politique d’attribution. |
| `ExpectedTransferPolicyVersion` | Version | Version attendue de la politique de transfert. |
| `ConfirmationId` | Identifiant | Confirmation d’une activation sensible. |
| `ApprovalId` | Identifiant | Approbation éventuelle. |
| `SecurityReviewId` | Identifiant | Revue de sécurité. |
| `ComplianceReviewId` | Identifiant | Revue de conformité. |
| `ReadinessAssessmentId` | Identifiant | Évaluation préalable. |
| `RemediationPlanId` | Identifiant | Plan de remédiation. |
| `CaseReference` | Identifiant | Dossier administratif ou de sécurité. |
| `ExternalReference` | Identifiant | Référence externe. |
| `ExternalVersion` | Version | Version de la source externe. |
| `TemplateId` | Identifiant | Modèle concerné. |
| `TemplateVersion` | Version | Version du modèle. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques non métier. |

---

## Précondition d’état

La transition valide est :

```text
Role.Status = Disabled
↓
Role.Status = Active
```

---

## Rôle déjà actif

Deux comportements existent.

### Succès idempotent uniquement pour la même demande

Lorsque le même `EnableRequestId` est rejoué, retourner le résultat initial.

### Nouvelle demande sur un rôle déjà actif

Retourner :

```text
RoleAlreadyActive
```

Cette règle distingue le retry technique d’une intention métier redondante.

---

## Rôle archivé

Un rôle archivé ne peut pas être activé.

```text
Archived
↓
Active
```

n’est pas une transition autorisée par `EnableRole`.

Erreur :

```text
ArchivedRoleCannotBeEnabled
```

---

Identity 1.0 ne définit aucun état `Removed` pour le `Role`.

---

## Readiness Assessment

L’activation doit être précédée d’une analyse de préparation.

Structure recommandée :

```text
RoleEnableReadiness
├── Status
├── PermissionSetValid
├── AssignmentPolicyValid
├── TransferPolicyValid
├── CurrentHoldersCompliant
├── SystemRoleRequirementsSatisfied
├── ExternalAuthorityValid
├── TemplateAuthorityValid
├── LicenseRequirementsSatisfied
├── AdministrationContinuityValid
├── SecurityReviewRequired
├── ComplianceReviewRequired
├── RemediationRequired
└── BlockingIssues
```

---

## RoleEnableReadinessStatus

Valeurs possibles :

```text
Ready
ReadyWithWarnings
Blocked
RequiresSecurityReview
RequiresComplianceReview
RequiresRemediation
RequiresSynchronization
```

---

## Ready

Toutes les préconditions sont satisfaites.

---

## ReadyWithWarnings

L’activation est possible, mais des risques non bloquants existent.

Exemples :

- aucun détenteur actuel ;
- aucune permission ;
- nombreuses invitations référençant le rôle ;
- rôle surprotégé par rapport à ses permissions ;
- intégration non critique temporairement indisponible.

---

## Blocked

L’activation violerait un invariant ou une règle obligatoire.

---

## RequiresSecurityReview

Une revue de sécurité valide est nécessaire.

---

## RequiresComplianceReview

Une revue de conformité est nécessaire.

---

## RequiresRemediation

Une ou plusieurs non-conformités doivent être corrigées avant activation, ou être couvertes par un plan autorisé.

---

## RequiresSynchronization

Le rôle dépend d’une source externe ou d’un modèle dont l’état n’est pas suffisamment récent ou cohérent.

---

## Validation du Permission Set

Avant activation, l’ensemble des permissions doit être valide.

Le système vérifie notamment :

- chaque permission explicitement affectée existe ou est traitée par une politique de migration ;
- aucune permission interdite n’est présente ;
- les dépendances sont satisfaites ;
- aucune incompatibilité bloquante n’existe ;
- les permissions système obligatoires sont présentes ;
- les permissions désactivées ou supprimées sont traitées ;
- le scope de chaque permission est compatible ;
- la classification du rôle est calculable.

---

## Permission dépréciée

Un rôle peut-il être activé avec une permission dépréciée ?

Politique recommandée :

```text
Deprecated Permission
→ warning or blocking according to Permission policy
```

Une permission dépréciée peut être tolérée temporairement pendant une migration.

---

## Permission désactivée

Une permission désactivée ne doit pas devenir effective.

Deux approches sont possibles :

### Bloquer l’activation du rôle

Approche stricte et prévisible.

### Activer le rôle en ignorant la permission désactivée

Approche plus permissive, mais potentiellement trompeuse.

---

## Recommandation

Refuser l’activation lorsque l’affectation contient une permission désactivée qui n’est pas explicitement tolérée par une politique de migration.

---

## Dépendances

Toutes les permissions restantes doivent conserver leurs dépendances.

```text
for every explicit Permission
RequiredPermissions
subset of
EffectivePermissionSet
```

---

## Incompatibilités

Aucune combinaison interdite ne doit devenir effective lors de l’activation.

---

## Validation de RoleAssignmentPolicy

La politique d’attribution doit être :

- structurellement valide ;
- compatible avec le type de rôle ;
- compatible avec `SystemType` ;
- compatible avec la sensibilité actuelle ;
- administrable ;
- cohérente avec les types de cible ;
- compatible avec les sources actives ;
- versionnée.

---

## Validation de RoleTransferPolicy

La politique de transfert doit être :

- structurellement valide ;
- compatible avec la politique d’attribution ;
- compatible avec la sensibilité actuelle ;
- compatible avec le rôle de remplacement ;
- protectrice des rôles critiques ;
- administrable ;
- versionnée.

---

## Compatibilité des politiques

Lorsque :

```text
TransferPolicy.Transferability = Transferable
```

alors :

```text
AssignmentPolicy.AllowedSources
contains RoleTransfer
```

Les types de cible doivent rester compatibles.

---

## Sensibilité du Role

Le système recalcule :

```text
RoleCapabilityProfile
```

et détermine notamment :

```text
RoleSensitivity
```

Valeurs possibles :

```text
Standard
Elevated
Privileged
Critical
```

L’activation d’un rôle privilégié ou critique peut exiger des contrôles renforcés.

---

## Exigences minimales selon la sensibilité

Exemple conceptuel :

### Standard

- autorisation standard ;
- aucune approbation supplémentaire nécessaire.

### Elevated

- authentification récente de l’acteur ;
- notification éventuelle.

### Privileged

- MFA ;
- approbation ;
- revue des détenteurs ;
- politique d’attribution renforcée.

### Critical

- authentification résistante au phishing ;
- double approbation ;
- séparation des devoirs ;
- revue de sécurité ;
- revue des sessions ;
- continuité administrative explicite.

La configuration exacte appartient à la politique produit.

---

## Current Holders

Le système doit analyser les memberships qui détiennent actuellement le rôle.

Structure possible :

```text
RoleHolderReadiness
├── MembershipId
├── MembershipStatus
├── IdentityType
├── AssignmentPolicyCompliance
├── ContinuousRequirementsCompliance
├── AuthenticationRequirementCompliance
├── ExternalRequirementsCompliance
├── RequiresReview
└── BlockingIssues
```

---

## Membership actif conforme

Le rôle peut redevenir effectif pour ce membership après activation.

---

## Membership actif non conforme

Exemples :

- identité technique alors qu’un humain est exigé ;
- MFA absent ;
- source externe non vérifiée ;
- formation obligatoire expirée ;
- contrainte continue non satisfaite.

Le comportement doit être explicite.

---

## Modes d’application

Valeurs possibles pour `RoleEnableEnforcementMode` :

```text
RequireAllCurrentHoldersCompliant
AllowWithRemediation
EnableWithoutCurrentHolders
```

---

## RequireAllCurrentHoldersCompliant

L’activation échoue si un détenteur actif est non conforme.

Cette option est recommandée pour les rôles privilégiés.

---

## AllowWithRemediation

L’activation réussit uniquement avec un plan de remédiation valide.

Les détenteurs non conformes peuvent rester temporairement incapables d’utiliser le rôle selon la stratégie d’autorisation.

---

## EnableWithoutCurrentHolders

Applicable lorsque le rôle ne possède aucun détenteur actif.

La conformité des futures affectations sera contrôlée par `RoleAssignmentPolicy`.

---

## Recommandation initiale

Pour simplifier :

```text
Standard Role
→ activation allowed if policies and permissions are valid

Privileged or Critical Role
→ all current active holders must be compliant
```

---

## Membership suspendu

Un membership suspendu ne bloque normalement pas l’activation du rôle.

Il ne reçoit pas les permissions tant qu’il reste suspendu.

Sa conformité devra être réévaluée lors de :

```text
ReactivateMembership
```

---

## Membership supprimé

Un membership supprimé ne participe pas à l’autorisation.

Il ne bloque pas l’activation.

---

## Invitations en attente

Une invitation peut référencer le rôle.

Après activation, elle peut potentiellement être acceptée.

L’acceptation doit néanmoins recharger :

```text
Current Role
Current AssignmentPolicy
Current AssignmentPolicyVersion
```

L’activation ne valide pas automatiquement les invitations.

---

## Transferts en attente

Un transfert en attente peut référencer le rôle.

L’activation ne rend pas automatiquement le transfert valide.

Le transfert doit être réévalué au moment de son exécution.

---

## Rôle sans permission

Un rôle actif sans permission est valide.

```text
PermissionAssignments = empty
```

Il ne confère aucune capacité.

L’activation peut être autorisée avec un avertissement :

```text
RoleHasNoPermissions
```

---

## Rôle sans détenteur

L’activation est autorisée.

Le rôle devient disponible pour de futures affectations.

---

## Rôle non attribuable

Un rôle peut être actif tout en étant impossible à attribuer si sa politique est trop restrictive.

Le système doit éviter une politique structurellement impossible.

Toutefois, un rôle sans source d’attribution ordinaire peut rester administrable via une récupération explicite selon la décision de politique.

---

## Rôle Owner

Pour :

```text
SystemType = Owner
```

l’activation est particulièrement sensible.

Le système doit vérifier :

- les permissions owner obligatoires ;
- `MinimumActiveAssignments >= 1` lorsque le rôle est en usage ;
- les identités humaines ;
- les exigences d’authentification ;
- la continuité owner ;
- la capacité de transfert ;
- la récupération administrative ;
- la conformité des détenteurs ;
- l’absence de gap d’ownership.

---

## Owner Role désactivé

La désactivation du rôle owner devrait normalement être interdite tant qu’il est nécessaire au workspace.

Si un rôle owner désactivé existe à la suite d’une récupération ou migration, son activation doit être fortement contrôlée.

---

## Plusieurs Owner Roles

Le modèle recommande :

```text
at most one Role
with SystemType = Owner
per Workspace
```

L’activation doit vérifier qu’aucun conflit structurel n’existe.

---

## Rôle DefaultMember

Pour :

```text
SystemType = DefaultMember
```

le système vérifie :

- l’unicité structurelle ;
- les permissions minimales ;
- les sources d’attribution nécessaires ;
- les invitations en attente ;
- les workflows de création de membership.

---

## Rôle ServiceAccount

Pour :

```text
SystemType = ServiceAccount
```

le système vérifie :

- les types d’identité autorisés ;
- l’absence d’exigence humaine contradictoire ;
- la compatibilité des permissions ;
- les intégrations dépendantes ;
- la source d’autorité.

---

## Rôle External

Pour :

```text
RoleType = External
```

l’activation locale dépend de la source de vérité.

Le système vérifie :

- `ExternalReference` ;
- `ExternalVersion` ;
- statut de la source ;
- fraîcheur de la synchronisation ;
- autorité de `EnableSource` ;
- éventuels overrides.

---

## Rôle TemplateDerived

Pour :

```text
RoleType = TemplateDerived
```

le système vérifie :

- `TemplateId` ;
- `TemplateVersion` ;
- statut du modèle ;
- politique d’override ;
- compatibilité des permissions ;
- compatibilité des politiques ;
- divergence locale.

---

## Rôle System

Pour :

```text
RoleType = System
```

l’activation peut être réservée :

- au produit ;
- au bootstrap ;
- à une récupération ;
- à une migration ;
- à une autorité renforcée.

---

## Source de contrôle du Status

Le statut du rôle peut être :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

---

## LocallyManaged

L’activation locale est autorisée sous réserve des permissions.

---

## ExternallyManaged

Seule la source externe peut normalement activer le rôle.

---

## TemplateManaged

L’état est contrôlé par le modèle.

Une activation locale peut être refusée ou enregistrée comme override.

---

## ProductManaged

Seul le produit ou un workflow privilégié peut activer le rôle.

---

## LocallyOverridable

L’état de base peut être surchargé localement dans des limites explicites.

---

## Sessions et autorisation

L’activation peut accorder de nouveau des permissions à plusieurs sessions existantes.

Cette propagation doit être contrôlée.

---

## PermissionSetVersion

Les permissions explicites ne changent pas pendant `EnableRole`.

La commande ne doit donc pas nécessairement incrémenter :

```text
PermissionSetVersion
```

---

## AuthorizationStateVersion

En revanche, l’état d’autorisation du rôle change.

Il est recommandé d’introduire ou d’utiliser :

```text
Role.AuthorizationStateVersion
```

ou une version globale :

```text
Role.Version
```

Le moteur d’autorisation doit intégrer le statut du rôle et sa version courante.

---

## Recommandation

Utiliser :

```text
Role.Version += 1
Role.AuthorizationStateVersion += 1
```

sans modifier :

```text
PermissionSetVersion
```

La distinction est utile :

```text
PermissionSetVersion
→ Permission composition changed

AuthorizationStateVersion
→ Permission effectiveness changed
```

---

## Alternative simplifiée

Si une seule version spécialisée est souhaitée :

```text
Role.AuthorizationVersion += 1
```

à chaque changement de :

- statut ;
- permission set ;
- règle affectant l’efficacité du rôle.

---

## Claims statiques

Les sessions contenant des claims statiques peuvent ne pas voir immédiatement les permissions redevenues disponibles.

Un mécanisme de refresh ou de réémission peut être nécessaire.

---

## Sécurité positive

L’activation ajoute potentiellement des capacités.

Elle doit donc être traitée comme une opération d’élévation collective.

Elle est souvent plus sensible que `DisableRole`.

---

## Réauthentification

Une activation sensible peut exiger :

```text
RecentAuthentication
Mfa
PhishingResistantMfa
```

selon la sensibilité finale du rôle.

---

## Approbation

Une approbation peut être obligatoire lorsque :

- le rôle est privilégié ;
- le rôle est critique ;
- le rôle est owner ;
- plusieurs memberships actifs sont concernés ;
- la désactivation était liée à un incident ;
- la source externe reste incertaine ;
- un plan de remédiation est utilisé.

---

## Portée de l’approbation

L’approbation doit couvrir :

```text
RoleId
WorkspaceId
RoleVersion
PermissionSetVersion
AssignmentPolicyVersion
TransferPolicyVersion
EnableReason
CurrentHolderCount
RoleSensitivity
```

---

## Revue de sécurité

Une revue de sécurité peut vérifier :

- la cause initiale de la désactivation ;
- la résolution de l’incident ;
- les permissions actuelles ;
- les détenteurs ;
- les sessions ;
- les politiques ;
- les dépendances externes ;
- les logs récents.

---

## Revue de conformité

Une revue de conformité peut être exigée lorsque le rôle donne accès :

- à des données personnelles ;
- à des données financières ;
- à des fonctions réglementées ;
- à des fonctions de validation ;
- à des fonctions de séparation des devoirs.

---

## RemediationPlan

Une activation avec non-conformités peut exiger :

```text
RoleEnableRemediationPlan
├── PlanId
├── BlockingIssues
├── RequiredActions
├── ResponsibleActors
├── Deadline
├── TemporaryRestrictions
├── MonitoringRequirements
└── Approval
```

---

## Recommandation

Ne pas autoriser de remédiation différée pour les violations d’invariants durs.

Exemples non remédiables après activation :

- permission obligatoire absente ;
- politique owner invalide ;
- incompatibilité de permissions ;
- source externe non autoritaire ;
- rôle archivé ;
- rôle système dupliqué.

---

## Préconditions

Avant l’exécution :

- le rôle existe ;
- le workspace existe ;
- le rôle appartient au workspace ;
- le rôle est `Disabled` ;
- le rôle n’est pas archivé ;
- l’acteur ou le workflow est autorisé ;
- la source contrôle le statut ;
- l’ensemble des permissions est valide ;
- les dépendances de permissions sont satisfaites ;
- aucune incompatibilité bloquante n’existe ;
- les permissions obligatoires sont présentes ;
- `RoleAssignmentPolicy` est valide ;
- `RoleTransferPolicy` est valide ;
- les politiques sont compatibles ;
- les exigences liées au type de rôle sont satisfaites ;
- les exigences liées au `SystemType` sont satisfaites ;
- les détenteurs actifs sont conformes selon le mode retenu ;
- la continuité administrative est préservée ;
- la source externe ou le modèle est valide ;
- les limitations produit et licence sont respectées ;
- les revues requises sont valides ;
- les approbations requises sont valides ;
- le plan de remédiation est valide lorsqu’il est permis ;
- les versions attendues correspondent ;
- la demande est idempotente ;
- aucune modification concurrente incompatible n’a gagné.

---

## Traitement métier

### 1. Vérifier l’idempotence

Le système recherche une demande déjà traitée avec :

```text
RoleId + EnableRequestId
```

Une répétition identique retourne le résultat initial.

---

### 2. Charger le Role

Le système charge :

- identité ;
- workspace ;
- statut ;
- métadonnées ;
- type ;
- `SystemType` ;
- permissions ;
- politiques ;
- versions ;
- sources de contrôle ;
- références externes ou de modèle.

---

### 3. Vérifier le statut

Condition attendue :

```text
Role.Status = Disabled
```

---

### 4. Refuser les états incompatibles

```text
Active
→ RoleAlreadyActive
```

```text
Archived
→ ArchivedRoleCannotBeEnabled
```

### 5. Charger le Workspace

Le système vérifie :

- son existence ;
- son état ;
- sa capacité à utiliser le rôle ;
- ses règles produit ;
- ses règles de licence.

---

### 6. Charger le contexte de l’acteur

Pour un acteur humain :

- `User` ;
- `Membership` ;
- permissions effectives ;
- niveau d’authentification ;
- relation avec le rôle cible ;
- bénéfice potentiel de l’activation.

Pour un `SystemActor` :

- identité technique ;
- autorité ;
- périmètre ;
- source ;
- références.

---

### 7. Autoriser la commande

Le système vérifie :

```text
Actor may enable Roles
```

puis :

```text
Actor may enable this RoleType
```

et :

```text
Actor may enable this SystemType
```

---

### 8. Vérifier la source de contrôle

Le système vérifie que `EnableSource` peut modifier le statut courant.

---

### 9. Calculer le Permission Set effectif

Le système calcule :

```text
ExplicitPermissionSet
```

puis :

```text
EffectivePermissionSet
```

---

### 10. Valider les Permissions

Le système vérifie :

- existence ;
- état ;
- scope ;
- dépendances ;
- incompatibilités ;
- règles produit ;
- permissions obligatoires.

---

### 11. Recalculer RoleCapabilityProfile

Le système calcule la sensibilité et les capacités finales.

---

### 12. Valider AssignmentPolicy

La politique est validée contre :

- le rôle ;
- la sensibilité ;
- le `SystemType` ;
- les permissions finales ;
- les règles produit.

---

### 13. Valider TransferPolicy

Même principe pour la politique de transfert.

---

### 14. Vérifier la compatibilité des politiques

Le système vérifie la composition des deux politiques.

---

### 15. Appliquer les règles du RoleType

Le système applique les règles propres à :

```text
System
Custom
External
TemplateDerived
```

---

### 16. Appliquer les règles du SystemType

Le système applique les règles propres à :

```text
Owner
DefaultMember
Guest
ServiceAccount
None
```

---

### 17. Charger les détenteurs actuels

Le système charge ou projette les memberships qui référencent le rôle.

---

### 18. Évaluer leur conformité

Le système vérifie :

- statut ;
- type d’identité ;
- exigences continues ;
- authentification ;
- source externe ;
- conditions particulières.

---

### 19. Appliquer RoleEnableEnforcementMode

Le système applique la stratégie choisie.

---

### 20. Vérifier la continuité administrative

Le système s’assure que l’activation ne crée pas de conflit structurel et qu’elle respecte la gouvernance.

---

### 21. Vérifier les invitations et transferts en attente

Cette étape est principalement informative.

L’activation ne les valide pas automatiquement.

---

### 22. Vérifier les approbations

Le système valide :

- l’identité de l’approbateur ;
- la séparation des devoirs ;
- la portée ;
- la version ;
- la durée de validité.

---

### 23. Vérifier les revues

Le système valide les revues de sécurité ou de conformité lorsque nécessaires.

---

### 24. Vérifier le plan de remédiation

Lorsque permis, le système valide :

- les problèmes couverts ;
- les actions ;
- les responsables ;
- les délais ;
- les restrictions temporaires ;
- l’approbation.

---

### 25. Vérifier les versions attendues

```text
Role.Version = ExpectedRoleVersion
```

```text
PermissionSetVersion = ExpectedPermissionSetVersion
```

```text
AssignmentPolicyVersion = ExpectedAssignmentPolicyVersion
```

```text
TransferPolicyVersion = ExpectedTransferPolicyVersion
```

---

### 26. Modifier le statut

```text
Role.Status = Active
```

---

### 27. Enregistrer EnabledAt et EnabledBy

Le rôle peut conserver :

```text
LastEnabledAt
LastEnabledBy
LastEnableReason
LastEnableSource
```

Ces données peuvent également rester exclusivement dans l’événement et l’audit.

---

### 28. Incrémenter AuthorizationStateVersion

```text
Role.AuthorizationStateVersion += 1
```

---

### 29. Incrémenter Role.Version

```text
Role.Version += 1
```

---

### 30. Produire RoleEnabled

L’agrégat produit :

```text
RoleEnabled
```

---

### 31. Enregistrer l’idempotence

La demande et son résultat sont enregistrés.

---

### 32. Commit atomique

Le changement de statut, les versions, l’idempotence et l’événement sont persistés ensemble.

---

## Résultat attendu

Après succès :

```text
Role
├── same RoleId
├── same WorkspaceId
├── same Metadata
├── Status: Active
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
RoleEnableResult
├── RoleId
├── PreviousStatus
├── CurrentStatus
├── RoleSensitivity
├── ActiveMembershipCount
├── ActiveSessionCount
├── CompliantHolderCount
├── NonCompliantHolderCount
├── Warnings
├── RemediationPlanId
├── AuthorizationStateVersion
└── RoleVersion
```

---

## Invariants concernés

### Transition valide

```text
Disabled
→ Active
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

### AssignmentPolicy inchangée

La commande ne remplace pas la politique d’attribution.

---

### TransferPolicy inchangée

La commande ne remplace pas la politique de transfert.

---

### Permission Set valide

Toutes les dépendances et incompatibilités doivent être satisfaites.

---

### Rôle système valide

Les protections du `SystemType` doivent être satisfaites.

---

### Owner continuity

L’activation ne doit pas créer une configuration owner contradictoire.

---

### Unicité structurelle

Les contraintes d’unicité de `Owner` et `DefaultMember` restent respectées.

---

### Source d’autorité

La source ayant le contrôle du statut doit être respectée.

---

### Version d’autorisation

```text
AuthorizationStateVersion changes
when Role Status changes
```

---

## Événement produit

### RoleEnabled

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `PreviousStatus`
- `CurrentStatus`
- `RoleType`
- `RoleSystemType`
- `RoleSensitivity`
- `ExplicitPermissionCount`
- `EffectivePermissionCount`
- `ActiveMembershipCount`
- `SuspendedMembershipCount`
- `ActiveSessionCount`
- `CompliantHolderCount`
- `NonCompliantHolderCount`
- `Warnings`
- `EnabledBy`
- `EnabledAt`
- `EnableReason`
- `EnableSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `ReadinessAssessmentId`
- `RemediationPlanId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `EnableRequestId`
- `CorrelationId`
- `PermissionSetVersion`
- `AssignmentPolicyVersion`
- `TransferPolicyVersion`
- `AuthorizationStateVersion`
- `RoleVersion`

---

## Données interdites dans l’événement

L’événement ne doit pas contenir :

- de secrets ;
- de tokens ;
- de données MFA ;
- de contenu confidentiel de revue ;
- de justificatifs complets ;
- de liste nominative des détenteurs ;
- de données personnelles inutiles ;
- de claims de session ;
- de détails internes sur un incident.

---

## Effets et signaux secondaires possibles

Selon le contexte :

```text
RoleAuthorizationReactivated
RoleHoldersAuthorizationReevaluationRequested
RoleSessionsRefreshRequested
RoleEnabledWithWarnings
RoleEnableRemediationRequired
ExternalRoleActivationConfirmed
```

Ces noms représentent des signaux internes ou des demandes d'intégration, pas
des Domain Events 1.0.

---

## Événements non produits

La commande ne produit pas :

```text
MembershipReactivated
MembershipRestored
MembershipRoleChanged
RolePermissionGranted
RolePermissionRevoked
RoleAssignmentPolicyChanged
RoleTransferPolicyChanged
RoleMetadataUpdated
SessionCreated
SessionReactivated
```

---

## Effet sur les Memberships

La commande ne modifie pas les memberships.

Leurs permissions effectives peuvent redevenir disponibles.

---

## Effet sur les Sessions

Les sessions actives peuvent nécessiter :

- invalidation de cache ;
- recalcul d’autorisation ;
- refresh de claims ;
- réauthentification avant usage de permissions sensibles.

La commande ne garantit pas qu’un token statique expose immédiatement les nouvelles capacités.

---

## Propagation de l’autorisation

Après commit, des handlers peuvent :

```text
invalidate Role authorization caches
recompute effective Permissions
refresh authorization projections
request Session claims refresh
```

---

## Idempotence

Clé recommandée :

```text
RoleId + EnableRequestId
```

---

## Empreinte idempotente

L’empreinte inclut au minimum :

```text
RoleId
EnabledBy
EnabledAt
EnableReason
EnableSource
ConfirmationId
ApprovalId
SecurityReviewId
ComplianceReviewId
ReadinessAssessmentId
RemediationPlanId
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
```

---

## Répétition identique

Une répétition exacte retourne le résultat initial sans :

- produire un second événement ;
- modifier `EnabledAt` ;
- incrémenter une nouvelle version ;
- répéter les notifications ;
- répéter les invalidations ;
- répéter les revues ;
- consommer une nouvelle approbation.

---

## Nouvelle demande sur un rôle déjà actif

Une nouvelle demande, avec un autre `EnableRequestId`, retourne :

```text
RoleAlreadyActive
```

---

## Conflit d’idempotence

Le même `EnableRequestId` utilisé pour une autre intention produit :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
EnableRole succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry retourne :

- le même statut final ;
- le même `EnabledAt` ;
- les mêmes versions ;
- le même événement logique ;
- la même analyse d’impact.

---

## Concurrence

### Deux activations concurrentes

Une seule réussit.

La seconde rencontre :

```text
RoleVersionConflict
```

ou :

```text
RoleAlreadyActive
```

après rechargement.

---

### Enable contre Disable

Deux commandes opposées ciblent le même rôle.

Une seule version gagne.

L’autre doit recharger l’état et réévaluer son intention.

---

### Enable contre Archive

Si l’archivage gagne d’abord :

```text
ArchivedRoleCannotBeEnabled
```

---

### Enable contre GrantPermissionToRole

Une permission peut être ajoutée pendant l’analyse d’activation.

L’activation doit réévaluer :

- le permission set ;
- la sensibilité ;
- les politiques ;
- la conformité des détenteurs.

---

### Enable contre RevokePermissionFromRole

Même principe.

---

### Enable contre ChangeRoleAssignmentPolicy

La politique peut changer entre l’analyse et le commit.

La version attendue doit protéger l’activation.

---

### Enable contre ChangeRoleTransferPolicy

Même principe pour la politique de transfert.

---

### Enable contre ChangeMembershipRole

Un membership peut recevoir ou perdre le rôle pendant l’analyse.

La conformité des détenteurs doit être réévaluée lorsque cette information est bloquante.

---

### Enable contre SuspendMembership

Le nombre de détenteurs actifs peut changer.

---

### Enable contre modification du catalogue

Une permission peut être désactivée, dépréciée ou modifiée.

La commande doit utiliser une vue cohérente du catalogue.

---

### Enable de rôles structurels concurrents

Deux rôles pouvant prétendre au même `SystemType` ne doivent pas devenir valides simultanément si l’unicité est requise.

---

## Coordination multi-agrégats

L’activation peut dépendre :

- des memberships ;
- du workspace ;
- du catalogue de permissions ;
- de sources externes.

La modification principale reste sur `Role`, mais certaines validations nécessitent une coordination cohérente.

Pour les invariants forts, utiliser :

- verrou de gouvernance workspace ;
- transaction cohérente ;
- projection fortement consistante ;
- version de gouvernance ;
- orchestration sérielle.

---

## Atomicité

Le même commit doit contenir :

```text
Role.Status = Active
+
AuthorizationStateVersion increment
+
Role.Version increment
+
Idempotency record
+
RoleEnabled event
```

---

## États interdits

```text
Role.Status = Active
AND
RoleEnabled missing
```

```text
RoleEnabled persisted
AND
Role.Status remains Disabled
```

```text
Archived Role enabled
```

```text
PermissionSet changed by EnableRole
```

```text
AssignmentPolicy changed by EnableRole
```

```text
TransferPolicy changed by EnableRole
```

```text
AuthorizationStateVersion unchanged
after activation
```

```text
Role enabled with invalid Permission dependencies
```

```text
Role enabled with incompatible Permissions
```

```text
Owner Role enabled with invalid ownership protections
```

```text
ExternallyManaged Role enabled by unauthorized local actor
```

---

## Outbox transactionnelle

`RoleEnabled` doit être enregistré dans la même transaction que le rôle.

La publication intervient après commit.

---

## Effets externes

Après succès, des handlers peuvent :

- invalider les caches d’autorisation ;
- recalculer les permissions effectives ;
- réindexer le rôle ;
- mettre à jour l’administration ;
- notifier les détenteurs ;
- notifier les owners ;
- notifier la sécurité ;
- demander un refresh de session ;
- relancer une intégration ;
- reprendre un workflow suspendu ;
- mettre à jour les projections ;
- poursuivre une récupération ;
- synchroniser un système externe.

---

## Notifications

Une notification est recommandée lorsque :

- le rôle est privilégié ou critique ;
- le rôle est owner ;
- le rôle est default member ;
- plusieurs membres sont concernés ;
- le rôle était désactivé après un incident ;
- une remédiation reste en cours ;
- les permissions redeviennent effectives ;
- une intégration est relancée ;
- l’acteur bénéficie lui-même de l’activation.

---

## Notification des détenteurs

Les détenteurs actifs peuvent être informés que le rôle redevient utilisable.

Le message doit éviter d’exposer :

- les permissions sensibles détaillées ;
- les motifs confidentiels de désactivation ;
- les autres détenteurs ;
- le contenu des revues ;
- les données de session.

---

## Audit

Une activation réussie doit enregistrer :

- `RoleId`
- `WorkspaceId`
- statut précédent
- statut final
- `RoleType`
- `RoleSystemType`
- source de contrôle
- permissions explicites
- permissions effectives
- classification du rôle
- politique d’attribution
- politique de transfert
- conformité des politiques
- nombre de détenteurs actifs
- nombre de détenteurs suspendus
- nombre de sessions actives
- nombre de détenteurs conformes
- nombre de détenteurs non conformes
- avertissements
- risques opérationnels
- continuité administrative
- `EnabledBy`
- `EnabledAt`
- `EnableReason`
- `EnableSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `ReadinessAssessmentId`
- `RemediationPlanId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `EnableRequestId`
- `CorrelationId`
- versions précédentes
- versions finales
- résultat final.

---

## Questions auxquelles l’audit doit répondre

```text
which Role was enabled
inside which Workspace
who enabled it
why it was enabled
which source controlled the Status
which Permissions became effective again
how many active Memberships were affected
whether all active holders were compliant
whether the Role was privileged or critical
which approvals and reviews were used
whether a remediation plan was accepted
whether administrative continuity remained valid
which authorization version became effective
```

---

## Sécurité

La commande doit garantir que :

- seul un acteur autorisé active le rôle ;
- le rôle est réellement désactivé ;
- un rôle archivé n’est pas réactivé ;
- la source de vérité est respectée ;
- les permissions sont valides ;
- les dépendances sont satisfaites ;
- les incompatibilités sont absentes ;
- les politiques sont valides et adaptées ;
- les rôles système restent cohérents ;
- les détenteurs actuels sont évalués ;
- les rôles privilégiés exigent les contrôles appropriés ;
- l’activation ne contourne pas une revue de sécurité ;
- l’effet sur les sessions est maîtrisé ;
- les caches peuvent être invalidés ;
- l’activation est idempotente ;
- l’état et l’événement sont commités atomiquement.

---

## Confidentialité

La commande et l’événement ne doivent pas contenir :

- de secrets ;
- de tokens ;
- de mots de passe ;
- de détails MFA ;
- de contenu complet de revue ;
- de détails d’incident confidentiels ;
- de liste nominative de détenteurs ;
- de données personnelles inutiles ;
- de claims complets de session.

---

## Erreurs métier

### RoleNotFound

Le rôle n’existe pas.

---

### WorkspaceNotFound

Le workspace n’existe pas.

---

### WorkspaceUnavailable

Le workspace n’autorise pas l’activation.

---

### RoleAlreadyActive

Le rôle est déjà actif.

---

### ArchivedRoleCannotBeEnabled

Un rôle archivé ne peut pas être activé.

---

### ActorNotAuthorized

L’acteur ne peut pas activer de rôle.

---

### RoleEnableNotAuthorized

L’acteur ne peut pas activer ce rôle précis.

---

### PrivilegedRoleEnableNotAuthorized

L’acteur ne peut pas activer un rôle privilégié.

---

### CriticalRoleEnableNotAuthorized

L’acteur ne peut pas activer un rôle critique.

---

### SystemRoleEnableNotAuthorized

L’acteur ne peut pas activer ce rôle système.

---

### OwnerRoleEnableNotAuthorized

L’acteur ne peut pas activer le rôle owner.

---

### RoleStatusSourceNotAuthoritative

La source ne contrôle pas le statut.

---

### ExternallyManagedRoleCannotBeEnabledLocally

Le rôle externe ne peut pas être activé localement.

---

### TemplateManagedRoleCannotBeEnabledLocally

Le modèle contrôle le statut.

---

### ProductManagedRoleCannotBeEnabledLocally

Le produit contrôle le statut.

---

### ExternalReferenceRequired

Une référence externe est nécessaire.

---

### ExternalVersionRequired

Une version externe est nécessaire.

---

### ExternalSourceUnavailable

La source externe n’est pas disponible ou valide.

---

### ExternalSynchronizationRequired

Une synchronisation préalable est nécessaire.

---

### TemplateReferenceRequired

Le rôle dérivé ne référence pas son modèle.

---

### TemplateVersionConflict

La version du modèle est obsolète.

---

### TemplateUnavailable

Le modèle n’est plus utilisable.

---

### PermissionSetInvalid

L’ensemble des permissions est invalide.

---

### PermissionNotFound

Une permission référencée n’existe plus.

---

### PermissionDisabled

Une permission affectée est désactivée.

---

### PermissionScopeNotCompatible

Une permission est incompatible avec le contexte du rôle.

---

### PermissionDependencyViolation

Une dépendance de permission n’est pas satisfaite.

---

### PermissionIncompatibilityDetected

Des permissions incompatibles coexistent.

---

### MandatoryPermissionMissing

Une permission obligatoire manque.

---

### RoleAssignmentPolicyInvalid

La politique d’attribution est invalide.

---

### RoleAssignmentPolicyInsufficient

La politique d’attribution est insuffisante pour la sensibilité du rôle.

---

### RoleTransferPolicyInvalid

La politique de transfert est invalide.

---

### RoleTransferPolicyInsufficient

La politique de transfert est insuffisante.

---

### RolePoliciesNotCompatible

Les politiques sont incompatibles.

---

### OwnerAssignmentPolicyInvalid

La politique owner est invalide.

---

### OwnerTransferPolicyInvalid

La politique de transfert owner est invalide.

---

### OwnerRecoveryPathRequired

Aucune récupération owner valide n’existe.

---

### OwnerRoleConflict

Un conflit structurel owner existe.

---

### DefaultMemberRoleConflict

Un conflit structurel default member existe.

---

### RoleHolderNotCompliant

Un détenteur actif n’est pas conforme.

---

### RoleHoldersNotCompliant

Plusieurs détenteurs actifs ne sont pas conformes.

---

### HumanAssigneeRequirementViolation

Une identité non humaine détient un rôle exigeant un humain.

---

### AuthenticationRequirementNotSatisfied

Un détenteur ou l’acteur ne satisfait pas l’authentification requise.

---

### ContinuousRequirementNotSatisfied

Une exigence continue n’est pas satisfaite.

---

### SecurityReviewRequired

Une revue de sécurité est nécessaire.

---

### SecurityReviewInvalid

La revue fournie est invalide.

---

### ComplianceReviewRequired

Une revue de conformité est nécessaire.

---

### ComplianceReviewInvalid

La revue fournie est invalide.

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

L’approbation ne couvre pas l’activation exacte.

---

### ConfirmationRequired

Une confirmation explicite est nécessaire.

---

### ConfirmationInvalid

La confirmation ne correspond pas à l’intention.

---

### RemediationPlanRequired

Un plan de remédiation est requis.

---

### RemediationPlanInvalid

Le plan de remédiation est invalide.

---

### BlockingIssueCannotBeRemediatedAfterEnablement

Une violation dure doit être corrigée avant activation.

---

### AdministrationContinuityViolation

L’activation crée une configuration administrative invalide.

---

### ProductPolicyViolation

Une règle produit interdit l’activation.

---

### LicenseRestriction

La licence n’autorise pas le rôle ou ses capacités.

---

### RoleVersionConflict

Le rôle a changé.

---

### PermissionSetVersionConflict

L’ensemble des permissions a changé.

---

### AssignmentPolicyVersionConflict

La politique d’attribution a changé.

---

### TransferPolicyVersionConflict

La politique de transfert a changé.

---

### RoleEnableConflict

Une modification concurrente empêche l’activation.

---

### IdempotencyConflict

Le même identifiant représente une autre intention.

---

## Décisions de conception

### EnableRole ne restaure pas un Role archivé

L’activation concerne uniquement :

```text
Disabled
→ Active
```

---

### EnableRole ne modifie pas les Memberships

Les memberships conservaient déjà leur relation au rôle.

---

### EnableRole ne réactive pas les Memberships

La réactivation d’un membership reste explicite.

---

### EnableRole ne modifie pas les Permissions

Le permission set est validé, mais inchangé.

---

### EnableRole ne modifie pas les policies

Les politiques doivent être corrigées avant l’activation si nécessaire.

---

### L’activation est une élévation collective

Elle peut rendre des permissions effectives pour plusieurs détenteurs.

---

### Les détenteurs actuels sont réévalués

Une affectation historique ne garantit pas une conformité actuelle.

---

### Les invitations et transferts restent soumis à réévaluation

L’activation ne les valide pas automatiquement.

---

### Le PermissionSetVersion reste inchangé

La composition des permissions ne change pas.

---

### AuthorizationStateVersion est incrémentée

L’efficacité du rôle change.

---

### Les sessions ne sont pas recréées

Elles peuvent seulement être réévaluées ou rafraîchies.

---

### Les rôles privilégiés nécessitent une gouvernance renforcée

La sensibilité est calculée à partir des permissions effectives.

---

### Un événement métier dédié est produit

```text
RoleEnabled
```

---

## Cas limites

### Rôle désactivé sans permission

L’activation peut réussir avec un avertissement.

Le rôle devient actif mais ne confère aucune capacité.

---

### Rôle désactivé sans détenteur

L’activation est autorisée.

---

### Rôle avec détenteurs suspendus uniquement

L’activation est autorisée.

Aucun détenteur ne reçoit immédiatement les permissions.

---

### Rôle avec détenteurs actifs non conformes

L’activation est bloquée ou exige une remédiation selon la politique.

---

### Permission dépréciée

L’activation peut être tolérée avec avertissement ou refusée selon le catalogue.

---

### Permission supprimée

L’activation doit normalement être refusée tant que la référence n’est pas corrigée.

---

### Rôle externe avec source indisponible

L’activation est refusée si la source est autoritaire.

---

### Rôle template-derived divergent

L’activation dépend de la politique d’override.

---

### Owner Role désactivé

L’activation exige des validations renforcées.

---

### DefaultMember Role activé avec invitations en attente

Les invitations ne sont pas automatiquement acceptables ; elles seront réévaluées.

---

### Rôle devenant critique après une modification de permissions

L’activation applique les exigences du niveau critique courant.

---

### L’acteur détient le rôle cible

L’activation lui redonne potentiellement des permissions.

Cette situation constitue un bénéfice direct et peut nécessiter une approbation.

---

### Claims statiques encore obsolètes

Le rôle est actif dans le domaine, même si certains tokens nécessitent un refresh.

---

### Retry après succès

Le même résultat est retourné sans nouvel événement.

---

## Checklist de validation

Avant commit :

```text
Role exists
Workspace exists
Role belongs to Workspace
Role Status is Disabled
Role is not Archived
Actor or SystemActor is authorized
EnableSource controls Role Status
Permission assignments are valid
Effective Permission set is calculable
Permission dependencies are satisfied
No hard Permission incompatibility exists
Mandatory Permissions are present
Role sensitivity is calculated
AssignmentPolicy is valid
TransferPolicy is valid
AssignmentPolicy and TransferPolicy are compatible
RoleType-specific rules are satisfied
SystemType-specific rules are satisfied
Owner protections are satisfied when applicable
DefaultMember uniqueness is preserved
External authority is valid when applicable
Template authority is valid when applicable
Current active Role holders are evaluated
Holder compliance mode is satisfied
Administration continuity is valid
Required confirmation is valid
Required approval is valid
Required security review is valid
Required compliance review is valid
Remediation plan is valid when allowed
Expected Role version matches
Expected PermissionSetVersion matches
Expected AssignmentPolicyVersion matches
Expected TransferPolicyVersion matches
Idempotency is verified
PermissionSetVersion remains unchanged
AuthorizationStateVersion can be incremented
No Membership is modified
No Session is recreated
RoleEnabled can be persisted atomically
```

---

## Synthèse

`EnableRole` réactive un rôle désactivé dans son workspace.

Elle garantit que :

- le rôle existe ;
- la transition `Disabled → Active` est valide ;
- un rôle archivé n’est pas réactivé ;
- l’acteur ou le workflow est autorisé ;
- la source de vérité est respectée ;
- l’ensemble des permissions est valide ;
- les dépendances sont satisfaites ;
- les incompatibilités sont absentes ;
- les politiques d’attribution et de transfert restent adaptées ;
- les contraintes des rôles système sont respectées ;
- les détenteurs actuels sont réévalués ;
- les rôles privilégiés sont soumis à une gouvernance renforcée ;
- aucun membership n’est directement modifié ;
- aucun membership suspendu n’est réactivé ;
- aucune session n’est recréée ;
- la composition des permissions reste inchangée ;
- l’état d’autorisation est versionné ;
- les caches et sessions peuvent être réévalués ;
- l’opération est idempotente et sûre face à la concurrence ;
- le changement d’état et l’événement sont commités atomiquement.

Le résultat final est :

```text
Role
├── same identity
├── same Workspace
├── same metadata
├── Status: Active
├── same AssignmentPolicy
├── same TransferPolicy
├── same PermissionAssignments
├── same PermissionSetVersion
└── new AuthorizationStateVersion
```
