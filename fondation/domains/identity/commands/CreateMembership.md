---
id: IDN-CMD-CREATE-MEMBERSHIP
title: CreateMembership
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-30

aggregate: Membership

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../value-objects.md
  - ../invariants.md
  - ../permissions.md
  - ../events.md
  - AcceptInvitation.md
  - RestoreMembership.md
  - SuspendMembership.md
  - RemoveMembership.md
  - ChangeMembershipRole.md
---

# CreateMembership

## Objectif

La commande `CreateMembership` crée une nouvelle appartenance entre un `User` et un `Workspace`.

Elle attribue au nouveau `Membership` un `Role` appartenant au même `Workspace`.

Le résultat métier attendu est :

```text
one active Membership
for one User
inside one Workspace
with one Role
```

La commande ne doit jamais créer plusieurs appartenances pour le même couple :

```text
UserId + WorkspaceId
```

---

## Agrégat concerné

`Membership`

Le `Membership` constitue la racine de l’agrégat créé.

La commande référence également :

- un `User` ;
- un `Workspace` ;
- un `Role`.

Ces éléments ne sont pas créés ou modifiés par cette commande.

---

## Acteur

La commande peut être demandée par :

- un `User` autorisé à gérer les membres du `Workspace` ;
- un workflow d’acceptation d’`Invitation` ;
- un workflow de création de `Workspace` ;
- un processus administratif ;
- un processus système explicitement autorisé.

L’origine de la création doit toujours être identifiable.

---

## Sources de création

La création peut provenir de plusieurs parcours métier.

Valeurs recommandées :

```text
Invitation
WorkspaceCreation
ManualAdministration
SystemProvisioning
ExternalSynchronization
```

### Invitation

Le `Membership` est créé après l’acceptation d’une `Invitation`.

### WorkspaceCreation

Le `Membership` initial du créateur du `Workspace` est créé pendant le workflow de création.

### ManualAdministration

Un administrateur autorisé ajoute directement un `User`.

Cette capacité ne doit exister que si la politique produit l’autorise.

### SystemProvisioning

Un processus système crée l’appartenance selon une règle explicite.

### ExternalSynchronization

L’appartenance est issue d’un système externe faisant autorité pour ce parcours.

Cette source nécessite une politique d’intégration et de réconciliation dédiée.

---

## Permission requise

Lorsqu'un `User` demande directement la création, la permission canonique est :

```text
workspace.members.create
```

Le droit de créer un `Membership` ne signifie pas nécessairement que l’acteur peut attribuer n’importe quel `Role`.

L’autorisation doit prendre en compte :

```text
Actor
Workspace
TargetUser
TargetRole
CreationSource
```

---

## Cas sans permission préalable

Certains workflows peuvent créer un `Membership` sans qu’un acteur humain possède déjà une permission dans le `Workspace`.

Exemples :

```text
WorkspaceCreation
InvitationAcceptance
SystemProvisioning
```

Dans ces situations, le workflow lui-même constitue l’autorité.

Cette autorité doit être :

- explicitement identifiée ;
- limitée au cas d’usage ;
- vérifiée par le service applicatif ;
- enregistrée dans l’audit.

---

## Préconditions

Avant l’exécution de `CreateMembership`, les conditions suivantes doivent être satisfaites :

- le `User` existe ;
- le `User` est actif ;
- le `Workspace` existe ;
- le `Workspace` accepte la création d’un membre ;
- le `Role` existe ;
- le `Role` est actif ;
- le `Role` appartient au `Workspace` ;
- le `Role` est attribuable dans ce contexte ;
- aucun `Membership` n’existe déjà pour le couple `UserId + WorkspaceId` ;
- aucun ancien `Membership` ne doit être restauré à la place ;
- l’acteur ou le workflow est autorisé ;
- la source de création est valide ;
- les éventuelles références de source sont cohérentes ;
- la création respecte les limites du `Workspace`.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `MembershipId` | `MembershipId` | Oui | Identifie le nouveau `Membership`. |
| `UserId` | `UserId` | Oui | Identifie le `User` qui rejoint le `Workspace`. |
| `WorkspaceId` | `WorkspaceId` | Oui | Identifie le périmètre d’appartenance. |
| `RoleId` | `RoleId` | Oui | Identifie le `Role` initial. |
| `CreatedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’origine opérationnelle. |
| `CreatedAt` | Instant | Oui | Date la création métier. |
| `CreationSource` | `MembershipCreationSource` | Oui | Indique le parcours ayant produit l’appartenance. |
| `CreationRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `InvitationId` | `InvitationId` | Conditionnel | Référence l’`Invitation` acceptée. |
| `ExternalReference` | `ExternalReference` | Conditionnel | Référence une source externe. |
| `CorrelationId` | Identifiant | Non | Relie la création au workflow appelant. |
| `Metadata` | Données structurées | Non | Informations non décisionnelles et contrôlées. |

---

## MembershipCreationSource

Valeurs recommandées :

```text
Invitation
WorkspaceCreation
ManualAdministration
SystemProvisioning
ExternalSynchronization
```

La source influence :

- les contrôles d’autorisation ;
- les références obligatoires ;
- l’audit ;
- les notifications ;
- les règles de restauration ;
- la réconciliation avec les systèmes externes.

Elle ne doit pas modifier les invariants fondamentaux du `Membership`.

---

## Validation des données

### MembershipId

Le `MembershipId` doit :

- être unique ;
- être généré avant la persistance ;
- rester stable pendant les retries ;
- ne contenir aucune information métier encodée ;
- rester distinct des autres identifiants.

Une même demande rejouée ne doit pas générer plusieurs `MembershipId`.

---

### UserId

Le `UserId` doit identifier un `User` existant.

Le `User` doit être dans un état permettant une nouvelle appartenance.

Un `User` désactivé, supprimé ou bloqué selon la politique ne peut pas recevoir un nouveau `Membership`.

---

### WorkspaceId

Le `WorkspaceId` doit identifier un `Workspace` existant.

La commande ne doit pas déduire le `Workspace` uniquement à partir du `Role`.

Les deux références doivent être explicitement cohérentes.

---

### RoleId

Le `RoleId` doit identifier un `Role` :

- existant ;
- actif ;
- appartenant au `Workspace` ;
- attribuable au `User` dans ce contexte ;
- compatible avec la source de création.

La condition suivante doit être vraie :

```text
Role.WorkspaceId = WorkspaceId
```

---

### CreatedBy

Lorsque `CreatedBy` est un `UserId`, l’acteur doit être autorisé.

Lorsque `CreatedBy` est un `SystemActor`, ce processus doit être explicitement reconnu.

Une valeur générique telle que :

```text
System
```

sans identité plus précise est insuffisante pour les opérations sensibles.

Exemples plus précis :

```text
InvitationAcceptanceWorkflow
WorkspaceProvisioningWorkflow
DirectorySynchronization
AdministrativeRecovery
```

---

### CreatedAt

`CreatedAt` représente l’instant métier de création.

Il doit être fourni par une abstraction d’horloge fiable telle que :

```text
Clock
```

Il ne doit pas être réécrit lors d’un retry.

---

### CreationRequestId

Le `CreationRequestId` doit :

- identifier une seule intention logique ;
- rester stable pendant les retries techniques ;
- permettre de retrouver le résultat initial ;
- ne contenir aucune donnée sensible ;
- être unique dans un périmètre permettant la déduplication.

---

### InvitationId

Lorsque :

```text
CreationSource = Invitation
```

alors `InvitationId` est obligatoire.

L’`Invitation` doit :

- exister ;
- correspondre au `User` ;
- correspondre au `Workspace` ;
- prévoir le même `Role` ;
- être en cours d’acceptation valide ;
- ne pas avoir déjà produit un autre `Membership`.

---

### ExternalReference

Lorsque :

```text
CreationSource = ExternalSynchronization
```

une référence externe stable peut être obligatoire.

Elle permet :

- la déduplication ;
- la réconciliation ;
- l’audit ;
- la traçabilité de la source d’autorité.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Valider l’identité de la demande

Le système vérifie :

- le format des identifiants ;
- la présence des données obligatoires ;
- la validité de `CreationSource` ;
- la cohérence des références conditionnelles ;
- l’idempotence de la demande.

---

### 2. Charger le User

Le système charge le `User` identifié par `UserId`.

Si le `User` n’existe pas, la commande échoue.

---

### 3. Vérifier l’état du User

Le `User` doit pouvoir rejoindre un `Workspace`.

Les états incompatibles sont :

```text
PendingVerification
Disabled
Removed
```

---

### 4. Vérifier le Workspace

Le système demande au bounded context propriétaire du `Workspace` de confirmer que celui-ci :

- existe ;
- est actif ;
- accepte encore de nouveaux membres ;
- n’a pas atteint une limite bloquante ;
- permet la source de création concernée.

Le bounded context `Identity` ne doit pas reconstruire silencieusement les règles métier du `Workspace`.

---

### 5. Charger le Role

Le système charge le `Role` identifié par `RoleId`.

Si le `Role` n’existe pas, la commande échoue.

---

### 6. Vérifier la compatibilité du Role

Le système vérifie :

```text
Role.WorkspaceId = WorkspaceId
```

Il vérifie également que le `Role` :

- est actif ;
- peut être attribué ;
- n’est pas réservé à un workflow différent ;
- respecte la hiérarchie d’autorisation ;
- ne viole pas une politique de séparation des responsabilités.

---

### 7. Autoriser l’acteur ou le workflow

Pour une création manuelle, le système vérifie que l’acteur :

- possède la permission requise ;
- appartient au même `Workspace` ;
- peut attribuer le `Role` cible ;
- n’agit pas au-delà de son niveau d’autorité.

Pour un workflow, le système vérifie que :

- la source est autorisée ;
- les références associées sont valides ;
- l’intention est limitée à ce cas d’usage.

---

### 8. Rechercher un Membership existant

Le système recherche toute appartenance correspondant à :

```text
UserId + WorkspaceId
```

Cette recherche doit inclure les états non actifs et les enregistrements logiquement supprimés.

Cas possibles :

```text
No Membership
Active Membership
Suspended Membership
Removed Membership
```

---

### 9. Refuser ou rediriger selon l’état existant

#### Aucun Membership

La création peut continuer.

#### Membership actif

La création échoue ou retourne le résultat idempotent lorsqu’il s’agit du même processus déjà réussi.

#### Membership suspendu

La création échoue.

Une commande dédiée doit traiter la suspension.

#### Membership supprimé

La création ne doit pas produire automatiquement une nouvelle entité.

Le workflow doit utiliser :

```text
RestoreMembership
```

si la restauration est autorisée.

---

### 10. Vérifier les contraintes du Workspace

Le système vérifie les éventuelles limites telles que :

- nombre maximal de membres ;
- nombre maximal d’owners ;
- restrictions de domaine ;
- exigences de licence ;
- restrictions organisationnelles ;
- règles liées au type de `Workspace`.

Ces règles peuvent appartenir au bounded context `Workspace`.

Leur résultat doit être obtenu par un contrat d’intégration explicite.

---

### 11. Créer le Membership

L’agrégat `Membership` est créé avec :

```text
MembershipId
UserId
WorkspaceId
RoleId
Status = Active
CreatedAt
CreatedBy
CreationSource
```

Le `Membership` doit être complet dès sa création.

Il ne peut pas exister temporairement sans `Role`.

---

### 12. Enregistrer la référence d’origine

Lorsque la source est une `Invitation`, le `Membership` conserve une référence telle que :

```text
SourceInvitationId
```

Cette référence permet d’établir la traçabilité :

```text
Invitation
    ↓
Membership
```

Elle ne doit pas permettre de modifier le `Membership` en contournant ses commandes.

---

### 13. Produire l’événement

L’agrégat produit :

```text
MembershipCreated
```

Les projections, notifications et intégrations sont traitées après la transaction métier.

---

## Résultat attendu

Après une exécution réussie :

- un seul `Membership` existe pour `UserId + WorkspaceId` ;
- son état est `Active` ;
- il référence le bon `User` ;
- il référence le bon `Workspace` ;
- il possède le `Role` demandé ;
- son origine est traçable ;
- aucun autre agrégat n’est modifié directement ;
- la répétition de la même demande ne crée aucun doublon.

État conceptuel :

```text
Membership
├── MembershipId
├── UserId
├── WorkspaceId
├── RoleId
├── Status: Active
├── CreatedAt
├── CreatedBy
├── CreationSource
└── SourceReference
```

---

## Statut initial

Le statut initial recommandé est :

```text
Active
```

La création d’un `Membership` dans un état intermédiaire tel que :

```text
Pending
```

n’est pas recommandée sans besoin métier explicite.

Une invitation en attente ne constitue pas encore un `Membership`.

La séparation correcte est :

```text
Pending Invitation
```

puis :

```text
Active Membership
```

après acceptation.

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` peut exister pour :

```text
UserId + WorkspaceId
```

Cette unicité couvre les états actifs et historiques selon la stratégie de suppression retenue.

---

### `IDN-INV-002`

Le `Membership` doit référencer un `User`, un `Workspace` et un `Role` valides.

---

### `IDN-INV-003`

Un `Membership` possède exactement un `Role`.

Il ne peut pas être créé sans rôle.

---

### `IDN-INV-004`

Le `Membership` nouvellement créé est dans un état métier valide.

---

### `IDN-INV-005`

Le `Role` et le `Membership` appartiennent au même `Workspace`.

```text
Membership.WorkspaceId = Role.WorkspaceId
```

---

### `IDN-INV-006`

La création ne doit pas conduire à un `Workspace` sans owner lorsque ce workflow concerne la propriété initiale.

---

### `IDN-INV-010`

Les `Permission` ne sont jamais attribuées directement au `Membership`.

Elles sont obtenues par le `Role`.

---

### `IDN-INV-014`

L’autorisation est évaluée dans le contexte du `Workspace`.

---

### `IDN-INV-015`

Le `Workspace` doit autoriser l’opération.

---

## Contrainte d’unicité

Une contrainte persistante doit compléter l’invariant métier.

Recommandation :

```text
UNIQUE(UserId, WorkspaceId)
```

Lorsque les `Membership` supprimés sont conservés dans la même table, cette contrainte reste généralement globale.

La restauration doit alors réutiliser le même enregistrement.

Une contrainte limitée aux seuls membres actifs pourrait autoriser plusieurs historiques parallèles et complexifier fortement le modèle.

---

## Événement produit

### MembershipCreated

La commande produit :

```text
MembershipCreated
```

L’événement peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `RoleId`
- `Status`
- `CreatedAt`
- `CreatedBy`
- `CreationSource`
- `InvitationId`
- `ExternalReference`
- `CreationRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- de secret ;
- de token d’invitation ;
- de données de session ;
- la liste complète des permissions ;
- des données personnelles non nécessaires.

---

## Événements non produits

La commande ne produit pas :

```text
UserCreated
WorkspaceCreated
RoleCreated
PermissionGranted
InvitationAccepted
MembershipRestored
```

Lorsque la création intervient après une invitation, `InvitationAccepted` est produit par le workflow ou l’agrégat `Invitation`, et non par `CreateMembership`.

---

## Erreurs métier

### MembershipAlreadyExists

Un `Membership` existe déjà pour :

```text
UserId + WorkspaceId
```

Cette erreur ne doit pas être retournée lorsqu’il s’agit de la reprise idempotente du même résultat.

---

### MembershipMustBeRestored

Un ancien `Membership` supprimé existe.

Le workflow doit utiliser :

```text
RestoreMembership
```

---

### MembershipSuspended

Un `Membership` suspendu existe déjà.

La création d’une nouvelle entité ne peut pas contourner la suspension.

---

### UserNotFound

Le `User` n’existe pas.

---

### UserUnavailable

Le `User` n’est pas dans un état compatible avec une nouvelle appartenance.

---

### WorkspaceNotFound

Le `Workspace` n’existe pas.

---

### WorkspaceUnavailable

Le `Workspace` n’accepte pas la création.

---

### WorkspaceMemberLimitReached

La limite de membres est atteinte.

---

### RoleNotFound

Le `Role` n’existe pas.

---

### RoleDisabled

Le `Role` est inactif.

---

### RoleBelongsToAnotherWorkspace

La condition suivante est fausse :

```text
Role.WorkspaceId = WorkspaceId
```

---

### RoleNotAssignable

Le `Role` ne peut pas être attribué dans ce contexte.

---

### ActorNotAuthorized

L’acteur ne possède pas l’autorité nécessaire.

---

### RoleAssignmentNotAuthorized

L’acteur peut gérer des membres, mais ne peut pas attribuer le `Role` demandé.

---

### InvalidCreationSource

La source de création n’est pas reconnue.

---

### MissingSourceReference

Une référence obligatoire manque.

Exemples :

```text
CreationSource = Invitation
AND
InvitationId is missing
```

```text
CreationSource = ExternalSynchronization
AND
ExternalReference is missing
```

---

### InvitationNotCompatible

L’`Invitation` ne correspond pas au `User`, au `Workspace` ou au `Role`.

---

### CreationConflict

Une autre opération concurrente a créé ou restauré le `Membership`.

---

## Idempotence

`CreateMembership` doit être idempotente pour :

```text
CreationRequestId
```

ou, selon le périmètre choisi :

```text
WorkspaceId + CreationRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- créer un second `Membership` ;
- produire un nouvel événement métier ;
- modifier `CreatedAt` ;
- remplacer le `Role` initial ;
- changer la source de création ;
- répéter des effets externes non idempotents.

---

## Idempotence par source

Une contrainte fonctionnelle complémentaire peut être appliquée.

### Invitation

```text
one Invitation
produces at most one Membership
```

Clé possible :

```text
InvitationId
```

### ExternalSynchronization

```text
ExternalSystem + ExternalReference
```

### WorkspaceCreation

```text
WorkspaceId + InitialOwner
```

Ces clés ne remplacent pas l’unicité fondamentale :

```text
UserId + WorkspaceId
```

---

## Reprise après réponse perdue

Cas typique :

```text
CreateMembership succeeds

↓

MembershipCreated is persisted

↓

Response is lost

↓

Caller retries
```

La seconde exécution doit retrouver :

- le même `CreationRequestId` ;
- le même `MembershipId` ;
- le même `UserId` ;
- le même `WorkspaceId` ;
- le même `RoleId`.

Elle retourne le résultat initial.

---

## Concurrence

### Deux créations pour le même User et Workspace

Deux commandes concurrentes peuvent tenter de créer :

```text
UserId + WorkspaceId
```

Une seule doit réussir.

La seconde doit :

- retrouver le résultat idempotent ;
- ou échouer avec `MembershipAlreadyExists`.

La contrainte persistante d’unicité constitue la dernière ligne de défense.

---

### CreateMembership contre RestoreMembership

Lorsque les deux opérations ciblent le même couple :

```text
UserId + WorkspaceId
```

la présence d’un ancien `Membership` doit conduire à la restauration, pas à la création d’une nouvelle entité.

Une seule opération doit être validée.

---

### CreateMembership contre SuspendMembership

Une suspension ne peut normalement cibler qu’un `Membership` existant.

Si elle est déclenchée immédiatement après la création, l’ordre des transactions détermine l’état final.

Le résultat doit rester cohérent et auditable.

---

### CreateMembership contre RemoveMembership

Une suppression concurrente juste après la création doit être gérée par versionnement ou verrouillage.

Le système ne doit pas produire un état invisible où le `Membership` serait créé puis perdu sans événements cohérents.

---

### CreateMembership contre ChangeMembershipRole

Le changement de rôle ne peut s’appliquer qu’après la création.

Une commande concurrente doit utiliser la version créée ou échouer avec une erreur de concurrence.

---

## Atomicité

La transition suivante doit être atomique :

```text
verify uniqueness
+
create Membership
+
assign initial Role
+
record source
+
record domain event
```

Les états suivants sont interdits :

```text
Membership exists
AND
RoleId is missing
```

```text
Membership exists
AND
MembershipCreated event is not recorded for publication
```

```text
multiple Memberships exist
for the same UserId + WorkspaceId
```

Une outbox transactionnelle peut garantir la publication fiable de l’événement.

---

## Relation avec Invitation

Lorsque la source est `Invitation`, le workflow recommandé est :

```text
Validate Invitation
↓
CreateMembership
↓
MembershipCreated
↓
Accept Invitation
↓
InvitationAccepted
```

Le `Membership` créé doit reprendre exactement :

```text
UserId = Invitation recipient User
WorkspaceId = Invitation.WorkspaceId
RoleId = Invitation.RoleId
```

La commande ne valide pas seule l’intégralité du token d’invitation.

Cette responsabilité appartient au workflow d’acceptation.

---

## Relation avec WorkspaceCreation

Lors de la création d’un `Workspace`, un premier `Membership` doit généralement être créé pour son initiateur.

Le workflow peut être :

```text
CreateWorkspace
↓
WorkspaceCreated
↓
CreateMembership as initial Owner
↓
MembershipCreated
```

Le `Role` attribué doit avoir `RoleSystemType = Owner`.

Le workflow ne doit jamais laisser durablement un `Workspace` sans propriétaire actif.

---

## Premier owner

La création du premier owner constitue un cas particulier.

Elle peut être autorisée par :

```text
WorkspaceCreationWorkflow
```

sans qu’un autre membre possède déjà la permission requise.

Les conditions doivent inclure :

- le `Workspace` vient d’être créé ;
- aucun `Membership` n’existe encore ;
- le `User` correspond au créateur attendu ;
- le `Role` est le rôle owner autorisé ;
- la demande est idempotente.

---

## Création manuelle

La création manuelle permet à un administrateur d’ajouter un `User` sans passer par une `Invitation`.

Cette capacité présente davantage de risques.

Elle doit préciser :

- si l’accord du `User` est requis ;
- si son adresse doit être vérifiée ;
- si une notification est envoyée ;
- quels rôles peuvent être attribués ;
- quelles traces d’audit sont conservées ;
- si la politique du `Workspace` l’autorise.

Lorsque le consentement du destinataire est nécessaire, le parcours par `Invitation` doit être privilégié.

---

## Synchronisation externe

Une source externe peut créer des `Membership`.

La politique doit définir :

- le système faisant autorité ;
- la clé externe ;
- la fréquence de synchronisation ;
- le traitement des suppressions ;
- le traitement des conflits ;
- la correspondance des rôles ;
- la reprise en cas d’indisponibilité ;
- la protection contre les doublons.

Le système externe ne doit pas contourner les invariants du domaine.

---

## Attribution du Role initial

Le `Role` est obligatoire à la création.

Il ne doit pas être déduit silencieusement lorsqu’il est fourni explicitement par un workflow.

Lorsqu’un rôle par défaut existe, la résolution doit être réalisée avant la commande.

La commande reçoit alors un `RoleId` déterminé.

Cette séparation rend l’intention explicite :

```text
Resolve default Role
↓
CreateMembership with resolved RoleId
```

---

## Permission et hiérarchie des rôles

La possession de :

```text
workspace.members.create
```

ne permet pas nécessairement d’attribuer tous les rôles.

Une règle complémentaire peut être :

```text
Actor may assign TargetRole
```

Cette règle peut dépendre :

- du rôle de l’acteur ;
- du niveau du rôle cible ;
- de la qualité d’owner ;
- d’une permission spécifique ;
- de la source de création.

---

## Permissions effectives

La commande n’enregistre pas directement les `Permission`.

Les permissions effectives résultent de :

```text
Membership
↓
Role
↓
Permission
```

L’événement `MembershipCreated` peut entraîner une mise à jour des projections d’autorisation.

Il ne doit pas dupliquer la liste complète des permissions dans l’agrégat `Membership`.

---

## Notifications

Après `MembershipCreated`, un handler peut :

- informer le nouveau membre ;
- informer les administrateurs ;
- préparer les ressources d’onboarding ;
- mettre à jour les projections ;
- synchroniser un annuaire ;
- déclencher une analyse de licence.

Les notifications ne font pas partie de la transaction métier principale.

Un échec de notification ne doit pas annuler la création.

---

## Intégrations

Des systèmes externes peuvent consommer :

```text
MembershipCreated
```

Exemples :

- audit centralisé ;
- facturation ;
- provisioning ;
- annuaire ;
- analytics ;
- notifications ;
- contrôle de licence.

Les consommateurs doivent être idempotents.

Ils ne doivent pas considérer la réception de l’événement comme une permission de modifier directement l’agrégat.

---

## Sécurité

La commande doit garantir que :

- l’acteur agit dans le bon `Workspace` ;
- le `Role` appartient au même `Workspace` ;
- le `Role` est attribuable ;
- un acteur ne peut pas attribuer un rôle supérieur sans autorisation ;
- aucun doublon n’est créé ;
- une suspension ou suppression antérieure n’est pas contournée ;
- les données d’origine sont traçables ;
- aucun secret n’est présent dans l’événement ;
- les références externes sont validées.

---

## Confidentialité

La commande et son événement doivent minimiser les données exposées.

Ils ne doivent pas publier inutilement :

- l’adresse e-mail complète du `User` ;
- son profil complet ;
- la liste des permissions ;
- les autres appartenances ;
- les données internes du `Workspace`.

Les identifiants métier suffisent généralement aux consommateurs autorisés.

---

## Audit

Une création réussie doit enregistrer :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `RoleId`
- `CreatedAt`
- `CreatedBy`
- `CreationSource`
- référence d’origine
- `CreationRequestId`
- `CorrelationId`
- résultat

L’audit doit également permettre de déterminer :

```text
who created the Membership
why it was created
which authority allowed it
which Role was assigned
```

---

## Décisions de conception

### Membership est créé directement Active

Une appartenance en attente est représentée par une `Invitation`, pas par un `Membership` incomplet.

Le `Membership` commence lorsque l’accès devient effectif.

---

### User, Workspace et Role doivent déjà exister

La commande ne crée pas d’autres agrégats.

Elle reçoit des références déjà déterminées et validées.

---

### Le Role est obligatoire

Un `Membership` sans `Role` ne permet pas d’évaluer correctement les autorisations.

Le rôle initial fait partie de la création atomique.

---

### La création ne remplace pas une restauration

Lorsqu’un ancien `Membership` existe, il doit être restauré ou rester supprimé selon une décision explicite.

Créer une nouvelle entité masquerait l’historique et fragiliserait l’unicité.

---

### Les Permission ne sont pas copiées

Le `Membership` référence un `Role`.

Les permissions restent définies par le rôle et ne sont pas dupliquées dans le `Membership`.

---

### La source est explicite

La création manuelle, l’acceptation d’une invitation et le provisioning système ne représentent pas la même origine métier.

La source doit donc être conservée.

---

### L’unicité est protégée à plusieurs niveaux

La protection repose sur :

- la vérification métier ;
- l’idempotence ;
- le contrôle de concurrence ;
- la contrainte persistante.

---

### Le MembershipId reste stable

Les retries d’une même création doivent retrouver le même agrégat.

Ils ne doivent pas générer de nouveaux identifiants.

---

## Synthèse

`CreateMembership` crée l’appartenance effective d’un `User` à un `Workspace`.

Elle garantit que :

- le `User`, le `Workspace` et le `Role` existent ;
- le `Role` appartient au bon `Workspace` ;
- l’acteur ou le workflow est autorisé ;
- aucun `Membership` compatible n’existe déjà ;
- un ancien `Membership` n’est pas contourné ;
- le nouveau `Membership` est immédiatement `Active` ;
- il possède exactement un `Role` ;
- sa source est traçable ;
- les retries et opérations concurrentes ne créent aucun doublon.

Le résultat final est :

```text
User
+
Workspace
+
Role
↓
Active Membership
```

avec l’unicité suivante :

```text
one Membership per UserId + WorkspaceId
```
