---
id: IDN-CMD-UPDATE-ROLE-METADATA
title: UpdateRoleMetadata
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
  - ../invariants.md
  - ../permissions.md
  - ../events/RoleMetadataUpdated.md
  - CreateRole.md
  - ChangeMembershipRole.md
  - TransferMembershipRole.md
---

# UpdateRoleMetadata

## Objectif

La commande `UpdateRoleMetadata` modifie les informations descriptives et de présentation d’un `Role` existant.

Elle peut faire évoluer :

```text
Name
Description
DisplayColor
Icon
DisplayOrder
DocumentationUrl
```

sans modifier :

- `RoleId` ;
- `WorkspaceId` ;
- `RoleType` ;
- `SystemType` ;
- `Status` ;
- les `Permission` associées ;
- les `Membership` utilisant le rôle ;
- `AssignmentMode` ;
- `TransferMode` ;
- les contraintes d’attribution ;
- les exigences d’authentification ;
- les politiques de sécurité ;
- les versions d’autorisation des membres.

La commande répond à une intention unique :

```text
change how an existing Role is named,
described or presented
```

---

## Signification métier

Les métadonnées permettent aux utilisateurs de comprendre et d’identifier un rôle.

Elles répondent notamment aux questions suivantes :

```text
How is the Role presented?
How is the Role explained?
How is the Role ordered in the user interface?
Which visual marker represents the Role?
```

Elles ne répondent pas à :

```text
What may the Role do?
Who may receive the Role?
How may the Role be transferred?
Is the Role active?
```

Ces dernières questions relèvent d’autres commandes.

---

## Périmètre fonctionnel

`UpdateRoleMetadata` peut modifier tout ou partie des champs suivants :

```text
Name
Description
DisplayColor
Icon
DisplayOrder
DocumentationUrl
```

Le produit peut retenir un sous-ensemble.

La commande ne doit pas devenir un mécanisme générique permettant de modifier arbitrairement l’ensemble du `Role`.

---

## Propriétés exclues

Les propriétés suivantes ne sont pas des métadonnées :

```text
RoleType
SystemType
Status
AssignmentMode
TransferMode
IsPrivileged
IsExclusive
MinimumActiveAssignments
MaximumActiveAssignments
RequiresHumanAssignee
RequiredAuthenticationLevel
RequiredAssignmentPermission
ExternalReference
TemplateRoleId
```

Elles ne doivent jamais être modifiées par `UpdateRoleMetadata`.

---

## Pourquoi regrouper ces modifications

Les champs concernés partagent les mêmes caractéristiques :

- ils décrivent ou présentent le rôle ;
- ils ne changent pas les permissions effectives ;
- ils ne modifient pas les affectations ;
- ils ne modifient pas les règles de sécurité ;
- ils ne nécessitent pas d’invalidation d’autorisation ;
- ils peuvent être audités dans une même décision éditoriale.

Exemple :

```text
Name:
Billing Manager
↓
Billing Administrator
```

```text
Description:
Manages invoices
↓
Reviews invoices and billing settings
```

```text
DisplayOrder:
4
↓
2
```

Ces changements peuvent raisonnablement appartenir à une même intention administrative.

---

## Différence avec une commande générique UpdateRole

`UpdateRoleMetadata` reste volontairement limitée.

Une commande telle que :

```text
UpdateRole
```

pourrait involontairement modifier dans une même requête :

- le nom ;
- le statut ;
- les permissions ;
- le mode d’attribution ;
- les limites ;
- les règles de transfert ;
- la sécurité.

Cela rendrait difficiles :

- l’autorisation ;
- l’audit ;
- la concurrence ;
- les événements ;
- l’analyse d’impact ;
- les validations métier.

`UpdateRoleMetadata` ne concerne que la dimension descriptive du rôle.

---

## Agrégat concerné

`Role`

Le `Role` est la racine de l’agrégat modifié.

La commande consulte également :

- le `Workspace` ;
- l’acteur ;
- les autres rôles du `Workspace` ;
- les règles de nommage ;
- les noms réservés ;
- la politique de personnalisation ;
- la source de vérité des métadonnées ;
- les éventuels modèles ;
- les éventuelles sources externes.

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un membre autorisé ;
- un workflow de provisioning ;
- un système externe autoritaire ;
- un moteur de synchronisation de modèles ;
- un processus de migration ;
- un processus d’administration globale.

L’acteur doit être identifiable et auditable.

---

## Permission requise

Permission recommandée :

```text
workspace.roles.update-metadata
```

Une permission plus générale peut être utilisée :

```text
workspace.roles.manage
```

Des permissions plus fines peuvent être introduites si nécessaire :

```text
workspace.roles.rename
workspace.roles.edit-description
workspace.roles.customize-display
workspace.roles.update-system-metadata
workspace.roles.override-external-metadata
```

La granularité doit refléter un besoin réel d’autorisation.

---

## Autorisation complémentaire

La possession de la permission générale ne suffit pas nécessairement.

Le système doit également vérifier si l’acteur peut modifier :

- ce `RoleType` ;
- ce `SystemType` ;
- un rôle privilégié ;
- un rôle système ;
- un rôle synchronisé ;
- un rôle dérivé d’un modèle ;
- un champ contrôlé par une autre source.

Exemple :

```text
Actor may edit Custom Role metadata
```

ne signifie pas nécessairement :

```text
Actor may rename Owner Role
```

---

## Sources de modification

Valeurs recommandées pour `RoleMetadataUpdateSource` :

```text
ManualAdministration
WorkspaceGovernance
ExternalSynchronization
TemplateSynchronization
LocalizationUpdate
Migration
AdministrativeRecovery
SystemProvisioning
```

La source influence :

- les champs modifiables ;
- la priorité de la modification ;
- les versions attendues ;
- l’autorité requise ;
- l’audit ;
- la possibilité d’une future réécriture.

---

## Motifs de modification

Valeurs recommandées pour `RoleMetadataUpdateReason` :

```text
TerminologyChange
DescriptionClarification
OrganizationalChange
BrandingChange
DisplayReorganization
AccessibilityImprovement
LocalizationChange
ExternalDirectoryUpdate
TemplateUpdate
AdministrativeCorrection
Migration
Other
```

Le motif peut être obligatoire pour les rôles sensibles.

---

## Source de vérité

Chaque métadonnée peut être contrôlée par une source différente.

Exemple :

```text
Name -> ExternalSystem
Description -> LocalAdministration
DisplayColor -> LocalAdministration
Icon -> Template
```

Le modèle peut donc utiliser une politique par champ :

```text
RoleMetadataControl
```

---

## RoleMetadataControl

Valeurs possibles :

```text
LocallyManaged
ExternallyManaged
TemplateManaged
ProductManaged
LocallyOverridable
```

### LocallyManaged

Les administrateurs autorisés du `Workspace` contrôlent la valeur.

### ExternallyManaged

Seule la source externe autoritaire peut modifier la valeur.

### TemplateManaged

La valeur est contrôlée par un modèle.

### ProductManaged

La valeur est imposée par le produit.

### LocallyOverridable

Une valeur par défaut est fournie, mais le `Workspace` peut la remplacer.

---

## Priorité des sources

Lorsque plusieurs sources peuvent modifier une valeur, leur priorité doit être explicite.

Exemple :

```text
AdministrativeOverride
>
TemplateSynchronization
>
ProductDefault
```

Pour un rôle externe :

```text
ExternalSynchronization
>
LocalAdministration
```

La dernière écriture chronologique ne doit pas automatiquement gagner.

La source autoritaire doit primer.

---

## Préconditions

Avant l’exécution, les conditions suivantes doivent être satisfaites :

- le `Role` existe ;
- le `Workspace` existe ;
- le rôle appartient au `Workspace` attendu ;
- le rôle n’est pas supprimé ;
- son état autorise une modification de métadonnées ;
- l’acteur ou le workflow est autorisé ;
- au moins une métadonnée est réellement modifiée ;
- chaque champ modifié est contrôlable par la source de la commande ;
- le nouveau nom est valide lorsqu’il est fourni ;
- le nouveau nom est disponible dans le `Workspace` ;
- la description est valide lorsqu’elle est fournie ;
- les données de présentation respectent leurs formats ;
- aucune valeur ne contient de secret ou de donnée interdite ;
- les restrictions des rôles système sont respectées ;
- les versions externes ou de modèle sont valides ;
- la demande est idempotente ;
- la version du rôle est compatible avec la décision ;
- aucune modification concurrente incompatible n’a gagné.

---

## États autorisés

Politique recommandée :

```text
Active   -> metadata update allowed
Disabled -> metadata update allowed
Archived -> metadata update forbidden by default
Removed  -> metadata update forbidden
```

Un rôle désactivé peut être préparé avant une future réactivation.

Un rôle archivé doit rester historiquement stable, sauf commande administrative spécialisée.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `RoleId` | `RoleId` | Oui | Identifie le rôle à modifier. |
| `Changes` | `RoleMetadataChanges` | Oui | Contient uniquement les champs explicitement modifiés. |
| `UpdatedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’acteur ou le workflow. |
| `UpdatedAt` | Instant | Oui | Date d’effet de la modification. |
| `UpdateReason` | `RoleMetadataUpdateReason` | Oui | Motif structuré. |
| `UpdateSource` | `RoleMetadataUpdateSource` | Oui | Origine de la modification. |
| `UpdateRequestId` | Identifiant | Oui | Identifie la demande de façon idempotente. |

Données facultatives ou conditionnelles :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `ExpectedRoleVersion` | Version | Recommandé | Version attendue du rôle. |
| `ExternalReference` | Identifiant | Conditionnel | Référence le changement externe. |
| `ExternalVersion` | Version externe | Conditionnel | Ordonne les mises à jour externes. |
| `TemplateId` | Identifiant | Conditionnel | Identifie le modèle concerné. |
| `TemplateVersion` | Version | Conditionnel | Identifie la version du modèle. |
| `CaseReference` | Identifiant | Non | Référence un dossier administratif. |
| `CorrelationId` | Identifiant | Non | Relie la commande à un workflow. |

---

## RoleMetadataChanges

Le `Value Object` peut contenir :

```text
NameChange
DescriptionChange
DisplayColorChange
IconChange
DisplayOrderChange
DocumentationUrlChange
```

Chaque changement doit distinguer :

```text
NotProvided
Set(value)
Clear
```

Cette distinction est nécessaire pour différencier :

```text
do not modify Description
```

de :

```text
remove Description
```

---

## Patch explicite

La commande suit une sémantique de patch explicite.

Exemple :

```text
Changes:
  Name: Set("Billing Administrator")
  Description: NotProvided
  DisplayColor: Clear
```

Résultat :

- le nom est modifié ;
- la description reste inchangée ;
- la couleur est supprimée.

Une valeur absente ne doit jamais être interprétée implicitement comme une suppression.

---

## Name

Le nom représente le libellé métier principal du rôle.

Il est mutable.

Il ne constitue pas son identité.

```text
RoleId remains stable
```

Le moteur d’autorisation ne doit jamais dépendre du nom.

---

## Validation du Name

Le nom doit :

- être non vide ;
- respecter une longueur minimale et maximale ;
- être normalisé ;
- ne pas contenir uniquement des espaces ;
- ne pas contenir de caractères interdits ;
- ne pas contenir de secrets ;
- ne pas contenir de données personnelles inutiles ;
- ne pas usurper un rôle système ;
- ne pas être trompeur ;
- être unique dans le `Workspace`.

---

## NormalizedRoleName

Le système calcule :

```text
NormalizedRoleName
```

Exemple :

```text
"  Billing   Administrator  "
↓
"billing administrator"
```

La normalisation doit être la même que pour `CreateRole`.

---

## Unicité du nom

Recommandation :

```text
UNIQUE(WorkspaceId, NormalizedRoleName)
```

La contrainte doit exclure le rôle courant lors de la validation applicative.

La base de données ou un mécanisme équivalent doit protéger la concurrence.

---

## Modification typographique

Une modification peut être pertinente même si le nom normalisé reste identique.

Exemple :

```text
api manager
↓
API Manager
```

Le système doit donc distinguer :

```text
DisplayName equality
```

et :

```text
NormalizedName equality
```

Politique recommandée :

- valeur d’affichage strictement identique : aucun changement ;
- valeur d’affichage différente mais normalisée identique : modification autorisée ;
- valeur normalisée différente : validation complète d’unicité.

---

## Noms réservés

Exemples :

```text
Owner
Root
System
Super Administrator
Default Member
Platform Administrator
```

Un rôle custom ne doit pas pouvoir utiliser un nom donnant l’impression d’une sémantique qu’il ne possède pas.

---

## Cohérence avec SystemType

Le nom ne modifie jamais :

```text
SystemType
```

Un rôle avec :

```text
SystemType = Owner
```

reste owner quel que soit son libellé.

Cependant, le nouveau nom ne doit pas contredire de manière manifeste sa fonction.

Exemple potentiellement interdit :

```text
Owner Role
↓
Read-only Guest
```

---

## Description

La description explique la finalité du rôle.

Elle peut préciser :

- les responsabilités ;
- le public concerné ;
- le périmètre organisationnel ;
- les usages attendus ;
- les restrictions générales.

Elle ne doit jamais être utilisée comme source d’autorisation.

---

## Validation de la Description

La description doit :

- respecter une longueur maximale ;
- ne pas contenir de secret ;
- ne pas contenir de token ;
- ne pas contenir de données personnelles non nécessaires ;
- ne pas contenir de règles exécutables ;
- ne pas prétendre accorder une permission absente ;
- respecter les formats autorisés.

---

## Description et cohérence des permissions

La description peut devenir obsolète après une modification de permissions.

Le domaine peut :

- accepter cette divergence comme une question éditoriale ;
- produire un avertissement ;
- déclencher une revue ;
- calculer un indicateur de cohérence.

Il ne doit pas déduire ou modifier automatiquement les permissions depuis le texte.

---

## DisplayColor

`DisplayColor` est une aide de présentation.

Exemples de représentations :

```text
#4F46E5
indigo-600
RoleColor("indigo")
```

La recommandation est d’utiliser un `Value Object` contrôlé plutôt qu’une chaîne arbitraire.

---

## Validation de DisplayColor

La couleur doit :

- respecter un format connu ;
- appartenir éventuellement à une palette autorisée ;
- offrir un contraste suffisant ;
- ne pas porter de signification de sécurité automatique ;
- pouvoir être supprimée ;
- ne pas contenir de code exécutable.

---

## Couleur et autorisation

Une couleur telle que rouge ou doré ne doit jamais indiquer à elle seule :

- un rôle privilégié ;
- un rôle owner ;
- une interdiction ;
- une priorité de sécurité.

La classification réelle reste structurée dans le domaine.

---

## Icon

`Icon` représente une clé d’icône, pas un contenu arbitraire.

Exemples :

```text
shield
wallet
users
eye
settings
```

Éviter de stocker directement :

- du SVG non contrôlé ;
- du HTML ;
- du JavaScript ;
- une URL arbitraire ;
- un fichier exécutable.

---

## Validation de Icon

L’icône doit :

- appartenir à un catalogue autorisé ;
- être compatible avec l’interface ;
- être accessible ;
- ne pas introduire de contenu externe non fiable.

Une valeur absente signifie que l’interface utilise une icône par défaut.

---

## DisplayOrder

`DisplayOrder` contrôle uniquement l’ordre de présentation.

Il ne doit pas modifier :

- la hiérarchie d’autorisation ;
- la priorité de rôle ;
- la capacité d’attribution ;
- la comparaison de privilèges.

Exemple :

```text
DisplayOrder = 1
```

ne signifie pas :

```text
highest authority
```

---

## Validation de DisplayOrder

La valeur doit être :

- un entier ;
- comprise dans une plage raisonnable ;
- indépendante de toute hiérarchie d’autorité ;
- éventuellement non unique.

Des valeurs identiques peuvent être départagées par :

```text
Name
RoleId
CreatedAt
```

---

## DocumentationUrl

`DocumentationUrl` peut pointer vers une documentation expliquant le rôle.

Elle doit être :

- facultative ;
- valide ;
- limitée à des schémas autorisés ;
- contrôlée contre les URL dangereuses ;
- compatible avec les politiques de sécurité ;
- éventuellement limitée à des domaines approuvés.

---

## DocumentationUrl et secrets

L’URL ne doit pas contenir :

- de token ;
- de secret ;
- de signature temporaire ;
- de donnée personnelle ;
- de paramètre d’authentification.

---

## Localisation

Deux modèles sont possibles.

### Nom unique

Le `Workspace` définit un nom unique pour le rôle.

```text
Role.Name
```

### Noms localisés

Le rôle possède des traductions :

```text
RoleLocalizedMetadata
├── Locale
├── Name
└── Description
```

Dans ce second modèle, il est préférable d’utiliser une commande spécialisée :

```text
SetRoleLocalizedMetadata
```

plutôt que d’ajouter une structure complexe à `UpdateRoleMetadata`.

---

## Rôles système

Les rôles système peuvent avoir des métadonnées :

- imposées par le produit ;
- localisées automatiquement ;
- partiellement personnalisables ;
- totalement verrouillées.

La politique doit être explicite.

---

## Rôle Owner

Pour :

```text
SystemType = Owner
```

`UpdateRoleMetadata` ne doit jamais modifier :

- le caractère owner ;
- les permissions ;
- la protection du dernier owner ;
- l’exigence d’identité humaine ;
- les règles de transfert ;
- les règles de session.

Seule la présentation peut changer, si la politique l’autorise.

---

## Rôle External

Pour un rôle externe, les métadonnées peuvent être :

```text
ExternalName
ExternalDescription
LocalDisplayName
LocalDescription
```

Lorsque la source externe contrôle `Name`, une mise à jour manuelle de ce champ doit être refusée.

Une personnalisation locale doit utiliser un champ distinct.

---

## Rôle TemplateDerived

Un rôle dérivé d’un modèle peut autoriser :

- aucune personnalisation ;
- la personnalisation de la couleur uniquement ;
- la personnalisation du nom et de la description ;
- une surcharge complète des métadonnées.

Le système doit enregistrer les champs surchargés afin de gérer les futures synchronisations.

---

## Champ surchargé

Une surcharge locale peut être représentée par :

```text
MetadataOverride
├── Field
├── Value
├── OverriddenAt
├── OverriddenBy
└── BaseVersion
```

Cela permet au moteur de modèle de savoir qu’une valeur ne doit pas être remplacée automatiquement.

---

## Traitement métier

### 1. Charger le Role

Le système charge le `Role` identifié par `RoleId`.

S’il n’existe pas, la commande échoue.

---

### 2. Charger le Workspace

Le système récupère le `Workspace` associé.

Il vérifie que le contexte permet la modification.

---

### 3. Vérifier l’idempotence

Le système recherche une demande déjà traitée avec :

```text
RoleId + UpdateRequestId
```

Si elle correspond exactement, le résultat initial est retourné.

---

### 4. Vérifier l’état du Role

Le système vérifie que le statut autorise les modifications descriptives.

Politique recommandée :

```text
Active   -> allowed
Disabled -> allowed
Archived -> rejected
```

---

### 5. Vérifier que Changes n’est pas vide

Une commande sans changement explicite doit être refusée.

Erreur :

```text
NoRoleMetadataChangeRequested
```

---

### 6. Charger le contexte de l’acteur

Pour un acteur humain :

- `User` ;
- `Membership` ;
- rôle ;
- permissions ;
- restrictions ;
- niveau d’authentification.

Pour un `SystemActor` :

- identité technique ;
- source ;
- périmètre ;
- champs autorisés ;
- version externe ou de modèle.

---

### 7. Autoriser la commande

Le système vérifie :

```text
Actor may update Role metadata
```

puis :

```text
Actor may update this Role
```

et enfin :

```text
Actor may update each requested field
```

---

### 8. Déterminer l’autorité par champ

Pour chaque champ demandé, le système identifie :

```text
current controlling source
```

et vérifie que :

```text
UpdateSource may override current source
```

---

### 9. Valider Name lorsque fourni

Le système vérifie :

- le format ;
- la normalisation ;
- l’unicité ;
- les noms réservés ;
- la cohérence avec `SystemType` ;
- la politique du rôle.

---

### 10. Valider Description lorsque fournie

Le système vérifie :

- la longueur ;
- le contenu ;
- les formats ;
- la confidentialité ;
- la possibilité de suppression.

---

### 11. Valider DisplayColor lorsque fournie

Le système vérifie :

- le format ;
- la palette ;
- le contraste ;
- l’absence de contenu exécutable.

---

### 12. Valider Icon lorsque fournie

Le système vérifie que l’icône appartient au catalogue autorisé.

---

### 13. Valider DisplayOrder lorsque fourni

Le système vérifie la plage autorisée.

---

### 14. Valider DocumentationUrl lorsque fournie

Le système vérifie :

- le schéma ;
- le domaine ;
- l’absence de secret ;
- les restrictions de sécurité.

---

### 15. Calculer les changements effectifs

Le système compare chaque valeur demandée à la valeur actuelle.

Exemple :

```text
Requested Name = Current Name
```

ne constitue pas un changement.

Les changements réellement applicables sont rassemblés dans :

```text
EffectiveMetadataChanges
```

---

### 16. Vérifier qu’au moins un changement est effectif

Si aucune valeur ne change réellement, la commande retourne :

```text
RoleMetadataAlreadyMatches
```

ou un succès sans événement selon la convention retenue.

La recommandation est de ne pas produire d’événement sans transition d’état.

---

### 17. Vérifier les versions externes

Pour une source externe :

```text
ExternalVersion > LastAppliedExternalVersion
```

ou respecte la politique de comparaison.

Une version obsolète est rejetée.

---

### 18. Vérifier les versions de modèle

Pour un modèle :

```text
TemplateVersion
```

doit être compatible avec la version déjà appliquée.

---

### 19. Vérifier ExpectedRoleVersion

Condition recommandée :

```text
Role.Version = ExpectedRoleVersion
```

Cela évite d’écraser une mise à jour concurrente.

---

### 20. Capturer les valeurs précédentes

Pour chaque champ modifié, le système conserve :

```text
PreviousValue
NewValue
```

pour l’événement et l’audit.

---

### 21. Appliquer les changements

Le rôle modifie uniquement les champs autorisés.

Exemple :

```text
Name = NewName
Description = NewDescription
DisplayColor = NewDisplayColor
```

Aucune autre propriété ne doit être touchée.

---

### 22. Enregistrer les overrides

Lorsque la modification constitue une surcharge locale d’un modèle ou d’une source, le système enregistre l’override.

---

### 23. Incrémenter Role.Version

```text
Role.Version += 1
```

Une seule incrémentation est effectuée, quel que soit le nombre de champs modifiés.

---

### 24. Produire RoleMetadataUpdated

L’agrégat produit :

```text
RoleMetadataUpdated
```

---

## Résultat attendu

Après succès :

```text
Role
├── RoleId: unchanged
├── WorkspaceId: unchanged
├── RoleType: unchanged
├── SystemType: unchanged
├── Status: unchanged
├── Metadata: updated
├── Permissions: unchanged
├── Assignment policy: unchanged
├── Transfer policy: unchanged
├── Membership assignments: unchanged
└── Version: incremented
```

---

## Invariants concernés

### `IDN-INV-ROLE-001`

Le rôle appartient toujours au même `Workspace`.

---

### `IDN-INV-ROLE-002`

Le nom normalisé reste unique dans le `Workspace`.

```text
UNIQUE(WorkspaceId, NormalizedRoleName)
```

---

### `IDN-INV-ROLE-003`

Le `RoleId` reste stable.

---

### `IDN-INV-ROLE-004`

Le `SystemType` reste inchangé.

---

### `IDN-INV-ROLE-006`

Les politiques d’attribution restent inchangées.

---

### `IDN-INV-ROLE-007`

Les limites d’attribution restent inchangées.

---

### `IDN-INV-010`

Les permissions effectives ne changent pas.

---

## Effet sur les Membership

Aucun `Membership` n’est modifié.

```text
Membership.RoleId
=
unchanged
```

Aucune période d’affectation n’est ouverte ou fermée.

---

## Effet sur les Permission

Aucune relation entre `Role` et `Permission` n’est modifiée.

```text
RolePermissionAssignments
=
unchanged
```

---

## Effet sur les sessions

Les sessions ne doivent pas être révoquées.

Les versions d’autorisation des memberships ne changent pas.

Une invalidation de cache de présentation peut toutefois être nécessaire.

---

## Effet sur les claims

Lorsque le nom ou l’icône est inclus dans des claims à titre informatif, la valeur peut rester obsolète jusqu’au prochain renouvellement.

Cette obsolescence ne doit avoir aucun impact sur l’autorisation.

---

## Événement produit

### RoleMetadataUpdated

La commande produit :

```text
RoleMetadataUpdated
```

L’événement peut contenir :

- `RoleId`
- `WorkspaceId`
- `Changes`
- `UpdatedAt`
- `UpdatedBy`
- `UpdateReason`
- `UpdateSource`
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `CaseReference`
- `UpdateRequestId`
- `CorrelationId`
- `RoleVersion`

---

## Structure des Changes dans l’événement

Exemple :

```text
Changes:
  Name:
    Previous: Billing Manager
    Current: Billing Administrator

  Description:
    Previous: Manages billing
    Current: Manages invoices and billing configuration

  DisplayColor:
    Previous: blue
    Current: indigo
```

L’événement ne doit contenir que les champs effectivement modifiés.

---

## Événements spécialisés

Deux stratégies sont possibles.

### Événement unique

```text
RoleMetadataUpdated
```

Tous les consommateurs utilisent la structure des changements.

### Événements spécialisés dérivés

Les projections peuvent dériver :

```text
RoleRenamed
RoleDescriptionChanged
RoleDisplayChanged
```

La recommandation est de conserver un événement métier principal unique si le domaine traite réellement ces propriétés comme une seule catégorie.

---

## Événements non produits

La commande ne produit pas :

```text
RoleCreated
RoleDisabled
RoleEnabled
RoleArchived
RolePermissionGranted
RolePermissionRevoked
RoleAssignmentPolicyChanged
RoleTransferPolicyChanged
MembershipRoleChanged
MembershipAuthorizationChanged
```

---

## Erreurs métier

### RoleNotFound

Le rôle n’existe pas.

---

### WorkspaceNotFound

Le `Workspace` associé n’existe pas ou n’est plus accessible.

---

### WorkspaceUnavailable

Le `Workspace` ne permet pas cette opération.

---

### RoleMetadataUpdateNotAllowed

L’état ou la politique du rôle interdit la modification.

---

### RoleArchived

Le rôle archivé est immuable.

---

### ActorNotAuthorized

L’acteur ne peut pas modifier les métadonnées du rôle.

---

### RoleTypeMetadataUpdateNotAuthorized

L’acteur ne peut pas modifier ce type de rôle.

---

### SystemRoleMetadataUpdateForbidden

Les métadonnées du rôle système sont verrouillées.

---

### MetadataFieldUpdateNotAuthorized

L’acteur peut modifier certaines métadonnées, mais pas le champ demandé.

---

### MetadataSourceNotAuthoritative

La source de la commande ne contrôle pas la valeur.

---

### NoRoleMetadataChangeRequested

Aucun champ n’a été fourni.

---

### RoleMetadataAlreadyMatches

Aucune valeur effective ne change.

---

### RoleNameRequired

Le nom demandé est vide.

---

### RoleNameInvalid

Le nom ne respecte pas les règles.

---

### RoleNameTooLong

Le nom dépasse la taille maximale.

---

### RoleNameReserved

Le nom est réservé.

---

### RoleNameMisleading

Le nom entre en contradiction avec la sémantique structurelle.

---

### RoleNameAlreadyExists

Un autre rôle possède déjà ce nom normalisé.

---

### RoleDescriptionTooLong

La description dépasse la limite.

---

### RoleDescriptionInvalid

La description contient un format ou un contenu interdit.

---

### InvalidDisplayColor

La couleur n’appartient pas au format autorisé.

---

### DisplayColorAccessibilityViolation

La couleur ne respecte pas les exigences d’accessibilité.

---

### InvalidRoleIcon

L’icône ne fait pas partie du catalogue autorisé.

---

### InvalidDisplayOrder

L’ordre de présentation est invalide.

---

### InvalidDocumentationUrl

L’URL n’est pas valide.

---

### DocumentationUrlDomainNotAllowed

Le domaine ciblé n’est pas autorisé.

---

### SensitiveDataInRoleMetadata

Une valeur contient une donnée interdite ou sensible.

---

### ExternalReferenceRequired

La source externe n’est pas identifiée.

---

### ExternalVersionRequired

Une version externe est nécessaire.

---

### StaleExternalMetadataUpdate

La mise à jour externe est obsolète.

---

### TemplateVersionRequired

Une version de modèle est nécessaire.

---

### StaleTemplateMetadataUpdate

La mise à jour du modèle est obsolète.

---

### RoleVersionConflict

Le rôle a été modifié depuis la décision initiale.

---

### RoleMetadataUpdateConflict

Une modification concurrente empêche l’application.

---

### IdempotencyConflict

Le même `UpdateRequestId` a été utilisé avec d’autres changements.

---

## Idempotence

La commande doit être idempotente pour :

```text
RoleId + UpdateRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- réappliquer les changements ;
- modifier `UpdatedAt` ;
- produire un nouvel événement ;
- incrémenter une nouvelle version ;
- recréer des overrides ;
- répéter les notifications ;
- répéter les synchronisations.

---

## Empreinte idempotente

L’empreinte doit inclure :

```text
RoleId
NormalizedChanges
UpdateReason
UpdateSource
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
```

`NormalizedChanges` doit distinguer :

```text
NotProvided
Set(value)
Clear
```

---

## Reprise après réponse perdue

Cas :

```text
UpdateRoleMetadata succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry retourne :

- les mêmes anciennes valeurs ;
- les mêmes nouvelles valeurs ;
- le même `UpdatedAt` ;
- la même version finale ;
- le même événement logique.

---

## Concurrence

### Deux changements du même champ

État initial :

```text
Name = Billing Manager
```

Commandes concurrentes :

```text
Name -> Billing Administrator
```

et :

```text
Name -> Finance Manager
```

Une seule réussit avec la version attendue.

---

### Deux changements de champs différents

Commandes concurrentes :

```text
Name -> Billing Administrator
```

et :

```text
DisplayColor -> indigo
```

Deux stratégies sont possibles :

#### Concurrence stricte d’agrégat

Une seule modification gagne.

La seconde recharge puis rejoue son patch.

#### Fusion optimiste par champ

Le système détecte que les champs ne se chevauchent pas et peut les fusionner.

La recommandation initiale est la concurrence stricte, plus simple et plus sûre.

---

### Deux rôles renommés vers le même nom

Une seule commande peut réussir grâce à la contrainte :

```text
UNIQUE(WorkspaceId, NormalizedRoleName)
```

---

### Modification contre DisableRole

Les deux commandes modifient le même agrégat.

Une version optimiste doit empêcher les écritures perdues.

---

### Modification contre ArchiveRole

Si l’archivage gagne, la mise à jour est refusée.

---

### Modification contre synchronisation externe

La priorité des sources doit décider.

La chronologie seule ne suffit pas.

---

### Modification locale contre modèle

Si un champ est `LocallyOverridable`, la modification locale crée un override.

Sinon, elle est rejetée.

---

## Atomicité

L’opération suivante doit être atomique :

```text
load Role
+
verify Workspace
+
verify state
+
verify actor
+
verify field authority
+
validate requested metadata
+
verify name uniqueness
+
verify external or template version
+
verify Role version
+
apply effective changes
+
record overrides
+
record idempotency
+
record RoleMetadataUpdated
```

---

## États interdits

```text
Name changed
AND
NormalizedRoleName not updated
```

```text
Role metadata updated
AND
RoleMetadataUpdated missing
```

```text
RoleMetadataUpdated persisted
AND
Role not updated
```

```text
two Roles in same Workspace
with same NormalizedRoleName
```

```text
metadata command changed AssignmentMode
```

```text
metadata command changed Permissions
```

```text
metadata command changed Membership assignments
```

---

## Outbox transactionnelle

Le même commit doit inclure :

```text
Role update
Metadata override records
Idempotency record
RoleMetadataUpdated event
```

Les effets externes sont publiés après commit.

---

## Effets externes

Après `RoleMetadataUpdated`, des handlers peuvent :

- mettre à jour les projections ;
- rafraîchir l’administration ;
- invalider les caches d’affichage ;
- mettre à jour les index de recherche ;
- synchroniser une interface externe ;
- actualiser les exports ;
- notifier certains administrateurs ;
- enregistrer des analytics.

Ces handlers doivent être idempotents.

---

## Notifications

Une notification n’est pas toujours nécessaire.

Elle peut être pertinente lorsque :

- le nom d’un rôle très utilisé change ;
- un rôle privilégié est renommé ;
- la modification provient du support ;
- le rôle owner change de libellé ;
- la documentation change pour des raisons de conformité ;
- une source externe modifie la présentation.

---

## Message aux membres

Lorsque seuls les éléments de présentation changent, la notification doit préciser :

```text
The Role presentation changed.
Your permissions did not change.
```

Elle ne doit pas suggérer une évolution d’autorisation.

---

## Audit

Une modification réussie doit enregistrer :

- `RoleId`
- `WorkspaceId`
- `RoleType`
- `SystemType`
- statut du rôle
- champs demandés
- champs effectivement modifiés
- anciennes valeurs
- nouvelles valeurs
- `UpdatedAt`
- `UpdatedBy`
- `UpdateReason`
- `UpdateSource`
- source de contrôle par champ
- overrides créés ou supprimés
- `ExternalReference`
- `ExternalVersion`
- `TemplateId`
- `TemplateVersion`
- `CaseReference`
- `UpdateRequestId`
- `CorrelationId`
- version précédente
- version finale
- résultat final

---

## Questions auxquelles l’audit doit répondre

```text
which Role metadata changed
which fields changed
what the previous values were
what the new values are
who performed the change
why the change occurred
which source controlled each field
whether a local override was created
whether Permissions changed
whether Membership assignments changed
```

Les deux dernières réponses doivent toujours être :

```text
no
```

---

## Historique des métadonnées

L’historique peut être reconstruit depuis :

```text
RoleMetadataUpdated
```

Une projection peut fournir :

```text
RoleMetadataHistory
├── Field
├── PreviousValue
├── NewValue
├── ChangedAt
├── ChangedBy
├── ChangeReason
├── ChangeSource
└── RequestId
```

---

## Anciens noms

Les anciens noms peuvent être conservés comme :

- historique ;
- alias de recherche ;
- données d’audit ;
- référence d’export.

Le système doit décider si un ancien nom peut être réutilisé.

---

## Réutilisation des noms

Deux politiques sont possibles.

### Unicité des noms actuels uniquement

Un ancien nom peut être réutilisé.

### Réservation historique

Un ancien nom reste réservé dans le `Workspace`.

La seconde politique réduit les ambiguïtés d’audit, mais limite la flexibilité.

---

## Recherche

Les index de recherche peuvent contenir :

```text
CurrentName
PreviousNames
Description
```

Les anciens noms ne doivent pas être affichés comme noms actuels.

---

## Sécurité

La commande doit garantir que :

- seul un acteur autorisé modifie les métadonnées ;
- les champs contrôlés par une source externe sont protégés ;
- les rôles système ne sont pas rendus trompeurs ;
- le nom n’est jamais utilisé comme identité ;
- les métadonnées ne contiennent pas de secrets ;
- les URL sont contrôlées ;
- les icônes ne contiennent pas de code arbitraire ;
- l’unicité du nom est protégée ;
- aucune permission ne change ;
- aucun membre n’est réattribué ;
- aucune session n’acquiert ou ne perd de droits ;
- les retries ne dupliquent aucun effet.

---

## Confidentialité

Les métadonnées ne doivent pas contenir inutilement :

- le nom d’une personne ;
- une donnée de santé ;
- une sanction individuelle ;
- une donnée contractuelle sensible ;
- une information d’enquête ;
- un secret ;
- un token ;
- un identifiant d’authentification ;
- une URL privée signée.

Exemples à éviter :

```text
Replacement for Alice
Employees under investigation
Former contractor John Doe
```

Les rôles doivent exprimer des responsabilités stables.

---

## Décisions de conception

### Les métadonnées sont regroupées

`Name`, `Description` et les propriétés de présentation appartiennent à une même catégorie non autorisante.

---

### UpdateRoleMetadata reste limitée

La commande ne remplace pas toutes les commandes du cycle de vie de `Role`.

---

### Le RoleId reste stable

Une modification de nom ou de présentation ne crée pas de nouveau rôle.

---

### Le WorkspaceId reste stable

Un rôle ne peut pas être déplacé vers un autre `Workspace`.

---

### RoleType et SystemType restent stables

La présentation ne modifie pas la nature structurelle.

---

### Les Permission restent inchangées

Aucune permission n’est ajoutée ou retirée.

---

### Les Membership restent inchangés

Aucune affectation n’est créée, supprimée ou remplacée.

---

### Les politiques restent inchangées

La commande ne modifie ni attribution, ni transfert, ni sécurité.

---

### Une sémantique de patch explicite est requise

`NotProvided`, `Set` et `Clear` doivent être distingués.

---

### Seuls les changements effectifs sont persistés

Une commande ne produisant aucune transition ne doit pas générer d’événement.

---

### Les sources de vérité sont évaluées par champ

Un rôle externe ou dérivé d’un modèle peut autoriser des personnalisations partielles.

---

### Un seul événement principal est produit

```text
RoleMetadataUpdated
```

---

### Aucune AuthorizationVersion de Membership n’est modifiée

Les droits effectifs restent identiques.

---

## Cas limites

### Name fourni avec la valeur actuelle

Aucun changement effectif.

---

### Changement typographique uniquement

Exemple :

```text
api administrator
↓
API Administrator
```

La modification peut être acceptée même si le nom normalisé reste identique.

---

### Description vidée

La commande doit utiliser :

```text
Description = Clear
```

et non une absence de champ.

---

### Couleur identique dans un autre format

Exemple :

```text
#ffffff
```

et :

```text
#FFFFFF
```

Le `Value Object` doit normaliser avant comparaison.

---

### DisplayOrder identique

Aucun changement effectif.

---

### Plusieurs champs fournis, un seul change

L’événement ne contient que le champ réellement modifié.

---

### Un champ valide et un champ invalide

La commande entière échoue.

Aucun changement partiel n’est persisté.

---

### Rôle très utilisé

Une seule ligne ou un seul agrégat est modifié.

Les memberships ne doivent pas être mis à jour.

---

### Rôle sans Permission

La modification reste autorisée.

---

### Rôle privilégié

La modification peut exiger un audit ou une notification renforcée, mais ne change pas les privilèges.

---

### Rôle synchronisé

Les champs externes sont protégés ; les champs locaux peuvent rester modifiables.

---

### Mise à jour externe en retard

Elle est rejetée grâce à `ExternalVersion`.

---

### Changement de modèle après override local

Le champ localement surchargé n’est pas remplacé, sauf politique explicite.

---

### Suppression d’un override

Le produit peut introduire une commande :

```text
ResetRoleMetadataToSource
```

ou accepter une opération explicite dans `UpdateRoleMetadata`.

La décision doit être visible et auditable.

---

## Checklist de validation

Avant commit :

```text
Role exists
Workspace exists
Role belongs to Workspace
Role state allows metadata update
Actor or SystemActor is authorized
At least one change is requested
Each requested field is supported
Each requested field may be modified by UpdateSource
Name is valid when provided
Name is unique when changed
Name is not reserved
Name is not misleading
Description is valid when provided
DisplayColor is valid when provided
Icon is valid when provided
DisplayOrder is valid when provided
DocumentationUrl is valid when provided
No sensitive data is introduced
External version is current when required
Template version is current when required
At least one effective change remains
Role version matches ExpectedRoleVersion
Idempotency is verified
No authorization property is modified
No Membership assignment is modified
RoleMetadataUpdated can be recorded atomically
```

---

## Synthèse

`UpdateRoleMetadata` modifie uniquement les informations descriptives et de présentation d’un rôle existant.

Elle garantit que :

- le rôle existe ;
- l’acteur ou le workflow est autorisé ;
- chaque champ est contrôlé par une source légitime ;
- le nom reste valide et unique ;
- les descriptions et éléments visuels sont sûrs ;
- les rôles système et externes restent protégés ;
- seules les valeurs explicitement fournies sont modifiées ;
- seuls les changements effectifs sont persistés ;
- `RoleId`, `WorkspaceId`, `RoleType` et `SystemType` restent stables ;
- les permissions restent inchangées ;
- les memberships restent inchangés ;
- les politiques d’attribution et de transfert restent inchangées ;
- aucune session ne gagne ou ne perd de droits ;
- l’opération est idempotente et concurrentiellement sûre ;
- l’événement `RoleMetadataUpdated` est enregistré atomiquement.

Le résultat final est :

```text
Role
├── same identity
├── same structural meaning
├── same authorization behavior
├── same Permission assignments
├── same Membership assignments
└── updated descriptive metadata
```