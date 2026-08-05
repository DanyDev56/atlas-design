---
id: IDN-CMD-REVOKE-PERMISSION-FROM-ROLE
title: RevokePermissionFromRole
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
  - GrantPermissionToRole.md
  - ChangeRoleAssignmentPolicy.md
  - ChangeRoleTransferPolicy.md
  - DisableRole.md
  - ArchiveRole.md
  - ../workflows.md
---

# RevokePermissionFromRole

## Objectif

La commande `RevokePermissionFromRole` retire une `Permission` explicitement accordée à un `Role`.

```text
Role.PermissionAssignments
-
PermissionId
```

La commande modifie l’ensemble des capacités explicites du rôle.

Elle ne doit pas :

- supprimer la `Permission` du catalogue global ;
- modifier la définition de la `Permission` ;
- supprimer le `Role` ;
- changer le rôle d’un `Membership` ;
- modifier directement un `Membership` ;
- révoquer directement une `Session` ;
- modifier automatiquement `RoleAssignmentPolicy` ;
- modifier automatiquement `RoleTransferPolicy` ;
- retirer implicitement d’autres permissions ;
- archiver ou désactiver le rôle ;
- retirer une permission directement à un `User`.

---

## Intention métier

La commande répond à l’intention suivante :

```text
remove one explicitly granted capability
from the Role
and therefore from its eligible holders
```

La révocation porte sur une affectation explicite :

```text
Role
→ Permission
```

Elle ne garantit pas que la permission cesse d’être effective.

Une permission peut rester disponible par implication depuis une autre permission.

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
- les permissions restantes du rôle ;
- le graphe des dépendances ;
- le graphe des implications ;
- les politiques produit ;
- les règles de gouvernance ;
- les sources externes ;
- les modèles ;
- les memberships actuellement affectés ;
- les sessions potentiellement concernées.

---

## Distinction fondamentale

Il faut distinguer :

```text
ExplicitPermissionSet
```

et :

```text
EffectivePermissionSet
```

L’ensemble effectif est calculé à partir des permissions explicites et du graphe d’implication.

```text
ExplicitPermissionSet
↓
PermissionImplicationGraph
↓
EffectivePermissionSet
```

`RevokePermissionFromRole` retire uniquement une permission de :

```text
ExplicitPermissionSet
```

---

## Exemple d’implication

```text
workspace.members.change-role
```

implique :

```text
workspace.members.read
```

Le rôle possède explicitement :

```text
workspace.members.change-role
workspace.members.read
```

La commande retire explicitement :

```text
workspace.members.read
```

Après révocation :

```text
ExplicitPermissionSet:
- workspace.members.change-role
```

mais :

```text
EffectivePermissionSet:
- workspace.members.change-role
- workspace.members.read
```

La permission retirée reste effective par implication.

---

## Conséquence métier

La commande doit distinguer :

```text
ExplicitPermissionRevoked
```

de :

```text
EffectivePermissionLost
```

Ces deux résultats ne sont pas équivalents.

---

## Résultat fonctionnel possible

Une révocation peut produire l’un des résultats suivants :

```text
ExplicitAndEffectivePermissionRemoved
```

```text
ExplicitPermissionRemovedButStillEffective
```

La commande doit calculer et exposer ce résultat dans l’événement et l’audit.

---

## Modèle conceptuel

Avant :

```text
Role
└── PermissionAssignments
    ├── Permission A
    ├── Permission B
    └── Permission C
```

Après révocation de `Permission B` :

```text
Role
└── PermissionAssignments
    ├── Permission A
    └── Permission C
```

L’ensemble effectif doit ensuite être recalculé.

---

## RolePermissionAssignment

La commande retire un élément du type :

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

La révocation doit conserver les informations historiques dans :

- l’événement ;
- l’audit ;
- une projection historique ;
- ou un journal dédié.

L’affectation active disparaît de l’état courant du rôle.

---

## Permission directe interdite

Le modèle conserve la règle :

```text
Permission only through Role
```

La commande ne modifie jamais directement :

```text
User → Permission
```

ou :

```text
Membership → Permission
```

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un administrateur de sécurité ;
- un administrateur de gouvernance ;
- un workflow de conformité ;
- un `SystemActor` ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un processus de migration ;
- un processus de récupération administrative ;
- un administrateur de plateforme dans un périmètre autorisé.

L’acteur doit être identifiable et auditable.

---

## Permission requise

Permission canonique :

```text
workspace.roles.revoke-permission
```

La sensibilité de la permission cible, le type de rôle et sa source de contrôle
sont évalués par des politiques contextuelles. Une approbation, une séparation
des responsabilités ou une réauthentification peut être exigée sans introduire
de clé de révocation alternative en 1.0.

---

## Permission dynamique de révocation

Une `Permission` peut définir :

```text
Permission.RequiredRevokePermission
```

La valeur doit référencer une permission active du catalogue. En 1.0, une
permission critique peut conserver `workspace.roles.revoke-permission` comme
autorité et ajouter une approbation ou une séparation des responsabilités.

La condition devient :

```text
Actor has workspace.roles.revoke-permission
AND Actor satisfies Permission.RequiredRevokePermission when distinct
```

---

## Self-demotion

La commande peut retirer une permission du rôle détenu par l’acteur.

```text
ActorMembership.RoleId = TargetRoleId
```

Cette opération constitue une réduction de privilèges, et non une élévation.

Elle peut néanmoins être dangereuse lorsqu’elle retire :

- la dernière capacité d’administration ;
- la seule capacité de récupération ;
- une permission indispensable au rôle owner ;
- une permission nécessaire pour gérer les rôles ;
- une permission nécessaire pour corriger l’opération.

---

## Politique recommandée pour self-demotion

Une self-demotion est autorisée lorsque :

- l’acteur est autorisé ;
- le rôle reste administrable ;
- aucune permission obligatoire n’est retirée ;
- aucune dépendance n’est violée ;
- la continuité administrative est préservée.

Une confirmation ou une réauthentification peut être exigée.

---

## Auto-verrouillage

Exemple :

```text
Actor holds Role A
Role A is the only Role with workspace.roles.change-assignment-policy
Actor revokes workspace.roles.change-assignment-policy from Role A
```

Le `Workspace` peut perdre toute capacité locale d’administration des rôles.

La commande doit détecter cette situation.

---

## Principe de continuité administrative

Après révocation, il doit rester au moins un chemin valide permettant d’administrer les rôles et permissions lorsque le produit l’exige.

Condition conceptuelle :

```text
At least one active authorized administration path remains
```

Cette règle peut être :

- un invariant produit ;
- une politique de la permission ;
- une règle du rôle owner ;
- une règle de récupération administrative.

---

## Sources de révocation

Valeurs recommandées pour `PermissionRevocationSource` :

```text
ManualAdministration
SecurityGovernance
ComplianceGovernance
SystemProvisioning
ExternalSynchronization
TemplateSynchronization
Migration
AdministrativeRecovery
ProductConfiguration
PermissionDeprecation
WorkspaceClosure
```

---

## ManualAdministration

Un acteur humain autorisé retire explicitement la permission.

---

## SecurityGovernance

La révocation résulte d’une décision de sécurité.

---

## ComplianceGovernance

La révocation résulte d’une obligation de conformité ou de séparation des devoirs.

---

## SystemProvisioning

Un workflow système ajuste les permissions du rôle.

---

## ExternalSynchronization

La source externe retire une permission qu’elle contrôle.

---

## TemplateSynchronization

Un modèle de rôle ne contient plus la permission.

---

## Migration

La permission est retirée dans le cadre d’une migration contrôlée.

---

## AdministrativeRecovery

La révocation corrige une configuration dangereuse ou invalide.

---

## ProductConfiguration

Le produit modifie les permissions d’un rôle système.

---

## PermissionDeprecation

La permission est retirée parce qu’elle est dépréciée ou remplacée.

---

## Motifs de révocation

Valeurs recommandées pour `PermissionRevocationReason` :

```text
ResponsibilityRemoved
OrganizationalChange
SecurityHardening
ComplianceRequirement
LeastPrivilegeEnforcement
SeparationOfDuties
FeatureDisabled
TemplateUpdate
ExternalDirectoryUpdate
AdministrativeCorrection
PermissionDeprecation
Migration
Recovery
WorkspaceClosure
Other
```

---

## Données d’entrée

### Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `RoleId` | `RoleId` | Rôle perdant la permission. |
| `PermissionId` | `PermissionId` | Permission explicitement retirée. |
| `RevokedBy` | `UserId` ou `SystemActor` | Acteur ou workflow responsable. |
| `RevokedAt` | Instant | Date métier de la révocation. |
| `RevocationReason` | `PermissionRevocationReason` | Motif de la révocation. |
| `RevocationSource` | `PermissionRevocationSource` | Origine de la révocation. |
| `RevocationRequestId` | Identifiant | Identifiant idempotent. |

### Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `ExpectedRoleVersion` | Version | Version attendue du rôle. |
| `ExpectedPermissionSetVersion` | Version | Version attendue de l’ensemble des permissions. |
| `ExpectedPermissionCatalogVersion` | Version | Version attendue du catalogue. |
| `ConfirmationId` | Identifiant | Confirmation d’une révocation sensible. |
| `ApprovalId` | Identifiant | Approbation requise. |
| `SecurityReviewId` | Identifiant | Revue de sécurité. |
| `ComplianceReviewId` | Identifiant | Revue de conformité. |
| `CaseReference` | Identifiant | Dossier administratif ou de sécurité. |
| `ExternalReference` | Identifiant | Référence externe. |
| `ExternalVersion` | Version | Version de la source externe. |
| `TemplateId` | Identifiant | Modèle ayant demandé la révocation. |
| `TemplateVersion` | Version | Version du modèle. |
| `RemediationPlanId` | Identifiant | Plan de remédiation éventuel. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques non métier. |

---

## Révocation immédiate

Dans la première version, la révocation est :

```text
immediate
```

Elle prend effet après commit.

La commande ne gère pas :

```text
EffectiveAt
```

ou :

```text
ScheduledRevocation
```

---

## Révocation différée

Un besoin ultérieur peut introduire :

```text
SchedulePermissionRevocation
```

Une révocation différée devra être entièrement réévaluée à son exécution.

---

## Permission globale

La permission doit exister ou être identifiable dans le catalogue global.

Une permission retirée ou dépréciée peut encore être référencée par une affectation historique.

La commande doit être capable de la révoquer même si son état n’est plus `Active`.

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

## Politique par état

```text
Active     -> revocation allowed
Deprecated -> revocation allowed
Disabled   -> revocation allowed
Removed    -> revocation allowed if assignment still exists
```

La révocation doit rester possible afin de nettoyer les références obsolètes.

---

## Permission introuvable

Si l’affectation existe encore mais que la définition de permission est introuvable, le domaine est incohérent.

La commande ordinaire échoue avec `PermissionDefinitionNotFound`. Un
`SystemActor` de récupération peut retirer l'affectation orpheline à partir du
`PermissionId` historique afin de restaurer `IDN-INV-004`.

---

## Recommandation

Autoriser uniquement via :

```text
AdministrativeRecovery
Migration
PermissionDeprecation
```

avec audit renforcé.

---

## Permission explicitement accordée

Précondition principale :

```text
Role.PermissionAssignments
contains PermissionId
```

Si l’affectation explicite n’existe pas :

```text
PermissionNotGrantedToRole
```

Même si la permission est effective par implication, elle ne peut pas être révoquée explicitement depuis ce rôle sans retirer la permission qui l’implique.

---

## Permission effective mais non explicite

Exemple :

```text
workspace.members.change-role
implies
workspace.members.read
```

Le rôle ne possède explicitement que :

```text
workspace.members.change-role
```

Une demande de révocation de :

```text
workspace.members.read
```

doit échouer avec :

```text
PermissionNotExplicitlyGrantedToRole
```

La commande ne doit pas modifier le graphe d’implication.

---

## Permission obligatoire

Une permission peut être obligatoire en raison de :

- `RoleSystemType` ;
- `RoleType` ;
- politique produit ;
- modèle ;
- source externe ;
- dépendance d’une autre permission ;
- invariant de récupération ;
- licence ou fonctionnalité active.

---

## MandatoryPermissionPolicy

Structure conceptuelle :

```text
MandatoryPermissionPolicy
├── RequiredForRoleTypes
├── RequiredForSystemTypes
├── RequiredByProduct
├── RequiredByTemplate
├── RequiredByExternalSource
├── RequiredForRecovery
└── RevocationAuthority
```

---

## Permission obligatoire pour Owner

Certaines permissions peuvent être obligatoires pour :

```text
SystemType = Owner
```

Exemples conceptuels :

```text
workspace.members.change-role
workspace.roles.change-assignment-policy
workspace.members.transfer-role
```

La liste exacte appartient à la politique produit.

La commande doit refuser toute révocation qui rendrait le rôle owner structurellement invalide.

---

## Permission obligatoire pour DefaultMember

Le rôle par défaut peut nécessiter certaines permissions minimales.

Exemple :

```text
workspace.profile.read
```

La révocation peut être interdite si elle rend les nouveaux memberships inutilisables.

---

## Permission obligatoire pour ServiceAccount

Une permission peut être nécessaire à la fonction technique du rôle.

Toutefois, une permission techniquement utile n’est pas automatiquement un invariant métier.

Cette distinction doit être explicite.

---

## Permission contrôlée par le produit

Lorsque :

```text
PermissionAssignment.Control = ProductManaged
```

une révocation locale est interdite.

---

## Permission contrôlée par une source externe

Lorsque :

```text
PermissionAssignment.Control = ExternallyManaged
```

la révocation locale est interdite, sauf override explicitement autorisé.

---

## Permission contrôlée par un modèle

Lorsque :

```text
PermissionAssignment.Control = TemplateManaged
```

la révocation locale peut être :

- interdite ;
- enregistrée comme override ;
- autorisée jusqu’à la prochaine synchronisation ;
- convertie en exclusion locale.

Le comportement doit être explicite.

---

## Override local

Un modèle dérivé peut gérer une exclusion :

```text
Template grants Permission
Local override excludes Permission
```

Dans ce cas, la commande ne supprime pas nécessairement l’origine template.

Elle peut créer une règle d’exclusion distincte.

Cette complexité doit être séparée du modèle simple si elle n’est pas encore requise.

---

## Recommandation initiale

Dans la première version :

```text
ExternallyManaged assignment -> local revocation forbidden
TemplateManaged assignment -> local revocation forbidden
ProductManaged assignment -> local revocation forbidden
LocallyManaged assignment -> local revocation allowed
```

---

## Dépendances inverses

Une permission peut être requise par une autre permission explicitement
présente. Identity 1.0 n'en déclare aucune dans son propre catalogue, mais la
commande traite ce graphe pour les permissions enregistrées par les autres
domaines et les évolutions futures.

Lorsqu'une telle relation est déclarée, la permission requise ne peut pas être
révoquée tant que la permission dépendante reste explicitement affectée.

---

## RequiredByPermissions

La commande doit calculer :

```text
PermissionsDependingOnRevokedPermission
```

Condition :

```text
for each RemainingExplicitPermission
its RequiredPermissions remain satisfied
```

---

## Stratégies possibles

### Refus strict

La révocation échoue si une autre permission dépend de celle-ci.

### Cascade

La commande retire également les permissions dépendantes.

### Dégradation contrôlée

La permission dépendante reste présente mais devient inactive.

---

## Décision recommandée

Utiliser le refus strict.

```text
RevokePermissionFromRole
removes exactly one explicit Permission
```

En cas de dépendances :

```text
PermissionRequiredByAnotherPermission
```

Le demandeur doit d’abord révoquer les permissions dépendantes.

---

## Ordre de révocation

Exemple :

```text
Permission A requires Permission B
```

Ordre valide :

```text
Revoke A
↓
Revoke B
```

Ordre invalide :

```text
Revoke B
while A remains
```

---

## Dépendance satisfaite par implication

Une permission requise peut rester effective par implication.

Exemple :

```text
Permission A requires B
Permission C implies B
```

Si B est révoquée explicitement, mais reste effective via C, la dépendance peut rester satisfaite.

Le modèle doit préciser si une dépendance exige :

```text
explicit grant
```

ou :

```text
effective permission
```

---

## Recommandation

Par défaut :

```text
RequiredPermissions are satisfied by EffectivePermissionSet
```

Une dépendance nécessitant une affectation explicite doit le déclarer.

---

## Implications inverses

La commande doit calculer quelles permissions sont impliquées par la permission révoquée.

Exemple :

```text
A implies B and C
```

Après retrait de A :

- B peut disparaître ;
- C peut disparaître ;
- B ou C peuvent rester effectives via d’autres chemins.

---

## Recalcul complet

La commande doit recalculer :

```text
EffectivePermissionSetBefore
```

et :

```text
EffectivePermissionSetAfter
```

Le delta effectif est :

```text
LostEffectivePermissions
=
EffectivePermissionSetBefore
-
EffectivePermissionSetAfter
```

---

## Permission retirée mais encore effective

Condition :

```text
PermissionId not in ExplicitPermissionSetAfter
AND
PermissionId in EffectivePermissionSetAfter
```

Résultat :

```text
ExplicitPermissionRemovedButStillEffective
```

---

## Permissions indirectement perdues

La révocation d’une permission peut entraîner la perte effective de plusieurs permissions impliquées.

Exemple :

```text
A implies B
A implies C
```

Révoquer A peut produire :

```text
LostEffectivePermissions = {A, B, C}
```

L’événement doit pouvoir exposer ce delta.

---

## Pas de révocations implicites

Même si plusieurs permissions cessent d’être effectives, une seule affectation explicite est retirée.

La commande ne doit pas émettre plusieurs événements :

```text
RolePermissionRevoked
```

pour chaque permission impliquée.

L’événement principal peut contenir :

```text
LostEffectivePermissionIds
```

---

## Incompatibilités

La révocation supprime généralement une incompatibilité.

Elle ne devrait pas en créer directement.

Cependant, certaines politiques contextuelles peuvent exiger une combinaison minimale.

Exemple :

```text
Permission A requires either B or C
```

La révocation de B pourrait rendre A invalide si C est absente.

Cette situation relève des dépendances.

---

## Ensemble effectif après révocation

Calcul recommandé :

```text
ExplicitPermissionsAfterRevocation
=
CurrentExplicitPermissions
-
PermissionId
```

Puis :

```text
EffectivePermissionsAfterRevocation
=
closure(
  ExplicitPermissionsAfterRevocation,
  PermissionImplicationGraph
)
```

Puis vérifier :

```text
all remaining explicit Permissions
have their dependencies satisfied
```

---

## Sensibilité du rôle

La révocation peut réduire la sensibilité du rôle.

Exemples :

```text
Critical
↓
Privileged
```

```text
Privileged
↓
Elevated
```

```text
Elevated
↓
Standard
```

---

## RoleCapabilityProfile

Le système peut recalculer :

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

---

## Pas de relâchement automatique des politiques

Une baisse de sensibilité ne modifie pas automatiquement :

```text
RoleAssignmentPolicy
```

ou :

```text
RoleTransferPolicy
```

Exemple :

```text
Role becomes Standard
but still requires Mfa for assignment
```

Cette configuration reste valide.

---

## Principe de non-relâchement

```text
Security requirements may remain stricter
than the minimum required by current Permissions
```

La simplification des politiques nécessite des commandes explicites :

```text
ChangeRoleAssignmentPolicy
ChangeRoleTransferPolicy
```

---

## Rôle actif

Politique recommandée :

```text
Active   -> revocation allowed
Disabled -> revocation allowed
Archived -> revocation forbidden
Removed  -> revocation forbidden
```

---

## Rôle désactivé

Une permission peut être retirée d’un rôle désactivé afin de préparer une future réactivation.

---

## Rôle archivé

Un rôle archivé est immuable.

La révocation est interdite.

---

## Rôle externe

Pour :

```text
RoleType = External
```

la commande doit vérifier :

- la source de vérité ;
- la propriété de l’affectation ;
- l’autorité du demandeur ;
- la version externe ;
- le comportement du prochain cycle de synchronisation.

---

## Rôle dérivé d’un modèle

Pour :

```text
RoleType = TemplateDerived
```

la commande doit vérifier :

- si la permission est héritée ;
- si les overrides sont autorisés ;
- si la révocation crée une exclusion locale ;
- si la prochaine synchronisation rétablira la permission.

---

## Rôle système

Un rôle système peut imposer :

- des permissions minimales ;
- une source product-managed ;
- une autorité spécifique ;
- une procédure de récupération ;
- une validation de continuité.

---

## Owner Role

La révocation d’une permission au rôle owner affecte tous les owners actifs.

La commande doit vérifier :

- les permissions owner obligatoires ;
- la continuité administrative ;
- la capacité de transfert ;
- la capacité à gérer les memberships ;
- la récupération administrative ;
- la capacité à corriger une configuration erronée.

---

## DefaultMember Role

La révocation affecte :

- les membres actuels utilisant le rôle ;
- les futurs membres ;
- les invitations en attente ;
- les workflows de bootstrap.

L’impact doit être calculé explicitement.

---

## ServiceAccount Role

La révocation peut interrompre une intégration ou un service.

Une confirmation ou un contrôle opérationnel peut être requis pour les permissions critiques.

---

## Détenteurs actuels

La commande doit évaluer les memberships utilisant le rôle.

Structure possible :

```text
PermissionRevocationImpact
├── ActiveMembershipCount
├── SuspendedMembershipCount
├── ActiveSessionCount
├── ExplicitPermissionRemoved
├── EffectivePermissionStillPresent
├── LostEffectivePermissions
├── RoleSensitivityBefore
├── RoleSensitivityAfter
├── AdministrationPathPreserved
└── OperationalRisk
```

---

## Impact sur les Memberships

La commande ne modifie aucun `Membership`.

Elle ne change pas :

- leur statut ;
- leur rôle ;
- leur workspace ;
- leur identité ;
- leur historique.

Elle réduit potentiellement leurs permissions effectives.

---

## Impact sur les Sessions

La perte d’une permission doit être propagée rapidement.

Une session ne doit pas continuer à utiliser durablement une permission révoquée en raison d’un cache obsolète.

---

## PermissionSetVersion

Recommandation :

```text
Role.PermissionSetVersion += 1
```

Le moteur d’autorisation doit considérer toute décision utilisant une ancienne version comme obsolète.

---

## Claims statiques

Lorsque des permissions sont intégrées dans des tokens ou claims :

- le token peut être marqué obsolète ;
- un refresh peut être imposé ;
- une réémission peut être déclenchée ;
- l’autorisation critique doit vérifier la source de vérité courante.

---

## Révocation de session

La commande ne révoque pas automatiquement les sessions.

Une politique de sécurité peut néanmoins déclencher :

```text
SessionRevocationRequested
```

notamment lorsque :

- une permission critique est retirée ;
- un incident de sécurité est en cours ;
- les claims ne peuvent pas être invalidés rapidement ;
- l’usage récent de la permission présente un risque.

---

## Recommandation

Pour les autorisations sensibles :

```text
current authorization decision
must depend on current PermissionSetVersion
```

La révocation de session reste un effet secondaire exceptionnel.

---

## Préconditions

Avant exécution :

- le rôle existe ;
- le workspace existe ;
- le rôle appartient au workspace ;
- le rôle n'est pas archivé ;
- la permission est explicitement affectée ;
- l’acteur ou le workflow est autorisé ;
- la source de révocation contrôle l’affectation ;
- la permission n’est pas obligatoire ;
- la permission n’est pas verrouillée par le produit ;
- la permission n’est pas verrouillée par un modèle ;
- la permission n’est pas verrouillée par une source externe ;
- aucune permission restante ne dépend invalidement de celle-ci ;
- les permissions effectives après révocation sont calculables ;
- les rôles système restent valides ;
- la continuité administrative est préservée ;
- les règles de self-demotion sont satisfaites ;
- les approbations ou confirmations requises sont valides ;
- la version du rôle correspond ;
- la version du permission set correspond ;
- la version du catalogue correspond lorsque nécessaire ;
- la demande est idempotente ;
- aucune modification concurrente incompatible n’a gagné.

---

## Traitement métier

### 1. Vérifier l’idempotence

Le système recherche une demande déjà traitée avec :

```text
RoleId + RevocationRequestId
```

Une répétition identique retourne le résultat initial.

---

### 2. Charger le Role

Le rôle est chargé avec :

- son workspace ;
- son statut ;
- son type ;
- son `SystemType` ;
- ses permissions explicites ;
- ses politiques ;
- ses versions ;
- ses sources de contrôle.

---

### 3. Vérifier l’état du Role

```text
Active   -> allowed
Disabled -> allowed
Archived -> forbidden
Removed  -> forbidden
```

---

### 4. Charger la Permission

Le système charge la permission globale lorsque sa définition existe.

---

### 5. Charger RolePermissionAssignment

Le système retrouve l’affectation explicite correspondant à :

```text
PermissionId
```

---

### 6. Vérifier l’existence de l’affectation

Si aucune affectation explicite n’existe :

```text
PermissionNotGrantedToRole
```

---

### 7. Charger le contexte de l’acteur

Pour un acteur humain :

- `User` ;
- `Membership` ;
- rôle courant ;
- permissions effectives ;
- niveau d’authentification ;
- relation avec le rôle cible ;
- bénéfice ou perte personnelle ;
- capacité d’administration restante.

Pour un `SystemActor` :

- identité technique ;
- périmètre ;
- autorité ;
- source ;
- référence externe ou de modèle.

---

### 8. Autoriser la commande

Le système vérifie :

```text
Actor may revoke Permissions from Roles
```

puis :

```text
Actor may revoke this Permission
```

et :

```text
Actor may modify this Role
```

---

### 9. Vérifier l’autorité de la source

Le système vérifie que `RevocationSource` contrôle l’affectation ou possède un droit d’override.

---

### 10. Vérifier le contrôle de l’affectation

L’affectation peut être :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

Le comportement applicable est vérifié.

---

### 11. Vérifier les permissions obligatoires

Le système vérifie si la permission est requise par :

- le rôle système ;
- le produit ;
- le modèle ;
- une source externe ;
- la récupération administrative ;
- une fonctionnalité active.

---

### 12. Détecter la self-demotion

Le système détermine si l’acteur détient le rôle cible.

---

### 13. Calculer ExplicitPermissionSetBefore

```text
ExplicitPermissionSetBefore
=
Role.PermissionAssignments.PermissionIds
```

---

### 14. Calculer EffectivePermissionSetBefore

```text
EffectivePermissionSetBefore
=
closure(
  ExplicitPermissionSetBefore,
  PermissionImplicationGraph
)
```

---

### 15. Construire ExplicitPermissionSetAfter

```text
ExplicitPermissionSetAfter
=
ExplicitPermissionSetBefore
-
PermissionId
```

---

### 16. Calculer EffectivePermissionSetAfter

```text
EffectivePermissionSetAfter
=
closure(
  ExplicitPermissionSetAfter,
  PermissionImplicationGraph
)
```

---

### 17. Calculer le delta effectif

```text
LostEffectivePermissions
=
EffectivePermissionSetBefore
-
EffectivePermissionSetAfter
```

---

### 18. Déterminer si la permission reste effective

```text
RevokedPermissionStillEffective
=
PermissionId in EffectivePermissionSetAfter
```

---

### 19. Vérifier les dépendances restantes

Pour chaque permission explicite restante :

```text
RequiredPermissions
subset of
EffectivePermissionSetAfter
```

sauf règle imposant une affectation explicite.

---

### 20. Refuser les dépendances cassées

Si une permission restante dépend de la permission révoquée :

```text
PermissionRequiredByAnotherPermission
```

---

### 21. Recalculer RoleCapabilityProfile

Le système calcule :

```text
RoleSensitivityBefore
RoleSensitivityAfter
```

---

### 22. Vérifier les invariants du rôle système

Le rôle doit conserver ses capacités structurelles obligatoires.

---

### 23. Vérifier la continuité administrative

Le système vérifie qu’au moins un chemin d’administration valide reste disponible.

---

### 24. Analyser les détenteurs actuels

Le système calcule l’impact sur les memberships et sessions.

---

### 25. Vérifier les risques opérationnels

Une permission peut être nécessaire à une intégration ou un workflow critique.

Une confirmation ou un dossier de changement peut être requis.

---

### 26. Vérifier les approbations et revues

Le système valide :

- confirmation ;
- approbation ;
- revue de sécurité ;
- revue de conformité ;
- séparation des devoirs ;
- portée exacte ;
- validité temporelle.

---

### 27. Vérifier ExpectedRoleVersion

```text
Role.Version = ExpectedRoleVersion
```

---

### 28. Vérifier ExpectedPermissionSetVersion

```text
Role.PermissionSetVersion
=
ExpectedPermissionSetVersion
```

---

### 29. Vérifier ExpectedPermissionCatalogVersion

Lorsque fournie :

```text
PermissionCatalog.Version
=
ExpectedPermissionCatalogVersion
```

---

### 30. Retirer RolePermissionAssignment

```text
Role.PermissionAssignments.remove(PermissionId)
```

---

### 31. Incrémenter PermissionSetVersion

```text
Role.PermissionSetVersion += 1
```

---

### 32. Incrémenter Role.Version

```text
Role.Version += 1
```

---

### 33. Produire RolePermissionRevoked

L’agrégat produit :

```text
RolePermissionRevoked
```

---

### 34. Enregistrer l’idempotence

La demande et son résultat sont enregistrés.

---

### 35. Commit atomique

La révocation, les versions, l’idempotence et l’événement sont persistés ensemble.

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
│   └── no longer contains PermissionId
├── PermissionSetVersion: incremented
└── Role.Version: incremented
```

Aucun membership n’est directement modifié.

---

## Résultat de révocation

Structure recommandée :

```text
PermissionRevocationResult
├── ExplicitPermissionRemoved
├── RevokedPermissionStillEffective
├── LostEffectivePermissionIds
├── RoleSensitivityBefore
├── RoleSensitivityAfter
├── AffectedActiveMembershipCount
├── AffectedActiveSessionCount
└── AdministrationPathPreserved
```

---

## Invariants concernés

### Permission uniquement via Role

```text
Membership receives Permission only through Role
```

---

### Identité stable

```text
RoleId remains unchanged
```

---

### Appartenance stable

```text
Role remains in the same Workspace
```

---

### Unicité des affectations

Le retrait ne doit supprimer qu’une seule affectation explicite.

---

### Dépendances valides

```text
all remaining explicit Permissions
have their dependencies satisfied
```

---

### Permissions système obligatoires

Les rôles système conservent leurs permissions minimales.

---

### Continuité administrative

```text
at least one valid administration path remains
```

lorsque cet invariant est requis.

---

### Owner continuity

La révocation ne doit pas rendre le rôle owner incapable de préserver ou transférer l’ownership.

---

### Version d’autorisation

```text
PermissionSetVersion changes after revocation
```

---

## Événement produit

### RolePermissionRevoked

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `PermissionId`
- `PermissionKeySnapshot`
- `PermissionSensitivity`
- `RoleType`
- `RoleSystemType`
- `ExplicitPermissionRemoved`
- `RevokedPermissionStillEffective`
- `LostEffectivePermissionIds`
- `RoleSensitivityBefore`
- `RoleSensitivityAfter`
- `AffectedActiveMembershipCount`
- `AffectedActiveSessionCount`
- `AdministrationPathPreserved`
- `RevokedBy`
- `RevokedAt`
- `RevocationReason`
- `RevocationSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `RevocationRequestId`
- `CorrelationId`
- `PermissionSetVersion`
- `RoleVersion`

---

## PermissionKeySnapshot

L’événement peut contenir :

```text
PermissionId
+
PermissionKeySnapshot
```

L’identité reste `PermissionId`.

La clé améliore :

- l’audit ;
- la lisibilité ;
- les projections ;
- le diagnostic.

---

## LostEffectivePermissionIds

Cette collection contient les permissions devenues réellement indisponibles après recalcul.

Elle peut inclure :

- la permission explicitement révoquée ;
- des permissions qu’elle impliquait ;
- des capacités dérivées.

Elle ne signifie pas que plusieurs affectations explicites ont été retirées.

---

## Signaux secondaires possibles

```text
RoleEffectivePermissionLost
RoleNoLongerPrivileged
RoleNoLongerCritical
RoleAdministrationContinuityAtRisk
RoleAuthorizationCacheInvalidationRequested
RoleSessionsReevaluationRequested
```

Ils sont enregistrés uniquement si leur condition est satisfaite et ne font pas
partie des Domain Events 1.0.

---

## Événements non produits

La commande ne produit pas :

```text
PermissionRemoved
MembershipRoleChanged
RoleAssignmentPolicyChanged
RoleTransferPolicyChanged
RoleMetadataUpdated
RoleDisabled
RoleArchived
```

---

## Effet sur les Memberships

Les memberships ne sont pas modifiés.

Leurs permissions effectives peuvent diminuer.

```text
Membership effective permissions
=
current effective permissions of its Role
```

---

## Effet sur les Sessions

La commande doit rendre les décisions d’autorisation obsolètes.

Recommandation :

```text
Role.PermissionSetVersion += 1
```

Les caches et sessions doivent intégrer cette version dans leur validation.

---

## Invalidation des caches

Après commit, un handler peut produire ou traiter :

```text
RoleAuthorizationCacheInvalidationRequested
```

L’invalidation doit être idempotente.

---

## Révocation urgente

Une révocation liée à un incident peut exiger :

```text
ImmediateSessionReevaluation
```

ou :

```text
TargetedSessionRevocation
```

Cette décision dépend de la sensibilité de la permission et du moteur de session.

---

## Idempotence

Clé recommandée :

```text
RoleId + RevocationRequestId
```

---

## Empreinte idempotente

L’empreinte inclut au minimum :

```text
RoleId
PermissionId
RevokedBy
RevokedAt
RevocationReason
RevocationSource
ConfirmationId
ApprovalId
SecurityReviewId
ComplianceReviewId
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
```

---

## Répétition identique

Une répétition exacte retourne le résultat initial sans :

- retirer une seconde fois ;
- produire un second événement ;
- incrémenter une nouvelle version ;
- modifier `RevokedAt` ;
- répéter les invalidations ;
- répéter les notifications ;
- consommer une nouvelle approbation.

---

## Permission déjà absente avec une autre demande

Si la permission n’est plus explicitement présente et que la demande utilise un autre identifiant :

```text
PermissionNotGrantedToRole
```

Le système ne doit pas inventer une nouvelle révocation historique.

---

## Conflit d’idempotence

Le même `RevocationRequestId` utilisé avec une autre intention produit :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
RevokePermissionFromRole succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry retourne :

- la même révocation ;
- le même résultat effectif ;
- le même `RevokedAt` ;
- les mêmes versions ;
- le même événement logique.

---

## Concurrence

### Deux révocations concurrentes de la même Permission

Une seule réussit.

La seconde rencontre :

```text
RoleVersionConflict
```

ou :

```text
PermissionNotGrantedToRole
```

après rechargement.

---

### Grant contre Revoke

Une commande accorde pendant qu’une autre révoque.

L’ordre de commit détermine l’état final.

Chaque opération doit utiliser :

```text
ExpectedRoleVersion
```

et :

```text
ExpectedPermissionSetVersion
```

---

### Deux révocations de permissions dépendantes

Exemple :

```text
A requires B
```

Deux commandes tentent de révoquer A et B.

Selon l’ordre :

- révoquer A puis B est valide ;
- révoquer B avant A est invalide.

La commande en conflit doit recharger et réévaluer.

---

### Révocation contre changement du catalogue

La définition de la permission ou ses dépendances peuvent changer entre lecture et commit.

Une version du catalogue protège cette situation.

---

### Révocation contre changement de politique

Une autre commande peut modifier les politiques du rôle.

La révocation ne les modifie pas, mais la continuité administrative doit être évaluée sur une version cohérente.

---

### Révocation contre ArchiveRole

Si l’archivage gagne d’abord, la révocation échoue.

---

### Révocation contre DisableRole

Les deux opérations peuvent être compatibles.

La version du rôle détermine l’ordre.

---

### Révocation concurrente de permissions administratives

Deux commandes peuvent chacune préserver un chemin administratif isolément, mais supprimer ensemble tous les chemins.

La continuité doit être protégée transactionnellement au niveau approprié.

---

## Invariant multi-rôles

La continuité administrative peut dépendre de plusieurs rôles.

Exemple :

```text
Role A has workspace.roles.change-assignment-policy
Role B has workspace.roles.change-assignment-policy
```

Deux révocations concurrentes peuvent retirer la permission des deux rôles.

Cette règle dépasse un seul agrégat `Role`.

---

## Coordination recommandée

Pour les permissions critiques :

- verrou de gouvernance du workspace ;
- compteur transactionnel ;
- agrégat de gouvernance ;
- coordination sérielle ;
- contrainte persistante spécialisée.

---

## Version spécialisée

Le rôle possède :

```text
Role.Version
```

et :

```text
PermissionSetVersion
```

`Role.Version` protège l’ensemble de l’agrégat.

`PermissionSetVersion` permet de suivre spécifiquement les changements d’autorisation.

---

## Atomicité

Le même commit contient :

```text
RolePermissionAssignment removal
+
PermissionSetVersion increment
+
Role.Version increment
+
Idempotency record
+
RolePermissionRevoked event
```

---

## États interdits

```text
Permission assignment removed
AND
RolePermissionRevoked missing
```

```text
RolePermissionRevoked persisted
AND
Permission assignment still present
```

```text
PermissionSetVersion unchanged
after explicit revocation
```

```text
remaining Permission dependency is broken
```

```text
mandatory system Permission removed
```

```text
administration path destroyed
without authorized recovery workflow
```

```text
ProductManaged assignment removed locally
```

```text
TemplateManaged assignment removed without allowed override
```

```text
ExternallyManaged assignment removed without authority
```

---

## Outbox transactionnelle

L’événement doit être enregistré dans la même transaction que le rôle.

Sa publication intervient après commit.

---

## Effets externes

Après succès, des handlers peuvent :

- invalider les caches d’autorisation ;
- recalculer les permissions effectives ;
- réindexer le rôle ;
- actualiser les projections ;
- notifier les détenteurs ;
- notifier les owners ;
- notifier la sécurité ;
- actualiser les claims ;
- demander une réévaluation des sessions ;
- poursuivre une migration ;
- synchroniser une source externe ;
- mettre à jour l’audit ;
- désactiver une fonctionnalité dépendante.

---

## Notifications

Une notification est recommandée lorsque :

- la permission était privilégiée ou critique ;
- la permission devient réellement indisponible ;
- le rôle est `Owner` ;
- le rôle est `DefaultMember` ;
- le rôle est détenu par de nombreux membres ;
- la permission concerne des données sensibles ;
- une intégration peut cesser de fonctionner ;
- une self-demotion a eu lieu ;
- une révocation urgente de sécurité a été exécutée.

---

## Notification des détenteurs

Les détenteurs peuvent être informés de la perte d’une capacité.

Le message doit distinguer :

```text
explicit assignment removed
```

et :

```text
effective access removed
```

lorsque cela est pertinent.

---

## Audit

Une révocation réussie doit enregistrer :

- `RoleId`
- `WorkspaceId`
- `RoleType`
- `RoleSystemType`
- `PermissionId`
- `PermissionKeySnapshot`
- affectation retirée
- contrôle de l’affectation
- ensemble explicite précédent
- ensemble explicite final
- ensemble effectif précédent
- ensemble effectif final
- permission encore effective ou non
- permissions effectivement perdues
- dépendances vérifiées
- permissions dépendantes détectées
- classification du rôle avant
- classification du rôle après
- nombre de détenteurs actifs concernés
- nombre de sessions actives concernées
- self-demotion éventuelle
- continuité administrative
- `RevokedBy`
- `RevokedAt`
- `RevocationReason`
- `RevocationSource`
- `ConfirmationId`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `CaseReference`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `RevocationRequestId`
- `CorrelationId`
- version précédente
- version finale
- `PermissionSetVersion`
- résultat final.

---

## Questions auxquelles l’audit doit répondre

```text
which explicit Permission was revoked
from which Role
inside which Workspace
who authorized the revocation
why the revocation occurred
which source controlled the assignment
whether the Permission remained effective
which effective Permissions were lost
which active Memberships were affected
whether the actor lost the Permission personally
whether administrative continuity remained possible
whether any dependency would have been broken
which approvals or reviews were used
which authorization version became effective
```

---

## Sécurité

La commande doit garantir que :

- seule une autorité valide retire la permission ;
- l’affectation explicite existe ;
- les sources de vérité sont respectées ;
- les permissions obligatoires restent présentes ;
- les dépendances ne sont pas cassées ;
- les rôles système restent valides ;
- la continuité administrative est préservée ;
- la self-demotion est contrôlée ;
- les permissions effectives sont recalculées ;
- la perte d’accès est propagée rapidement ;
- les caches obsolètes sont invalidables ;
- la concurrence ne crée pas d’état incohérent ;
- la révocation et l’événement sont atomiques ;
- les retries ne dupliquent aucun effet.

---

## Confidentialité

La commande et l’événement ne doivent pas contenir :

- de secrets ;
- de tokens ;
- de mots de passe ;
- de détails MFA ;
- de contenu confidentiel de dossier ;
- de liste nominative complète des détenteurs ;
- de justification sensible brute ;
- de données personnelles inutiles ;
- de claims de session complets.

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

### RoleArchived

Le rôle archivé est immuable.

---

### PermissionNotGrantedToRole

La permission n’est pas explicitement affectée au rôle.

---

### PermissionNotExplicitlyGrantedToRole

La permission est éventuellement effective par implication, mais aucune affectation explicite ne peut être retirée.

---

### PermissionDefinitionNotFound

La définition globale de la permission est introuvable.

---

### ActorNotAuthorized

L’acteur ne peut pas révoquer de permission.

---

### PermissionRevocationNotAuthorized

L’acteur ne peut pas révoquer cette permission précise.

---

### PrivilegedPermissionRevocationNotAuthorized

L’acteur ne peut pas révoquer une permission privilégiée.

---

### CriticalPermissionRevocationNotAuthorized

L’acteur ne peut pas révoquer une permission critique.

---

### RolePermissionSourceNotAuthoritative

La source ne contrôle pas cette affectation.

---

### ProductManagedPermissionCannotBeRevoked

La permission est imposée par le produit.

---

### ExternallyManagedPermissionCannotBeRevoked

La permission est contrôlée par une source externe.

---

### TemplateManagedPermissionCannotBeRevoked

La permission est contrôlée par un modèle.

---

### LocalOverrideNotAllowed

Le rôle ne permet pas d’exclusion locale.

---

### MandatoryPermissionCannotBeRevoked

La permission est obligatoire pour ce rôle.

---

### OwnerPermissionCannotBeRevoked

La révocation rendrait le rôle owner invalide.

---

### DefaultMemberPermissionCannotBeRevoked

La permission est obligatoire pour le rôle par défaut.

---

### PermissionRequiredByAnotherPermission

Une permission restante dépend de celle qui doit être révoquée.

---

### PermissionDependencyViolation

L’ensemble final ne satisfait plus les dépendances.

---

### PermissionDependencyGraphInvalid

Le graphe de permissions est incohérent.

---

### AdministrationContinuityViolation

La révocation supprimerait le dernier chemin valide d’administration.

---

### RecoveryPathRequired

La révocation supprimerait la capacité de récupération.

---

### SelfDemotionConfirmationRequired

Une confirmation explicite est nécessaire.

---

### SelfDemotionWouldLockActorOut

L’acteur se priverait de toute capacité nécessaire pour terminer ou corriger le workflow.

---

### ApprovalRequired

La révocation nécessite une approbation.

---

### ApprovalInvalid

L’approbation est invalide.

---

### ApprovalExpired

L’approbation a expiré.

---

### ApprovalScopeMismatch

L’approbation ne couvre pas cette révocation.

---

### ConfirmationRequired

Une confirmation est obligatoire.

---

### ConfirmationInvalid

La confirmation ne couvre pas l’intention exacte.

---

### SecurityReviewRequired

Une revue de sécurité est nécessaire.

---

### ComplianceReviewRequired

Une revue de conformité est nécessaire.

---

### OperationalRiskNotAccepted

Le risque opérationnel doit être explicitement accepté.

---

### RemediationPlanRequired

Un plan de remédiation est nécessaire.

---

### RemediationPlanInvalid

Le plan fourni est invalide.

---

### ProductPolicyViolation

La révocation viole une règle produit.

---

### LicensePolicyViolation

La révocation rendrait une configuration de licence invalide.

---

### RoleVersionConflict

Le rôle a changé depuis la lecture initiale.

---

### PermissionSetVersionConflict

L’ensemble des permissions a changé.

---

### PermissionCatalogVersionConflict

Le catalogue a changé depuis la validation.

---

### RolePermissionRevocationConflict

Une modification concurrente empêche la révocation.

---

### IdempotencyConflict

Le même identifiant représente une autre intention.

---

## Décisions de conception

### La commande retire une seule affectation explicite

```text
RevokePermissionFromRole
removes exactly one RolePermissionAssignment
```

---

### La permission peut rester effective

Une révocation explicite ne garantit pas une perte effective.

---

### Le graphe d’implication est recalculé

Le résultat doit identifier les permissions réellement perdues.

---

### Les dépendances ne sont pas révoquées en cascade

La commande échoue si une permission restante serait invalide.

---

### Les permissions obligatoires sont protégées

Les politiques produit, système, template et externes sont respectées.

---

### La self-demotion est autorisée sous conditions

Elle ne doit pas détruire l’administrabilité.

---

### Aucun Membership n’est modifié

Les capacités diminuent par héritage depuis le rôle.

---

### Aucune politique n’est relâchée automatiquement

Une baisse de sensibilité ne modifie ni l’attribution ni le transfert.

---

### PermissionSetVersion est incrémentée

Les décisions d’autorisation obsolètes peuvent être détectées.

---

### Les sessions ne sont pas systématiquement révoquées

Une réévaluation ou invalidation est demandée.

---

### La révocation est immédiate

Les révocations planifiées sont exclues de la première version.

---

### Un événement métier dédié est produit

```text
RolePermissionRevoked
```

---

## Cas limites

### Permission retirée mais toujours effective

La commande réussit.

Résultat :

```text
ExplicitPermissionRemoved = true
RevokedPermissionStillEffective = true
```

---

### Permission non explicite mais effective

La commande échoue avec :

```text
PermissionNotExplicitlyGrantedToRole
```

---

### Permission requise par une autre permission

La commande échoue.

Le demandeur doit d’abord retirer les permissions dépendantes.

---

### Permission obligatoire pour Owner

La commande échoue.

---

### Rôle sans détenteur

La révocation est autorisée.

Elle modifie les capacités des futurs détenteurs.

---

### Rôle désactivé

La révocation est autorisée.

---

### Rôle archivé

La révocation est refusée.

---

### Permission dépréciée

La révocation est autorisée.

---

### Permission supprimée du catalogue

Une récupération contrôlée peut permettre la révocation de la référence orpheline.

---

### Rôle externe

La révocation locale est refusée si la source externe contrôle l’affectation.

---

### Rôle dérivé d’un modèle

La révocation dépend de la politique d’override.

---

### Révocation réduisant la sensibilité

Les politiques du rôle restent inchangées.

---

### Révocation supprimant la dernière permission du rôle

Cela peut être autorisé si :

- le rôle n’est pas système ;
- aucune permission obligatoire n’est requise ;
- la continuité administrative n’est pas affectée.

Le rôle reste actif mais sans capacité.

---

### Rôle actif sans permission

Cette configuration est valide, mais potentiellement peu utile.

La désactivation du rôle reste une décision distincte.

---

### Acteur perdant sa propre permission

La commande peut réussir si la continuité est préservée.

La réponse doit être calculée avant que le nouvel état d’autorisation ne s’applique.

---

### Acteur perdant la permission nécessaire au workflow

L’opération peut réussir, mais toute étape ultérieure nécessitant cette permission devra être portée par une orchestration déjà autorisée ou un autre acteur.

---

### Permission critique liée à un incident

Une réévaluation immédiate des sessions peut être déclenchée.

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
Role state allows Permission revocation
Permission assignment exists explicitly
Actor or SystemActor is authorized
RevocationSource controls the assignment
Assignment control allows revocation
Permission is not mandatory
Permission is not ProductManaged
Permission is not externally locked
Permission is not template locked
Self-demotion is evaluated
Required confirmation is valid
ExplicitPermissionSetBefore is calculated
EffectivePermissionSetBefore is calculated
ExplicitPermissionSetAfter is calculated
EffectivePermissionSetAfter is calculated
LostEffectivePermissions are calculated
RevokedPermissionStillEffective is determined
Remaining Permission dependencies are satisfied
No mandatory Role capability is lost
Administration continuity is preserved
Recovery path remains available
Role sensitivity after revocation is calculated
Current Role holders are evaluated
Operational impact is accepted when required
Approvals are valid when required
Security review is valid when required
Compliance review is valid when required
Role version matches ExpectedRoleVersion
PermissionSetVersion matches when required
Permission catalog version matches when required
Idempotency is verified
PermissionSetVersion can be incremented
No Membership is directly modified
No Role policy is automatically modified
RolePermissionRevoked can be persisted atomically
```

---

## Synthèse

`RevokePermissionFromRole` retire une affectation explicite de permission d’un rôle de workspace.

Elle garantit que :

- le rôle existe ;
- l’affectation explicite existe ;
- l’acteur ou le workflow est autorisé ;
- la source de vérité est respectée ;
- les permissions obligatoires restent présentes ;
- les dépendances ne sont pas cassées ;
- aucune révocation en cascade implicite n’a lieu ;
- la permission effective est recalculée ;
- une permission retirée explicitement peut rester effective par implication ;
- les permissions réellement perdues sont identifiées ;
- la continuité administrative est préservée ;
- les rôles système restent valides ;
- la self-demotion est contrôlée ;
- les détenteurs et sessions concernés sont évalués ;
- aucun membership n’est directement modifié ;
- les politiques du rôle ne sont pas relâchées automatiquement ;
- les caches d’autorisation peuvent détecter le changement ;
- l’opération est idempotente et protégée contre la concurrence ;
- la révocation et son événement sont commités atomiquement.

Le résultat final est :

```text
Role
├── same identity
├── same Workspace
├── same metadata
├── same lifecycle status
├── same AssignmentPolicy
├── same TransferPolicy
├── one fewer explicit Permission
├── recalculated effective Permission set
└── new PermissionSetVersion
```
