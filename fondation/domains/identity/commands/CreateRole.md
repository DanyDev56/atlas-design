---
id: IDN-CMD-CREATE-ROLE
title: CreateRole
status: Draft
owner: Product
version: 2.0.0
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
  - ../events/RoleCreated.md
  - UpdateRoleMetadata.md
  - ChangeRoleAssignmentPolicy.md
  - ChangeRoleTransferPolicy.md
  - GrantPermissionToRole.md
---

# CreateRole

## Objectif

La commande `CreateRole` crée un nouveau `Role` dans un `Workspace`.

Le rôle représente un ensemble stable de responsabilités pouvant être associé à un `Membership`.

La commande initialise :

- l’identité du rôle ;
- son appartenance au `Workspace` ;
- ses métadonnées ;
- sa nature structurelle ;
- sa politique d’attribution ;
- sa politique de transfert ;
- son état initial ;
- ses références de gouvernance.

Elle ne doit pas :

- attribuer le rôle à un `Membership` ;
- créer un `Membership` ;
- ajouter une `Permission` au rôle ;
- retirer une `Permission` ;
- cloner implicitement un autre rôle ;
- créer une `Invitation` ;
- modifier un rôle existant ;
- créer un `Workspace` ;
- créer une `Session`.

---

## Intention métier

La commande répond à l’intention suivante :

```text
create a new Role definition
inside one Workspace
```

Un `Role` décrit une responsabilité.

Une `Permission` décrit une capacité.

```text
Role
↓
responsibility

Permission
↓
capability
```

Un rôle peut ensuite recevoir des permissions au moyen de commandes explicites telles que :

```text
GrantPermissionToRole
RevokePermissionFromRole
```

---

## Agrégat concerné

```text
Role
```

Le `Role` constitue la racine de l’agrégat créé.

---

## Appartenance au Workspace

Chaque rôle appartient exactement à un `Workspace`.

```text
Role.WorkspaceId
```

est obligatoire et immuable.

Un rôle ne peut pas être :

- global ;
- partagé entre plusieurs workspaces ;
- déplacé vers un autre workspace ;
- associé à plusieurs workspaces.

Les `Permission`, en revanche, sont globales.

```text
Role
belongs to one Workspace

Permission
belongs to the global Permission catalog
```

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un membre autorisé ;
- un workflow de création de `Workspace` ;
- un `SystemActor` ;
- un processus de provisioning ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un processus de migration ;
- un processus de récupération administrative.

L’acteur doit être identifiable et auditable.

---

## Permission requise

Permission recommandée :

```text
workspace.roles.create
```

Une permission plus générale peut être utilisée :

```text
workspace.roles.manage
```

Des permissions renforcées peuvent être nécessaires pour créer certains rôles :

```text
workspace.roles.create-privileged
workspace.roles.create-system
workspace.roles.create-external
workspace.roles.create-template-derived
workspace.owners.create-role
```

---

## Autorisation complémentaire

Posséder la permission générale ne signifie pas nécessairement que l’acteur peut créer n’importe quel rôle.

Le système doit également vérifier :

- le `RoleType` demandé ;
- le `SystemType` demandé ;
- le caractère privilégié du rôle ;
- les politiques produit ;
- les restrictions de licence ;
- les règles de gouvernance ;
- la source de vérité ;
- les modèles autorisés ;
- les permissions référencées dans les politiques ;
- les capacités de récupération administrative.

---

## Données d’entrée

### Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `RoleId` | `RoleId` | Identifiant stable du rôle. |
| `WorkspaceId` | `WorkspaceId` | Workspace propriétaire. |
| `Name` | `RoleName` | Nom métier du rôle. |
| `RoleType` | `RoleType` | Nature de création du rôle. |
| `AssignmentPolicy` | `RoleAssignmentPolicy` | Règles d’attribution du rôle. |
| `TransferPolicy` | `RoleTransferPolicy` | Règles de transfert du rôle. |
| `CreatedBy` | `UserId` ou `SystemActor` | Acteur ou workflow créateur. |
| `CreatedAt` | Instant | Date de création. |
| `CreationSource` | `RoleCreationSource` | Origine de la création. |
| `CreationRequestId` | Identifiant | Identifiant idempotent de la demande. |

### Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `Description` | `RoleDescription` | Description métier. |
| `SystemType` | `RoleSystemType` | Fonction système éventuelle. |
| `DisplayColor` | `RoleDisplayColor` | Couleur de présentation. |
| `Icon` | `RoleIcon` | Clé d’icône. |
| `DisplayOrder` | Entier | Ordre de présentation. |
| `DocumentationUrl` | URL | Documentation associée. |
| `IsPrivileged` | Booléen | Classification de sensibilité. |
| `RequiredHumanGovernance` | Booléen | Indique une gouvernance humaine obligatoire. |
| `ExternalReference` | Identifiant | Référence de la source externe. |
| `ExternalVersion` | Version externe | Version fournie par une source externe. |
| `TemplateRoleId` | `RoleId` ou identifiant de modèle | Modèle d’origine. |
| `TemplateVersion` | Version | Version du modèle. |
| `CaseReference` | Identifiant | Dossier administratif ou de sécurité. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques non métier. |

---

## RoleId

`RoleId` identifie durablement le rôle.

Il doit être :

- unique ;
- opaque ;
- immuable ;
- indépendant du nom ;
- indépendant du `SystemType` ;
- indépendant des permissions ;
- indépendant des memberships utilisant le rôle.

Un renommage futur ne modifie jamais `RoleId`.

---

## Name

`Name` représente le libellé métier principal du rôle.

Exemples :

```text
Billing Administrator
Security Reviewer
Project Contributor
External Auditor
```

Le nom n’est pas l’identité du rôle.

Le moteur d’autorisation ne doit jamais s’appuyer sur le nom.

---

## Validation du Name

Le nom doit :

- être présent ;
- ne pas être vide ;
- respecter la longueur maximale ;
- être normalisable ;
- ne pas contenir uniquement des espaces ;
- ne pas contenir de secrets ;
- ne pas contenir de code exécutable ;
- ne pas contenir de données personnelles inutiles ;
- respecter les noms réservés ;
- ne pas être trompeur ;
- être unique dans le `Workspace`.

---

## NormalizedRoleName

Le système calcule :

```text
NormalizedRoleName
```

à partir de `Name`.

Exemple :

```text
"  Billing   Administrator  "
↓
"billing administrator"
```

La normalisation peut inclure :

- trim ;
- réduction des espaces ;
- normalisation Unicode ;
- comparaison insensible à la casse ;
- normalisation contrôlée des caractères équivalents.

---

## Unicité du nom

Recommandation :

```text
UNIQUE(WorkspaceId, NormalizedRoleName)
```

Cette contrainte doit être protégée au niveau persistant.

Une simple validation applicative n’est pas suffisante face à la concurrence.

---

## Noms réservés

Exemples de noms potentiellement réservés :

```text
Owner
Root
System
Super Administrator
Platform Administrator
Default Member
Guest
```

La réservation dépend :

- du `RoleType` ;
- du `SystemType` ;
- des règles produit ;
- de la localisation ;
- du contexte du `Workspace`.

Un rôle custom ne doit pas usurper l’apparence d’un rôle système.

---

## Description

La description explique la finalité du rôle.

Elle peut préciser :

- les responsabilités ;
- le public concerné ;
- le périmètre fonctionnel ;
- les usages attendus ;
- les restrictions générales.

Elle ne doit jamais être interprétée comme une règle d’autorisation.

---

## Métadonnées de présentation

Les propriétés suivantes sont descriptives :

```text
Name
Description
DisplayColor
Icon
DisplayOrder
DocumentationUrl
```

Elles ne modifient pas les permissions effectives.

Leur évolution ultérieure relève de :

```text
UpdateRoleMetadata
```

---

## RoleType

Valeurs recommandées :

```text
System
Custom
External
TemplateDerived
```

---

## System

Un rôle `System` est créé ou gouverné par le produit.

Il peut avoir :

- des protections non modifiables ;
- un `SystemType` obligatoire ;
- une politique d’attribution minimale ;
- une politique de transfert minimale ;
- une capacité de récupération obligatoire ;
- un nom ou des métadonnées partiellement verrouillés.

---

## Custom

Un rôle `Custom` est défini dans le `Workspace`.

Il reste soumis :

- aux invariants du domaine ;
- au catalogue global des permissions ;
- aux politiques produit ;
- aux limites de licence ;
- aux règles de sécurité.

---

## External

Un rôle `External` est contrôlé totalement ou partiellement par une source externe.

Exemples :

```text
LDAP
SCIM
Identity Provider
Human Resources Directory
External Governance System
```

Sa création peut exiger :

```text
ExternalReference
ExternalVersion
```

---

## TemplateDerived

Un rôle `TemplateDerived` est créé à partir d’un modèle.

Sa création peut exiger :

```text
TemplateRoleId
TemplateVersion
```

Le modèle doit préciser :

- les champs hérités ;
- les champs surchargés ;
- les politiques verrouillées ;
- les permissions synchronisées ;
- la stratégie de mise à jour.

---

## SystemType

Valeurs recommandées :

```text
None
Owner
DefaultMember
Guest
ServiceAccount
```

`SystemType` représente la fonction structurelle du rôle.

Il est distinct de `RoleType`.

Exemple :

```text
RoleType = System
SystemType = Owner
```

---

## SystemType.None

Le rôle ne possède aucune fonction système particulière.

C’est la valeur recommandée pour la majorité des rôles custom.

---

## SystemType.Owner

Le rôle représente l’ownership du `Workspace`.

La reconnaissance d’un owner repose sur :

```text
SystemType = Owner
```

et non sur :

- le nom ;
- une permission particulière ;
- une couleur ;
- un ordre de présentation.

---

## Unicité du Owner Role

Recommandation :

```text
at most one Role
with SystemType = Owner
per Workspace
```

L’invariant du dernier owner concerne ensuite les memberships actifs affectés à ce rôle.

---

## SystemType.DefaultMember

Le rôle constitue le rôle par défaut du `Workspace`.

Recommandation :

```text
at most one Role
with SystemType = DefaultMember
per Workspace
```

---

## SystemType.Guest

Le rôle représente le comportement structurel des invités lorsque le produit utilise ce concept.

---

## SystemType.ServiceAccount

Le rôle est destiné aux identités techniques ou de service.

Sa politique doit normalement interdire les identités humaines si cette séparation est stricte.

---

## Immutabilité de SystemType

Après la création, `SystemType` ne doit normalement pas être modifiable.

Transformer :

```text
SystemType = None
```

en :

```text
SystemType = Owner
```

constituerait une mutation structurelle majeure et non une simple mise à jour.

La recommandation est de ne pas fournir de commande générique pour cette transformation.

---

## IsPrivileged

`IsPrivileged` classifie le rôle comme sensible.

Cette propriété peut déclencher :

- MFA obligatoire ;
- approbation renforcée ;
- audit renforcé ;
- notifications ;
- restrictions de transfert ;
- restrictions de création ;
- réévaluation des sessions.

Elle ne doit pas remplacer l’analyse réelle des permissions.

Un rôle peut devenir privilégié en raison des permissions qui lui sont ensuite accordées.

Le système peut donc calculer une classification effective complémentaire.

---

## AssignmentPolicy

`AssignmentPolicy` est obligatoire.

Type :

```text
RoleAssignmentPolicy
```

Elle répond à la question :

```text
Under which conditions may this Role
be assigned to a Membership?
```

Elle définit notamment :

- les sources d’attribution autorisées ;
- les identités éligibles ;
- les permissions nécessaires ;
- les exigences d’authentification ;
- les approbations ;
- les acceptations ;
- les limites d’affectation ;
- les exigences continues ;
- la récupération administrative.

Voir :

```text
value-objects/RoleAssignmentPolicy.md
```

---

## TransferPolicy

`TransferPolicy` est obligatoire.

Type :

```text
RoleTransferPolicy
```

Elle répond à la question :

```text
Under which conditions may this Role
be transferred from one Membership to another?
```

Elle définit notamment :

- si le rôle est transférable ;
- les sources autorisées ;
- les initiateurs autorisés ;
- les confirmations ;
- les acceptations ;
- les approbations ;
- les exigences d’authentification ;
- les contraintes du rôle de remplacement ;
- les exigences de continuité ;
- la récupération administrative.

---

## Compatibilité des politiques

`AssignmentPolicy` et `TransferPolicy` doivent être cohérentes.

Lorsque :

```text
TransferPolicy.Transferability = Transferable
```

alors :

```text
AssignmentPolicy.AllowedSources
contains RoleTransfer
```

Les types de cible autorisés doivent également avoir une intersection non vide :

```text
AssignmentPolicy.AllowedTargetIdentityTypes
INTERSECT
TransferPolicy.AllowedTargetIdentityTypes
is not empty
```

---

## Politique d’un rôle non transférable

Exemple :

```text
TransferPolicy.Transferability = NotTransferable
```

Dans ce cas, `AssignmentPolicy` peut ne pas inclure :

```text
RoleTransfer
```

Une récupération administrative peut néanmoins rester possible par un workflow distinct.

---

## Politique du Owner Role

Pour :

```text
SystemType = Owner
```

des contraintes minimales sont recommandées.

### AssignmentPolicy minimale

```text
RequiresHumanAssignee = true

AllowedTargetIdentityTypes
contains only human-compatible types

MinimumActiveAssignments >= 1

AdministrationRecoveryPolicy.RecoveryAllowed = true
```

Selon le niveau de sécurité du produit :

```text
ActorAuthenticationRequirement >= Mfa
TargetAuthenticationRequirement >= Mfa
```

### TransferPolicy minimale

```text
SourceReplacementRolePolicy.Required = true

ContinuityPolicy.NoTransientGapAllowed = true

ContinuityPolicy.MinimumActiveAssignmentsAfterTransfer >= 1

AllowedTargetIdentityTypes
contains only human-compatible types

AdministrationRecoveryPolicy.RecoveryAllowed = true
```

Le produit peut également imposer :

```text
SourceConfirmationPolicy.Required = true
TargetAcceptancePolicy.Required = true
```

---

## Politique du DefaultMember Role

Pour :

```text
SystemType = DefaultMember
```

la politique doit normalement permettre au moins un workflow standard :

```text
Invitation
ManualAdministration
WorkspaceCreation
```

selon le produit.

Le rôle ne doit pas devenir impossible à attribuer si le bootstrap ou les invitations en dépendent.

---

## Politique du ServiceAccount Role

Pour :

```text
SystemType = ServiceAccount
```

la politique peut imposer :

```text
AllowedTargetIdentityTypes =
{
  ServiceAccount,
  MachineIdentity
}
```

et :

```text
RequiresHumanAssignee = false
```

---

## Permissions initiales

Le rôle est créé sans permission par défaut :

```text
Permission assignments = empty
```

Cette décision garantit que la création du rôle et l’octroi de capacités restent deux intentions distinctes.

Les permissions sont ajoutées avec :

```text
GrantPermissionToRole
```

---

## Exception des rôles système bootstrap

Lors du bootstrap d’un `Workspace`, un orchestrateur peut exécuter :

```text
Create Owner Role
↓
Grant required Owner Permissions
↓
Create Owner Membership
```

Ces étapes peuvent appartenir à une transaction ou une saga de bootstrap, mais elles doivent rester conceptuellement distinctes.

---

## État initial

Un nouveau rôle commence dans l’état :

```text
Status = Active
```

Il peut ensuite évoluer via :

```text
DisableRole
EnableRole
ArchiveRole
```

---

## Pourquoi Active par défaut

La création exprime normalement l’intention de rendre le rôle utilisable.

Si un rôle doit être préparé sans être assignable, deux options existent :

- créer le rôle puis le désactiver explicitement ;
- autoriser un état initial `Disabled` uniquement pour certains workflows.

La recommandation par défaut reste :

```text
Active
```

---

## Version initiale

Le rôle commence avec :

```text
Role.Version = 1
AssignmentPolicy.Version = 1
TransferPolicy.Version = 1
```

La stratégie exacte de numérotation peut varier, mais elle doit rester cohérente.

---

## Sources de création

Valeurs recommandées pour `RoleCreationSource` :

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

## Motifs de création

Un `RoleCreationReason` facultatif peut contenir :

```text
OrganizationalNeed
SecurityResponsibility
BillingResponsibility
ProjectResponsibility
ExternalDirectoryProvisioning
TemplateApplication
WorkspaceBootstrap
AdministrativeCorrection
Migration
Other
```

Le motif peut devenir obligatoire pour les rôles privilégiés.

---

## Source de vérité

La création doit établir l’autorité des principales propriétés.

Exemple :

```text
Metadata -> LocallyManaged
AssignmentPolicy -> ProductManaged
TransferPolicy -> ProductManaged
Permissions -> ProductManaged
```

ou :

```text
Metadata -> ExternallyManaged
AssignmentPolicy -> ExternallyManaged
TransferPolicy -> LocallyManaged
Permissions -> ExternalSynchronization
```

La source de vérité peut être représentée par les `ControlPolicy` contenus dans les politiques et les métadonnées.

---

## Préconditions

Avant l’exécution, les conditions suivantes doivent être satisfaites :

- le `Workspace` existe ;
- le `Workspace` autorise la création de rôles ;
- `RoleId` n’est pas déjà utilisé ;
- le nom est valide ;
- le nom normalisé est disponible ;
- `RoleType` est valide ;
- `SystemType` est valide ;
- la combinaison `RoleType` et `SystemType` est autorisée ;
- les contraintes d’unicité des rôles système sont respectées ;
- `AssignmentPolicy` est valide ;
- `TransferPolicy` est valide ;
- les deux politiques sont compatibles ;
- les permissions référencées dans les politiques existent ;
- les contraintes du `SystemType` sont satisfaites ;
- la source de création est autorisée ;
- les références externes sont valides lorsqu’elles sont requises ;
- les références de modèle sont valides lorsqu’elles sont requises ;
- les limites de licence sont respectées ;
- l’acteur ou le workflow est autorisé ;
- la demande est idempotente ;
- aucune création concurrente incompatible n’a gagné.

---

## Traitement métier

### 1. Vérifier l’idempotence

Le système recherche une création déjà traitée avec :

```text
WorkspaceId + CreationRequestId
```

Si une demande identique a déjà réussi, le résultat initial est retourné.

---

### 2. Charger le Workspace

Le système charge le `Workspace` référencé.

Il vérifie :

- son existence ;
- son état ;
- sa capacité à accepter un nouveau rôle ;
- les limites applicables.

---

### 3. Charger le contexte de l’acteur

Pour un acteur humain :

- `User` ;
- `Membership` ;
- `Role` ;
- `Permission` effectives ;
- niveau d’authentification ;
- restrictions de gouvernance.

Pour un `SystemActor` :

- identité technique ;
- périmètre ;
- source ;
- autorité ;
- références externes ou de modèle.

---

### 4. Autoriser la commande

Le système vérifie :

```text
Actor may create a Role
```

puis :

```text
Actor may create this RoleType
```

et :

```text
Actor may create this SystemType
```

---

### 5. Vérifier RoleId

Le système vérifie que `RoleId` n’existe pas déjà.

---

### 6. Valider les métadonnées

Le système valide :

- `Name` ;
- `Description` ;
- `DisplayColor` ;
- `Icon` ;
- `DisplayOrder` ;
- `DocumentationUrl`.

---

### 7. Normaliser le nom

Le système calcule :

```text
NormalizedRoleName
```

---

### 8. Vérifier l’unicité du nom

Le système vérifie qu’aucun autre rôle du `Workspace` ne possède ce nom normalisé.

---

### 9. Valider RoleType

Le système vérifie que le type est supporté et compatible avec la source.

Exemples :

```text
RoleType = External
requires ExternalReference
```

```text
RoleType = TemplateDerived
requires TemplateRoleId and TemplateVersion
```

---

### 10. Valider SystemType

Le système vérifie :

- que le type système est supporté ;
- qu’il est compatible avec `RoleType` ;
- qu’il n’existe pas déjà lorsqu’il doit être unique ;
- que l’acteur peut le créer.

---

### 11. Valider AssignmentPolicy

Le système applique toutes les règles de :

```text
RoleAssignmentPolicy
```

Il vérifie notamment :

- les sources autorisées ;
- les identités éligibles ;
- les permissions référencées ;
- les exigences d’authentification ;
- les approbations ;
- les acceptations ;
- les capacités ;
- les exigences continues ;
- la récupération administrative.

---

### 12. Valider TransferPolicy

Le système applique toutes les règles de :

```text
RoleTransferPolicy
```

Il vérifie notamment :

- la transférabilité ;
- les sources ;
- les initiateurs ;
- la permission requise ;
- les confirmations ;
- les acceptations ;
- les approbations ;
- les cibles ;
- le remplacement de la source ;
- la continuité ;
- la récupération administrative.

---

### 13. Vérifier la compatibilité des politiques

Le système vérifie la composition :

```text
TransferPolicy
AND
AssignmentPolicy
```

Un rôle ne doit pas être déclaré transférable si son attribution par transfert est interdite.

---

### 14. Appliquer les contraintes du SystemType

Le système vérifie les protections minimales du rôle système.

Pour owner :

```text
human assignee required
minimum active assignments >= 1
no transient ownership gap
administrative recovery enabled
```

---

### 15. Vérifier les limites produit

Le système vérifie notamment :

- nombre maximal de rôles custom ;
- disponibilité des rôles privilégiés ;
- fonctionnalités autorisées par la licence ;
- restrictions liées au type de workspace.

---

### 16. Créer le Role

Le rôle est créé avec :

```text
RoleId
WorkspaceId
Name
NormalizedRoleName
Description
RoleType
SystemType
Status = Active
AssignmentPolicy
TransferPolicy
PermissionAssignments = empty
CreatedBy
CreatedAt
CreationSource
Version = 1
```

---

### 17. Produire RoleCreated

L’agrégat produit :

```text
RoleCreated
```

---

### 18. Enregistrer l’idempotence

La demande et son résultat sont enregistrés.

---

### 19. Commit atomique

Le rôle, l’événement et l’enregistrement d’idempotence sont persistés dans une même transaction.

---

## Résultat attendu

Après succès :

```text
Role
├── RoleId
├── WorkspaceId
├── Name
├── NormalizedRoleName
├── Description
├── RoleType
├── SystemType
├── Status: Active
├── AssignmentPolicy
├── TransferPolicy
├── Permission assignments: empty
├── Membership assignments: empty
├── CreatedBy
├── CreatedAt
└── Version: 1
```

---

## Invariants concernés

### Identité stable

```text
RoleId is unique and immutable
```

---

### Appartenance unique

```text
Role belongs to exactly one Workspace
```

---

### Unicité du nom

```text
UNIQUE(WorkspaceId, NormalizedRoleName)
```

---

### Permission globale

Les permissions référencées par les politiques doivent exister dans le catalogue global.

---

### Unicité du Owner Role

```text
at most one Role
with SystemType = Owner
per Workspace
```

---

### Unicité du DefaultMember Role

```text
at most one Role
with SystemType = DefaultMember
per Workspace
```

---

### Cohérence AssignmentPolicy

La politique d’attribution doit être valide et administrable.

---

### Cohérence TransferPolicy

La politique de transfert doit être valide et administrable.

---

### Compatibilité des politiques

```text
Transferable Role
requires AssignmentPolicy to allow RoleTransfer
```

---

### Owner continuity

La politique owner doit permettre de maintenir :

```text
at least one active Owner
```

---

## Événement produit

### RoleCreated

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `Name`
- `NormalizedRoleName`
- `Description`
- `RoleType`
- `SystemType`
- `Status`
- `AssignmentPolicy`
- `TransferPolicy`
- `IsPrivileged`
- `CreatedBy`
- `CreatedAt`
- `CreationSource`
- `CreationRequestId`
- `ExternalReference`
- `ExternalVersion`
- `TemplateRoleId`
- `TemplateVersion`
- `CorrelationId`
- `RoleVersion`

---

## Données interdites dans l’événement

L’événement ne doit pas contenir :

- de secrets ;
- de tokens ;
- d’informations d’authentification ;
- de commentaires confidentiels ;
- de données personnelles inutiles ;
- de clés d’API ;
- de justificatifs bruts.

---

## Événements non produits

La commande ne produit pas :

```text
RolePermissionGranted
RolePermissionRevoked
MembershipCreated
MembershipRoleChanged
RoleAssignmentPolicyChanged
RoleTransferPolicyChanged
RoleEnabled
RoleDisabled
RoleArchived
```

Les politiques font partie de l’état initial ; elles ne sont pas « modifiées » lors de la création.

---

## Idempotence

Clé recommandée :

```text
WorkspaceId + CreationRequestId
```

L’empreinte doit inclure au minimum :

```text
RoleId
WorkspaceId
NormalizedName
RoleType
SystemType
AssignmentPolicy
TransferPolicy
ExternalReference
ExternalVersion
TemplateRoleId
TemplateVersion
CreationSource
```

---

## Répétition identique

Une répétition strictement identique doit retourner le résultat initial sans :

- créer un second rôle ;
- produire un second événement ;
- modifier la date de création ;
- incrémenter une version ;
- consommer une seconde approbation ;
- répéter les notifications.

---

## Conflit d’idempotence

Si le même `CreationRequestId` est réutilisé avec une autre intention :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
CreateRole succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry doit retourner :

- le même `RoleId` ;
- la même date de création ;
- les mêmes politiques ;
- la même version ;
- le même événement logique.

---

## Concurrence

### Deux créations avec le même RoleId

Une seule création réussit.

---

### Deux créations avec le même nom

Une seule création réussit grâce à :

```text
UNIQUE(WorkspaceId, NormalizedRoleName)
```

---

### Deux créations du Owner Role

Une seule création réussit grâce à une contrainte sur :

```text
WorkspaceId + SystemType.Owner
```

---

### Deux créations du DefaultMember Role

Une seule création réussit.

---

### Création pendant la fermeture du Workspace

La transaction doit vérifier l’état courant du `Workspace`.

---

### Modification du catalogue de Permission

Une permission référencée par une politique peut être désactivée entre validation et commit.

La validation doit être transactionnellement cohérente avec la politique de catalogue.

---

## Atomicité

Le même commit doit contenir :

```text
Role creation
+
Role initial version
+
Idempotency record
+
RoleCreated event
```

Si des contraintes d’unicité ou de politique échouent, aucun élément ne doit être persisté.

---

## Outbox transactionnelle

L’événement `RoleCreated` doit être enregistré dans la même transaction que le rôle.

Sa publication externe intervient après commit.

---

## Effets externes

Après `RoleCreated`, des handlers peuvent :

- créer des projections ;
- mettre à jour l’administration ;
- indexer le rôle ;
- déclencher l’octroi de permissions système ;
- poursuivre le bootstrap d’un workspace ;
- synchroniser une source externe ;
- notifier certains administrateurs ;
- enregistrer des analytics.

Ces effets ne font pas partie de la commande elle-même.

---

## Bootstrap d’un Workspace

Workflow recommandé :

```text
Create Workspace
↓
Create Owner Role
↓
Grant Owner Permissions
↓
Create Default Member Role
↓
Grant Default Member Permissions
↓
Create Owner Membership
```

Le workflow doit éviter de rendre le `Workspace` disponible avant que les invariants minimaux soient satisfaits.

---

## Erreurs métier

### WorkspaceNotFound

Le `Workspace` n’existe pas.

---

### WorkspaceUnavailable

Le `Workspace` n’autorise pas la création.

---

### ActorNotAuthorized

L’acteur ne peut pas créer de rôle.

---

### RoleTypeCreationNotAuthorized

L’acteur ne peut pas créer ce type de rôle.

---

### SystemRoleCreationNotAuthorized

L’acteur ne peut pas créer ce rôle système.

---

### RoleIdAlreadyExists

`RoleId` est déjà utilisé.

---

### RoleNameRequired

Le nom est absent ou vide.

---

### RoleNameInvalid

Le nom ne respecte pas les règles.

---

### RoleNameTooLong

Le nom dépasse la longueur autorisée.

---

### RoleNameReserved

Le nom est réservé.

---

### RoleNameMisleading

Le nom contredit la sémantique structurelle.

---

### RoleNameAlreadyExists

Un rôle du `Workspace` possède déjà le même nom normalisé.

---

### InvalidRoleType

Le type de rôle est invalide.

---

### InvalidSystemType

Le type système est invalide.

---

### RoleTypeSystemTypeMismatch

La combinaison est interdite.

---

### OwnerRoleAlreadyExists

Un owner role existe déjà.

---

### DefaultMemberRoleAlreadyExists

Un default member role existe déjà.

---

### InvalidRoleAssignmentPolicy

La politique d’attribution est invalide.

---

### InvalidRoleTransferPolicy

La politique de transfert est invalide.

---

### RolePoliciesNotCompatible

Les deux politiques sont incompatibles.

---

### RequiredPermissionNotFound

Une permission référencée par une politique n’existe pas.

---

### RequiredPermissionUnavailable

Une permission référencée n’est pas utilisable.

---

### OwnerAssignmentPolicyInvalid

La politique owner ne respecte pas les protections minimales.

---

### OwnerTransferPolicyInvalid

La politique de transfert owner ne protège pas la continuité.

---

### OwnerRecoveryPathRequired

Aucune récupération administrative valide n’existe.

---

### ExternalReferenceRequired

Un rôle externe n’a pas de référence externe.

---

### ExternalVersionRequired

La version externe est obligatoire.

---

### TemplateReferenceRequired

Un rôle dérivé d’un modèle n’identifie pas son modèle.

---

### TemplateVersionRequired

La version du modèle est obligatoire.

---

### RoleLimitReached

La limite de rôles est atteinte.

---

### PrivilegedRoleNotAvailable

Le produit ou la licence n’autorise pas ce rôle privilégié.

---

### IdempotencyConflict

La clé idempotente correspond à une autre intention.

---

### RoleCreationConflict

Une création concurrente empêche l’opération.

---

## Audit

Une création réussie doit enregistrer :

- `RoleId`
- `WorkspaceId`
- `Name`
- `NormalizedRoleName`
- `Description`
- `RoleType`
- `SystemType`
- état initial
- politique d’attribution initiale
- politique de transfert initiale
- classification privilégiée
- permissions initiales
- memberships initiaux
- `CreatedBy`
- `CreatedAt`
- `CreationSource`
- `CreationReason`
- `ExternalReference`
- `ExternalVersion`
- `TemplateRoleId`
- `TemplateVersion`
- `CaseReference`
- `CreationRequestId`
- `CorrelationId`
- résultat final.

Les permissions initiales et memberships initiaux doivent normalement être :

```text
empty
```

---

## Questions auxquelles l’audit doit répondre

```text
which Role was created
in which Workspace
who created it
why it was created
which structural type it has
which assignment rules were established
which transfer rules were established
whether it is privileged
whether any Permission was granted during creation
whether any Membership was assigned during creation
```

Les deux dernières réponses doivent normalement être :

```text
no
```

---

## Sécurité

La commande doit garantir que :

- seul un acteur autorisé crée le rôle ;
- les rôles système sont protégés ;
- le nom n’est jamais utilisé comme identité ;
- les rôles privilégiés sont détectés ;
- les politiques de sécurité sont valides ;
- les permissions référencées existent ;
- la récupération administrative reste possible ;
- les contraintes owner sont satisfaites ;
- aucun droit n’est accordé implicitement ;
- aucun membership n’est modifié ;
- les retries ne créent pas de doublon ;
- l’événement est enregistré atomiquement.

---

## Confidentialité

Les métadonnées du rôle ne doivent pas contenir :

- de secret ;
- de token ;
- de mot de passe ;
- de donnée de santé ;
- de sanction individuelle ;
- de commentaire d’enquête ;
- de donnée personnelle non nécessaire ;
- d’URL signée contenant un accès privé.

Un rôle exprime une responsabilité durable, pas une situation nominative.

---

## Décisions de conception

### Le rôle appartient à un Workspace

Les rôles ne sont pas globaux.

---

### Les permissions sont globales

Le rôle référence des permissions du catalogue global.

---

### Le rôle est créé sans permission

L’octroi de capacités relève de commandes explicites.

---

### Le rôle est créé sans Membership

L’attribution relève de commandes explicites.

---

### AssignmentMode est supprimé

Il est remplacé par :

```text
RoleAssignmentPolicy.AllowedSources
```

---

### TransferMode est supprimé

Il est remplacé par :

```text
RoleTransferPolicy
```

---

### IsExclusive n’est pas stocké comme vérité indépendante

L’exclusivité est dérivée de :

```text
MaximumActiveAssignments = 1
```

---

### Les politiques sont obligatoires dès la création

Un rôle ne doit jamais exister sans règles d’attribution et de transfert explicites.

---

### Les politiques sont des Value Objects immuables

Toute modification future remplace la politique complète.

---

### Les politiques sont versionnées

Les décisions, approbations et acceptations peuvent référencer une version précise.

---

### SystemType définit la fonction structurelle

Le nom ne détermine jamais l’ownership ou la nature du rôle.

---

### Le rôle commence Active

Sauf décision produit explicite contraire.

---

### Un événement unique est produit

```text
RoleCreated
```

---

## Checklist de validation

Avant commit :

```text
Workspace exists
Workspace allows Role creation
Actor or SystemActor is authorized
RoleId is available
Name is valid
Normalized name is available
RoleType is valid
SystemType is valid
RoleType and SystemType are compatible
SystemType uniqueness is preserved
AssignmentPolicy is valid
TransferPolicy is valid
AssignmentPolicy and TransferPolicy are compatible
Referenced Permissions exist
SystemType-specific protections are satisfied
Owner recovery remains possible
External reference is valid when required
Template reference is valid when required
License limits are satisfied
Creation request is idempotent
No Permission is granted
No Membership is assigned
RoleCreated can be recorded atomically
```

---

## Synthèse

`CreateRole` crée la définition complète d’un rôle dans un workspace.

Elle garantit que :

- l’identité du rôle est stable ;
- le rôle appartient à un seul workspace ;
- son nom est valide et unique ;
- sa nature structurelle est explicite ;
- sa politique d’attribution est complète ;
- sa politique de transfert est complète ;
- les deux politiques sont compatibles ;
- les contraintes des rôles système sont respectées ;
- les voies de récupération restent possibles ;
- aucune permission n’est accordée implicitement ;
- aucun membership n’est affecté implicitement ;
- la commande est idempotente ;
- la création et l’événement sont atomiques.

Le résultat est :

```text
Role
├── stable identity
├── one Workspace
├── explicit metadata
├── explicit structural meaning
├── explicit AssignmentPolicy
├── explicit TransferPolicy
├── no Permission assignment
└── no Membership assignment
```