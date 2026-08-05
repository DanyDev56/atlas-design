---
id: IDN-CMD-GRANT-PERMISSION-TO-ROLE
title: GrantPermissionToRole
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
  - ../invariants.md
  - ../permissions.md
  - ../events.md
  - CreateRole.md
  - RevokePermissionFromRole.md
  - ChangeRoleAssignmentPolicy.md
  - ChangeRoleTransferPolicy.md
  - ../workflows.md
---

# GrantPermissionToRole

## Objectif

La commande `GrantPermissionToRole` accorde une `Permission` globale à un `Role` appartenant à un `Workspace`.

Elle modifie l’ensemble des capacités accordées par le rôle.

```text
Role.PermissionAssignments
+
PermissionId
```

La commande ne doit pas :

- créer une `Permission` ;
- modifier la définition d’une `Permission` ;
- créer un `Role` ;
- attribuer le rôle à un `Membership` ;
- modifier la politique d’attribution du rôle ;
- modifier la politique de transfert du rôle ;
- modifier les métadonnées du rôle ;
- activer ou désactiver un rôle ;
- suspendre ou retirer un membre ;
- attribuer directement une permission à un `User` ;
- attribuer directement une permission à un `Membership`.

---

## Intention métier

La commande répond à l’intention suivante :

```text
allow every eligible holder of this Role
to exercise one additional capability
inside the Role Workspace
```

Une permission représente une capacité.

Un rôle représente une responsabilité.

```text
Role
↓
responsibility

Permission
↓
capability
```

Accorder une permission à un rôle signifie :

```text
Membership holds Role
AND
Membership is Active
AND
Role is Active
AND
Permission assignment is effective
↓
Membership may exercise Permission
within the Workspace context
```

---

## Agrégat concerné

```text
Role
```

La commande modifie un seul agrégat `Role`.

Elle consulte également :

- le `Workspace` ;
- l’acteur ;
- la `Permission` globale ;
- le catalogue de permissions ;
- les règles de gouvernance ;
- les politiques de sécurité ;
- les limites de licence ;
- les contraintes de séparation des devoirs ;
- les memberships actuellement affectés ;
- les éventuelles sources externes ;
- les éventuels modèles de rôle.

---

## Pourquoi l’affectation appartient au Role

Le rôle détermine les capacités obtenues par ses détenteurs.

La relation conceptuelle est :

```text
Role
many-to-many
Permission
```

Le rôle possède donc un ensemble de références vers des permissions globales :

```text
Role.PermissionAssignments
```

La `Permission` elle-même reste indépendante du rôle et du workspace.

---

## Modèle conceptuel

```text
Workspace
└── Role
    └── PermissionAssignments
        ├── PermissionId A
        ├── PermissionId B
        └── PermissionId C
```

Les permissions appartiennent au catalogue global :

```text
Global Permission Catalog
├── workspace.members.read
├── workspace.members.change-role
├── workspace.roles.create
└── workspace.billing.read
```

---

## Distinction entre Permission et PermissionAssignment

La `Permission` définit la capacité globale.

Exemple :

```text
workspace.members.change-role
```

L’affectation au rôle peut être modélisée comme une simple référence :

```text
PermissionId
```

ou comme un `Value Object` enrichi :

```text
RolePermissionAssignment
├── PermissionId
├── GrantedAt
├── GrantedBy
├── GrantSource
├── GrantReason
├── ExternalReference
└── PolicyVersion
```

La seconde approche est recommandée lorsque l’historique, l’origine ou la synchronisation doivent être conservés directement dans l’agrégat.

---

## Recommandation de modèle

Utiliser :

```text
Role
└── PermissionAssignments: Set<RolePermissionAssignment>
```

avec une unicité métier sur :

```text
PermissionId
```

Un rôle ne doit pas posséder plusieurs affectations actives de la même permission.

---

## RolePermissionAssignment

Structure recommandée :

```text
RolePermissionAssignment
├── PermissionId
├── GrantedAt
├── GrantedBy
├── GrantSource
├── GrantReason
├── ExternalReference
├── ExternalVersion
├── TemplateId
├── TemplateVersion
└── GrantRequestId
```

L’égalité fonctionnelle de l’affectation repose principalement sur :

```text
PermissionId
```

Les autres propriétés décrivent l’origine et l’audit.

---

## Effet sur l’autorisation

Une fois la commande commitée, la permission devient potentiellement effective pour tous les memberships qui :

```text
Membership.Status = Active
AND
Membership.RoleId = Role.RoleId
AND
Role.Status = Active
```

L’effet peut être immédiat ou dépendre de la propagation des caches et projections.

---

## Chaîne d’autorisation

```text
Session
→ User
→ Membership
→ Role
→ Permission
```

Après l’octroi :

```text
Role
→ newly granted Permission
```

La permission devient accessible via la chaîne d’autorisation existante.

---

## Permission directe interdite

Le modèle ne doit pas créer une relation directe :

```text
User → Permission
```

ou :

```text
Membership → Permission
```

sauf décision architecturale ultérieure explicite.

Dans le modèle actuel :

```text
Permission only through Role
```

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un administrateur de sécurité ;
- un administrateur de gouvernance ;
- un workflow de bootstrap ;
- un `SystemActor` ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un processus de migration ;
- un processus de récupération administrative ;
- un administrateur de plateforme dans un cadre autorisé.

L’acteur doit être identifiable et auditable.

---

## Permission requise

Permission canonique :

```text
workspace.roles.grant-permission
```

La sensibilité de la permission cible, le type de rôle et sa source de contrôle
sont évalués par des politiques contextuelles. Une approbation, une séparation
des responsabilités ou une réauthentification peut être exigée sans introduire
de clé d'octroi alternative en 1.0.

---

## Permission dynamique d’octroi

Certaines permissions peuvent définir leur propre exigence d’octroi.

Exemple :

```text
Permission.RequiredGrantPermission
```

La valeur doit référencer une permission active du catalogue. En 1.0, une
permission critique peut conserver `workspace.roles.grant-permission` comme
autorité et ajouter une approbation ou une séparation des responsabilités.

La condition devient :

```text
Actor has workspace.roles.grant-permission
AND Actor satisfies Permission.RequiredGrantPermission when distinct
```

---

## Principe de non-escalade

Un acteur ne doit pas nécessairement pouvoir accorder une permission qu’il ne possède pas lui-même.

Deux politiques sont possibles.

### Politique stricte

```text
Actor may grant Permission
only if Actor effectively has Permission
```

### Politique gouvernée

```text
Actor may grant Permission
if Actor has dedicated grant authority
even if Actor does not exercise the granted capability
```

---

## Recommandation

Préférer une autorité dédiée :

```text
grant authority
```

plutôt qu’une règle générale :

```text
you may grant what you have
```

Certaines responsabilités administratives doivent pouvoir distribuer des capacités qu’elles n’utilisent pas directement.

Exemple :

```text
Security Administrator
may grant billing permissions
without having billing access
```

La permission d’octroi doit donc être explicitement modélisée.

---

## Self-escalation

La commande peut permettre à un acteur d’ajouter une permission au rôle qu’il détient lui-même.

Exemple :

```text
Actor Membership.RoleId = Target RoleId
```

Cette situation constitue une potentielle auto-élévation.

Elle doit être détectée explicitement.

---

## Politique recommandée pour self-escalation

Pour une permission non privilégiée :

```text
allowed only with explicit grant authority
```

Pour une permission privilégiée :

```text
additional approval required
OR
self-escalation forbidden
```

Pour une permission critique :

```text
self-escalation forbidden
```

---

## Autorisation renforcée

L’octroi peut exiger :

- MFA ;
- authentification récente ;
- approbation ;
- séparation des devoirs ;
- double contrôle ;
- justification ;
- revue de sécurité ;
- revue de conformité ;
- notification ;
- délai de prise d’effet ;
- ticket ou dossier de changement.

---

## Sources d’octroi

Valeurs recommandées pour `PermissionGrantSource` :

```text
ManualAdministration
WorkspaceBootstrap
SecurityGovernance
ComplianceGovernance
SystemProvisioning
ExternalSynchronization
TemplateSynchronization
Migration
AdministrativeRecovery
ProductConfiguration
```

---

## ManualAdministration

Un acteur humain autorisé ajoute explicitement la permission.

---

## WorkspaceBootstrap

La permission est ajoutée lors de la création initiale du workspace.

Exemple :

```text
Create Owner Role
↓
Grant Owner Permissions
```

---

## SecurityGovernance

L’octroi résulte d’une décision de sécurité.

---

## ComplianceGovernance

L’octroi résulte d’une obligation de conformité.

---

## SystemProvisioning

L’octroi est exécuté par un workflow interne identifié.

---

## ExternalSynchronization

L’ensemble des permissions est contrôlé par une source externe.

---

## TemplateSynchronization

La permission est héritée d’un modèle de rôle.

---

## Migration

La permission est attribuée lors d’une migration contrôlée.

---

## AdministrativeRecovery

La permission est ajoutée dans le cadre d’une récupération administrative exceptionnelle.

---

## ProductConfiguration

La permission est imposée par le produit pour un rôle système.

---

## Motifs d’octroi

Valeurs recommandées pour `PermissionGrantReason` :

```text
ResponsibilityRequirement
OrganizationalChange
SecurityRequirement
ComplianceRequirement
FeatureEnablement
WorkspaceBootstrap
TemplateApplication
ExternalDirectoryUpdate
AdministrativeCorrection
Migration
Recovery
Other
```

---

## Données d’entrée

### Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `RoleId` | `RoleId` | Rôle recevant la permission. |
| `PermissionId` | `PermissionId` | Permission globale accordée. |
| `GrantedBy` | `UserId` ou `SystemActor` | Acteur ou workflow responsable. |
| `GrantedAt` | Instant | Date métier de l’octroi. |
| `GrantReason` | `PermissionGrantReason` | Motif de l’octroi. |
| `GrantSource` | `PermissionGrantSource` | Origine de l’octroi. |
| `GrantRequestId` | Identifiant | Identifiant idempotent. |

### Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `ExpectedRoleVersion` | Version | Version attendue du rôle. |
| `ExpectedPermissionCatalogVersion` | Version | Version attendue du catalogue. |
| `ApprovalId` | Identifiant | Approbation requise pour une permission sensible. |
| `SecurityReviewId` | Identifiant | Revue de sécurité. |
| `ComplianceReviewId` | Identifiant | Revue de conformité. |
| `CaseReference` | Identifiant | Dossier administratif ou de sécurité. |
| `ExternalReference` | Identifiant | Référence externe. |
| `ExternalVersion` | Version | Version de la source externe. |
| `TemplateId` | Identifiant | Modèle ayant demandé l’octroi. |
| `TemplateVersion` | Version | Version du modèle. |
| `EffectiveAt` | Instant | Date différée de prise d’effet. |
| `ExpiresAt` | Instant | Expiration éventuelle de l’affectation. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques non métier. |

---

## Octroi permanent ou temporaire

Deux modèles sont possibles.

### Affectation permanente uniquement

La permission reste affectée jusqu’à :

```text
RevokePermissionFromRole
```

### Affectation temporaire

L’affectation possède :

```text
EffectiveAt
ExpiresAt
```

---

## Recommandation initiale

Pour un premier modèle simple :

```text
Role Permission assignments are permanent
until explicitly revoked
```

Les permissions temporaires devraient être introduites seulement avec un besoin métier clair.

Sinon, la commande et l’autorisation doivent gérer :

- activation différée ;
- expiration ;
- réévaluation ;
- concurrence ;
- planification ;
- notifications ;
- sessions ;
- audit temporel.

---

## EffectiveAt

Si l’octroi différé est supporté, la commande peut créer une affectation planifiée.

La permission ne doit pas devenir effective avant :

```text
EffectiveAt
```

Une réévaluation complète est requise à la date d’effet.

---

## ExpiresAt

Si l’expiration est supportée :

```text
ExpiresAt > EffectiveAt
```

L’expiration doit produire une transition métier explicite ou un événement dédié.

Elle ne doit pas dépendre uniquement d’un filtre silencieux dans les requêtes.

---

## Recommandation de périmètre

Dans la première version de `GrantPermissionToRole`, ne pas supporter :

```text
EffectiveAt
ExpiresAt
```

La commande réalise un octroi immédiat et permanent jusqu’à révocation.

Les octrois différés ou temporaires pourront être traités par :

```text
SchedulePermissionGrant
GrantTemporaryPermissionToRole
ExpireRolePermissionGrant
```

si le besoin apparaît.

---

## Permission globale

La `Permission` doit exister dans le catalogue global.

Elle doit être identifiable par :

```text
PermissionId
```

et non uniquement par sa clé textuelle.

Une clé telle que :

```text
workspace.members.change-role
```

peut être un identifiant fonctionnel unique, mais le contrat doit être explicite.

---

## Structure d’une Permission

La commande peut consulter les propriétés suivantes :

```text
Permission
├── PermissionId
├── Key
├── Name
├── Description
├── Status
├── Scope
├── Sensitivity
├── GrantPolicy
├── IncompatiblePermissions
├── RequiredPermissions
├── ImplicationRules
├── DeprecatedAt
└── CatalogVersion
```

---

## PermissionStatus

Valeurs possibles :

```text
Active
Deprecated
Disabled
Removed
```

---

## Politique recommandée par état

```text
Active     -> grant allowed
Deprecated -> grant normally forbidden
Disabled   -> grant forbidden
Removed    -> grant forbidden
```

Une permission dépréciée peut éventuellement rester sur des rôles existants pendant une migration, sans permettre de nouveaux octrois.

---

## PermissionScope

Une permission doit être compatible avec le contexte du rôle.

Exemples :

```text
Workspace
Platform
Organization
Project
Resource
```

Dans le modèle courant, un rôle de workspace doit normalement recevoir uniquement des permissions dont le scope est compatible avec :

```text
Workspace
```

---

## Permission de plateforme

Une permission globale au sens du catalogue n’est pas nécessairement une permission de plateforme.

Il faut distinguer :

```text
globally defined Permission
```

et :

```text
platform-scoped Permission
```

Une permission de plateforme ne doit pas être attribuée à un rôle de workspace sans règle explicite.

---

## Sensibilité d’une Permission

Valeurs possibles :

```text
Standard
Elevated
Privileged
Critical
SystemReserved
```

La sensibilité peut influencer :

- l’autorisation ;
- l’authentification ;
- les approbations ;
- l’audit ;
- les notifications ;
- les restrictions de rôle ;
- la séparation des devoirs.

---

## SystemReserved

Une permission `SystemReserved` ne peut être accordée que :

- par le produit ;
- à certains rôles système ;
- via un workflow de bootstrap ;
- via une migration ou récupération contrôlée.

---

## Permissions réservées au Owner Role

Certaines permissions peuvent imposer :

```text
TargetRole.SystemType = Owner
```

Exemple conceptuel :

```text
workspace.members.transfer-role
```

À l’inverse, certaines permissions peuvent être interdites au rôle owner pour préserver une séparation des devoirs.

La règle doit appartenir à la politique de la permission ou à la gouvernance produit.

---

## GrantPolicy de la Permission

Structure possible :

```text
PermissionGrantPolicy
├── AllowedRoleTypes
├── AllowedSystemTypes
├── ForbiddenRoleTypes
├── ForbiddenSystemTypes
├── RequiredGrantPermission
├── RequiredAuthenticationLevel
├── ApprovalPolicy
├── SelfEscalationPolicy
├── SeparationOfDutiesPolicy
├── RequiredPermissions
├── IncompatiblePermissions
├── MaximumRolesPerWorkspace
└── ProductManaged
```

---

## AllowedRoleTypes

Exemple :

```text
{
  System,
  Custom
}
```

Une permission peut être interdite aux rôles externes ou dérivés de modèles.

---

## AllowedSystemTypes

Exemple :

```text
{
  Owner,
  None
}
```

---

## ProductManaged

Lorsque :

```text
ProductManaged = true
```

l’affectation ne peut pas être modifiée librement par le workspace.

---

## Permissions incompatibles

Une permission peut être incompatible avec une autre.

Exemple conceptuel :

```text
workspace.payments.create
```

et :

```text
workspace.payments.approve
```

peuvent être séparées pour appliquer une séparation des devoirs.

La commande doit vérifier :

```text
NewPermission
is compatible with
Role.CurrentPermissions
```

---

## PermissionIncompatibility

Les incompatibilités peuvent être :

```text
Hard
Soft
Contextual
```

### Hard

L’octroi est interdit.

### Soft

L’octroi exige un avertissement, une justification ou une approbation.

### Contextual

L’incompatibilité dépend :

- du type de rôle ;
- du workspace ;
- de la licence ;
- du secteur ;
- d’une politique de conformité.

---

## RequiredPermissions

Une permission peut dépendre d'autres permissions. Identity 1.0 n'en déclare
aucune dans son catalogue ; cette structure reste disponible pour les
permissions enregistrées par d'autres domaines ou une évolution future.

Identity 1.0 utilise exclusivement la stratégie explicite : toutes les
dépendances doivent déjà être présentes ou être accordées par des commandes
distinctes.

```text
Required Permission assignments must already exist
```

Elle évite qu’une commande produise plusieurs octrois implicites.

Si plusieurs permissions doivent être ajoutées atomiquement, introduire une commande ou orchestration dédiée :

```text
GrantPermissionSetToRole
ApplyRolePermissionTemplate
```

---

## Permissions impliquées

Une permission peut logiquement en impliquer une autre au moment de l’évaluation.

Exemple :

```text
workspace.members.change-role
implies
workspace.members.read
```

Dans ce cas, deux options existent :

- stocker uniquement la permission forte et calculer les implications ;
- matérialiser toutes les permissions.

---

## Recommandation

Conserver les implications dans le catalogue :

```text
Permission implication graph
```

et ne pas créer plusieurs affectations implicites.

L’ensemble effectif est calculé comme :

```text
ExplicitRolePermissions
+
ImpliedPermissions
```

---

## Cycle d’implication

Le graphe d’implication doit être acyclique ou géré explicitement.

Exemple invalide :

```text
Permission A implies B
Permission B implies A
```

Même si un moteur peut techniquement gérer ce cycle, il complique :

- les audits ;
- la révocation ;
- l’explication de l’autorisation ;
- les migrations.

---

## Permission déjà accordée

Si le rôle possède déjà la permission, deux comportements sont possibles.

### Succès idempotent

La commande retourne le résultat existant.

### Erreur métier

```text
PermissionAlreadyGrantedToRole
```

---

## Recommandation

Distinguer :

### Même `GrantRequestId`

Retourner le résultat initial.

### Autre `GrantRequestId`, permission déjà présente

Retourner :

```text
PermissionAlreadyGrantedToRole
```

Cela évite de masquer une intention redondante ou une erreur de workflow.

---

## Rôle actif

Politique recommandée :

```text
Active   -> grant allowed
Disabled -> grant allowed with restrictions
Archived -> grant forbidden
Removed  -> grant forbidden
```

---

## Rôle désactivé

Un rôle désactivé peut recevoir une permission afin de préparer sa réactivation.

Cependant :

- la permission n’est pas effective tant que le rôle reste désactivé ;
- les contrôles de sécurité restent applicables ;
- la réactivation doit analyser le nouvel ensemble de permissions.

---

## Rôle archivé

Un rôle archivé est immuable.

L’octroi est refusé.

---

## Rôle externe

Pour :

```text
RoleType = External
```

les permissions peuvent être :

- entièrement contrôlées par la source externe ;
- partiellement contrôlées ;
- localement surchargées ;
- verrouillées par le produit.

La commande doit vérifier la source d’autorité.

---

## Rôle dérivé d’un modèle

Pour :

```text
RoleType = TemplateDerived
```

l’octroi peut constituer :

- une modification locale autorisée ;
- un override explicite ;
- une violation du modèle ;
- une modification destinée à être écrasée.

Le comportement doit être explicite.

---

## Rôle système

Un rôle système peut imposer :

- un ensemble minimal de permissions ;
- un ensemble maximal ;
- des permissions obligatoires ;
- des permissions interdites ;
- des permissions product-managed.

---

## Owner Role

Accorder une permission au rôle owner peut affecter tous les owners actifs.

La commande doit donc analyser :

- le nombre de détenteurs ;
- la sensibilité de la permission ;
- l’impact global ;
- l’auto-élévation éventuelle ;
- la séparation des devoirs ;
- les exigences de conformité.

---

## DefaultMember Role

Accorder une permission au rôle par défaut peut affecter :

- tous les membres existants utilisant ce rôle ;
- tous les futurs membres auxquels ce rôle sera attribué ;
- les invitations en attente ;
- les workflows de bootstrap.

Cette opération peut avoir un impact plus large qu’un octroi à un rôle spécialisé.

---

## ServiceAccount Role

Une permission accordée à un rôle de compte de service doit être compatible avec :

- l’usage machine ;
- l’absence d’intervention humaine ;
- les restrictions d’authentification ;
- les règles d’intégration ;
- le principe du moindre privilège.

---

## Principe du moindre privilège

La commande doit permettre d’évaluer si l’octroi est proportionné à la responsabilité du rôle.

Cette évaluation peut être :

- informative ;
- bloquante ;
- soumise à approbation ;
- déléguée à une politique de sécurité.

Elle ne doit pas dépendre uniquement du nom du rôle.

---

## RoleCapabilityProfile

Une projection ou politique peut classifier le rôle après l’octroi.

Exemple :

```text
RoleCapabilityProfile
├── Sensitivity
├── PrivilegeLevel
├── DataAccessLevel
├── AdministrativeScope
├── FinancialCapability
├── SecurityCapability
└── ComplianceRisk
```

La classification peut être recalculée après chaque modification de permissions.

---

## Changement de classification

L’octroi peut transformer un rôle :

```text
Standard
↓
Privileged
```

ou :

```text
Privileged
↓
Critical
```

Cette transition peut exiger :

- une approbation ;
- une réévaluation de `RoleAssignmentPolicy` ;
- une réévaluation de `RoleTransferPolicy` ;
- MFA pour les détenteurs ;
- des notifications ;
- une revue des sessions ;
- un contrôle de conformité.

---

## Cohérence avec RoleAssignmentPolicy

Une nouvelle permission sensible peut rendre la politique d’attribution actuelle insuffisante.

Exemple :

```text
Grant critical security Permission
```

alors que :

```text
AssignmentPolicy.TargetAuthenticationRequirement = Standard
```

Le système doit détecter cette incohérence.

---

## Cohérence avec RoleTransferPolicy

Une nouvelle permission critique peut rendre une politique de transfert trop permissive.

Exemple :

```text
TransferPolicy.TargetAcceptancePolicy absent
```

alors que le rôle devient propriétaire d’une capacité critique.

---

## Politiques de sécurité dérivées

L'octroi échoue tant que les politiques du rôle ne sont pas suffisamment
protectrices. Un workflow peut les mettre en conformité avant de rejouer la
commande, mais `GrantPermissionToRole` ne les modifie jamais.

Pour une permission privilégiée ou critique :

```text
Role AssignmentPolicy
AND
Role TransferPolicy
must satisfy minimum security requirements
before grant
```

L’octroi ne doit pas modifier automatiquement ces politiques.

Le workflow recommandé est :

```text
ChangeRoleAssignmentPolicy
↓
ChangeRoleTransferPolicy
↓
GrantPermissionToRole
```

---

## Détenteurs actuels

La commande doit connaître le nombre et le profil des memberships actifs qui détiennent le rôle.

L’octroi peut immédiatement élargir leurs capacités.

Le système peut calculer :

```text
PermissionGrantImpact
├── ActiveMembershipCount
├── SuspendedMembershipCount
├── ActiveSessionCount
├── PrivilegeLevelBefore
├── PrivilegeLevelAfter
├── ExistingPolicyCompliance
└── RequiresRemediation
```

---

## Impact sur les Memberships

La commande ne modifie aucun `Membership`.

Elle ne change pas :

- leur identité ;
- leur statut ;
- leur rôle ;
- leur workspace ;
- leur historique d’appartenance.

Elle modifie les capacités effectives obtenues via leur rôle.

---

## Impact sur les Sessions

Les sessions existantes peuvent utiliser :

- des claims statiques ;
- un cache d’autorisation ;
- un `AuthorizationVersion` ;
- une réévaluation dynamique.

La commande doit garantir que la nouvelle permission devient effective selon un mécanisme cohérent.

---

## Stratégies de propagation

### Autorisation dynamique

Chaque requête relit ou recalcule les permissions.

L’effet devient visible immédiatement après commit.

### Cache versionné

La commande incrémente une version d’autorisation.

Les caches obsolètes sont rejetés.

### Claims statiques

Les sessions doivent être renouvelées ou réémises.

---

## Recommandation

Utiliser :

```text
Role.PermissionSetVersion
```

ou une version équivalente.

Après l’octroi :

```text
PermissionSetVersion += 1
```

Le moteur d’autorisation compare la version utilisée par la session ou le cache avec la version courante.

---

## AuthorizationVersion des Memberships

La commande versionne l'ensemble de permissions du `Role` :

```text
Role.PermissionSetVersion
```

Cette version participe à chaque décision d'autorisation. Aucun
`Membership.AuthorizationVersion` n'est incrémenté individuellement par cette
commande.

---

## Sessions et ajout de permission

Un ajout de permission ne nécessite normalement pas de révoquer une session.

Cependant, une capacité sensible peut exiger une réauthentification avant usage.

Exemple :

```text
Permission requires RecentAuthentication
```

Cette exigence appartient à la permission ou à la politique d’accès, pas à la commande d’octroi elle-même.

---

## Préconditions

Avant l’exécution, les conditions suivantes doivent être satisfaites :

- le rôle existe ;
- le workspace existe ;
- le rôle appartient au workspace ;
- le rôle n'est pas archivé ;
- la permission existe ;
- la permission est active ;
- la permission est compatible avec le scope du rôle ;
- l’acteur ou le workflow est autorisé ;
- la source de la commande contrôle l’affectation ;
- la permission n’est pas déjà accordée ;
- les dépendances requises sont présentes ;
- aucune incompatibilité bloquante n’existe ;
- les règles du rôle système sont respectées ;
- les restrictions produit sont respectées ;
- les restrictions de licence sont respectées ;
- les règles de self-escalation sont respectées ;
- les approbations requises sont valides ;
- les politiques d’attribution et de transfert restent suffisamment protectrices ;
- l’impact sur les détenteurs a été évalué ;
- la version du rôle est compatible ;
- la version du catalogue est compatible lorsque nécessaire ;
- la demande est idempotente ;
- aucune modification concurrente incompatible n’a gagné.

---

## Traitement métier

### 1. Vérifier l’idempotence

Le système recherche une demande déjà traitée avec :

```text
RoleId + GrantRequestId
```

Si une demande identique a déjà réussi, le résultat initial est retourné.

---

### 2. Charger le Role

Le système charge le rôle avec :

- son workspace ;
- son état ;
- son type ;
- son `SystemType` ;
- ses permissions actuelles ;
- ses politiques ;
- sa version ;
- sa source de contrôle.

---

### 3. Vérifier l’état du Role

Politique recommandée :

```text
Active   -> allowed
Disabled -> allowed
Archived -> forbidden
Removed  -> forbidden
```

---

### 4. Charger la Permission

Le système charge la permission dans le catalogue global.

---

### 5. Vérifier l’état de la Permission

La permission doit être active et attribuable.

---

### 6. Vérifier le scope

Le scope de la permission doit être compatible avec le rôle de workspace.

---

### 7. Charger le contexte de l’acteur

Pour un acteur humain :

- `User` ;
- `Membership` ;
- rôle ;
- permissions effectives ;
- niveau d’authentification ;
- relation avec le rôle cible ;
- éventuelle self-escalation.

Pour un `SystemActor` :

- identité ;
- périmètre ;
- autorité ;
- source de vérité ;
- version externe ou de modèle.

---

### 8. Autoriser la commande

Le système vérifie :

```text
Actor may grant Permissions to Roles
```

puis :

```text
Actor may grant this Permission
```

et :

```text
Actor may modify this Role
```

---

### 9. Vérifier l’autorité de la source

Le système vérifie que `GrantSource` contrôle l’ensemble de permissions du rôle ou possède un droit d’override.

---

### 10. Vérifier l’absence de doublon

Condition :

```text
Role.PermissionAssignments
does not contain PermissionId
```

---

### 11. Vérifier la politique d’octroi de la Permission

Le système applique :

```text
Permission.GrantPolicy
```

Il vérifie notamment :

- types de rôles autorisés ;
- types système autorisés ;
- permission d’octroi requise ;
- authentification ;
- approbation ;
- séparation des devoirs ;
- self-escalation ;
- restrictions produit.

---

### 12. Détecter la self-escalation

Le système détermine si l’acteur détient actuellement le rôle cible.

```text
ActorMembership.RoleId = RoleId
```

Si oui, la politique correspondante est appliquée.

---

### 13. Vérifier les dépendances

Pour chaque permission requise :

```text
RequiredPermissionId
```

le rôle doit déjà disposer d’une affectation explicite ou d’une implication reconnue selon la politique du catalogue.

---

### 14. Vérifier les incompatibilités

Le système compare la nouvelle permission avec les permissions actuelles.

---

### 15. Calculer l’ensemble effectif après octroi

```text
ExplicitPermissionsAfterGrant
=
CurrentExplicitPermissions
+
PermissionId
```

Puis :

```text
EffectivePermissionsAfterGrant
=
closure(
  ExplicitPermissionsAfterGrant,
  PermissionImplicationGraph
)
```

---

### 16. Détecter les changements de sensibilité

Le système compare :

```text
RoleSensitivityBefore
RoleSensitivityAfter
```

---

### 17. Vérifier AssignmentPolicy

Le système vérifie que la politique d’attribution reste adaptée à la sensibilité finale du rôle.

---

### 18. Vérifier TransferPolicy

Le système vérifie que la politique de transfert reste adaptée à la sensibilité finale du rôle.

---

### 19. Analyser les détenteurs actuels

Le système évalue :

- le nombre de memberships actifs ;
- les types d’identité ;
- les exigences d’authentification ;
- les approbations historiques ;
- les exigences continues ;
- les éventuelles non-conformités.

---

### 20. Appliquer le mode de conformité

Un mode facultatif peut être prévu :

```text
PermissionGrantEnforcementMode
```

avec :

```text
RequireCurrentCompliance
AllowWithRemediation
```

---

## RequireCurrentCompliance

L’octroi échoue si les détenteurs actuels ou les politiques du rôle ne respectent pas les exigences de la permission.

---

## AllowWithRemediation

L’octroi réussit uniquement si un plan de remédiation valide est fourni.

---

## Recommandation

Pour une première version :

```text
RequireCurrentCompliance
```

pour les permissions critiques.

Pour les permissions standard :

```text
no additional holder compliance check
```

Les règles exactes doivent être portées par la politique de la permission.

---

### 21. Vérifier les approbations et revues

Le système valide :

- `ApprovalId` ;
- `SecurityReviewId` ;
- `ComplianceReviewId` ;
- séparation des devoirs ;
- version de la permission ;
- version du rôle ;
- portée exacte de la décision.

---

### 22. Vérifier ExpectedRoleVersion

Condition :

```text
Role.Version = ExpectedRoleVersion
```

---

### 23. Vérifier ExpectedPermissionCatalogVersion

Lorsque fourni :

```text
PermissionCatalog.Version
=
ExpectedPermissionCatalogVersion
```

Cela protège contre une modification concurrente de la définition ou des règles de la permission.

---

### 24. Créer RolePermissionAssignment

Le système construit :

```text
RolePermissionAssignment
```

avec les données normalisées.

---

### 25. Ajouter l’affectation au Role

```text
Role.PermissionAssignments.add(
  RolePermissionAssignment
)
```

---

### 26. Incrémenter PermissionSetVersion

```text
Role.PermissionSetVersion += 1
```

---

### 27. Incrémenter Role.Version

```text
Role.Version += 1
```

---

### 28. Produire RolePermissionGranted

L’agrégat produit :

```text
RolePermissionGranted
```

---

### 29. Enregistrer l’idempotence

La demande et son résultat sont enregistrés.

---

### 30. Commit atomique

L’état du rôle, les versions, l’idempotence et l’événement sont persistés ensemble.

---

## Résultat attendu

Après succès :

```text
Role
├── same RoleId
├── same WorkspaceId
├── same Metadata
├── same Status
├── same AssignmentPolicy
├── same TransferPolicy
├── PermissionAssignments
│   └── includes PermissionId
├── PermissionSetVersion: incremented
└── Role.Version: incremented
```

Aucun `Membership` n’est directement modifié.

---

## Invariants concernés

### Permission uniquement via Role

```text
Membership receives Permission only through Role
```

---

### Permission globale

```text
PermissionId references the global Permission catalog
```

---

### Appartenance du Role

```text
Role remains in the same Workspace
```

---

### Identité stable

```text
RoleId remains unchanged
```

---

### Unicité de l’affectation

```text
at most one active RolePermissionAssignment
per RoleId and PermissionId
```

---

### Compatibilité de scope

```text
Permission.Scope is compatible with Role context
```

---

### Cohérence des dépendances

```text
all required Permissions are satisfied
```

---

### Absence d’incompatibilité bloquante

```text
no hard-incompatible Permission pair exists
```

---

### Politique des rôles système

Les restrictions produit restent satisfaites.

---

### Politique de sécurité

La politique d’attribution et de transfert reste compatible avec la sensibilité finale du rôle.

---

## Événement produit

### RolePermissionGranted

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `PermissionId`
- `PermissionKey`
- `PermissionSensitivity`
- `RoleType`
- `RoleSystemType`
- `RoleSensitivityBefore`
- `RoleSensitivityAfter`
- `AffectedActiveMembershipCount`
- `GrantedBy`
- `GrantedAt`
- `GrantReason`
- `GrantSource`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `GrantRequestId`
- `CorrelationId`
- `PermissionSetVersion`
- `RoleVersion`

---

## PermissionKey dans l’événement

Deux options existent.

### PermissionId uniquement

Avantage :

- événement stable ;
- pas de duplication du catalogue.

### PermissionId et PermissionKey

Avantage :

- meilleure lisibilité ;
- consommation simplifiée ;
- audit plus explicite.

Recommandation :

```text
PermissionId
+
PermissionKey snapshot
```

La clé est informative.

L’identité reste `PermissionId`.

---

## Données interdites dans l’événement

L’événement ne doit pas contenir :

- de secrets ;
- de tokens ;
- de données MFA ;
- de justificatifs confidentiels ;
- de contenu complet de dossier ;
- de données personnelles des détenteurs ;
- de détail nominatif inutile ;
- de claims de session.

---

## Signaux secondaires possibles

Selon l’impact :

```text
RoleBecamePrivileged
RoleBecameCritical
RoleSecurityPolicyReviewRequired
RoleHoldersBecameNonCompliant
RoleAuthorizationCacheInvalidationRequested
```

Ces signaux internes ne sont enregistrés que si leur condition est satisfaite.
Ils ne font pas partie des Domain Events 1.0.

---

## Événements non produits

La commande ne produit pas :

```text
PermissionCreated
PermissionUpdated
MembershipRoleChanged
MembershipAuthorizationChanged
RoleAssignmentPolicyChanged
RoleTransferPolicyChanged
RoleMetadataUpdated
RoleEnabled
RoleDisabled
```

---

## Effet sur les Memberships

La commande ne modifie pas l’état des memberships.

Cependant, leurs permissions effectives peuvent évoluer immédiatement.

```text
Membership effective permissions
=
permissions of current Role
```

---

## Effet sur AuthorizationVersion

Recommandation :

```text
Role.PermissionSetVersion += 1
```

Le moteur d’autorisation doit inclure cette version dans ses clés ou décisions de cache.

---

## Effet sur les sessions

La commande ne révoque normalement pas les sessions.

Elle peut déclencher :

```text
AuthorizationCacheInvalidation
```

Une permission sensible peut exiger une réauthentification au moment de son utilisation.

---

## Réévaluation des claims

Lorsque les sessions contiennent des claims statiques, un handler peut :

- marquer les claims comme obsolètes ;
- forcer un refresh ;
- réémettre un token ;
- imposer une nouvelle authentification.

Le domaine ne doit pas considérer des claims obsolètes comme source de vérité.

---

## Idempotence

Clé recommandée :

```text
RoleId + GrantRequestId
```

---

## Empreinte idempotente

L’empreinte doit inclure au minimum :

```text
RoleId
PermissionId
GrantedBy
GrantedAt
GrantReason
GrantSource
ApprovalId
SecurityReviewId
ComplianceReviewId
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
```

Les métadonnées non métier ne doivent pas nécessairement affecter l’empreinte.

---

## Répétition identique

Une répétition exacte retourne le résultat initial sans :

- ajouter un doublon ;
- produire un second événement ;
- incrémenter une nouvelle version ;
- modifier `GrantedAt` ;
- répéter les notifications ;
- répéter les invalidations ;
- consommer une nouvelle approbation.

---

## Permission déjà présente avec une autre demande

Si la permission est déjà affectée, mais avec un autre `GrantRequestId` :

```text
PermissionAlreadyGrantedToRole
```

Le système ne doit pas créer un nouvel historique d’octroi fictif.

---

## Conflit d’idempotence

Le même `GrantRequestId` utilisé avec une autre permission ou un autre rôle produit :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
GrantPermissionToRole succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry doit retourner :

- la même affectation ;
- le même `GrantedAt` ;
- la même version finale ;
- le même impact calculé ;
- le même événement logique.

---

## Concurrence

### Deux octrois concurrents de la même Permission

Une seule commande doit réussir.

Protection recommandée :

```text
UNIQUE(RoleId, PermissionId)
```

ou équivalent dans l’agrégat persistant.

---

### Deux permissions différentes

Les deux commandes modifient le même agrégat.

Avec une version optimiste stricte :

- une commande réussit ;
- l’autre recharge ;
- les dépendances et incompatibilités sont réévaluées.

---

### Grant contre Revoke

Une commande accorde la permission pendant qu’une autre la révoque.

Une seule version gagne.

L’état final doit correspondre à une intention commitée et auditable.

---

### Grant contre ChangeRoleAssignmentPolicy

L’octroi peut augmenter la sensibilité du rôle pendant qu’une autre commande modifie sa politique d’attribution.

La cohérence doit être réévaluée sur la version finale.

---

### Grant contre ChangeRoleTransferPolicy

Même principe pour la politique de transfert.

---

### Grant contre DisableRole

L’octroi peut réussir avant ou après la désactivation selon l’ordre de commit.

Si la désactivation gagne en premier, l'octroi reste autorisé : un rôle
`Disabled` peut être préparé avant sa réactivation, sans rendre la permission
effective immédiatement.

---

### Grant contre ArchiveRole

Si l’archivage gagne, l’octroi doit échouer.

---

### Grant contre Permission deprecation

La permission peut devenir dépréciée ou désactivée entre la lecture et le commit.

Une version du catalogue ou une transaction cohérente doit protéger cette situation.

---

### Grant de permissions incompatibles

Commande A accorde :

```text
Permission X
```

Commande B accorde :

```text
Permission Y
```

alors que X et Y sont incompatibles.

Une seule doit réussir après vérification de l’état final.

---

### Grant atteignant une limite

Une permission peut limiter le nombre de rôles pouvant la recevoir dans un workspace.

La vérification doit être protégée contre la concurrence.

---

## Version spécialisée

Le rôle peut posséder :

```text
PermissionSetVersion
```

distincte de :

```text
Role.Version
```

---

## Recommandation

Utiliser :

```text
Role.Version
+
PermissionSetVersion
```

`Role.Version` protège l’agrégat.

`PermissionSetVersion` permet aux caches et preuves d’autorisation de suivre spécifiquement l’évolution des permissions.

---

## Atomicité

Le même commit doit contenir :

```text
RolePermissionAssignment creation
+
PermissionSetVersion increment
+
Role.Version increment
+
Idempotency record
+
RolePermissionGranted event
```

---

## États interdits

```text
Permission assignment persisted
AND
RolePermissionGranted missing
```

```text
RolePermissionGranted persisted
AND
Permission assignment missing
```

```text
same Permission assigned twice to same Role
```

```text
Permission assigned while Permission does not exist
```

```text
Permission assigned while Permission is disabled
```

```text
hard-incompatible Permissions coexist
```

```text
Role becomes critical
AND
required Role policies are not satisfied
```

```text
PermissionSetVersion unchanged after grant
```

---

## Outbox transactionnelle

L’événement doit être enregistré dans la même transaction que le rôle.

La publication externe intervient après commit.

---

## Effets externes

Après succès, des handlers peuvent :

- invalider les caches d’autorisation ;
- recalculer les permissions effectives ;
- réindexer le rôle ;
- mettre à jour les interfaces d’administration ;
- notifier les détenteurs ;
- notifier les owners ;
- notifier la sécurité ;
- déclencher une revue de conformité ;
- actualiser les claims ;
- synchroniser une source externe ;
- mettre à jour les projections d’audit ;
- poursuivre un bootstrap.

---

## Notifications

Une notification est recommandée lorsque :

- la permission est privilégiée ou critique ;
- le rôle possède de nombreux détenteurs ;
- le rôle est `DefaultMember` ;
- le rôle est `Owner` ;
- la permission donne accès à des données sensibles ;
- la permission permet une administration ;
- la permission modifie une séparation des devoirs ;
- une self-escalation a été autorisée ;
- une approbation renforcée a été utilisée.

---

## Notification des détenteurs

Les détenteurs peuvent être informés qu’une nouvelle capacité leur est accessible.

Le message ne doit pas exposer :

- les permissions confidentielles ;
- les détails de sécurité internes ;
- les autres détenteurs ;
- les justificatifs administratifs.

---

## Audit

Un octroi réussi doit enregistrer :

- `RoleId`
- `WorkspaceId`
- `RoleType`
- `RoleSystemType`
- `PermissionId`
- `PermissionKey`
- `PermissionScope`
- `PermissionSensitivity`
- permissions explicites précédentes
- permissions explicites finales
- classification du rôle avant
- classification du rôle après
- nombre de détenteurs actifs concernés
- éventuelle self-escalation
- dépendances vérifiées
- incompatibilités vérifiées
- conformité des politiques
- `GrantedBy`
- `GrantedAt`
- `GrantReason`
- `GrantSource`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `GrantRequestId`
- `CorrelationId`
- version précédente
- version finale
- `PermissionSetVersion`
- résultat final.

---

## Questions auxquelles l’audit doit répondre

```text
which Permission was granted
to which Role
inside which Workspace
who authorized the grant
why the grant occurred
which source controlled the change
whether the Permission was privileged
whether the Role became privileged or critical
which active Memberships were affected
whether the actor benefited from the grant
which approvals and reviews were used
whether Role policies remained sufficient
which authorization version became effective
```

---

## Sécurité

La commande doit garantir que :

- seule une autorité valide accorde la permission ;
- la permission existe et est attribuable ;
- le scope est compatible ;
- les permissions réservées sont protégées ;
- les dépendances sont satisfaites ;
- les incompatibilités sont détectées ;
- la self-escalation est contrôlée ;
- les rôles système sont protégés ;
- les rôles externes respectent leur source de vérité ;
- les permissions critiques exigent des politiques adaptées ;
- l’impact sur les détenteurs est évalué ;
- les caches sont invalidables ;
- la concurrence ne crée pas de doublon ;
- l’octroi et l’événement sont atomiques ;
- les retries ne dupliquent aucun effet.

---

## Confidentialité

La commande et son événement ne doivent pas contenir :

- de secrets ;
- de tokens ;
- de mots de passe ;
- de détails MFA ;
- de données personnelles nominatives inutiles ;
- de contenu confidentiel de ticket ;
- de justification sensible brute ;
- de liste complète de détenteurs.

Les références de dossier doivent rester des identifiants opaques.

---

## Erreurs métier

### RoleNotFound

Le rôle n’existe pas.

---

### WorkspaceNotFound

Le workspace associé n’existe pas.

---

### WorkspaceUnavailable

Le workspace n’autorise pas l’opération.

---

### RoleArchived

Le rôle archivé est immuable.

---

### PermissionNotFound

La permission n’existe pas dans le catalogue global.

---

### PermissionDisabled

La permission est désactivée.

---

### PermissionDeprecated

La permission ne peut plus être nouvellement accordée.

---

### PermissionRemoved

La permission a été supprimée du catalogue.

---

### PermissionScopeNotCompatible

Le scope de la permission est incompatible avec le rôle.

---

### ActorNotAuthorized

L’acteur ne peut pas accorder de permission.

---

### PermissionGrantNotAuthorized

L’acteur ne peut pas accorder cette permission précise.

---

### PrivilegedPermissionGrantNotAuthorized

L’acteur ne peut pas accorder une permission privilégiée.

---

### CriticalPermissionGrantNotAuthorized

L’acteur ne peut pas accorder une permission critique.

---

### SystemPermissionGrantNotAuthorized

La permission est réservée au produit ou au système.

---

### RolePermissionSourceNotAuthoritative

La source de la commande ne contrôle pas les permissions du rôle.

---

### ExternalRolePermissionOverrideNotAllowed

Le rôle externe ne permet pas cet override local.

---

### TemplateRolePermissionOverrideNotAllowed

Le rôle dérivé d’un modèle ne permet pas cette modification.

---

### PermissionAlreadyGrantedToRole

La permission est déjà affectée au rôle.

---

### RequiredPermissionMissing

Une dépendance requise n’est pas présente.

---

### PermissionDependencyCycleDetected

Le graphe de dépendances ou d’implications est invalide.

---

### PermissionIncompatibleWithExistingPermission

Une incompatibilité bloquante existe.

---

### SeparationOfDutiesViolation

L’ensemble final viole une règle de séparation des devoirs.

---

### SelfEscalationForbidden

L’acteur bénéficierait directement d’une élévation interdite.

---

### SelfEscalationApprovalRequired

Une approbation supplémentaire est nécessaire.

---

### ApprovalRequired

L’octroi nécessite une approbation.

---

### ApprovalInvalid

L’approbation fournie est invalide.

---

### ApprovalExpired

L’approbation a expiré.

---

### ApprovalScopeMismatch

L’approbation ne couvre pas le rôle et la permission concernés.

---

### SelfApprovalForbidden

L’acteur ne peut pas approuver sa propre demande.

---

### SecurityReviewRequired

Une revue de sécurité est nécessaire.

---

### ComplianceReviewRequired

Une revue de conformité est nécessaire.

---

### RoleAssignmentPolicyInsufficient

La politique d’attribution n’est pas suffisamment protectrice.

---

### RoleTransferPolicyInsufficient

La politique de transfert n’est pas suffisamment protectrice.

---

### RoleHoldersNotCompliant

Au moins un détenteur actuel ne respecte pas les exigences finales.

---

### RemediationPlanRequired

Un plan de remédiation est obligatoire.

---

### RemediationPlanInvalid

Le plan de remédiation est incomplet ou invalide.

---

### ProductPolicyViolation

L’octroi viole une règle produit.

---

### LicenseRestriction

La licence n’autorise pas la permission ou la fonctionnalité.

---

### PermissionRoleLimitReached

Le nombre maximal de rôles pouvant recevoir la permission est atteint.

---

### OwnerRolePermissionNotAllowed

La permission ne peut pas être accordée au rôle owner.

---

### PermissionRequiresOwnerRole

La permission ne peut être accordée qu’au rôle owner.

---

### ServiceAccountRolePermissionNotAllowed

La permission est incompatible avec un rôle de compte de service.

---

### RoleVersionConflict

Le rôle a changé depuis la décision initiale.

---

### PermissionCatalogVersionConflict

Le catalogue a changé depuis la validation.

---

### RolePermissionGrantConflict

Une modification concurrente empêche l’octroi.

---

### IdempotencyConflict

Le même `GrantRequestId` représente une autre intention.

---

## Décisions de conception

### La Permission reste globale

La commande affecte une permission globale à un rôle de workspace.

---

### L’octroi modifie le Role

L’affectation appartient à l’agrégat `Role`.

---

### Aucun droit direct n’est accordé à un User

Les permissions transitent par le rôle du membership.

---

### L’octroi est unitaire

La commande accorde exactement une permission.

---

### Les dépendances ne sont pas accordées implicitement

Elles doivent déjà être satisfaites.

---

### Les implications sont calculées

Une permission impliquée n’a pas besoin d’être matérialisée comme affectation supplémentaire.

---

### La self-escalation est détectée explicitement

Elle ne doit pas être noyée dans l’autorisation générale.

---

### Les politiques du Role sont réévaluées

Une nouvelle permission peut augmenter la sensibilité du rôle.

---

### Aucun Membership n’est modifié

Les capacités effectives changent par héritage depuis le rôle.

---

### PermissionSetVersion est incrémentée

Les caches et décisions d’autorisation peuvent détecter le changement.

---

### La commande ne révoque pas les sessions

Elle déclenche une invalidation ou réévaluation d’autorisation.

---

### L’octroi initial est immédiat et permanent

Les octrois temporaires ou différés sont exclus de la première version.

---

### Un événement métier dédié est produit

```text
RolePermissionGranted
```

---

## Cas limites

### Rôle sans détenteur

L’octroi est autorisé.

Il prépare les futures affectations du rôle.

---

### Rôle désactivé

L’octroi peut être autorisé pour préparer sa réactivation.

La permission reste inefficace tant que le rôle est désactivé.

---

### Rôle archivé

L’octroi est refusé.

---

### Permission déjà impliquée

Le rôle peut déjà bénéficier effectivement de la permission par implication, sans affectation explicite.

Deux options existent :

```text
allow explicit grant
```

ou :

```text
reject redundant explicit grant
```

Recommandation :

autoriser l’affectation explicite uniquement si elle possède une signification métier ou de gouvernance.

Sinon retourner :

```text
PermissionAlreadyEffectiveForRole
```

---

### Dépendance satisfaite par implication

Une permission requise peut être considérée comme satisfaite si elle est effective par implication.

Cette décision doit être cohérente dans tout le catalogue.

---

### Permission dépréciée mais déjà effective

La commande ne doit normalement pas permettre de la matérialiser explicitement.

---

### Grant sur DefaultMember

L’impact peut concerner un grand nombre de membres présents et futurs.

Une confirmation renforcée peut être nécessaire.

---

### Grant sur Owner

Tous les owners deviennent détenteurs de la capacité.

La permission doit être compatible avec les règles owner.

---

### Grant à un rôle externe

Le prochain cycle de synchronisation peut écraser l’octroi.

L’override doit être explicite ou interdit.

---

### Grant à un rôle template-derived

La mise à jour du modèle peut retirer ou réintroduire la permission.

Le contrôle et la priorité doivent être définis.

---

### Permission critique avec politiques insuffisantes

La commande échoue.

Elle ne modifie pas automatiquement les politiques.

---

### Acteur détenteur du rôle cible

La self-escalation est évaluée, même si plusieurs autres membres détiennent également le rôle.

---

### Acteur non détenteur mais futur bénéficiaire

Une invitation ou un transfert en attente peut faire de l’acteur un futur détenteur.

Le modèle peut considérer cette situation comme un conflit d’intérêts si les règles de gouvernance l’exigent.

---

### Aucun détenteur conforme

Le rôle peut ne pas avoir de détenteur actuel.

La conformité des futurs détenteurs reste garantie par `RoleAssignmentPolicy`.

---

### Changement du catalogue après approbation

L’approbation doit référencer la permission et, si nécessaire, sa version de catalogue.

---

### Permission renommée

L’identité reste :

```text
PermissionId
```

Le renommage ou changement de clé descriptive ne crée pas une nouvelle affectation.

---

### Permission supprimée après octroi

La suppression ou désactivation d’une permission doit traiter les références existantes via un workflow distinct.

---

### Retry après succès

Le même résultat est retourné sans nouveau changement.

---

## Checklist de validation

Avant commit :

```text
Role exists
Workspace exists
Role belongs to Workspace
Role state allows Permission grant
Permission exists
Permission is Active
Permission scope is compatible
Actor or SystemActor is authorized
GrantSource controls Role Permission assignments
Permission is not already explicitly granted
Permission GrantPolicy allows target Role
Required grant Permission is satisfied
Self-escalation is evaluated
Self-escalation policy is satisfied
Required Permissions are satisfied
No hard incompatibility exists
Separation of duties is preserved
Product restrictions are satisfied
License restrictions are satisfied
Role sensitivity after grant is calculated
AssignmentPolicy remains sufficient
TransferPolicy remains sufficient
Current Role holders are evaluated when required
Approvals are valid when required
Security review is valid when required
Compliance review is valid when required
Role version matches ExpectedRoleVersion
Permission catalog version matches when required
Idempotency is verified
PermissionSetVersion can be incremented
No Membership is directly modified
No Session is directly revoked
RolePermissionGranted can be persisted atomically
```

---

## Synthèse

`GrantPermissionToRole` ajoute une capacité globale à un rôle de workspace.

Elle garantit que :

- le rôle existe ;
- la permission existe et est active ;
- le scope est compatible ;
- l’acteur ou le workflow est autorisé ;
- la source de vérité est respectée ;
- la permission n’est pas déjà accordée ;
- les dépendances sont satisfaites ;
- les incompatibilités sont détectées ;
- la séparation des devoirs est préservée ;
- la self-escalation est contrôlée ;
- les rôles système et privilégiés sont protégés ;
- les politiques d’attribution et de transfert restent adaptées ;
- l’impact sur les détenteurs actuels est évalué ;
- aucun membership n’est directement modifié ;
- les caches d’autorisation peuvent détecter le changement ;
- l’opération est idempotente et sûre face à la concurrence ;
- l’octroi et l’événement sont commités atomiquement.

Le résultat final est :

```text
Role
├── same identity
├── same Workspace
├── same metadata
├── same lifecycle status
├── same AssignmentPolicy
├── same TransferPolicy
├── one additional explicit Permission
└── new PermissionSetVersion
```
