---
id: IDN-CMD-CHANGE-MEMBERSHIP-ROLE
title: ChangeMembershipRole
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-31

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
  - CreateMembership.md
  - RestoreMembership.md
  - SuspendMembership.md
  - ReactivateMembership.md
  - RemoveMembership.md
---

# ChangeMembershipRole

## Objectif

La commande `ChangeMembershipRole` modifie le `Role` attribué à un `Membership`.

Elle fait passer le membre de :

```text
Current Role
    ↓
Target Role
```

sans modifier :

- le `MembershipId` ;
- le `UserId` ;
- le `WorkspaceId` ;
- l’état du `Membership` ;
- l’identité du `User` ;
- la définition des `Role` concernés.

La commande modifie uniquement la relation entre le `Membership` et son `Role`.

---

## Signification métier

Le changement de rôle représente une modification explicite du niveau d’autorité d’un membre dans un `Workspace`.

Il peut correspondre à :

- une promotion ;
- une rétrogradation ;
- un changement de fonction ;
- une évolution organisationnelle ;
- une correction administrative ;
- une réduction de privilèges ;
- une séparation des responsabilités ;
- une synchronisation avec une source externe ;
- une réaffectation après changement de politique.

Exemples :

```text
Member
↓
Manager
```

```text
Manager
↓
Member
```

```text
Owner
↓
Administrator
```

```text
CustomRoleA
↓
CustomRoleB
```

---

## Résultat attendu

Le résultat nominal est :

```text
Active Membership
with CurrentRole
    ↓
Active Membership
with TargetRole
```

Le `Membership` reste actif.

Ses permissions effectives sont recalculées à partir du nouveau rôle.

---

## Agrégat concerné

`Membership`

Le `Membership` constitue la racine de l’agrégat modifié.

La commande consulte également :

- le `Role` actuel ;
- le `Role` cible ;
- le `Workspace` ;
- le `Membership` de l’acteur ;
- le `Role` de l’acteur ;
- les owners actifs du `Workspace` ;
- les politiques d’attribution de rôles ;
- les restrictions de sécurité.

La commande ne modifie pas directement ces objets.

---

## Acteur

La commande peut être demandée par :

- un `User` autorisé à gérer les rôles ;
- un `Owner` du `Workspace` ;
- un administrateur ;
- un workflow d’administration ;
- un système externe faisant autorité ;
- un processus de synchronisation ;
- un workflow de sécurité ;
- un processus de gouvernance.

L’acteur doit toujours être identifiable.

---

## Permission requise

La permission canonique est :

```text
workspace.members.change-role
```

La possession de cette permission est nécessaire mais non suffisante.

Elle doit également tenir compte de :

```text
Actor
ActorRole
TargetMembership
CurrentRole
TargetRole
Workspace
ChangeType
ChangeSource
```

Les promotions, rétrogradations et changements impliquant le rôle owner sont
des cas de politique contextuelle ; ils n'introduisent pas de clés de permission
supplémentaires en 1.0.

---

## Principe d’autorisation

La commande doit vérifier deux choses distinctes :

```text
Actor may change the Membership Role
```

et :

```text
Actor may assign the Target Role
```

Un acteur peut être autorisé à modifier certains rôles sans pouvoir attribuer tous les rôles disponibles.

Exemple :

```text
TeamManager
```

peut éventuellement attribuer :

```text
Member
Contributor
Viewer
```

mais pas :

```text
Owner
Administrator
```

---

## Cas sans permission utilisateur directe

Certains workflows peuvent demander un changement de rôle sans acteur humain ordinaire.

Exemples :

```text
ExternalDirectorySynchronization
WorkspaceProvisioning
ComplianceWorkflow
SecurityWorkflow
AdministrativeRecovery
```

Leur autorité doit être :

- explicite ;
- limitée à certains rôles ;
- limitée à certains `Workspace` ;
- auditée ;
- réévaluée à chaque opération ;
- incapable de contourner l’invariant du dernier owner.

---

## Préconditions

Avant l’exécution de `ChangeMembershipRole`, les conditions suivantes doivent être satisfaites :

- le `Membership` existe ;
- le `Membership` est `Active` ;
- le `User` existe ;
- le `User` est actif ;
- le `Workspace` existe ;
- le rôle actuel existe ;
- le rôle cible existe ;
- le rôle cible est actif ;
- le rôle cible appartient au même `Workspace` ;
- le rôle cible est attribuable ;
- le rôle cible est différent du rôle actuel ;
- l’acteur ou le workflow est autorisé ;
- l’acteur peut attribuer le rôle cible ;
- la hiérarchie des rôles est respectée ;
- la séparation des responsabilités est respectée ;
- le changement ne laisse pas le `Workspace` sans owner actif ;
- aucune restriction permanente ne bloque le changement ;
- aucune opération concurrente incompatible n’a déjà gagné ;
- les limites et politiques du `Workspace` sont respectées.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `MembershipId` | `MembershipId` | Oui | Identifie le membre dont le rôle doit changer. |
| `TargetRoleId` | `RoleId` | Oui | Identifie le nouveau rôle. |
| `ChangedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’auteur ou l’origine du changement. |
| `ChangedAt` | Instant | Oui | Date le changement métier. |
| `ChangeSource` | `MembershipRoleChangeSource` | Oui | Indique le workflow à l’origine de la demande. |
| `ChangeRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |
| `Reason` | `MembershipRoleChangeReason` | Oui | Motif structuré du changement. |

Données facultatives ou conditionnelles :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `Comment` | Texte court | Non | Précision interne facultative. |
| `CaseReference` | Identifiant | Conditionnel | Référence un dossier ou une décision. |
| `ExternalReference` | Identifiant | Conditionnel | Référence une source externe. |
| `CorrelationId` | Identifiant | Non | Relie la commande à un workflow plus large. |
| `RequireReauthentication` | Booléen | Non | Exige une nouvelle authentification après changement. |
| `RevokeExistingSessions` | Booléen | Non | Demande la révocation de sessions existantes. |

---

## MembershipRoleChangeSource

Valeurs recommandées :

```text
ManualAdministration
WorkspaceGovernance
ExternalSynchronization
SecurityWorkflow
ComplianceWorkflow
AdministrativeRecovery
SystemProvisioning
```

La source influence :

- l’autorité requise ;
- les références obligatoires ;
- les contrôles supplémentaires ;
- les notifications ;
- l’audit ;
- le traitement des sessions ;
- la politique de réconciliation.

---

## MembershipRoleChangeReason

Valeurs recommandées :

```text
Promotion
Demotion
ResponsibilityChange
OrganizationalChange
AccessReduction
SecurityDecision
ComplianceRequirement
AdministrativeCorrection
ExternalDirectoryUpdate
RoleDeprecation
SeparationOfDuties
Other
```

Le motif doit exprimer une décision métier.

Il ne doit pas se réduire à :

```text
Updated
Changed
ManualEdit
```

---

## Classification du changement

Le système peut classer la transition en fonction des rôles.

Valeurs conceptuelles :

```text
Promotion
Demotion
LateralChange
OwnerPromotion
OwnerDemotion
PrivilegeIncrease
PrivilegeReduction
PrivilegeReshaping
```

Cette classification peut être calculée dans une politique de domaine.

Elle ne doit pas reposer uniquement sur le nom des rôles.

---

## Promotion et rétrogradation

La notion de promotion suppose qu’une hiérarchie métier existe entre les rôles.

Exemple :

```text
Viewer < Member < Manager < Administrator < Owner
```

Cependant, une telle hiérarchie ne doit pas être supposée universellement.

Dans un modèle avec rôles personnalisés, deux rôles peuvent être incomparables.

Exemple :

```text
BillingManager
```

et :

```text
TechnicalManager
```

peuvent accorder des privilèges différents sans relation hiérarchique simple.

Le domaine doit donc privilégier :

- des politiques explicites d’attribution ;
- des restrictions par rôle ;
- des permissions de délégation ;
- éventuellement un niveau de privilège ;
- éventuellement un graphe de rôles assignables.

---

## Validation des données

### MembershipId

Le `MembershipId` doit identifier un `Membership` existant.

La commande ne doit pas cibler un membre uniquement à partir de données personnelles ou d’un e-mail.

---

### TargetRoleId

Le `TargetRoleId` doit identifier un rôle :

- existant ;
- actif ;
- attribuable ;
- appartenant au même `Workspace` ;
- compatible avec le membre ;
- compatible avec la politique du `Workspace`.

Condition obligatoire :

```text
TargetRole.WorkspaceId = Membership.WorkspaceId
```

---

### ChangedBy

Lorsque `ChangedBy` est un `UserId`, l’acteur doit :

- exister ;
- être actif ;
- posséder un `Membership` actif dans le même `Workspace` ;
- posséder la permission requise ;
- pouvoir agir sur le membre cible ;
- pouvoir attribuer le rôle cible ;
- respecter les restrictions de self-management.

Lorsque `ChangedBy` est un `SystemActor`, son autorité doit être explicitement définie.

---

### ChangedAt

`ChangedAt` représente l’instant métier auquel le nouveau rôle devient effectif.

Il doit :

- être fourni par une abstraction `Clock` ;
- rester stable lors des retries ;
- être cohérent avec l’historique du `Membership` ;
- être utilisé par l’audit et les événements.

---

### ChangeRequestId

Le `ChangeRequestId` doit :

- identifier une intention logique unique ;
- rester stable pendant les retries ;
- permettre la restitution du résultat initial ;
- être unique dans son périmètre de déduplication ;
- ne contenir aucune donnée sensible.

---

### Reason

Le motif doit appartenir au catalogue métier.

Certaines valeurs peuvent imposer :

- une approbation ;
- une référence de dossier ;
- une justification ;
- une authentification renforcée ;
- une validation par un owner ;
- une revue de sécurité.

---

### CaseReference

Une référence peut être obligatoire pour :

```text
SecurityDecision
ComplianceRequirement
AdministrativeCorrection
SeparationOfDuties
```

Elle doit pointer vers une décision traçable.

---

### ExternalReference

Lorsque :

```text
ChangeSource = ExternalSynchronization
```

une référence externe stable doit être fournie.

Elle permet :

- la déduplication ;
- la réconciliation ;
- l’audit ;
- la détection de conflits ;
- la répétition sûre des synchronisations.

---

## Traitement métier

### 1. Charger le Membership

Le système charge le `Membership` identifié par `MembershipId`.

S’il n’existe pas, la commande échoue.

---

### 2. Vérifier l’état du Membership

Le `Membership` doit être :

```text
Active
```

#### Active

Le changement peut continuer.

#### Suspended

La politique recommandée est de refuser le changement.

Le rôle pourra être modifié dans le cadre de :

```text
ReactivateMembership
```

si cela fait partie de la décision de retour.

#### Removed

Le membre n’appartient plus effectivement au `Workspace`.

Le rôle pourra être déterminé dans :

```text
RestoreMembership
```

---

### 3. Vérifier l’idempotence

Le système recherche une opération déjà appliquée avec :

```text
MembershipId + ChangeRequestId
```

Si elle existe, le résultat initial est retourné sans nouvel effet.

---

### 4. Charger le Role actuel

Le système charge le rôle actuellement attribué au `Membership`.

Le rôle courant sert notamment à déterminer :

- la nature du changement ;
- les permissions perdues ;
- les permissions acquises ;
- la protection du dernier owner ;
- les restrictions de rétrogradation ;
- la hiérarchie applicable.

---

### 5. Charger le Role cible

Le système charge le rôle identifié par `TargetRoleId`.

S’il n’existe pas, la commande échoue.

---

### 6. Vérifier que les rôles sont différents

La condition suivante doit être vraie :

```text
Membership.RoleId != TargetRoleId
```

Si les rôles sont identiques, aucune transition métier n’existe.

Le système retourne :

```text
MembershipAlreadyHasRole
```

sauf reprise idempotente d’une demande déjà appliquée.

---

### 7. Vérifier le Workspace

Le système confirme que :

```text
CurrentRole.WorkspaceId = Membership.WorkspaceId
```

et :

```text
TargetRole.WorkspaceId = Membership.WorkspaceId
```

Un rôle d’un autre `Workspace` ne peut jamais être attribué.

---

### 8. Vérifier l’état du Role cible

Le rôle cible doit être :

```text
Active
```

Il doit également être :

- assignable ;
- non supprimé ;
- non archivé ;
- non réservé à un workflow différent ;
- compatible avec les règles du `Workspace`.

---

### 9. Charger le contexte de l’acteur

Le système charge :

- l’identité de l’acteur ;
- son `Membership` ;
- son `Role` ;
- ses permissions ;
- ses éventuelles restrictions ;
- ses capacités de délégation.

---

### 10. Vérifier l’autorisation générale

Le système vérifie que l’acteur peut modifier le rôle du membre cible.

Exemple :

```text
Actor has workspace.members.change-role
```

Cette permission est nécessaire mais peut être insuffisante.

---

### 11. Vérifier l’autorité d’attribution

Le système vérifie que l’acteur peut attribuer le rôle cible.

Modèles possibles :

#### Permission canonique et politique contextuelle

```text
Actor has workspace.members.change-role
AND assignment policy allows TargetRoleId
```

#### Liste de rôles assignables

```text
ActorRole.AssignableRoleIds contains TargetRoleId
```

#### Niveau de délégation

```text
Actor.DelegationLevel >= TargetRole.RequiredDelegationLevel
```

#### Politique de domaine

```text
RoleAssignmentPolicy.canAssign(
    actorRole,
    targetCurrentRole,
    targetRole
)
```

La politique explicite est recommandée.

---

### 12. Vérifier les restrictions liées au Role actuel

Le domaine peut interdire à certains acteurs de modifier un rôle protégé.

Exemples :

- un manager ne peut pas modifier un owner ;
- un administrateur ne peut pas rétrograder un owner ;
- un rôle de support ne peut pas agir sur un rôle de sécurité ;
- un membre ne peut pas modifier un rôle de même autorité.

Erreur possible :

```text
TargetRoleProtected
```

---

### 13. Vérifier les restrictions de self-management

Le domaine doit préciser si l’acteur peut modifier son propre rôle.

Cas possibles :

- interdit dans tous les cas ;
- autorisé uniquement pour une réduction de privilèges ;
- autorisé avec approbation ;
- autorisé pour quitter un rôle non critique ;
- interdit lorsqu’il est owner ;
- traité par une commande dédiée.

La politique recommandée est :

```text
self-promotion forbidden
```

et :

```text
self-demotion allowed only if last-owner invariant remains valid
```

Une commande spécialisée peut être préférable pour les parcours utilisateur :

```text
RelinquishOwnership
```

ou :

```text
ChangeOwnMembershipRole
```

---

### 14. Classifier le changement

Le système détermine notamment si le changement constitue :

- une promotion ;
- une rétrogradation ;
- une attribution du rôle owner ;
- une perte du rôle owner ;
- une augmentation de privilèges ;
- une réduction de privilèges ;
- un changement latéral.

Cette classification guide les contrôles suivants.

---

### 15. Protéger le dernier Owner actif

Si le rôle actuel confère la qualité d’owner et que le rôle cible ne la confère pas, la commande diminue le nombre d’owners actifs.

La condition obligatoire est :

```text
ActiveOwnerCountAfterChange >= 1
```

Le changement suivant est autorisé :

```text
Owner A
Owner B

Owner A
↓
Member
```

Le changement suivant est interdit :

```text
Owner A
Member B

Owner A
↓
Member
```

L’erreur métier recommandée est :

```text
WorkspaceMustHaveActiveOwner
```

Cette erreur exprime l’invariant commun aux commandes :

- `ChangeMembershipRole`
- `SuspendMembership`
- `RemoveMembership`
- éventuellement `DisableUser`

---

### 16. Vérifier l’attribution du Role Owner

Lorsqu’un rôle owner est attribué, des règles renforcées peuvent s’appliquer.

Exemples :

- seuls les owners peuvent nommer un owner ;
- une authentification récente est obligatoire ;
- une authentification multifacteur est requise ;
- une approbation supplémentaire est nécessaire ;
- le nombre d’owners peut être limité ;
- certaines identités ne peuvent pas devenir owner ;
- les comptes de service sont exclus ;
- un propriétaire légal peut être requis.

---

### 17. Vérifier la séparation des responsabilités

Le rôle cible peut être incompatible avec d’autres responsabilités détenues par le membre.

Exemples :

```text
BillingApprover
```

incompatible avec :

```text
PaymentInitiator
```

ou :

```text
SecurityAuditor
```

incompatible avec :

```text
SecurityAdministrator
```

Si le bounded context `Identity` ne possède pas toutes les informations nécessaires, il consomme une décision de politique explicite.

---

### 18. Vérifier les restrictions du User

Le système confirme que le `User` est compatible avec le rôle cible.

Exemples de restrictions :

- type de compte ;
- niveau de vérification ;
- authentification multifacteur ;
- statut employé ou externe ;
- domaine d’e-mail ;
- niveau de confiance ;
- restriction de sécurité ;
- compte de service ;
- compte temporaire.

---

### 19. Vérifier les limites du Workspace

Le `Workspace` peut définir :

- un nombre maximal d’owners ;
- un nombre maximal d’administrateurs ;
- des quotas par rôle ;
- des restrictions de licence ;
- des rôles réservés ;
- des rôles incompatibles avec certaines offres.

Le changement doit respecter ces limites.

---

### 20. Vérifier les références de workflow

Selon la source, le système vérifie :

#### ManualAdministration

L’acteur humain et son autorité.

#### ExternalSynchronization

La référence externe et la priorité de la source.

#### SecurityWorkflow

Le dossier de sécurité.

#### ComplianceWorkflow

La décision de conformité.

#### AdministrativeRecovery

La justification de correction.

---

### 21. Vérifier les opérations concurrentes

Le système vérifie qu’aucune autre commande n’a modifié :

- le statut du `Membership` ;
- son rôle ;
- le rôle cible ;
- l’état du `User` ;
- la qualité d’owner ;
- les politiques nécessaires à l’opération.

---

### 22. Appliquer le nouveau Role

L’agrégat remplace :

```text
CurrentRoleId
```

par :

```text
TargetRoleId
```

Il conserve :

- `MembershipId` ;
- `UserId` ;
- `WorkspaceId` ;
- `Status = Active`.

Il enregistre :

- `PreviousRoleId` ;
- `RoleId` ;
- `ChangedAt` ;
- `ChangedBy` ;
- `ChangeSource` ;
- `Reason` ;
- `ChangeRequestId` ;
- les références associées.

---

### 23. Recalculer le contexte d’autorisation

Le système considère immédiatement les permissions du rôle précédent comme obsolètes.

Les permissions effectives deviennent :

```text
Permissions(TargetRole)
```

et non :

```text
Permissions(CurrentRole)
UNION
Permissions(TargetRole)
```

Le changement doit être atomique.

---

### 24. Invalider les caches

Les éléments suivants doivent être invalidés ou versionnés :

- caches de permissions ;
- projections de rôles ;
- claims de session ;
- contextes d’autorisation ;
- tokens contenant des rôles ;
- décisions d’accès mémorisées ;
- index de recherche de membres.

---

### 25. Réévaluer les sessions

Selon la politique de sécurité, le système doit :

- révoquer les sessions ;
- exiger une nouvelle authentification ;
- incrémenter une version d’autorisation ;
- forcer le renouvellement du token ;
- recalculer les claims ;
- réévaluer l’accès à la requête suivante.

Une réduction de privilèges doit prendre effet immédiatement.

---

### 26. Produire l’événement

L’agrégat produit :

```text
MembershipRoleChanged
```

Les notifications et intégrations sont traitées hors de la transaction principale.

---

## État final

Après succès :

```text
Membership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── Status: Active
├── RoleId: TargetRoleId
├── PreviousRoleId
├── RoleChangedAt
├── RoleChangedBy
├── RoleChangeSource
├── RoleChangeReason
└── ChangeRequestId
```

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` existe pour :

```text
UserId + WorkspaceId
```

Le changement de rôle ne crée aucune nouvelle appartenance.

---

### `IDN-INV-002`

Le `Membership` continue de référencer un `User`, un `Workspace` et un `Role` valides.

---

### `IDN-INV-003`

Un `Membership` actif possède exactement un rôle.

---

### `IDN-INV-005`

Le rôle appartient au même `Workspace`.

```text
Membership.WorkspaceId = Role.WorkspaceId
```

---

### `IDN-INV-006`

Le `Workspace` conserve toujours au moins un owner actif.

```text
ActiveOwnerCountAfterChange >= 1
```

---

### `IDN-INV-010`

Les permissions sont obtenues uniquement par le rôle.

---

### `IDN-INV-011`

Une session doit réévaluer les permissions après un changement de rôle.

---

### `IDN-INV-014`

L’autorisation est évaluée dans le contexte exact du `Workspace`.

---

### `IDN-INV-015`

Le `Workspace` doit accepter le rôle cible et la transition.

---

## Transition de rôle

Transition conceptuelle autorisée :

```text
Active Membership with Role A
    ↓
Active Membership with Role B
```

avec :

```text
Role A != Role B
```

La commande ne modifie pas le statut du `Membership`.

---

## Transitions interdites

```text
Suspended Membership
    ↓
ChangeMembershipRole
```

```text
Removed Membership
    ↓
ChangeMembershipRole
```

```text
Role A
    ↓
Role A
```

hors reprise idempotente.

---

## Modélisation du Owner

Le rôle owner peut être modélisé de différentes manières :

- un type de rôle réservé ;
- une propriété `IsOwner` ;
- une capability spéciale ;
- un `RoleSystemType`;
- une permission structurelle.

La qualité d’owner ne doit pas être déduite uniquement du nom :

```text
Role.Name = "Owner"
```

Une propriété stable est nécessaire.

Exemple :

```text
Role.SystemType = Owner
```

---

## Protection du dernier Owner

Le changement suivant doit être évalué en fonction de l’état après opération :

```text
CurrentRole.IsOwner = true
AND
TargetRole.IsOwner = false
```

Alors :

```text
ActiveOwnerCountAfterChange =
CurrentActiveOwnerCount - 1
```

La commande réussit uniquement si :

```text
ActiveOwnerCountAfterChange >= 1
```

---

## Cohérence concurrente du dernier Owner

Une vérification naïve est insuffisante :

```text
count owners
then change role
```

Deux transactions pourraient rétrograder simultanément deux owners.

Exemple initial :

```text
Owner A
Owner B
```

Transaction 1 lit deux owners et rétrograde A.

Transaction 2 lit deux owners et rétrograde B.

Résultat incorrect :

```text
0 active owner
```

La stratégie doit empêcher cette situation.

---

## Stratégies de coordination

Stratégies possibles :

### Verrou de coordination sur Workspace

La commande verrouille une ressource logique associée au `Workspace`.

### Transaction sérialisable

Les transactions concurrentes sont sérialisées.

### Agrégat de gouvernance

Un agrégat propriétaire du `Workspace` coordonne les changements d’owner.

### Compteur fortement cohérent

Un compteur d’owners actifs est mis à jour atomiquement.

### Contrainte persistante spécialisée

Une contrainte technique protège l’invariant.

La solution choisie doit couvrir toutes les commandes pouvant réduire le nombre d’owners.

---

## Politique recommandée

Utiliser une coordination commune au niveau du `Workspace` pour :

```text
ChangeMembershipRole
SuspendMembership
RemoveMembership
DisableUser
```

L’invariant ne doit pas être implémenté différemment dans chaque commande.

---

## Rôle cible inactif

Un rôle inactif ne peut pas être attribué.

La commande retourne :

```text
RoleDisabled
```

Même si le `Membership` possédait précédemment ce rôle dans son historique.

---

## Rôle cible archivé

Un rôle archivé ne peut pas recevoir de nouvelles attributions.

Un rôle archivé peut éventuellement rester référencé par des membres existants pendant une migration, mais cette politique doit être explicitement définie.

La commande ne doit pas réintroduire un rôle archivé.

---

## Rôle système

Certains rôles peuvent être réservés au système.

Exemples :

```text
WorkspaceOwner
ExternalAuditor
ServiceAccount
BillingSystem
```

Le rôle expose une `RoleAssignmentPolicy` dont `AllowedSources` peut notamment
contenir :

```text
Manual
SystemOnly
InvitationOnly
SynchronizationOnly
```

La commande doit respecter la politique complète et sa version courante.

---

## Rôles personnalisés

Dans un système avec rôles personnalisés, la politique d’attribution ne peut pas reposer sur une liste statique.

Le domaine peut utiliser :

```text
RoleAssignmentPolicy
```

avec une décision de type :

```text
canAssign(
    actorMembership,
    targetMembership,
    targetRole,
    context
)
```

Cette politique doit rester déterministe et testable.

---

## Hiérarchie de rôles

Une hiérarchie peut être modélisée par :

```text
Role.Level
```

ou :

```text
Role.Rank
```

Exemple :

```text
Viewer = 10
Member = 20
Manager = 30
Administrator = 40
Owner = 50
```

Cette solution est simple, mais limitée pour les rôles incomparables.

---

## Graphe d’attribution

Une alternative plus expressive consiste à définir les rôles assignables.

Exemple :

```text
Owner
├── may assign Administrator
├── may assign Manager
├── may assign Member
└── may assign Viewer
```

```text
Manager
├── may assign Member
└── may assign Viewer
```

Cette approche permet de modéliser des règles non linéaires.

---

## Permission et politique d'attribution

Tous les changements utilisent la permission canonique :

```text
workspace.members.change-role
```

Le graphe de délégation et `TargetRole.RoleAssignmentPolicy` déterminent ensuite
si l'acteur peut attribuer le rôle cible. Identity 1.0 n'introduit pas une clé de
permission par rôle.

---

## Self-promotion

La promotion de son propre `Membership` doit être interdite par défaut.

Condition :

```text
ChangedBy.UserId = Membership.UserId
AND
TargetRole is more privileged
```

Résultat :

```text
CannotPromoteSelf
```

Cette règle évite qu’un acteur utilise une permission générale pour augmenter sa propre autorité.

---

## Self-demotion

Une rétrogradation volontaire peut être autorisée.

Elle doit néanmoins vérifier :

- le dernier owner ;
- les obligations en cours ;
- les responsabilités exclusives ;
- les délégations actives ;
- les approbations attendues ;
- les workflows nécessitant l’ancien rôle.

Une commande dédiée peut améliorer la clarté :

```text
RelinquishRole
```

---

## Attribution du rôle Owner

L’attribution du rôle owner constitue une opération sensible.

Contrôles recommandés :

- acteur déjà owner ;
- authentification récente ;
- MFA validée ;
- absence de restriction de sécurité ;
- consentement ou confirmation du nouvel owner ;
- audit renforcé ;
- notification immédiate ;
- invalidation des sessions ;
- limitation du nombre d’owners si nécessaire.

---

## Perte du rôle Owner

La perte du rôle owner doit vérifier :

```text
ActiveOwnerCountAfterChange >= 1
```

Elle peut également exiger :

- transfert de responsabilités ;
- réattribution de ressources ;
- résolution de tâches ;
- confirmation renforcée ;
- notification des autres owners.

Ces étapes peuvent appartenir à un workflow applicatif autour de la commande.

---

## Permissions perdues

Le changement de rôle peut retirer des permissions.

Ces permissions doivent cesser d’être effectives dès le commit.

Le système ne doit pas attendre :

- l’expiration d’un token ;
- le prochain login ;
- une synchronisation différée ;
- la fin d’un cache long ;
- la fermeture du navigateur.

---

## Permissions acquises

Les nouvelles permissions deviennent disponibles uniquement si :

```text
Session is valid
AND
User is active
AND
Membership is active
AND
TargetRole is active
AND
Permission is granted
```

Le changement de rôle ne crée pas automatiquement une session.

---

## Tokens et claims

Si un token contient :

```text
RoleId
Permissions
AuthorizationVersion
```

le changement de rôle rend potentiellement le token obsolète.

Stratégies possibles :

- durée de vie très courte ;
- introspection à chaque requête ;
- version d’autorisation ;
- révocation ;
- rotation du token ;
- recalcul côté serveur.

---

## Politique recommandée pour les sessions

Combiner :

```text
AuthorizationVersion
```

avec :

```text
server-side Membership status and Role validation
```

Lors du changement :

```text
AuthorizationVersion += 1
```

Les anciennes sessions ou tokens deviennent immédiatement invalides pour les autorisations contextualisées.

---

## Réauthentification

Une nouvelle authentification peut être requise lorsque :

- le rôle gagne des privilèges élevés ;
- le rôle devient owner ;
- le changement provient d’une décision de sécurité ;
- la dernière authentification est ancienne ;
- la politique du `Workspace` l’exige.

La commande peut enregistrer :

```text
RequireReauthentication = true
```

mais la création d’une nouvelle session reste une responsabilité distincte.

---

## Événement produit

### MembershipRoleChanged

La commande produit :

```text
MembershipRoleChanged
```

L’événement peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `PreviousRoleId`
- `RoleId`
- `ChangedAt`
- `ChangedBy`
- `ChangeSource`
- `Reason`
- `ChangeType`
- `CaseReference`
- `ExternalReference`
- `RequireReauthentication`
- `ChangeRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- la liste complète des permissions ;
- des tokens ;
- des secrets ;
- des commentaires sensibles ;
- des données personnelles inutiles ;
- les permissions de l’acteur.

---

## ChangeType

L’événement peut inclure une classification :

```text
Promotion
Demotion
LateralChange
OwnerAssigned
OwnerRemoved
PrivilegeIncrease
PrivilegeReduction
```

Cette valeur doit être déterminée par une politique stable.

---

## Événements non produits

La commande ne produit pas :

```text
MembershipCreated
MembershipSuspended
MembershipReactivated
MembershipRemoved
RoleCreated
PermissionGranted
SessionRevoked
```

Des handlers peuvent déclencher des commandes de session distinctes.

---

## Erreurs métier

### MembershipNotFound

Le `Membership` n’existe pas.

---

### MembershipNotActive

Le `Membership` n’est pas actif.

---

### MembershipSuspended

Le membre est suspendu.

Le changement doit être intégré à la réactivation si nécessaire.

---

### MembershipRemoved

Le membre a été retiré.

Le changement doit être intégré à la restauration si nécessaire.

---

### MembershipAlreadyHasRole

Le rôle cible est déjà attribué.

Cette erreur ne s’applique pas à la reprise idempotente de la même demande.

---

### CurrentRoleNotFound

Le rôle actuel ne peut plus être résolu.

Cette situation indique généralement une incohérence de données.

---

### TargetRoleNotFound

Le rôle cible n’existe pas.

---

### RoleDisabled

Le rôle cible est inactif.

---

### RoleArchived

Le rôle cible est archivé.

---

### RoleBelongsToAnotherWorkspace

La condition suivante est fausse :

```text
TargetRole.WorkspaceId = Membership.WorkspaceId
```

---

### RoleNotAssignable

Le rôle cible ne peut pas être attribué par cette commande ou dans ce contexte.

---

### RoleAssignmentModeMismatch

La source du changement n’est pas compatible avec le mode d’attribution du rôle.

---

### ActorNotAuthorized

L’acteur ne peut pas modifier le rôle du membre.

---

### TargetRoleAssignmentNotAuthorized

L’acteur peut modifier des rôles, mais pas attribuer celui-ci.

---

### TargetMembershipProtected

Le membre cible est protégé contre ce changement.

---

### CurrentRoleProtected

Le rôle actuel ne peut pas être retiré par cet acteur.

---

### CannotPromoteSelf

L’acteur tente d’augmenter sa propre autorité.

---

### CannotChangeOwnRole

La politique interdit tout changement de son propre rôle.

---

### WorkspaceMustHaveActiveOwner

Le changement laisserait le `Workspace` sans owner actif.

---

### OwnerAssignmentNotAuthorized

L’acteur ne peut pas attribuer le rôle owner.

---

### OwnerLimitReached

Le nombre maximal d’owners serait dépassé.

---

### SeparationOfDutiesViolation

Le rôle cible crée une incompatibilité de responsabilités.

---

### UserNotEligibleForRole

Le `User` ne remplit pas les conditions du rôle cible.

---

### MfaRequired

Le rôle cible exige une authentification multifacteur active.

---

### RecentAuthenticationRequired

L’opération exige une authentification récente.

---

### ApprovalRequired

Une approbation manque.

---

### CaseReferenceRequired

Une référence de dossier obligatoire est absente.

---

### InvalidChangeSource

La source du changement n’est pas reconnue.

---

### MissingExternalReference

La synchronisation externe ne fournit pas de référence stable.

---

### WorkspaceRoleLimitReached

Le quota applicable au rôle cible serait dépassé.

---

### RoleChangeConflict

Une opération concurrente a modifié le `Membership` ou les règles nécessaires.

---

## Idempotence

`ChangeMembershipRole` doit être idempotente pour :

```text
MembershipId + ChangeRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- appliquer un nouveau changement ;
- modifier `ChangedAt` ;
- remplacer le motif ;
- modifier la source ;
- publier un nouvel événement ;
- répéter les notifications ;
- répéter les invalidations non idempotentes.

---

## Identité du résultat idempotent

Le résultat initial doit inclure au minimum :

- `MembershipId`
- `PreviousRoleId`
- `RoleId`
- `ChangedAt`
- `ChangeRequestId`
- version finale de l’agrégat.

---

## Reprise après réponse perdue

Cas typique :

```text
ChangeMembershipRole succeeds

↓

Role is changed

↓

Response is lost

↓

Caller retries
```

La seconde exécution doit retrouver :

- le même `ChangeRequestId` ;
- le même `PreviousRoleId` ;
- le même `TargetRoleId` ;
- le même `ChangedAt`.

Elle retourne le résultat initial.

---

## Réutilisation incorrecte d’un ChangeRequestId

Si le même `ChangeRequestId` est envoyé avec des données différentes, la commande doit échouer.

Exemple :

```text
RequestId = ABC
TargetRoleId = Manager
```

puis :

```text
RequestId = ABC
TargetRoleId = Owner
```

Erreur recommandée :

```text
IdempotencyConflict
```

---

## Idempotence par source

Des clés complémentaires peuvent être utilisées.

### ExternalSynchronization

```text
ExternalSystem + ExternalReference + ExternalVersion
```

### SecurityWorkflow

```text
SecurityCaseId + DecisionId
```

### AdministrativeRecovery

```text
RecoveryCaseId + MembershipId
```

Ces clés ne remplacent pas `ChangeRequestId`.

---

## Concurrence

### Deux changements de rôle concurrents

Deux commandes peuvent demander :

```text
Member -> Manager
```

et :

```text
Member -> Owner
```

Une seule doit réussir sur la version courante.

La seconde doit échouer avec :

```text
RoleChangeConflict
```

ou être réévaluée sur le nouvel état.

Elle ne doit pas écraser silencieusement le premier changement.

---

### ChangeMembershipRole contre SuspendMembership

Si le changement gagne en premier :

```text
Role changed
↓
Membership suspended
```

La suspension doit réévaluer la protection du rôle final.

Si la suspension gagne en premier, le changement de rôle doit échouer car le membre n’est plus actif.

---

### ChangeMembershipRole contre RemoveMembership

Si le retrait gagne en premier, le changement doit échouer.

Si le changement gagne en premier, le retrait doit être réévalué avec le nouveau rôle, notamment pour la règle du dernier owner.

---

### ChangeMembershipRole contre ReactivateMembership

La politique recommandée interdit le changement direct sur un membre suspendu.

Si la réactivation inclut un nouveau rôle, elle doit produire une seule décision cohérente.

Une commande concurrente doit échouer sur conflit de version.

---

### ChangeMembershipRole contre RestoreMembership

La restauration peut attribuer un rôle cible.

Un changement concurrent ne doit pas s’appliquer tant que le `Membership` est `Removed`.

---

### ChangeMembershipRole contre désactivation du Role cible

Le rôle cible peut être désactivé pendant l’opération.

La commande doit garantir qu’elle ne valide pas un rôle devenu inactif.

Selon les frontières transactionnelles :

- verrouillage ;
- version du rôle ;
- décision forte ;
- compensation immédiate ;
- réévaluation au commit.

---

### ChangeMembershipRole contre suppression du Role actuel

La suppression ou l’archivage du rôle actuel peut accélérer une migration.

Le changement reste valide si le rôle cible satisfait toutes les règles.

L’historique doit préserver l’identifiant du rôle précédent.

---

### Deux rétrogradations d’owners concurrentes

Ce cas est critique.

État initial :

```text
Owner A
Owner B
```

Commande 1 :

```text
A -> Member
```

Commande 2 :

```text
B -> Member
```

Une seule peut réussir.

La coordination doit garantir :

```text
ActiveOwnerCount >= 1
```

après chaque commit.

---

### Promotion et rétrogradation concurrentes

État initial :

```text
Owner A
Member B
```

Commandes :

```text
A -> Member
```

et :

```text
B -> Owner
```

Selon l’ordre, la rétrogradation de A peut être autorisée ou interdite.

Un workflow de transfert de propriété peut regrouper ces deux changements de façon atomique.

---

## TransferOwnership

Un transfert d’ownership ne doit pas nécessairement être implémenté comme deux commandes indépendantes.

Workflow risqué :

```text
Promote B to Owner
↓
Demote A
```

Il peut laisser un état intermédiaire acceptable, mais nécessite deux opérations.

Workflow inverse interdit :

```text
Demote A
↓
Promote B
```

Il peut violer l’invariant.

---

## Commande de transfert retenue

Pour un transfert structurant, Identity utilise :

```text
TransferMembershipRole
```

Elle réalise atomiquement :

```text
promote new Owner
+
optionally demote previous Owner
```

`ChangeMembershipRole` reste réservé aux changements unitaires qui ne forment pas
une décision de transfert indissociable.

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Membership Active
+
verify CurrentRole
+
verify TargetRole
+
verify actor authorization
+
verify assignment authority
+
protect last Owner
+
verify policies
+
change Role
+
update authorization version
+
record domain event
```

Les états suivants sont interdits :

```text
Membership.RoleId = TargetRoleId
AND
role change history is missing
```

```text
MembershipRoleChanged published
AND
Membership still references PreviousRoleId
```

```text
Workspace has no active Owner
```

```text
Membership is Active
AND
Role is invalid
```

Une outbox transactionnelle est recommandée.

---

## Frontière transactionnelle

Le changement du rôle du `Membership` est local à l’agrégat.

Cependant, certaines règles nécessitent une coordination avec :

- le `Workspace` ;
- les autres `Membership` owners ;
- le catalogue des rôles ;
- les politiques de licence ;
- les restrictions externes.

Les règles fortement cohérentes doivent être protégées dans la transaction ou par une coordination dédiée.

---

## Invariant inter-agrégats

La règle :

```text
Workspace always has at least one active Owner
```

implique plusieurs agrégats.

Elle doit être traitée comme une politique de cohérence forte.

Elle ne doit pas être confiée uniquement à :

- une projection asynchrone ;
- un événement ultérieur ;
- une vérification d’interface ;
- un compteur éventuellement cohérent.

---

## Rôle et Permissions

La commande ne modifie pas les `Permission`.

Elle modifie uniquement :

```text
Membership.RoleId
```

Les permissions effectives changent indirectement :

```text
PreviousRole.Permissions
↓
TargetRole.Permissions
```

Aucune permission ne doit être copiée directement sur le `Membership`.

---

## Snapshot de permissions

L’événement ne devrait pas contenir la liste complète des permissions.

Motifs :

- payload volumineux ;
- duplication ;
- fuite d’informations ;
- couplage ;
- évolution du rôle ;
- ambiguïté temporelle.

Les consommateurs peuvent résoudre le rôle ou maintenir leur propre projection.

---

## Audit des permissions

Pour certains environnements réglementés, l’audit peut nécessiter de savoir quelles capacités ont été gagnées ou perdues.

Cette information peut être calculée lors du traitement et enregistrée dans un journal d’audit privé :

```text
PermissionsAdded
PermissionsRemoved
```

Elle ne doit pas nécessairement figurer dans l’événement public.

---

## Suppression immédiate des privilèges

Une rétrogradation doit être effective immédiatement.

Exemple :

```text
Owner -> Member
```

L’utilisateur ne doit plus pouvoir :

- gérer les rôles ;
- inviter des owners ;
- modifier les paramètres critiques ;
- supprimer le `Workspace` ;
- accéder aux données réservées.

---

## Course avec une requête autorisée

Une requête peut avoir été autorisée juste avant la rétrogradation.

Selon le niveau de criticité, le système peut :

- accepter la décision déjà commencée ;
- réévaluer au moment du commit ;
- utiliser une version d’autorisation ;
- verrouiller les opérations sensibles ;
- demander une authentification transactionnelle.

Les opérations critiques doivent vérifier une version d’autorisation récente.

---

## AuthorizationVersion

Le `Membership` peut porter :

```text
AuthorizationVersion
```

Chaque changement de rôle incrémente cette version.

Exemple :

```text
AuthorizationVersion: 12 -> 13
```

Les sessions ou commandes sensibles portant la version 12 deviennent obsolètes.

---

## SessionVersionMismatch

Le moteur d’autorisation peut refuser une session lorsque :

```text
Session.AuthorizationVersion
!=
Membership.AuthorizationVersion
```

La session doit alors :

- recharger son contexte ;
- renouveler ses claims ;
- ou être révoquée.

---

## Sessions multi-Workspace

Une session peut donner accès à plusieurs `Workspace`.

Dans ce cas, un changement de rôle dans un `Workspace` ne doit pas nécessairement révoquer tous les accès.

Le modèle peut utiliser :

```text
SessionWorkspaceContext
```

ou un versionnement par `Membership`.

---

## Notification du membre

Le membre concerné peut être informé :

- du nouveau rôle ;
- de la date d’effet ;
- de l’acteur, selon la politique ;
- des conséquences principales ;
- d’une éventuelle réauthentification.

Le motif interne ne doit pas toujours être exposé.

---

## Notification des Owners

Une promotion vers owner ou une perte d’ownership peut déclencher une notification à tous les owners actifs.

Cette notification est particulièrement importante pour :

- la gouvernance ;
- la sécurité ;
- l’audit ;
- la détection d’une action non autorisée.

---

## Notifications sensibles

Les changements suivants peuvent exiger une alerte renforcée :

```text
Member -> Owner
Administrator -> Owner
Owner -> Member
SecurityRole -> StandardRole
```

Les notifications restent hors transaction principale.

---

## Intégrations

Les systèmes externes peuvent consommer :

```text
MembershipRoleChanged
```

Exemples :

- provisioning ;
- facturation ;
- annuaire ;
- outils collaboratifs ;
- contrôle de licence ;
- sécurité ;
- audit ;
- analytics ;
- notifications.

Les consommateurs doivent être idempotents.

---

## Provisioning

Un changement de rôle peut nécessiter :

- l’ajout d’accès ;
- la suppression d’accès ;
- la modification de groupes externes ;
- la rotation de secrets ;
- la désactivation de fonctions ;
- la création d’approbations.

Les suppressions de privilèges doivent être prioritaires.

---

## Ordre des effets externes

Pour une rétrogradation :

```text
Domain Role changed
↓
authorization invalidated
↓
external privileges removed
```

Pour une promotion :

```text
Domain Role changed
↓
authorization refreshed
↓
external privileges granted
```

Les intégrations doivent gérer les échecs et retries.

---

## Échec d’une intégration externe

Le changement métier peut réussir alors qu’un système externe échoue.

La politique doit prévoir :

- retry ;
- dead-letter queue ;
- alerte ;
- état de synchronisation ;
- réconciliation ;
- compensation éventuelle.

Une projection externe ne doit pas déterminer rétroactivement le rôle source de vérité sans politique explicite.

---

## Synchronisation externe

Un système externe peut être source de vérité pour certains rôles.

La politique doit définir :

- quels rôles il contrôle ;
- quels rôles restent locaux ;
- la priorité des sources ;
- la fréquence de synchronisation ;
- la détection de conflit ;
- la gestion des changements manuels ;
- le comportement en cas d’indisponibilité.

---

## Conflit local/externe

Exemple :

```text
Local admin assigns Manager
```

puis le système externe indique :

```text
Member
```

Le domaine doit décider :

- externe prioritaire ;
- local prioritaire ;
- fusion impossible ;
- revue manuelle ;
- rôle temporaire ;
- rejet de la commande locale.

Le comportement ne doit pas être implicite.

---

## Sécurité

La commande doit garantir que :

- seuls les acteurs autorisés peuvent changer un rôle ;
- l’acteur peut attribuer le rôle cible ;
- la self-promotion est bloquée ;
- le rôle cible appartient au bon `Workspace` ;
- le rôle cible est actif et attribuable ;
- le dernier owner actif est protégé ;
- les politiques de séparation des responsabilités sont respectées ;
- les anciennes permissions cessent immédiatement ;
- les tokens et caches deviennent obsolètes ;
- les changements sensibles sont auditables ;
- les retries ne produisent aucun doublon.

---

## Confidentialité

L’événement et les réponses publiques doivent minimiser les données exposées.

Ils ne doivent pas publier inutilement :

- les permissions détaillées ;
- les motifs de sécurité internes ;
- les commentaires administratifs ;
- les informations sur les autres owners ;
- les restrictions personnelles du membre ;
- les justificatifs confidentiels.

---

## Audit

Un changement de rôle réussi doit enregistrer :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `PreviousRoleId`
- `RoleId`
- classification du changement
- `ChangedAt`
- `ChangedBy`
- `ChangeSource`
- `Reason`
- `CaseReference`
- `ExternalReference`
- `ChangeRequestId`
- `CorrelationId`
- version d’autorisation précédente
- nouvelle version d’autorisation
- traitement des sessions
- résultat

---

## Questions auxquelles l’audit doit répondre

```text
who changed the Role
which Membership was affected
which Role was removed
which Role was assigned
why the change occurred
which authority allowed it
whether ownership changed
whether sessions were invalidated
which external workflow initiated the change
```

---

## Conservation de l’historique

Le modèle doit permettre de reconstituer la chronologie :

```text
Membership created as Member
↓
promoted to Manager
↓
promoted to Owner
↓
demoted to Administrator
```

L’état courant ne suffit pas.

L’historique peut être conservé par :

- événements ;
- journal d’audit ;
- table de périodes de rôles ;
- event sourcing ;
- historique applicatif.

---

## Périodes de Role

Une représentation temporelle possible est :

```text
MembershipRolePeriod
├── RoleId
├── StartedAt
├── AssignedBy
├── AssignmentReason
├── EndedAt
└── EndedByChangeRequestId
```

Cette structure permet de répondre à :

```text
which Role did the member have at a given time
```

---

## Cohérence de l’historique

Lors d’un changement :

```text
close CurrentRolePeriod at ChangedAt
+
open TargetRolePeriod at ChangedAt
```

Les périodes ne doivent pas :

- se chevaucher ;
- laisser de trou pour un membre actif ;
- posséder plusieurs rôles courants ;
- être modifiées lors d’un retry.

---

## Décisions de conception

### Le Membership reste actif

`ChangeMembershipRole` ne suspend ni ne retire le membre.

Elle modifie uniquement son rôle.

---

### Le TargetRoleId est explicite

La commande ne choisit pas automatiquement :

- un rôle par défaut ;
- le rôle le plus proche ;
- un rôle de secours ;
- un rôle basé sur les permissions demandées.

---

### L’acteur doit pouvoir attribuer le rôle cible

Une permission générale de gestion ne suffit pas nécessairement.

L’autorité d’attribution est une règle distincte.

---

### La self-promotion est interdite

Un membre ne peut pas augmenter lui-même ses privilèges.

---

### La perte du rôle Owner est autorisée

Conformément à l’option A retenue, un owner peut recevoir un rôle non owner si :

```text
ActiveOwnerCountAfterChange >= 1
```

La transition n’est donc pas interdite en elle-même.

Seul le résultat violant l’invariant est interdit.

---

### L’erreur commune est WorkspaceMustHaveActiveOwner

La même erreur exprime l’invariant dans :

- `ChangeMembershipRole`
- `SuspendMembership`
- `RemoveMembership`
- `DisableUser`

---

### Le dernier Owner est protégé de façon fortement cohérente

Une projection asynchrone n’est pas suffisante.

Une stratégie transactionnelle ou de coordination est obligatoire.

---

### Les Permissions ne sont pas copiées

Le `Membership` référence un `Role`.

Les permissions restent portées par le rôle.

---

### Une réduction de privilèges prend effet immédiatement

Le système ne doit pas dépendre de l’expiration naturelle des sessions.

---

### Une promotion sensible peut exiger une réauthentification

L’attribution d’un rôle privilégié peut nécessiter une preuve d’authentification récente.

---

### Le changement de Role produit un événement dédié

L’événement :

```text
MembershipRoleChanged
```

exprime une décision métier observable.

Il ne doit pas être remplacé par un événement générique :

```text
MembershipUpdated
```

---

### Le changement sur Membership suspendu est refusé

Le nouveau rôle peut être fourni à :

```text
ReactivateMembership
```

lorsque le changement fait partie de la décision de retour.

---

### Le changement sur Membership retiré est refusé

Le rôle cible peut être fourni à :

```text
RestoreMembership
```

lorsque le membre rejoint à nouveau le `Workspace`.

---

### Les changements d’ownership complexes peuvent utiliser une commande dédiée

`TransferMembershipRole` coordonne atomiquement la promotion du nouvel owner et
le rôle de remplacement de l'ancien owner.

---

## Cas limites

### Rôle cible supprimé entre validation et commit

La commande doit échouer ou détecter un conflit.

Elle ne doit pas persister un `RoleId` invalide.

---

### Acteur rétrogradé pendant l’opération

Si l’acteur perd sa permission avant le commit, la commande doit être réévaluée ou échouer selon la stratégie de cohérence.

---

### Acteur et cible identiques

La politique de self-management s’applique.

---

### Rôle actuel et cible sémantiquement équivalents

Deux rôles différents peuvent posséder exactement les mêmes permissions.

Le changement reste un événement métier si les rôles représentent des responsabilités distinctes.

---

### Permissions du rôle modifiées simultanément

Le changement attribue une identité de rôle, pas un snapshot de permissions.

Les permissions effectives dépendent de la version du rôle après commit.

Une stratégie de versionnement peut être nécessaire.

---

### Rôle cible devient owner par modification ultérieure

Si la propriété `IsOwner` d’un rôle peut changer, cela peut modifier plusieurs memberships simultanément.

Cette opération doit être fortement encadrée.

La recommandation est que le caractère owner d’un rôle système ne soit pas modifiable librement.

---

### Rôle owner renommé

Le renommage n’affecte pas la qualité d’owner si celle-ci repose sur une propriété stable.

---

### Dernier owner suspendu

Un owner suspendu ne compte pas comme owner actif.

La règle utilise :

```text
Active Membership
AND
Active User
AND
Owner Role
```

---

### Dernier owner avec User désactivé

Un `User` désactivé ne doit pas être compté comme owner actif.

---

### Compte de service owner

La politique doit préciser si un compte de service peut satisfaire l’invariant.

Recommandation :

```text
human active Owner required
```

si le produit exige une gouvernance humaine.

---

### Invitation owner en attente

Une invitation non acceptée ne compte pas comme owner actif.

---

### Owner retiré en cours de transaction

La coordination au niveau du `Workspace` doit empêcher la violation de l’invariant.

---

### Rôle cible identique après résolution externe

Si une synchronisation répète le même état avec une nouvelle version externe, la politique peut :

- traiter l’opération comme un no-op synchronisé ;
- enregistrer un état de synchronisation ;
- ne pas produire `MembershipRoleChanged`.

---

## Checklist de validation

Avant commit, la commande doit confirmer :

```text
Membership exists
Membership is Active
Current Role exists
Target Role exists
Target Role differs
Target Role is Active
Target Role is assignable
Target Role belongs to Workspace
User is active
Actor is authorized
Actor may assign Target Role
Self-promotion rule respected
Role hierarchy respected
Separation of duties respected
Workspace limits respected
Last active Owner preserved
Idempotency verified
Concurrency version valid
```

---

## Synthèse

`ChangeMembershipRole` modifie le niveau d’autorité d’un membre actif dans un `Workspace`.

Elle garantit que :

- le `Membership` existe et est actif ;
- le rôle cible existe, est actif et appartient au bon `Workspace` ;
- l’acteur peut modifier le membre ;
- l’acteur peut attribuer le rôle cible ;
- la self-promotion est interdite ;
- les règles de hiérarchie sont respectées ;
- les règles de séparation des responsabilités sont respectées ;
- la perte du rôle owner reste possible ;
- le `Workspace` conserve toujours au moins un owner actif ;
- les anciennes permissions cessent immédiatement ;
- les sessions et caches sont réévalués ;
- le changement est idempotent et auditable ;
- les opérations concurrentes ne violent aucun invariant.

Le résultat final est :

```text
Active Membership
with PreviousRole
    ↓
Active Membership
with TargetRole
```

avec conservation de l’identité :

```text
same MembershipId
same UserId
same WorkspaceId
same Active status
new Role
new authorization context
```
