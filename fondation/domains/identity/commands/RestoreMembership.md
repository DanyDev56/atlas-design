---
id: IDN-CMD-RESTORE-MEMBERSHIP
title: RestoreMembership
status: Draft
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
  - ../events/MembershipRestored.md
  - CreateMembership.md
  - SuspendMembership.md
  - ReactivateMembership.md
  - RemoveMembership.md
  - ChangeMembershipRole.md
  - AcceptInvitation.md
---

# RestoreMembership

## Objectif

La commande `RestoreMembership` réactive un `Membership` précédemment retiré du `Workspace`.

Elle conserve :

- le même `MembershipId` ;
- le même `UserId` ;
- le même `WorkspaceId` ;
- l’historique de l’appartenance ;
- la traçabilité de la suppression précédente.

La restauration peut attribuer un nouveau `Role`, déterminé explicitement par le parcours métier ayant autorisé le retour.

Le résultat attendu est :

```text
Removed Membership
    ↓
Active Membership
```

---

## Différence avec ReactivateMembership

`RestoreMembership` et `ReactivateMembership` expriment deux transitions différentes.

### RestoreMembership

```text
Removed
    ↓
Active
```

Le membre avait quitté le `Workspace` ou en avait été retiré.

Son appartenance n’était plus effective.

### ReactivateMembership

```text
Suspended
    ↓
Active
```

Le membre appartenait toujours au `Workspace`, mais son accès était temporairement bloqué.

La distinction doit être conservée pour :

- l’audit ;
- les permissions ;
- les notifications ;
- les règles d’autorisation ;
- les workflows de sécurité ;
- l’analyse de l’historique.

---

## Agrégat concerné

`Membership`

Le `Membership` constitue la racine de l’agrégat modifié.

La commande référence également :

- un `User` ;
- un `Workspace` ;
- un `Role` ;
- éventuellement une `Invitation`.

Ces éléments ne sont pas créés par la commande.

---

## Acteur

La commande peut être demandée par :

- un `User` autorisé à gérer les membres ;
- un workflow d’acceptation d’`Invitation` ;
- un processus administratif ;
- un workflow de retour d’un ancien membre ;
- un processus de synchronisation externe ;
- un processus système explicitement autorisé.

L’origine de la restauration doit toujours être identifiable.

---

## Sources de restauration

Valeurs recommandées :

```text
Invitation
ManualAdministration
SystemProvisioning
ExternalSynchronization
AdministrativeRecovery
```

### Invitation

Un ancien membre accepte une nouvelle `Invitation`.

### ManualAdministration

Un administrateur réintègre directement le `User`.

### SystemProvisioning

Un processus système restaure le membre selon une politique explicite.

### ExternalSynchronization

La restauration provient d’un système externe faisant autorité.

### AdministrativeRecovery

La restauration corrige une suppression erronée ou un incident opérationnel.

---

## Permission requise

Lorsqu’un `User` demande la restauration, la permission recommandée est :

```text
workspace.members.manage
```

Une permission plus spécifique peut être introduite :

```text
workspace.members.restore
```

L’autorisation doit être évaluée dans le `Workspace` du `Membership`.

Elle doit également vérifier que l’acteur peut attribuer le `Role` demandé.

---

## Cas sans permission utilisateur préalable

Certains workflows peuvent restaurer un `Membership` sans permission humaine directe.

Exemples :

```text
InvitationAcceptanceWorkflow
DirectorySynchronization
AdministrativeRecoveryWorkflow
```

Le workflow doit disposer d’une autorité explicite, limitée et traçable.

Il ne doit pas utiliser une identité système générique dépourvue de contexte.

---

## Préconditions

Avant l’exécution de `RestoreMembership`, les conditions suivantes doivent être satisfaites :

- le `Membership` existe ;
- son état est `Removed` ;
- le `User` existe ;
- le `User` est actif ;
- le `Workspace` existe ;
- le `Workspace` accepte la restauration ;
- le `Role` cible existe ;
- le `Role` est actif ;
- le `Role` appartient au `Workspace` ;
- le `Role` est attribuable ;
- l’acteur ou le workflow est autorisé ;
- aucune interdiction permanente ne bloque la restauration ;
- aucune autre appartenance concurrente n’existe ;
- la source de restauration est valide ;
- les références conditionnelles sont cohérentes ;
- la restauration ne viole pas l’invariant du dernier owner.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `MembershipId` | `MembershipId` | Oui | Identifie le `Membership` à restaurer. |
| `RoleId` | `RoleId` | Oui | Identifie le `Role` attribué après restauration. |
| `RestoredBy` | `UserId` ou `SystemActor` | Oui | Identifie l’origine de la restauration. |
| `RestoredAt` | Instant | Oui | Date la restauration métier. |
| `RestorationSource` | `MembershipRestorationSource` | Oui | Indique le parcours ayant autorisé le retour. |
| `RestorationRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `InvitationId` | `InvitationId` | Conditionnel | Référence l’`Invitation` autorisant le retour. |
| `ExternalReference` | `ExternalReference` | Conditionnel | Référence une source externe. |
| `Reason` | `MembershipRestorationReason` | Non | Motif structuré de la restauration. |
| `Comment` | Texte court | Non | Précision interne facultative. |
| `CorrelationId` | Identifiant | Non | Relie l’opération au workflow appelant. |

---

## MembershipRestorationSource

Valeurs recommandées :

```text
Invitation
ManualAdministration
SystemProvisioning
ExternalSynchronization
AdministrativeRecovery
```

La source détermine :

- le type d’autorité requis ;
- les références obligatoires ;
- les notifications ;
- l’audit ;
- la stratégie de réconciliation ;
- les contrôles complémentaires.

Elle ne modifie pas les invariants fondamentaux du `Membership`.

---

## MembershipRestorationReason

Valeurs possibles :

```text
UserRejoined
RemovalReversed
AdministrativeCorrection
InvitationAccepted
ExternalDirectoryRestored
WorkspacePolicyChanged
Other
```

Le motif peut être obligatoire pour certaines sources.

Exemple :

```text
RestorationSource = AdministrativeRecovery
```

peut exiger un motif précis et un commentaire d’audit.

---

## Validation des données

### MembershipId

Le `MembershipId` doit identifier un `Membership` existant.

La commande ne recherche pas un membre uniquement à partir de :

```text
UserId + WorkspaceId
```

Le workflow peut utiliser cette clé pour retrouver le `MembershipId`, mais l’intention finale cible explicitement l’agrégat.

---

### RoleId

Le `RoleId` doit identifier un `Role` :

- existant ;
- actif ;
- attribuable ;
- appartenant au même `Workspace` ;
- compatible avec la source de restauration.

La condition suivante doit être vraie :

```text
Role.WorkspaceId = Membership.WorkspaceId
```

---

### RestoredBy

Lorsque l’acteur est un `User`, celui-ci doit :

- exister ;
- être actif ;
- posséder un `Membership` actif dans le `Workspace` ;
- posséder la permission requise ;
- pouvoir attribuer le `Role` demandé.

Lorsque l’acteur est un `SystemActor`, son identité et son périmètre doivent être explicites.

---

### RestoredAt

`RestoredAt` représente l’instant métier de restauration.

Il doit :

- être fourni par une abstraction `Clock` ;
- être postérieur ou égal à la date de retrait ;
- rester inchangé lors des retries ;
- être utilisé pour l’audit et l’ordre des événements.

Condition recommandée :

```text
RestoredAt >= Membership.RemovedAt
```

---

### RestorationRequestId

Le `RestorationRequestId` doit :

- identifier une intention logique unique ;
- rester stable pendant les retries ;
- permettre de restituer le résultat initial ;
- ne contenir aucune donnée sensible.

---

### InvitationId

Lorsque :

```text
RestorationSource = Invitation
```

alors `InvitationId` est obligatoire.

L’`Invitation` doit :

- exister ;
- être valide ;
- correspondre au même `User` ;
- correspondre au même `Workspace` ;
- prévoir le même `Role` ;
- ne pas avoir déjà produit une autre restauration ou création ;
- être en cours d’acceptation.

---

### ExternalReference

Lorsque :

```text
RestorationSource = ExternalSynchronization
```

une référence externe stable doit généralement être fournie.

Elle sert à :

- dédupliquer ;
- auditer ;
- réconcilier ;
- retrouver l’origine externe.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Charger le Membership

Le système charge le `Membership` identifié par `MembershipId`.

S’il n’existe pas, la commande échoue.

---

### 2. Vérifier son état

Le `Membership` doit être dans l’état :

```text
Removed
```

Les autres états ont des significations différentes.

#### Active

Le `Membership` est déjà effectif.

La restauration n’est pas nécessaire.

#### Suspended

Le `Membership` doit être réactivé avec :

```text
ReactivateMembership
```

#### Removed

La restauration peut être envisagée.

---

### 3. Vérifier l’idempotence

Avant d’appliquer une nouvelle transition, le système vérifie si le même `RestorationRequestId` a déjà produit une restauration.

Lorsqu’un résultat existe, il doit être retourné sans nouvel effet.

---

### 4. Charger le User

Le système charge le `User` référencé par le `Membership`.

Le `UserId` ne peut pas être modifié pendant la restauration.

---

### 5. Vérifier l’état du User

Le `User` doit être dans un état compatible avec une nouvelle appartenance.

Les états incompatibles peuvent inclure :

```text
Disabled
Deleted
Blocked
PendingDeletion
```

La restauration ne doit pas contourner une désactivation du compte.

---

### 6. Vérifier le Workspace

Le système confirme que le `Workspace` :

- existe ;
- est actif ;
- accepte le retour d’un membre ;
- n’a pas atteint une limite bloquante ;
- ne possède pas une politique interdisant la restauration ;
- accepte la source concernée.

Le bounded context `Identity` consomme cette décision par un contrat explicite.

---

### 7. Charger le Role

Le système charge le `Role` identifié par `RoleId`.

Le rôle historique du `Membership` ne doit pas être réutilisé silencieusement.

---

### 8. Vérifier la compatibilité du Role

Le système vérifie :

```text
Role.WorkspaceId = Membership.WorkspaceId
```

Il vérifie également que le `Role` :

- est actif ;
- est attribuable ;
- respecte les restrictions de hiérarchie ;
- correspond à l’`Invitation`, lorsque applicable ;
- respecte les politiques du `Workspace`.

---

### 9. Autoriser l’acteur ou le workflow

Pour une restauration manuelle, le système vérifie que l’acteur :

- possède la permission requise ;
- appartient au bon `Workspace` ;
- peut restaurer ce membre ;
- peut attribuer le `Role` demandé ;
- ne contourne pas une décision de sécurité.

Pour un workflow, le système vérifie :

- sa source ;
- ses références ;
- son périmètre ;
- sa politique d’autorité.

---

### 10. Vérifier la nature du retrait précédent

Le système examine la raison du retrait.

Certains retraits peuvent être restaurables.

Exemples :

```text
UserLeft
AdministrativeRemoval
WorkspaceCleanup
RemovalByMistake
```

D’autres peuvent nécessiter une autorisation renforcée ou interdire la restauration.

Exemples :

```text
SecurityViolation
PermanentBan
LegalRestriction
Fraud
```

La restauration ne doit jamais annuler implicitement une interdiction permanente.

---

### 11. Vérifier l’unicité

Le système confirme qu’aucun autre `Membership` n’existe pour :

```text
UserId + WorkspaceId
```

Dans le modèle recommandé, le même enregistrement est conservé et réutilisé.

La contrainte suivante reste applicable :

```text
UNIQUE(UserId, WorkspaceId)
```

---

### 12. Vérifier les limites du Workspace

La restauration peut réaugmenter le nombre de membres actifs.

Le système vérifie notamment :

- la capacité maximale ;
- les contraintes de licence ;
- les restrictions de rôle ;
- les règles de domaine ;
- les politiques organisationnelles.

---

### 13. Restaurer le Membership

L’agrégat passe de :

```text
Removed
```

à :

```text
Active
```

Il conserve :

- `MembershipId` ;
- `UserId` ;
- `WorkspaceId` ;
- l’historique antérieur ;
- les données du retrait précédent.

Il enregistre :

- le nouveau `RoleId` ;
- `RestoredAt` ;
- `RestoredBy` ;
- `RestorationSource` ;
- `RestorationRequestId` ;
- le motif et les références associés.

---

### 14. Fermer le cycle de retrait précédent

Le modèle peut enregistrer que la période de retrait se termine à `RestoredAt`.

Exemple conceptuel :

```text
RemovalPeriod
├── RemovedAt
├── RemovedBy
├── RemovalReason
└── EndedAt: RestoredAt
```

Cette représentation relève du modèle d’historisation choisi.

---

### 15. Produire l’événement

L’agrégat produit :

```text
MembershipRestored
```

Les projections, notifications et intégrations sont traitées après la transaction.

---

## Résultat attendu

Après une exécution réussie :

- le même `Membership` existe ;
- son état est `Active` ;
- son `MembershipId` est inchangé ;
- son `UserId` est inchangé ;
- son `WorkspaceId` est inchangé ;
- son `RoleId` est celui explicitement validé ;
- le retrait précédent reste auditable ;
- la source de restauration est enregistrée ;
- aucun doublon n’est créé.

État conceptuel :

```text
Membership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── RoleId: restored role
├── Status: Active
├── RestoredAt
├── RestoredBy
├── RestorationSource
└── Previous removal history preserved
```

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` existe pour :

```text
UserId + WorkspaceId
```

La restauration réutilise l’entité existante.

---

### `IDN-INV-002`

Le `Membership` doit référencer un `User`, un `Workspace` et un `Role` valides.

---

### `IDN-INV-003`

Un `Membership` actif possède exactement un `Role`.

---

### `IDN-INV-004`

La transition d’état doit être valide :

```text
Removed -> Active
```

---

### `IDN-INV-005`

Le `Role` appartient au même `Workspace`.

```text
Membership.WorkspaceId = Role.WorkspaceId
```

---

### `IDN-INV-006`

La restauration ne doit pas violer la règle de propriété du `Workspace`.

---

### `IDN-INV-010`

Les `Permission` sont obtenues par le `Role`, jamais attribuées directement.

---

### `IDN-INV-014`

L’autorisation est évaluée dans le contexte du `Workspace`.

---

### `IDN-INV-015`

Le `Workspace` doit accepter la restauration.

---

## Transition d’état

Transition autorisée :

```text
Removed
    ↓
Active
```

Transitions interdites :

```text
Active
    ↓
Active
```

```text
Suspended
    ↓
Active
```

par `RestoreMembership`.

La transition depuis `Suspended` relève de :

```text
ReactivateMembership
```

---

## Role après restauration

Le `Role` après restauration doit être explicite.

Trois stratégies sont possibles.

### Reprendre l’ancien Role

Cette stratégie peut être utilisée uniquement si :

- l’ancien rôle existe encore ;
- il est actif ;
- il reste attribuable ;
- la politique l’autorise ;
- le workflow l’a explicitement sélectionné.

### Utiliser le Role de l’Invitation

Lorsque la source est une `Invitation`, cette stratégie est recommandée.

```text
Membership.RoleId = Invitation.RoleId
```

### Utiliser un Role déterminé par l’administrateur

La restauration manuelle peut attribuer un rôle différent, sous réserve d’autorisation.

---

## Politique recommandée

La commande reçoit toujours un `RoleId`.

Elle ne choisit pas silencieusement entre l’ancien rôle, le rôle par défaut et le rôle d’une invitation.

La résolution est effectuée avant la commande.

```text
Resolve restoration Role
↓
RestoreMembership with RoleId
```

---

## Événement produit

### MembershipRestored

La commande produit :

```text
MembershipRestored
```

L’événement peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `PreviousRoleId`
- `RoleId`
- `RestoredAt`
- `RestoredBy`
- `RestorationSource`
- `Reason`
- `InvitationId`
- `ExternalReference`
- `RestorationRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- le token d’invitation ;
- des secrets ;
- la liste complète des permissions ;
- des données personnelles non nécessaires ;
- des commentaires internes sensibles.

---

## Événements non produits

La commande ne produit pas :

```text
MembershipCreated
MembershipReactivated
MembershipRoleChanged
InvitationAccepted
UserActivated
```

Le changement de rôle inclus dans la restauration fait partie du fait `MembershipRestored`.

Il ne nécessite pas nécessairement un événement `MembershipRoleChanged`, car le `Membership` n’était pas actif avant la transition.

---

## Erreurs métier

### MembershipNotFound

Le `Membership` n’existe pas.

---

### MembershipAlreadyActive

Le `Membership` est déjà actif.

Cette erreur ne doit pas être retournée lorsqu’il s’agit de la reprise idempotente du même résultat.

---

### MembershipSuspended

Le `Membership` est suspendu.

Le workflow doit utiliser :

```text
ReactivateMembership
```

---

### MembershipNotRemoved

L’état courant ne permet pas la restauration.

---

### MembershipRestorationForbidden

La politique interdit la restauration de ce `Membership`.

---

### PermanentRestriction

Une restriction permanente empêche le retour du `User`.

---

### UserNotFound

Le `User` n’existe plus.

---

### UserUnavailable

Le `User` n’est pas dans un état compatible.

---

### WorkspaceNotFound

Le `Workspace` n’existe pas.

---

### WorkspaceUnavailable

Le `Workspace` n’accepte pas la restauration.

---

### WorkspaceMemberLimitReached

La restauration dépasserait la capacité autorisée.

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
Role.WorkspaceId = Membership.WorkspaceId
```

---

### RoleNotAssignable

Le `Role` ne peut pas être attribué dans ce contexte.

---

### ActorNotAuthorized

L’acteur ne peut pas restaurer ce `Membership`.

---

### RoleAssignmentNotAuthorized

L’acteur ne peut pas attribuer le `Role` demandé.

---

### InvalidRestorationSource

La source de restauration n’est pas reconnue.

---

### MissingSourceReference

Une référence obligatoire manque.

Exemples :

```text
RestorationSource = Invitation
AND
InvitationId is missing
```

```text
RestorationSource = ExternalSynchronization
AND
ExternalReference is missing
```

---

### InvitationNotCompatible

L’`Invitation` ne correspond pas au `Membership`, au `User`, au `Workspace` ou au `Role`.

---

### RestorationConflict

Une autre opération concurrente a modifié le `Membership`.

---

## Idempotence

`RestoreMembership` doit être idempotente pour :

```text
MembershipId + RestorationRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- appliquer une seconde restauration ;
- modifier `RestoredAt` ;
- produire un nouvel événement métier ;
- changer le `Role` ;
- remplacer la source ;
- répéter les effets externes.

---

## Idempotence par source

Des clés complémentaires peuvent être utilisées.

### Invitation

```text
InvitationId
```

Une `Invitation` ne doit produire qu’une seule restauration.

### ExternalSynchronization

```text
ExternalSystem + ExternalReference
```

### AdministrativeRecovery

```text
MembershipId + RecoveryCaseId
```

Ces clés complètent mais ne remplacent pas :

```text
MembershipId + RestorationRequestId
```

---

## Reprise après réponse perdue

Cas typique :

```text
RestoreMembership succeeds

↓

Membership becomes Active

↓

Response is lost

↓

Caller retries
```

La seconde exécution doit retrouver :

- le même `RestorationRequestId` ;
- le même `MembershipId` ;
- le même `RoleId` ;
- le même `RestoredAt`.

Elle retourne alors le résultat initial.

---

## Concurrence

### RestoreMembership contre CreateMembership

La restauration doit gagner lorsqu’un ancien `Membership` existe.

`CreateMembership` ne doit pas créer une nouvelle entité pour contourner l’état `Removed`.

La contrainte :

```text
UNIQUE(UserId, WorkspaceId)
```

empêche les doublons.

---

### RestoreMembership contre RemoveMembership

Deux opérations concurrentes peuvent tenter de restaurer et de retirer le même membre.

Une seule transition doit gagner.

Résultats possibles :

```text
Active
```

ou :

```text
Removed
```

selon la version validée.

Le résultat doit rester auditable.

---

### RestoreMembership contre SuspendMembership

Une suspension ne peut normalement s’appliquer qu’à un membre actif.

Si la restauration gagne d’abord, une suspension ultérieure peut s’appliquer.

Si la suspension cible une ancienne version, elle doit échouer avec un conflit.

---

### RestoreMembership contre ChangeMembershipRole

Le rôle attribué par la restauration fait partie de l’opération atomique.

Une commande concurrente de changement de rôle doit :

- s’appliquer après la restauration ;
- ou échouer sur conflit de version.

---

### Deux restaurations concurrentes

Deux commandes avec le même `RestorationRequestId` sont dédupliquées.

Deux commandes différentes ne doivent produire qu’une seule transition :

```text
Removed -> Active
```

Si elles demandent des rôles différents, une seule peut réussir.

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Removed
+
verify restoration allowed
+
assign Role
+
record Active
+
record restoration metadata
+
record domain event
```

Les états suivants sont interdits :

```text
Status = Active
AND
RoleId is missing
```

```text
Status = Active
AND
restoration history is missing
```

```text
MembershipRestored published
AND
Membership remains Removed
```

Une outbox transactionnelle peut garantir la publication fiable.

---

## Relation avec Invitation

Lorsque la restauration provient d’une `Invitation`, le workflow recommandé est :

```text
Validate Invitation
↓
Resolve existing Removed Membership
↓
RestoreMembership
↓
MembershipRestored
↓
Accept Invitation
↓
InvitationAccepted
```

Les références doivent correspondre exactement :

```text
Membership.UserId = Invitation recipient User
Membership.WorkspaceId = Invitation.WorkspaceId
RoleId = Invitation.RoleId
```

L’`Invitation` ne doit devenir `Accepted` qu’après la restauration réussie.

---

## Relation avec RemoveMembership

`RemoveMembership` clôt une période d’appartenance effective.

`RestoreMembership` ouvre une nouvelle période d’activité sur la même identité métier.

Historique conceptuel :

```text
Membership created
↓
Active
↓
Removed
↓
Restored
↓
Active
```

Le modèle doit conserver chaque transition.

---

## Relation avec SuspendMembership

Un `Membership` supprimé ne peut pas être suspendu.

Une suspension suppose une appartenance encore existante et temporairement bloquée.

```text
Removed
    -> no active membership relationship

Suspended
    -> relationship retained, access blocked
```

---

## Relation avec ChangeMembershipRole

Le rôle attribué pendant la restauration est une décision de restauration.

Il n’est pas nécessaire de restaurer d’abord avec l’ancien rôle puis d’exécuter `ChangeMembershipRole`.

Le workflow recommandé est :

```text
RestoreMembership with target RoleId
```

Cela évite un état intermédiaire inutile.

---

## Permissions effectives

Après restauration, les permissions effectives redeviennent accessibles par :

```text
Session
↓
User
↓
Membership
↓
Role
↓
Permission
```

Aucune permission n’est copiée directement sur le `Membership`.

Les projections d’autorisation doivent être recalculées ou invalidées après `MembershipRestored`.

---

## Sessions existantes

La restauration d’un `Membership` ne doit pas réactiver automatiquement d’anciennes sessions ou autorisations mises en cache.

Les sessions existantes du `User` doivent :

- recharger leur contexte d’autorisation ;
- recevoir une nouvelle version d’autorisation ;
- ou être invalidées selon la politique de sécurité.

Une ancienne session ne doit pas conserver un contexte issu de la période précédant le retrait.

---

## Invitations multiples

Lorsqu’un ancien membre possède plusieurs invitations actives pour le même `Workspace`, le workflow doit résoudre le conflit avant la restauration.

Une seule invitation doit autoriser l’opération.

Les autres doivent ensuite être :

- révoquées ;
- marquées obsolètes ;
- ou rendues inutilisables selon la politique retenue.

La restauration ne doit pas produire plusieurs effets.

---

## Notifications

Après `MembershipRestored`, un handler peut :

- informer le membre ;
- informer les administrateurs ;
- déclencher un nouvel onboarding ;
- mettre à jour les projections ;
- réinitialiser certains accès ;
- informer les systèmes de licence ;
- fermer une invitation concurrente.

Les notifications ne font pas partie de la transaction principale.

---

## Intégrations

Les systèmes externes peuvent consommer :

```text
MembershipRestored
```

Exemples :

- provisioning ;
- facturation ;
- annuaire ;
- audit ;
- analytics ;
- licences ;
- notifications.

Les consommateurs doivent être idempotents.

Ils doivent distinguer :

```text
MembershipCreated
```

de :

```text
MembershipRestored
```

afin de préserver la sémantique de l’historique.

---

## Sécurité

La commande doit garantir que :

- un retrait permanent n’est pas contourné ;
- le `User` est actif ;
- le `Workspace` accepte la restauration ;
- le `Role` appartient au bon `Workspace` ;
- l’acteur peut attribuer ce `Role` ;
- aucun doublon n’est créé ;
- les anciennes sessions ne récupèrent pas automatiquement un accès ;
- la source de restauration est vérifiable ;
- l’historique du retrait est conservé ;
- aucune donnée sensible n’est publiée.

---

## Confidentialité

L’événement et l’audit doivent minimiser les données exposées.

Ils ne doivent pas publier inutilement :

- l’adresse e-mail du `User` ;
- la liste complète des permissions ;
- les motifs de sécurité confidentiels ;
- le commentaire administratif ;
- les autres appartenances du `User`.

Les consommateurs autorisés peuvent résoudre les informations nécessaires à partir des identifiants.

---

## Audit

Une restauration réussie doit enregistrer :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- ancien `RoleId`
- nouveau `RoleId`
- `RestoredAt`
- `RestoredBy`
- `RestorationSource`
- `Reason`
- référence d’origine
- `RestorationRequestId`
- `CorrelationId`
- résultat

L’audit doit permettre de répondre à :

```text
why was the Membership previously removed
who restored it
which authority allowed the restoration
which Role was assigned
which workflow initiated the return
```

---

## Décisions de conception

### La restauration conserve le MembershipId

Le `Membership` représente la relation durable entre un `User` et un `Workspace`.

Une restauration réutilise cette identité au lieu de créer une nouvelle relation parallèle.

---

### Removed est restaurable uniquement par décision explicite

Un retrait ne disparaît pas automatiquement.

Le retour nécessite une nouvelle intention métier, une autorité et une trace.

---

### La restauration se distingue de la réactivation

`Removed` et `Suspended` ne représentent pas le même état.

Leurs commandes, événements et règles doivent rester séparés.

---

### Le Role est déterminé explicitement

L’ancien rôle ne doit pas être repris silencieusement.

La commande reçoit le rôle validé pour la nouvelle période d’activité.

---

### L’historique du retrait est conservé

La restauration n’efface ni la date, ni l’acteur, ni le motif du retrait précédent.

Elle ajoute une nouvelle transition à l’historique.

---

### Les restrictions permanentes priment

Une nouvelle invitation ou une action administrative ordinaire ne doit pas contourner une interdiction permanente.

Une levée explicite de restriction peut être nécessaire avant la restauration.

---

### La restauration ne réactive pas les sessions

Le retour du `Membership` et la validité d’une `Session` sont deux décisions distinctes.

Le contexte d’autorisation doit être recalculé.

---

### MembershipRestored est distinct de MembershipCreated

La distinction permet de préserver :

- l’identité ;
- l’historique ;
- les métriques ;
- les politiques d’onboarding ;
- le comportement des intégrations.

---

## Synthèse

`RestoreMembership` rétablit une appartenance précédemment retirée tout en conservant l’identité et l’historique du `Membership`.

Elle garantit que :

- le `Membership` existe et est `Removed` ;
- le `User` et le `Workspace` permettent le retour ;
- aucune restriction permanente n’est contournée ;
- le `Role` cible est valide et appartient au bon `Workspace` ;
- l’acteur ou le workflow est autorisé ;
- le même `MembershipId` est conservé ;
- l’état devient `Active` ;
- la source et le motif de restauration sont traçables ;
- les anciennes sessions ne récupèrent pas automatiquement un accès ;
- les retries et opérations concurrentes ne produisent aucun doublon.

Le résultat final est :

```text
Removed Membership
    ↓
Active Membership
```

avec conservation de l’identité :

```text
same MembershipId
same UserId
same WorkspaceId
new explicit active period
```