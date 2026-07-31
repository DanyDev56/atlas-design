---
id: IDN-CMD-REMOVE-MEMBERSHIP
title: RemoveMembership
status: Draft
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
  - ../events/MembershipRemoved.md
  - CreateMembership.md
  - RestoreMembership.md
  - SuspendMembership.md
  - ReactivateMembership.md
  - ChangeMembershipRole.md
---

# RemoveMembership

## Objectif

La commande `RemoveMembership` met fin à l’appartenance effective d’un `User` à un `Workspace`.

Elle fait passer le `Membership` de :

```text
Active
```

ou, selon la politique retenue :

```text
Suspended
```

vers :

```text
Removed
```

Le retrait conserve :

- le même `MembershipId` ;
- le même `UserId` ;
- le même `WorkspaceId` ;
- le dernier `RoleId` connu ;
- l’historique de l’appartenance ;
- les périodes d’activité précédentes ;
- les changements de rôle antérieurs ;
- les suspensions antérieures ;
- la possibilité d’une restauration future explicite.

Le retrait ne supprime pas physiquement le `Membership`.

---

## Signification métier

Un `Membership` retiré représente une relation d’appartenance terminée.

```text
Membership history still exists
AND
the User no longer belongs to the Workspace
```

Après le retrait :

- le membre ne possède plus d’accès au `Workspace` ;
- son rôle n’est plus effectif ;
- aucune permission n’est accordée ;
- ses sessions ne doivent plus utiliser ce contexte ;
- il ne compte plus dans les membres actifs ;
- il ne compte plus dans les owners actifs ;
- son historique reste consultable selon les règles de confidentialité.

---

## Différence avec SuspendMembership

### SuspendMembership

```text
Active
    ↓
Suspended
```

La relation d’appartenance existe toujours.

Le blocage est temporaire.

Le retour utilise :

```text
ReactivateMembership
```

### RemoveMembership

```text
Active or Suspended
    ↓
Removed
```

L’appartenance effective prend fin.

Le retour utilise :

```text
RestoreMembership
```

La distinction doit rester visible dans :

- les commandes ;
- les événements ;
- les permissions ;
- l’audit ;
- les projections ;
- les notifications ;
- les intégrations.

---

## Différence avec DeleteUser

`RemoveMembership` agit dans un seul `Workspace`.

```text
RemoveMembership
    -> one Workspace context
```

`DeleteUser` ou une commande équivalente agit sur l’identité globale.

```text
DeleteUser
    -> global User lifecycle
```

Retirer un membre d’un `Workspace` ne doit pas :

- supprimer son compte ;
- supprimer ses autres `Membership` ;
- supprimer ses sessions sans distinction si elles concernent d’autres `Workspace` ;
- effacer son identité globale ;
- supprimer ses données métier appartenant à d’autres bounded contexts.

---

## Différence entre retrait et départ volontaire

Deux intentions peuvent conduire au même état final :

### Retrait administratif

Un acteur autorisé retire un autre membre.

```text
Administrator
↓
RemoveMembership
```

### Départ volontaire

Le membre décide de quitter lui-même le `Workspace`.

```text
Member
↓
LeaveWorkspace
```

Même si les deux opérations produisent finalement :

```text
Membership.Status = Removed
```

leurs règles métier peuvent différer.

Une commande spécialisée est recommandée pour le départ volontaire :

```text
LeaveWorkspace
```

Elle peut appliquer des règles spécifiques telles que :

- interdiction au dernier owner de quitter ;
- transfert préalable de responsabilités ;
- confirmation utilisateur ;
- gestion des ressources possédées ;
- délai de rétractation ;
- notifications différentes.

`RemoveMembership` reste la commande administrative ou système de retrait.

---

## Agrégat concerné

`Membership`

Le `Membership` constitue la racine de l’agrégat modifié.

La commande peut consulter :

- le `User` ;
- le `Workspace` ;
- le `Role` du membre cible ;
- le `Membership` de l’acteur ;
- le `Role` de l’acteur ;
- les autres owners actifs ;
- les responsabilités encore détenues ;
- les restrictions de sécurité ;
- les politiques de rétention ;
- les dépendances appartenant à d’autres bounded contexts.

Ces objets ne sont pas nécessairement modifiés dans la même transaction.

---

## Acteur

La commande peut être demandée par :

- un `User` autorisé à gérer les membres ;
- un `Owner` du `Workspace` ;
- un administrateur ;
- un processus de gouvernance ;
- un workflow de sécurité ;
- un workflow de conformité ;
- un système externe faisant autorité ;
- un processus de provisioning ;
- un opérateur de support habilité.

L’acteur doit être explicitement identifié.

Une origine générique telle que :

```text
System
```

est insuffisante si plusieurs workflows système peuvent retirer un membre.

Préférer :

```text
DirectorySynchronization
SecurityEnforcement
ComplianceWorkflow
WorkspaceProvisioning
AdministrativeOperator
```

---

## Permission requise

La permission recommandée est :

```text
workspace.members.remove
```

Une permission plus générale peut être utilisée :

```text
workspace.members.manage
```

Des permissions plus fines peuvent distinguer :

```text
workspace.members.remove-standard
workspace.members.remove-admin
workspace.owners.remove
workspace.members.force-remove
```

L’autorisation doit tenir compte de :

```text
Actor
ActorRole
TargetMembership
TargetRole
Workspace
RemovalReason
RemovalSource
```

Le droit de retirer un membre ordinaire ne signifie pas automatiquement le droit de retirer :

- un owner ;
- un administrateur ;
- un membre protégé ;
- un membre de même niveau ;
- son propre `Membership` ;
- un compte de service ;
- un représentant légal.

---

## Cas sans permission utilisateur directe

Certains workflows peuvent retirer un membre sans permission utilisateur ordinaire.

Exemples :

```text
ExternalDirectorySynchronization
SecurityEnforcement
ComplianceEnforcement
WorkspaceDeprovisioning
ContractTerminationWorkflow
AdministrativeRecovery
```

Leur autorité doit être :

- explicitement configurée ;
- limitée à certains motifs ;
- limitée à certains rôles ;
- limitée à certains `Workspace` ;
- réévaluée à chaque commande ;
- auditée ;
- incapable de violer l’invariant du dernier owner.

---

## Sources de retrait

Valeurs recommandées pour `MembershipRemovalSource` :

```text
ManualAdministration
WorkspaceGovernance
SecurityWorkflow
ComplianceWorkflow
ExternalSynchronization
SystemProvisioning
ContractTermination
AdministrativeRecovery
WorkspaceClosure
```

La source influence :

- l’autorité requise ;
- la visibilité du motif ;
- les références obligatoires ;
- les notifications ;
- les règles de restauration ;
- les intégrations ;
- la politique de conservation.

---

## Motifs de retrait

Valeurs recommandées pour `MembershipRemovalReason` :

```text
AccessNoLongerRequired
EmploymentEnded
ContractEnded
OrganizationLeft
PolicyViolation
SecurityDecision
ComplianceDecision
ExternalDirectoryRemoved
AdministrativeCorrection
DuplicateResolution
WorkspaceReorganization
WorkspaceClosure
Other
```

Le motif doit exprimer pourquoi l’appartenance prend fin.

Il ne doit pas se limiter à :

```text
Deleted
Removed
ManualAction
```

---

## Préconditions

Avant l’exécution de `RemoveMembership`, les conditions suivantes doivent être satisfaites :

- le `Membership` existe ;
- son état est `Active` ou `Suspended`, selon la politique ;
- le `Workspace` existe ;
- l’acteur ou le workflow est autorisé ;
- le membre cible peut être retiré ;
- le rôle cible n’est pas protégé contre cet acteur ;
- le retrait ne laisse pas le `Workspace` sans owner actif ;
- les responsabilités bloquantes ont été transférées ou résolues ;
- les restrictions de self-management sont respectées ;
- le motif est valide ;
- les références obligatoires sont présentes ;
- aucune transition concurrente incompatible n’a déjà gagné ;
- la demande est idempotente ;
- les contraintes de sécurité sont satisfaites ;
- les règles de conservation et d’audit peuvent être respectées.

---

## États sources autorisés

### Active

Transition nominale :

```text
Active
    ↓
Removed
```

### Suspended

Transition recommandée :

```text
Suspended
    ↓
Removed
```

Cette transition permet de terminer définitivement une appartenance déjà bloquée.

Elle évite une réactivation artificielle :

```text
Suspended
↓
Active
↓
Removed
```

qui n’aurait aucun sens métier.

### Removed

Aucune nouvelle transition n’est appliquée.

La commande déclenche le comportement idempotent ou retourne :

```text
MembershipAlreadyRemoved
```

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `MembershipId` | `MembershipId` | Oui | Identifie le `Membership` à retirer. |
| `RemovedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’auteur ou l’origine du retrait. |
| `RemovedAt` | Instant | Oui | Date la fin d’appartenance. |
| `Reason` | `MembershipRemovalReason` | Oui | Motif structuré du retrait. |
| `RemovalSource` | `MembershipRemovalSource` | Oui | Indique le workflow à l’origine du retrait. |
| `RemovalRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |

Données facultatives ou conditionnelles :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `Comment` | Texte court | Non | Précision interne facultative. |
| `CaseReference` | Identifiant | Conditionnel | Référence un dossier ou une décision. |
| `ExternalReference` | Identifiant | Conditionnel | Référence la source externe. |
| `CorrelationId` | Identifiant | Non | Relie l’opération à un workflow plus large. |
| `ReplacementMembershipId` | `MembershipId` | Conditionnel | Identifie un membre reprenant certaines responsabilités. |
| `RevokeSessions` | Booléen | Non | Demande la révocation des sessions concernées. |
| `PreventAutomaticRestore` | Booléen | Non | Bloque certaines restaurations automatisées. |
| `RetentionClass` | Valeur métier | Non | Indique une politique de conservation particulière. |

---

## Validation des données

### MembershipId

Le `MembershipId` doit identifier une appartenance précise.

La commande ne doit pas retirer tous les accès d’un `User` en utilisant uniquement :

```text
UserId
```

Un `User` peut appartenir à plusieurs `Workspace`.

---

### RemovedBy

Lorsque `RemovedBy` est un `UserId`, l’acteur doit :

- exister ;
- être actif ;
- posséder un `Membership` actif dans le même `Workspace` ;
- posséder la permission nécessaire ;
- être autorisé à agir sur le membre cible ;
- être autorisé à retirer le rôle cible ;
- respecter les restrictions de self-management.

Lorsque l’acteur est un `SystemActor`, son autorité doit être explicitement définie.

---

### RemovedAt

`RemovedAt` représente l’instant métier auquel l’appartenance cesse.

Il doit :

- être fourni par une abstraction `Clock` ;
- rester stable lors des retries ;
- être postérieur ou égal à la création ou restauration la plus récente ;
- être cohérent avec les périodes de rôle et de suspension ;
- servir de référence aux événements et à l’audit.

---

### Reason

Le motif doit appartenir au catalogue autorisé.

Certaines valeurs peuvent exiger :

- une référence de dossier ;
- une autorité renforcée ;
- une notification spécifique ;
- une interdiction de restauration automatique ;
- une durée de conservation particulière.

---

### RemovalSource

La source doit être compatible avec le motif.

Exemple cohérent :

```text
RemovalSource = ExternalSynchronization
Reason = ExternalDirectoryRemoved
```

Exemple potentiellement incohérent :

```text
RemovalSource = ExternalSynchronization
Reason = SecurityDecision
```

sauf si le système externe possède explicitement cette autorité.

---

### RemovalRequestId

Le `RemovalRequestId` doit :

- identifier une intention logique unique ;
- rester stable pendant les retries ;
- permettre de restituer le résultat initial ;
- être unique dans le périmètre de déduplication ;
- ne contenir aucune donnée personnelle sensible.

---

### CaseReference

Une référence peut être obligatoire pour :

```text
SecurityDecision
ComplianceDecision
PolicyViolation
AdministrativeCorrection
```

Elle doit permettre de relier le retrait à une décision traçable.

---

### ExternalReference

Lorsque :

```text
RemovalSource = ExternalSynchronization
```

une référence externe stable doit être fournie.

Exemples :

```text
DirectoryEntryId
ExternalMembershipId
ExternalChangeVersion
```

---

### ReplacementMembershipId

Cette donnée peut être nécessaire lorsque le membre détient des responsabilités transférables.

Le membre remplaçant doit :

- exister ;
- être actif ;
- appartenir au même `Workspace` ;
- être éligible aux responsabilités concernées ;
- ne pas être le membre retiré ;
- disposer du rôle ou des permissions nécessaires.

Le transfert effectif peut appartenir à un workflow ou à d’autres bounded contexts.

---

## Traitement métier

### 1. Charger le Membership

Le système charge le `Membership` identifié par `MembershipId`.

S’il n’existe pas, la commande échoue.

---

### 2. Vérifier l’état courant

Cas possibles :

#### Active

Le retrait peut continuer.

#### Suspended

Le retrait peut continuer si la politique autorise :

```text
Suspended -> Removed
```

#### Removed

Le système vérifie l’idempotence.

S’il ne s’agit pas de la même demande, il retourne :

```text
MembershipAlreadyRemoved
```

---

### 3. Vérifier l’idempotence

Le système recherche un retrait déjà appliqué avec :

```text
MembershipId + RemovalRequestId
```

Si une opération correspond, le résultat initial est retourné sans nouvel effet.

---

### 4. Charger le contexte du Workspace

Le système récupère :

- l’état du `Workspace` ;
- les politiques de retrait ;
- les règles de gouvernance ;
- les limites ;
- les owners actifs ;
- les responsabilités connues ;
- les règles de rétention applicables.

---

### 5. Charger le contexte de l’acteur

Pour un acteur humain, le système charge :

- son `Membership` ;
- son rôle ;
- ses permissions ;
- son niveau d’autorité ;
- ses restrictions ;
- son éventuelle relation avec la cible.

Pour un workflow système, il charge :

- son identité ;
- sa source d’autorité ;
- son périmètre ;
- ses motifs autorisés ;
- ses limitations.

---

### 6. Autoriser l’acteur ou le workflow

Le système vérifie :

```text
Actor may remove TargetMembership
```

et, si nécessaire :

```text
Actor may remove TargetRole
```

La possession de :

```text
workspace.members.remove
```

peut être nécessaire mais insuffisante.

---

### 7. Vérifier les règles de hiérarchie

Le domaine peut interdire qu’un acteur retire :

- un membre de niveau supérieur ;
- un membre de même niveau ;
- un owner ;
- un rôle protégé ;
- un membre de sécurité ;
- un représentant légal.

Une permission dédiée peut être nécessaire :

```text
workspace.owners.remove
```

---

### 8. Vérifier le self-removal

Si :

```text
RemovedBy.UserId = Membership.UserId
```

la commande doit appliquer la politique de départ volontaire.

La recommandation est de refuser cette utilisation avec :

```text
UseLeaveWorkspace
```

ou :

```text
CannotRemoveOwnMembership
```

afin de préserver la différence d’intention.

Une exception peut exister pour certains workflows administratifs explicitement conçus.

---

### 9. Identifier le statut owner

Le système détermine si le membre cible compte actuellement comme owner actif.

Condition conceptuelle :

```text
Membership.Status = Active
AND
User.Status = Active
AND
Role.SystemType = Owner
```

Un owner suspendu ne compte déjà plus comme owner actif.

---

### 10. Protéger le dernier Owner actif

Lorsque le membre cible compte comme owner actif, la commande doit vérifier :

```text
ActiveOwnerCountAfterRemoval >= 1
```

Le retrait suivant est autorisé :

```text
Owner A
Owner B

Remove Owner A
```

Le retrait suivant est interdit :

```text
Owner A
Member B

Remove Owner A
```

L’erreur métier commune est :

```text
WorkspaceMustHaveActiveOwner
```

Cette erreur exprime le même invariant que dans :

- `ChangeMembershipRole` ;
- `SuspendMembership` ;
- `DisableUser` ;
- éventuellement d’autres commandes de gouvernance.

---

### 11. Vérifier les responsabilités bloquantes

Le membre peut détenir des responsabilités qui ne doivent pas rester sans propriétaire.

Exemples :

- approbations en attente ;
- ressources dont il est seul propriétaire ;
- intégrations administrées ;
- clés ou secrets opérationnels ;
- dossiers métier ;
- responsabilités réglementaires ;
- facturation ;
- administration du domaine ;
- workflows en attente.

Le bounded context `Identity` ne doit pas nécessairement posséder ces responsabilités.

Il peut demander une décision externe telle que :

```text
MembershipRemovalReadiness
```

---

### 12. Évaluer la readiness de retrait

Le système ou le workflow applicatif peut obtenir une décision :

```text
Ready
Blocked
ReadyWithWarnings
RequiresReplacement
```

La commande ne doit pas dépendre d’une lecture asynchrone obsolète pour un invariant critique.

Les dépendances non critiques peuvent être traitées après le retrait.

---

### 13. Vérifier le remplacement éventuel

Si une responsabilité exige un successeur, le système valide `ReplacementMembershipId`.

Le remplaçant doit être compatible avec :

- le `Workspace` ;
- la responsabilité ;
- le rôle requis ;
- les restrictions de sécurité ;
- les règles de séparation des responsabilités.

---

### 14. Vérifier les restrictions permanentes ou légales

Certaines situations peuvent modifier le traitement :

- obligation de conservation ;
- gel légal ;
- investigation ;
- interdiction de restauration ;
- effacement différé ;
- données à pseudonymiser ;
- accès à révoquer immédiatement.

Le retrait ne doit pas effacer les preuves nécessaires.

---

### 15. Vérifier les références de workflow

Selon la source, le système vérifie notamment :

#### ManualAdministration

L’acteur humain et son autorité.

#### ExternalSynchronization

La référence externe et sa version.

#### SecurityWorkflow

Le dossier de sécurité et la décision applicable.

#### ComplianceWorkflow

Le dossier de conformité.

#### ContractTermination

La référence du contrat ou de la relation terminée.

#### WorkspaceClosure

Le workflow global de fermeture.

---

### 16. Vérifier les opérations concurrentes

Le système vérifie qu’aucune autre transition n’a modifié :

- le statut du `Membership` ;
- son rôle ;
- l’état du `User` ;
- le nombre d’owners actifs ;
- la readiness de retrait ;
- les responsabilités critiques.

Concurrences principales :

- `SuspendMembership` ;
- `ChangeMembershipRole` ;
- `ReactivateMembership` ;
- `RestoreMembership` ;
- une autre `RemoveMembership` ;
- `DisableUser` ;
- transfert d’ownership ;
- fermeture du `Workspace`.

---

### 17. Clôturer la période active ou suspendue

Le système ferme la période de vie courante du `Membership`.

Exemple conceptuel :

```text
MembershipLifecyclePeriod
├── StartedAt
├── StartedBy
├── StartSource
├── EndedAt = RemovedAt
├── EndedBy = RemovedBy
├── EndReason
└── EndSource
```

Si le membre était suspendu, la période de suspension doit également être clôturée de manière cohérente.

---

### 18. Enregistrer le retrait

L’agrégat passe vers :

```text
Removed
```

Il conserve :

- `MembershipId` ;
- `UserId` ;
- `WorkspaceId` ;
- le dernier `RoleId` ;
- l’historique complet.

Il enregistre :

- `RemovedAt` ;
- `RemovedBy` ;
- `RemovalReason` ;
- `RemovalSource` ;
- `RemovalRequestId` ;
- `CaseReference`, le cas échéant ;
- `ExternalReference`, le cas échéant ;
- `ReplacementMembershipId`, le cas échéant ;
- `PreventAutomaticRestore`, le cas échéant.

---

### 19. Rendre les permissions ineffectives

Dès le retrait, la chaîne d’autorisation est invalide :

```text
Session
↓
User
↓
Removed Membership
✕
Role
↓
Permission
```

Le moteur d’autorisation doit exiger :

```text
Membership.Status = Active
```

Le dernier `RoleId` reste historique mais ne confère aucun accès.

---

### 20. Invalider le contexte d’autorisation

Le système doit :

- incrémenter une version d’autorisation ;
- invalider les caches ;
- invalider les claims ;
- révoquer ou réévaluer les sessions ;
- fermer les connexions actives sensibles ;
- supprimer les accès temporaires liés au `Workspace`.

Le retrait ne doit pas attendre l’expiration naturelle d’un token.

---

### 21. Produire l’événement

L’agrégat produit :

```text
MembershipRemoved
```

Les notifications, déprovisionnements et nettoyages externes sont déclenchés par des handlers fiables.

---

## Résultat attendu

Après une exécution réussie :

- le `Membership` existe toujours ;
- son état est `Removed` ;
- son identité est inchangée ;
- son historique est préservé ;
- son rôle n’est plus effectif ;
- aucune permission n’est accordée ;
- il ne compte plus comme membre actif ;
- il ne compte plus comme owner actif ;
- ses sessions ne peuvent plus utiliser ce contexte ;
- une restauration explicite reste éventuellement possible.

État conceptuel :

```text
Membership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── RoleId: last known Role
├── Status: Removed
├── RemovedAt
├── RemovedBy
├── RemovalReason
├── RemovalSource
├── RemovalRequestId
├── CaseReference
├── ExternalReference
└── History preserved
```

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` existe pour :

```text
UserId + WorkspaceId
```

Le retrait ne crée pas une nouvelle ligne logique.

---

### `IDN-INV-002`

Le `Membership` conserve ses références identitaires.

---

### `IDN-INV-004`

Transitions autorisées :

```text
Active -> Removed
```

et, selon la politique retenue :

```text
Suspended -> Removed
```

---

### `IDN-INV-005`

Le dernier rôle historique appartient toujours au même `Workspace`.

---

### `IDN-INV-006`

Le `Workspace` conserve au moins un owner actif.

```text
ActiveOwnerCountAfterRemoval >= 1
```

---

### `IDN-INV-010`

Aucune permission n’est obtenue directement par le `Membership`.

Un `Membership` retiré n’active pas les permissions de son rôle historique.

---

### `IDN-INV-011`

Une `Session` ne peut autoriser un contexte dont le `Membership` est `Removed`.

---

### `IDN-INV-014`

L’autorisation reste contextualisée par `Workspace`.

---

## Transition d’état

Transitions autorisées :

```text
Active
    ↓
Removed
```

```text
Suspended
    ↓
Removed
```

Transitions interdites :

```text
Removed
    ↓
Removed
```

hors reprise idempotente.

```text
Removed
    ↓
Active
```

Cette transition relève de :

```text
RestoreMembership
```

---

## Pourquoi conserver le RoleId

Le dernier `RoleId` peut être conservé pour :

- l’audit ;
- l’analyse historique ;
- la restitution de la dernière responsabilité ;
- les projections temporelles ;
- les décisions de restauration ;
- les enquêtes de sécurité ;
- la compréhension des permissions précédemment détenues.

Cependant, une restauration ne doit pas réutiliser implicitement ce rôle.

`RestoreMembership` exige un `RoleId` explicite et valide.

---

## Le Role historique ne confère aucun accès

La règle doit être explicite :

```text
Membership.Status != Active
    -> no effective Permission
```

Il serait incorrect de calculer les permissions uniquement par :

```text
Membership.RoleId
↓
Role.Permissions
```

sans vérifier le statut.

---

## Protection du dernier Owner

La condition de protection s’applique lorsque le membre cible compte actuellement comme owner actif.

Calcul conceptuel :

```text
CurrentActiveOwnerCount
-
TargetCountsAsActiveOwner
>= 1
```

La commande ne doit pas compter comme owners actifs :

- les membres suspendus ;
- les membres retirés ;
- les users désactivés ;
- les invitations en attente ;
- les comptes non conformes à la politique d’ownership ;
- éventuellement les comptes de service.

---

## Cohérence concurrente du dernier Owner

Une vérification naïve est insuffisante :

```text
count owners
then remove
```

Deux transactions pourraient retirer deux owners simultanément.

État initial :

```text
Owner A
Owner B
```

Deux retraits concurrents ne doivent pas produire :

```text
0 active owner
```

---

## Stratégie de coordination recommandée

Utiliser la même stratégie de coordination que pour :

- `ChangeMembershipRole` ;
- `SuspendMembership` ;
- `DisableUser`.

Par exemple :

```text
lock Workspace governance resource
↓
recalculate active owners
↓
validate invariant
↓
apply operation
```

L’invariant doit être fortement cohérent.

---

## Retrait d’un Membership suspendu

Le retrait d’un membre suspendu est autorisé par défaut.

Exemple :

```text
Active
↓
SuspendMembership
↓
Suspended
↓
RemoveMembership
↓
Removed
```

Le retrait :

- clôture la suspension active ;
- conserve son historique ;
- termine l’appartenance ;
- ne produit pas `MembershipReactivated`.

---

## Retrait d’un Owner suspendu

Un owner suspendu ne compte déjà plus comme owner actif.

Le passage :

```text
Suspended Owner Membership
↓
Removed
```

ne réduit donc pas nécessairement `ActiveOwnerCount`.

La commande doit néanmoins utiliser la définition exacte de l’owner actif retenue par le domaine.

---

## Retrait d’un membre protégé

Certains memberships peuvent être protégés.

Exemples :

- owner fondateur ;
- représentant légal ;
- administrateur de sécurité ;
- compte de service critique ;
- membre requis par un contrat ;
- compte de récupération ;
- identité technique de continuité.

Le retrait peut nécessiter :

- un workflow spécialisé ;
- une approbation ;
- un remplacement ;
- une autorité supérieure ;
- une authentification renforcée.

---

## Retrait de l’Owner fondateur

Le concept d’owner fondateur doit être distingué du dernier owner actif.

Politiques possibles :

### Aucune protection permanente

L’owner fondateur peut être retiré si un autre owner actif demeure.

### Protection renforcée

Son retrait nécessite une procédure particulière.

### Impossibilité de retrait

Il doit transférer son statut fondateur ou fermer le `Workspace`.

La politique doit être explicite.

---

## Responsabilités externes

Identity ne doit pas devenir propriétaire de toutes les dépendances métier du membre.

Exemples appartenant à d’autres bounded contexts :

- projets ;
- factures ;
- ressources ;
- documents ;
- approbations ;
- intégrations ;
- équipes ;
- contrats.

Identity peut recevoir une décision :

```text
RemovalReadiness
```

mais ne doit pas reproduire leurs modèles internes.

---

## RemovalReadiness

Valeur de décision possible :

```text
Ready
ReadyWithWarnings
Blocked
RequiresReplacement
```

Elle peut contenir des codes tels que :

```text
OwnsCriticalResources
HasPendingApprovals
IsBillingContact
IsOnlyIntegrationAdministrator
HasOpenSecurityCases
```

La commande doit distinguer les blocages forts des avertissements.

---

## Blocage fort et nettoyage asynchrone

Un blocage fort empêche le retrait.

Exemple :

```text
member is the only legal representative
```

Un nettoyage non critique peut être asynchrone.

Exemple :

```text
remove member from optional notification lists
```

La frontière doit être définie par la criticité métier.

---

## Déprovisionnement externe

Après `MembershipRemoved`, les systèmes externes peuvent devoir :

- supprimer des groupes ;
- retirer des accès ;
- désactiver des comptes locaux ;
- révoquer des licences ;
- supprimer des délégations ;
- fermer des sessions ;
- désactiver des intégrations personnelles.

Ces effets doivent être idempotents.

---

## Priorité des effets de sécurité

Les suppressions d’accès critiques doivent être traitées en priorité.

Ordre conceptuel :

```text
Membership marked Removed
↓
authorization invalidated immediately
↓
sessions revoked or re-evaluated
↓
external provisioning removed
↓
secondary cleanup
```

L’accès principal ne doit pas dépendre de la réussite immédiate d’une intégration externe.

---

## Échec du déprovisionnement

Un système externe peut être indisponible après le retrait.

Le domaine doit prévoir :

- retry ;
- outbox ;
- dead-letter queue ;
- alerting ;
- état de synchronisation ;
- réconciliation ;
- escalade de sécurité.

Le `Membership` ne doit pas redevenir actif parce qu’une intégration a échoué.

---

## Sessions

Le retrait doit empêcher immédiatement l’utilisation du `Workspace`.

Stratégies possibles :

### Révocation des sessions contextualisées

Révoquer uniquement les sessions ou contextes liés au `Membership`.

### Révocation de toutes les sessions

Appropriée pour un retrait lié à un incident global de sécurité.

### Version d’autorisation

Incrémenter :

```text
AuthorizationVersion
```

### Vérification serveur

Exiger à chaque accès :

```text
Membership.Status = Active
```

---

## Politique recommandée pour les sessions

Combiner :

```text
server-side Membership status check
```

avec :

```text
AuthorizationVersion increment
```

et :

```text
targeted session revocation
```

pour les contextes concernés.

Le retrait d’un `Membership` ne doit pas nécessairement fermer les sessions d’autres `Workspace`.

---

## Sessions multi-Workspace

Une session peut couvrir plusieurs contextes.

Le retrait dans un `Workspace` doit produire :

```text
Access to Workspace A revoked
Access to Workspace B preserved
```

sauf si le motif justifie une révocation globale du `User`.

---

## AuthorizationVersion

Le retrait peut appliquer :

```text
AuthorizationVersion += 1
```

Les tokens portant l’ancienne version deviennent obsolètes.

Cette version doit être scoped au `Membership` si l’accès est multi-workspace.

---

## Événement produit

### MembershipRemoved

La commande produit :

```text
MembershipRemoved
```

L’événement peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `LastRoleId`
- `PreviousStatus`
- `RemovedAt`
- `RemovedBy`
- `Reason`
- `RemovalSource`
- `CaseReference`
- `ExternalReference`
- `ReplacementMembershipId`
- `PreventAutomaticRestore`
- `RemovalRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- les tokens ;
- les secrets ;
- la liste complète des permissions ;
- les commentaires sensibles ;
- les données détaillées des responsabilités externes ;
- les données personnelles inutiles.

---

## PreviousStatus

L’événement peut préciser :

```text
Active
```

ou :

```text
Suspended
```

Cette information permet aux consommateurs de distinguer :

```text
active access terminated
```

de :

```text
already blocked membership permanently removed
```

---

## Événements non produits

La commande ne produit pas :

```text
MembershipSuspended
MembershipRoleChanged
MembershipRestored
UserDeleted
SessionRevoked
WorkspaceClosed
```

Des handlers peuvent déclencher des commandes distinctes pour :

```text
RevokeSession
RemoveExternalAccess
TransferResourceOwnership
```

---

## Erreurs métier

### MembershipNotFound

Le `Membership` n’existe pas.

---

### MembershipAlreadyRemoved

Le membre a déjà été retiré.

Cette erreur ne s’applique pas à la reprise idempotente de la même demande.

---

### MembershipStateNotRemovable

L’état courant ne permet pas le retrait.

---

### ActorNotAuthorized

L’acteur ne peut pas retirer ce membre.

---

### TargetMembershipProtected

Le membre cible est protégé contre ce retrait.

---

### TargetRoleProtected

Le rôle du membre cible exige une autorité supérieure.

---

### CannotRemoveOwnMembership

La commande administrative ne peut pas être utilisée pour un départ volontaire.

---

### UseLeaveWorkspace

Le workflow doit utiliser la commande spécialisée de départ volontaire.

---

### WorkspaceMustHaveActiveOwner

Le retrait laisserait le `Workspace` sans owner actif.

---

### RemovalBlockedByResponsibilities

Des responsabilités critiques empêchent le retrait.

---

### ReplacementMembershipRequired

Un remplaçant est obligatoire.

---

### ReplacementMembershipNotFound

Le membre remplaçant n’existe pas.

---

### ReplacementMembershipNotActive

Le membre remplaçant n’est pas actif.

---

### ReplacementMembershipBelongsToAnotherWorkspace

Le remplaçant n’appartient pas au même `Workspace`.

---

### ReplacementMembershipNotEligible

Le remplaçant ne peut pas recevoir les responsabilités requises.

---

### RemovalReasonInvalid

Le motif n’est pas reconnu.

---

### RemovalSourceInvalid

La source n’est pas reconnue.

---

### CaseReferenceRequired

Une référence de dossier obligatoire est absente.

---

### ExternalReferenceRequired

La source externe n’a pas fourni de référence stable.

---

### RemovalNotAuthorizedForSource

Le workflow ne peut pas utiliser ce motif ou retirer ce rôle.

---

### LegalHoldConflict

Une obligation légale bloque ou modifie le retrait.

---

### WorkspaceUnavailable

Le `Workspace` ne permet pas l’opération.

---

### RemovalConflict

Une autre transition concurrente a modifié le `Membership`.

---

### IdempotencyConflict

Le même `RemovalRequestId` a été réutilisé avec des données différentes.

---

## Idempotence

`RemoveMembership` doit être idempotente pour :

```text
MembershipId + RemovalRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- appliquer un second retrait ;
- modifier `RemovedAt` ;
- remplacer le motif ;
- changer la source ;
- fermer à nouveau une période ;
- produire un nouvel événement ;
- répéter les notifications ;
- répéter les déprovisionnements non idempotents.

---

## Reprise après réponse perdue

Cas typique :

```text
RemoveMembership succeeds

↓

Membership becomes Removed

↓

Response is lost

↓

Caller retries
```

La seconde exécution doit retrouver :

- le même `RemovalRequestId` ;
- le même `RemovedAt` ;
- le même motif ;
- la même source ;
- le même dernier rôle ;
- la même version finale.

Elle retourne le résultat initial.

---

## Réutilisation incorrecte du RemovalRequestId

Exemple initial :

```text
RemovalRequestId = ABC
Reason = EmploymentEnded
```

Nouvelle demande :

```text
RemovalRequestId = ABC
Reason = SecurityDecision
```

Le système doit retourner :

```text
IdempotencyConflict
```

Il ne doit pas réécrire l’historique.

---

## Clé d’idempotence par source

Des clés complémentaires peuvent être utilisées.

### ExternalSynchronization

```text
ExternalSystem
+
ExternalMembershipId
+
ExternalVersion
```

### SecurityWorkflow

```text
SecurityCaseId
+
DecisionId
```

### ContractTermination

```text
ContractId
+
TerminationDecisionId
```

Elles complètent mais ne remplacent pas `RemovalRequestId`.

---

## Concurrence

### RemoveMembership contre SuspendMembership

Une seule transition doit gagner.

Si le retrait gagne :

```text
Removed
```

La suspension concurrente échoue.

Si la suspension gagne :

```text
Suspended
```

Le retrait peut être réévalué et éventuellement s’appliquer depuis cet état.

---

### RemoveMembership contre ReactivateMembership

Ce cas concerne principalement un membre suspendu.

Si la réactivation gagne :

```text
Active
```

le retrait doit être réévalué.

Si le retrait gagne :

```text
Removed
```

la réactivation doit échouer et ne jamais restaurer implicitement le membre.

---

### RemoveMembership contre RestoreMembership

`RestoreMembership` exige un état `Removed`.

Une restauration ne peut pas précéder le retrait qu’elle prétend annuler.

Si les commandes sont concurrentes autour d’un retry ou d’un workflow incohérent, le versionnement doit garantir une seule séquence valide.

---

### RemoveMembership contre ChangeMembershipRole

Si le changement de rôle gagne en premier, le retrait doit revalider :

- le rôle final ;
- les protections ;
- la qualité d’owner ;
- les responsabilités.

Si le retrait gagne, le changement de rôle échoue.

---

### RemoveMembership contre DisableUser

Les deux opérations peuvent être légitimes mais ont des portées différentes.

```text
RemoveMembership
    -> one Workspace

DisableUser
    -> all Workspaces
```

Le résultat doit rester cohérent même si les deux réussissent.

---

### Deux retraits concurrents

Deux commandes avec le même `RemovalRequestId` sont dédupliquées.

Deux demandes différentes ne doivent produire qu’une seule transition vers `Removed`.

La seconde retourne :

```text
MembershipAlreadyRemoved
```

ou un conflit selon la politique.

---

### Deux retraits d’owners concurrents

État initial :

```text
Owner A
Owner B
```

Deux retraits concurrents ne doivent pas tous deux réussir.

Une coordination commune au `Workspace` est obligatoire.

---

### Retrait contre promotion d’un nouvel Owner

État initial :

```text
Owner A
Member B
```

Commandes concurrentes :

```text
Remove A
```

et :

```text
Promote B to Owner
```

Le retrait de A peut devenir valide après la promotion de B.

Le système doit soit :

- sérialiser les opérations ;
- utiliser un workflow de transfert ;
- demander un retry après conflit.

Il ne doit pas accepter le retrait à partir d’une projection obsolète.

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Membership removable
+
verify authorization
+
protect last active Owner
+
verify critical responsibilities
+
record Removed
+
close lifecycle period
+
increment authorization version
+
record domain event
```

Les états suivants sont interdits :

```text
Status = Removed
AND
RemovedAt is missing
```

```text
Status = Removed
AND
RemovalReason is missing
```

```text
MembershipRemoved published
AND
Membership remains Active
```

```text
Workspace has no active Owner
```

Une outbox transactionnelle est recommandée.

---

## Frontière transactionnelle

Le changement de statut du `Membership` doit être transactionnel.

Les opérations externes telles que :

- révocation de licences ;
- transfert de ressources ;
- suppression de groupes ;
- notifications ;
- nettoyage d’accès ;
- archivage documentaire ;

peuvent être asynchrones.

Cependant, les préconditions critiques doivent être validées avant commit.

---

## Retrait physique et suppression logique

`RemoveMembership` doit utiliser une suppression logique métier.

```text
Status = Removed
```

et non :

```text
DELETE FROM memberships
```

La suppression physique empêcherait :

- la restauration ;
- l’audit ;
- la déduplication ;
- la conservation de l’historique ;
- la protection de l’unicité ;
- la compréhension des événements passés.

---

## Contrainte d’unicité

La recommandation reste :

```text
UNIQUE(UserId, WorkspaceId)
```

Cette contrainte couvre aussi les membres retirés.

Lors d’un retour, le système doit :

```text
restore existing Membership
```

et non :

```text
create duplicate Membership
```

---

## Restauration future

Un membre retiré peut éventuellement revenir par :

```text
RestoreMembership
```

La restauration doit :

- cibler le même `MembershipId` ;
- recevoir un nouveau `RoleId` explicite ;
- vérifier le `User` ;
- vérifier le `Workspace` ;
- vérifier les restrictions ;
- vérifier le motif de retrait ;
- respecter `PreventAutomaticRestore` ;
- ouvrir une nouvelle période active.

---

## Interdiction de restauration automatique

Certains retraits ne doivent pas être annulés par une simple synchronisation.

Exemples :

```text
SecurityDecision
ComplianceDecision
PolicyViolation
```

Le retrait peut enregistrer :

```text
PreventAutomaticRestore = true
```

Une restauration nécessite alors :

- une décision humaine ;
- une clôture de dossier ;
- une permission renforcée ;
- une levée explicite de restriction.

---

## Conflit avec une synchronisation externe

Exemple :

```text
Local SecurityWorkflow removes Membership
```

puis :

```text
ExternalDirectory reports Membership active
```

La synchronisation ne doit pas restaurer automatiquement si une restriction locale prioritaire existe.

Règle possible :

```text
LocalSecurityRemoval
>
ExternalDirectoryActiveState
```

La priorité des sources doit être explicite.

---

## Retrait lié à la fermeture du Workspace

Lors de la fermeture d’un `Workspace`, tous les memberships peuvent devenir inactifs.

Ce workflow peut utiliser :

- une commande spécialisée globale ;
- un état du `Workspace` rendant tous les accès ineffectifs ;
- une série de `RemoveMembership`.

Le choix dépend de la frontière des bounded contexts.

Il peut être inutile d’appliquer l’invariant du dernier owner lorsque le `Workspace` est lui-même définitivement fermé.

Cette exception doit être explicite :

```text
WorkspaceClosure workflow
```

---

## Exception à l’invariant du dernier Owner

L’invariant :

```text
Workspace always has at least one active Owner
```

s’applique tant que le `Workspace` est actif.

Lors d’une fermeture définitive :

```text
Workspace.Status = Closed
```

le domaine peut autoriser :

```text
ActiveOwnerCount = 0
```

Cette exception appartient au workflow de fermeture, pas à un retrait ordinaire.

---

## Notifications

Après `MembershipRemoved`, un handler peut :

- informer le membre ;
- informer les owners ;
- informer la sécurité ;
- informer les responsables de ressources ;
- déclencher un transfert ;
- mettre à jour les projections ;
- lancer le déprovisionnement ;
- fermer des workflows ;
- recalculer les licences.

Les notifications ne font pas partie de la transaction de l’agrégat.

---

## Visibilité du motif

Le produit doit définir quelles informations sont visibles par :

- le membre retiré ;
- les owners ;
- les administrateurs ;
- le support ;
- la sécurité ;
- les systèmes externes.

Exemples :

```text
AccessNoLongerRequired
```

peut être affiché directement.

```text
SecurityDecision
```

peut nécessiter un message générique.

Le commentaire interne ne doit pas être exposé par défaut.

---

## Message utilisateur

Le message présenté au membre peut distinguer :

```text
Your access to this Workspace has ended
```

de :

```text
Your User account has been disabled
```

Ces situations ne doivent pas être confondues.

---

## Intégrations

Les systèmes externes peuvent consommer :

```text
MembershipRemoved
```

Exemples :

- provisioning ;
- annuaire ;
- contrôle de licence ;
- sécurité ;
- audit ;
- facturation ;
- analytics ;
- collaboration ;
- notifications.

Les consommateurs doivent être idempotents.

---

## Interprétation par les consommateurs

Les consommateurs ne doivent pas interpréter systématiquement :

```text
MembershipRemoved
```

comme :

```text
delete all User data
```

Ils doivent respecter :

- leur propre responsabilité ;
- les règles de conservation ;
- la portée du `Workspace` ;
- les obligations légales ;
- la distinction entre désactivation et suppression.

---

## Sécurité

La commande doit garantir que :

- seuls les acteurs autorisés peuvent retirer ;
- l’acteur peut agir sur le rôle cible ;
- le dernier owner actif est protégé ;
- le self-removal administratif est contrôlé ;
- les responsabilités critiques sont résolues ;
- le retrait prend effet immédiatement ;
- les caches et tokens deviennent obsolètes ;
- les sessions concernées perdent leur accès ;
- les systèmes externes reçoivent un événement fiable ;
- les retries ne produisent aucun doublon ;
- les motifs sensibles restent confidentiels.

---

## Confidentialité

L’événement et les réponses publiques doivent minimiser les données exposées.

Ils ne doivent pas révéler inutilement :

- les détails d’une enquête ;
- les violations alléguées ;
- les responsabilités des autres membres ;
- les permissions détaillées ;
- les commentaires internes ;
- les données personnelles non nécessaires ;
- les informations juridiques sensibles.

---

## Audit

Un retrait réussi doit enregistrer :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- dernier `RoleId`
- statut précédent
- `RemovedAt`
- `RemovedBy`
- `RemovalReason`
- `RemovalSource`
- `CaseReference`
- `ExternalReference`
- `ReplacementMembershipId`
- `PreventAutomaticRestore`
- `RemovalRequestId`
- `CorrelationId`
- version d’autorisation précédente
- nouvelle version d’autorisation
- politique appliquée aux sessions
- résultat de la readiness
- résultat final

---

## Questions auxquelles l’audit doit répondre

```text
who removed the Membership
which Workspace was affected
why the Membership was removed
which authority allowed the removal
which Role was previously held
whether the Membership was Active or Suspended
whether ownership was affected
whether a replacement was required
when access stopped
whether sessions were revoked
whether restoration is restricted
```

---

## Conservation de l’historique

Le système doit pouvoir reconstituer :

```text
Membership created
↓
Role changed
↓
Suspended
↓
Reactivated
↓
Removed
↓
Restored
```

Le retrait ne doit pas effacer les périodes précédentes.

---

## Périodes d’appartenance

Une représentation possible est :

```text
MembershipActivePeriod
├── StartedAt
├── StartSource
├── StartedBy
├── EndedAt
├── EndSource
├── EndedBy
└── EndReason
```

Lors d’une restauration, une nouvelle période commence.

---

## Cohérence temporelle

Les périodes actives ne doivent pas :

- se chevaucher ;
- rester ouvertes après retrait ;
- être recréées lors d’un retry ;
- être modifiées rétroactivement sans commande explicite.

---

## Rétention

Le retrait et la rétention sont deux concepts distincts.

```text
RemoveMembership
    -> end access
```

```text
RetentionPolicy
    -> determine how long historical data remains
```

Le retrait ne doit pas déclencher automatiquement une suppression irréversible des données.

---

## Effacement de données personnelles

Une demande d’effacement peut survenir après le retrait.

Elle doit être traitée par un workflow distinct prenant en compte :

- les obligations légales ;
- l’audit ;
- la fraude ;
- la sécurité ;
- les contrats ;
- la pseudonymisation ;
- les données appartenant à d’autres bounded contexts.

Le `MembershipId` peut être conservé même si certaines données personnelles sont pseudonymisées.

---

## Décisions de conception

### RemoveMembership termine l’appartenance

Le membre ne fait plus partie du `Workspace`.

Cette sémantique est plus forte qu’une suspension.

---

### Le Membership n’est pas supprimé physiquement

L’identité et l’historique sont conservés.

Le statut devient :

```text
Removed
```

---

### Le retrait depuis Suspended est autorisé

Un membre suspendu peut être retiré directement.

Aucune réactivation intermédiaire n’est nécessaire.

---

### Le dernier Role est conservé comme donnée historique

Il ne confère plus aucune permission.

Une restauration doit recevoir un nouveau rôle explicite.

---

### Le dernier Owner est protégé

Le retrait d’un owner est autorisé uniquement si :

```text
ActiveOwnerCountAfterRemoval >= 1
```

---

### L’erreur commune est WorkspaceMustHaveActiveOwner

Elle exprime l’invariant partagé avec :

- `ChangeMembershipRole`
- `SuspendMembership`
- `DisableUser`

---

### La coordination du dernier Owner est fortement cohérente

Une projection asynchrone ne suffit pas.

---

### Le départ volontaire mérite une commande distincte

`LeaveWorkspace` exprime l’intention du membre.

`RemoveMembership` exprime une décision administrative ou système.

---

### Les responsabilités bloquantes sont vérifiées avant retrait

Identity peut consommer une décision de readiness sans absorber les modèles des autres bounded contexts.

---

### Les permissions cessent immédiatement

Le retrait ne dépend pas de l’expiration des tokens.

---

### Les sessions d’autres Workspace peuvent rester valides

La portée du retrait est le `Membership`, pas nécessairement le `User`.

---

### La restauration reste explicite

Le retour utilise :

```text
RestoreMembership
```

et ne recrée pas une nouvelle appartenance.

---

### Certains retraits bloquent la restauration automatique

Les décisions de sécurité ou conformité peuvent exiger une levée explicite.

---

### MembershipRemoved est un événement métier dédié

Il ne doit pas être remplacé par :

```text
MembershipUpdated
```

ou :

```text
MembershipDeleted
```

---

## Cas limites

### Membership sans Role résolvable

Un membre actif ou suspendu dont le rôle n’existe plus indique une incohérence.

Le retrait peut éventuellement rester possible pour sécuriser l’accès, mais l’anomalie doit être auditée.

---

### User déjà désactivé

Le retrait peut rester utile pour terminer l’appartenance locale.

Le `User` désactivé ne compte pas comme owner actif.

---

### Workspace en cours de fermeture

Le retrait doit suivre la politique du workflow de fermeture.

L’invariant du dernier owner peut être levé uniquement dans ce contexte explicite.

---

### Rôle owner renommé

La qualité d’owner repose sur une propriété stable, pas sur le nom du rôle.

---

### Compte de service owner

La politique doit préciser s’il compte dans `ActiveOwnerCount`.

La recommandation est de ne pas considérer un compte de service comme l’unique owner valide lorsque la gouvernance humaine est requise.

---

### Invitation en attente vers un futur Owner

Elle ne compte pas comme owner actif.

Le dernier owner ne peut pas être retiré en supposant que l’invitation sera acceptée.

---

### Remplaçant suspendu

Un `ReplacementMembershipId` suspendu n’est pas un remplaçant valide pour une responsabilité active.

---

### Retrait avec déprovisionnement externe partiel

Le domaine reste `Removed`.

L’intégration passe dans un état de retry ou d’incident.

---

### Retrait puis invitation immédiate

Le workflow peut :

```text
RemoveMembership
↓
CreateInvitation
↓
RestoreMembership after acceptance
```

Il ne doit pas créer un second `Membership`.

---

### Retrait par synchronisation après retrait manuel

Le système doit reconnaître que l’état cible est déjà atteint sans réécrire la cause du retrait initial.

La synchronisation peut enregistrer sa propre progression sans produire un second événement métier.

---

## Checklist de validation

Avant commit, la commande doit confirmer :

```text
Membership exists
Membership is Active or Suspended
Removal request is idempotent
Workspace exists
Actor or SystemActor is authorized
Target Membership is removable
Target Role is removable by Actor
Self-removal policy is respected
Removal reason is valid
Source is valid
Required references are present
Critical responsibilities are resolved
Replacement is valid when required
Last active Owner remains
Concurrency version is valid
Authorization invalidation can be recorded
Removal event can be persisted
```

---

## Synthèse

`RemoveMembership` met fin à l’appartenance effective d’un membre à un `Workspace`.

Elle garantit que :

- le `Membership` existe ;
- son état permet le retrait ;
- l’acteur ou le workflow est autorisé ;
- le membre cible peut être retiré ;
- les responsabilités critiques sont résolues ;
- le dernier owner actif est protégé ;
- l’état devient `Removed` ;
- l’identité et l’historique sont conservés ;
- le dernier rôle reste historique mais ineffectif ;
- aucune permission n’est encore accordée ;
- les sessions et caches perdent immédiatement cet accès ;
- les déprovisionnements externes peuvent être traités de manière fiable ;
- la restauration future reste explicite ;
- les retries et opérations concurrentes ne créent aucun état incohérent.

Le résultat final est :

```text
Active or Suspended Membership
    ↓
Removed Membership
```

avec conservation de l’identité :

```text
same MembershipId
same UserId
same WorkspaceId
last Role preserved as history
no effective access
restoration possible through RestoreMembership
```