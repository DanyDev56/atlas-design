---
id: IDN-CMD-REACTIVATE-MEMBERSHIP
title: ReactivateMembership
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
  - ../events/MembershipReactivated.md
  - CreateMembership.md
  - RestoreMembership.md
  - SuspendMembership.md
  - RemoveMembership.md
  - ChangeMembershipRole.md
---

# ReactivateMembership

## Objectif

La commande `ReactivateMembership` rétablit l’accès d’un `Membership` précédemment suspendu.

Elle fait passer le `Membership` de :

```text
Suspended
```

à :

```text
Active
```

La réactivation conserve :

- le même `MembershipId` ;
- le même `UserId` ;
- le même `WorkspaceId` ;
- l’historique de la suspension ;
- la continuité de l’appartenance.

Le `Role` peut être conservé ou remplacé explicitement selon la politique retenue.

---

## Signification métier

Un `Membership` réactivé redevient utilisable pour l’autorisation dans son `Workspace`.

La chaîne d’autorisation redevient potentiellement valide :

```text
Session
↓
User
↓
Active Membership
↓
Role
↓
Permission
```

La réactivation ne doit pas être interprétée comme une simple modification de statut technique.

Elle représente la décision métier suivante :

```text
the temporary blocking condition is resolved
AND
the Membership may grant access again
```

---

## Différence avec RestoreMembership

### ReactivateMembership

```text
Suspended
    ↓
Active
```

La relation d’appartenance existait toujours pendant la suspension.

### RestoreMembership

```text
Removed
    ↓
Active
```

L’appartenance effective avait pris fin.

Ces commandes doivent rester séparées pour préserver :

- la sémantique métier ;
- l’historique ;
- les permissions ;
- les notifications ;
- les intégrations ;
- les règles de sécurité.

---

## Agrégat concerné

`Membership`

Le `Membership` constitue la racine de l’agrégat modifié.

La commande peut consulter :

- le `User` ;
- le `Workspace` ;
- le `Role` actuel ;
- un nouveau `Role`, le cas échéant ;
- la suspension active ;
- les restrictions de sécurité ;
- les autres owners actifs du `Workspace`.

Ces éléments ne sont pas directement modifiés par la commande.

---

## Acteur

La commande peut être demandée par :

- un `User` autorisé à gérer les membres ;
- un owner du `Workspace` ;
- un administrateur ;
- un workflow de sécurité ;
- un processus de conformité ;
- un système externe faisant autorité ;
- un processus automatique de réévaluation ;
- le membre lui-même, si la politique l’autorise.

L’acteur ou le workflow doit être explicitement identifiable.

---

## Permission requise

La permission recommandée est :

```text
workspace.members.reactivate
```

À défaut, une permission plus générale peut être utilisée :

```text
workspace.members.manage
```

L’autorisation doit tenir compte de :

```text
Actor
Workspace
TargetMembership
CurrentRole
TargetRole
SuspensionReason
ReactivationSource
```

Le droit de réactiver un membre ne signifie pas nécessairement le droit de :

- réactiver un owner ;
- réactiver un membre suspendu pour raison de sécurité ;
- modifier son rôle ;
- lever une restriction permanente ;
- réactiver son propre `Membership`.

---

## Cas sans permission utilisateur directe

Certains workflows peuvent demander la réactivation sans acteur humain disposant d’une permission ordinaire.

Exemples :

```text
SecurityReviewWorkflow
ComplianceReviewWorkflow
DirectorySynchronization
TemporarySuspensionScheduler
AdministrativeRecoveryWorkflow
```

Leur autorité doit être :

- explicite ;
- limitée au type de suspension concerné ;
- contrôlée à chaque exécution ;
- auditable ;
- incapable de contourner une interdiction permanente.

---

## Préconditions

Avant l’exécution de `ReactivateMembership`, les conditions suivantes doivent être satisfaites :

- le `Membership` existe ;
- son état est `Suspended` ;
- une suspension active existe ;
- le `User` existe ;
- le `User` est actif ;
- le `Workspace` existe ;
- le `Workspace` accepte la réactivation ;
- le motif ayant conduit à la suspension est résolu ou levé ;
- aucune restriction permanente ne bloque la réactivation ;
- le `Role` effectif après réactivation existe ;
- le `Role` est actif ;
- le `Role` appartient au `Workspace` ;
- le `Role` est attribuable ;
- l’acteur ou le workflow est autorisé ;
- la demande respecte les règles de hiérarchie ;
- aucune transition concurrente n’a déjà gagné ;
- les conditions éventuelles de revue sont satisfaites ;
- les limites du `Workspace` permettent la réactivation.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `MembershipId` | `MembershipId` | Oui | Identifie le `Membership` à réactiver. |
| `ReactivatedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’origine de la réactivation. |
| `ReactivatedAt` | Instant | Oui | Date la réactivation métier. |
| `ReactivationSource` | `MembershipReactivationSource` | Oui | Indique le parcours ayant autorisé le retour. |
| `ReactivationRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |

Données conditionnelles ou facultatives :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `RoleId` | `RoleId` | Conditionnel | Nouveau rôle à appliquer lors de la réactivation. |
| `Resolution` | `SuspensionResolution` | Oui | Décrit la résolution de la suspension. |
| `Reason` | `MembershipReactivationReason` | Non | Motif structuré de réactivation. |
| `Comment` | Texte court | Non | Précision interne facultative. |
| `CaseReference` | Identifiant | Conditionnel | Référence le dossier ayant autorisé la réactivation. |
| `CorrelationId` | Identifiant | Non | Relie l’opération au workflow appelant. |
| `RequireNewAuthentication` | Booléen | Non | Demande une nouvelle authentification avant accès. |
| `RevokeExistingSessions` | Booléen | Non | Demande la révocation des sessions existantes. |

---

## MembershipReactivationSource

Valeurs recommandées :

```text
ManualAdministration
SecurityReview
ComplianceReview
ExternalSynchronization
WorkspacePolicy
UserRequest
AutomaticReview
AdministrativeRecovery
```

La source influence :

- l’autorité requise ;
- les justificatifs ;
- les contrôles supplémentaires ;
- le traitement des sessions ;
- les notifications ;
- l’audit ;
- la visibilité du motif.

---

## MembershipReactivationReason

Valeurs possibles :

```text
IssueResolved
ReviewCompleted
TemporaryLeaveEnded
ExternalDirectoryEnabled
RestrictionLifted
AdministrativeCorrection
UserRequestedReturn
PolicyConditionSatisfied
Other
```

Le motif doit exprimer pourquoi l’accès peut redevenir effectif.

---

## SuspensionResolution

La résolution décrit le traitement de la cause initiale.

Valeurs possibles :

```text
ConditionResolved
RiskAccepted
FalsePositive
RestrictionExpired
ManualOverride
ExternalStateRestored
AdministrativeCorrection
NotApplicable
```

Certaines résolutions nécessitent une autorité renforcée.

Exemple :

```text
RiskAccepted
```

peut exiger :

- un rôle de sécurité spécifique ;
- une référence de dossier ;
- une justification ;
- une approbation complémentaire.

---

## Validation des données

### MembershipId

Le `MembershipId` doit identifier un `Membership` existant.

La commande agit sur une appartenance précise.

Elle ne réactive pas globalement tous les `Membership` d’un `User`.

---

### ReactivatedBy

Lorsque `ReactivatedBy` est un `UserId`, l’acteur doit :

- exister ;
- être actif ;
- posséder un `Membership` actif dans le même `Workspace` ;
- posséder la permission requise ;
- être autorisé à réactiver le membre cible ;
- pouvoir attribuer le `Role` demandé, le cas échéant.

Lorsque l’acteur est un `SystemActor`, son identité et son périmètre doivent être explicitement définis.

---

### ReactivatedAt

`ReactivatedAt` représente l’instant métier de retour à l’état `Active`.

Il doit :

- être fourni par une abstraction `Clock` ;
- être postérieur ou égal à `SuspendedAt` ;
- rester stable lors des retries ;
- être cohérent avec les décisions de revue.

Condition recommandée :

```text
ReactivatedAt >= Membership.SuspendedAt
```

---

### ReactivationRequestId

Le `ReactivationRequestId` doit :

- identifier une intention logique unique ;
- rester stable pendant les retries ;
- permettre de restituer le résultat initial ;
- ne contenir aucune donnée sensible ;
- être unique dans le périmètre de déduplication.

---

### RoleId

Deux stratégies sont possibles.

#### Conserver le Role actuel

`RoleId` peut être omis si la politique autorise la conservation du rôle suspendu.

Le système utilise alors :

```text
TargetRoleId = Membership.RoleId
```

#### Attribuer un nouveau Role

`RoleId` doit être fourni lorsque :

- l’ancien rôle n’est plus actif ;
- l’ancien rôle n’est plus attribuable ;
- la politique exige une réduction de privilèges ;
- le workflow décide explicitement d’un nouveau niveau d’accès ;
- une séparation des responsabilités doit être appliquée.

Dans tous les cas, le rôle effectif doit être validé.

---

### Resolution

La résolution doit être compatible avec :

- le motif de suspension ;
- la source de suspension ;
- la source de réactivation ;
- l’autorité de l’acteur ;
- les règles de sécurité.

Exemple :

```text
SuspensionReason = SecurityReview
AND
Resolution = RestrictionExpired
```

peut être invalide si une revue explicite est requise.

---

### CaseReference

Une référence de dossier peut être obligatoire lorsque la suspension provenait de :

```text
SecurityReview
ComplianceReview
PolicyViolation
AccountRisk
```

La réactivation doit alors être reliée au même dossier ou à une décision de clôture reconnue.

---

### Comment

Le commentaire doit :

- respecter une longueur maximale ;
- être traité comme une donnée non fiable ;
- ne contenir aucun secret ;
- être protégé contre les injections ;
- respecter la politique de conservation ;
- rester distinct du motif structuré.

---

## Traitement métier

### 1. Charger le Membership

Le système charge le `Membership` identifié par `MembershipId`.

S’il n’existe pas, la commande échoue.

---

### 2. Vérifier l’état courant

Le `Membership` doit être :

```text
Suspended
```

Cas possibles :

#### Suspended

La réactivation peut continuer.

#### Active

La commande déclenche le comportement idempotent ou retourne `MembershipAlreadyActive`.

#### Removed

La commande échoue.

Le retour d’un membre retiré relève de :

```text
RestoreMembership
```

---

### 3. Vérifier l’idempotence

Le système recherche une réactivation déjà appliquée avec :

```text
MembershipId + ReactivationRequestId
```

Si elle existe, il retourne le résultat initial sans nouvel effet.

---

### 4. Charger la suspension active

Le système charge les informations de la période de suspension :

- `SuspendedAt` ;
- `SuspendedBy` ;
- `SuspensionReason` ;
- `SuspensionSource` ;
- `ExpectedEndAt` ;
- `CaseReference` ;
- restrictions de réactivation ;
- conditions de revue.

Une réactivation ne doit pas ignorer la cause initiale.

---

### 5. Vérifier la résolution de la suspension

Le système détermine si la cause ayant conduit à la suspension est résolue.

Exemples :

#### TemporaryLeave

La date ou la condition de retour est atteinte.

#### ExternalDirectoryDisabled

La source externe indique que le membre est de nouveau actif.

#### SecurityReview

Le dossier de sécurité est clôturé ou une décision explicite autorise le retour.

#### ComplianceReview

Les exigences de conformité sont satisfaites.

#### PaymentRestriction

Le bounded context propriétaire confirme la levée de la restriction.

La commande ne doit pas reconstituer seule les règles appartenant à d’autres bounded contexts.

---

### 6. Vérifier les restrictions permanentes

Le système vérifie qu’aucune restriction permanente n’interdit la réactivation.

Exemples :

```text
PermanentBan
LegalRestriction
FraudConfirmed
AccountCompromised
WorkspaceExclusion
```

La levée d’une telle restriction doit être une décision distincte et explicite.

---

### 7. Charger le User

Le système charge le `User` référencé par le `Membership`.

Le `UserId` ne peut pas être modifié.

---

### 8. Vérifier l’état du User

Le `User` doit être compatible avec une réactivation.

États potentiellement incompatibles :

```text
Disabled
Deleted
Blocked
PendingDeletion
Compromised
```

Un `Membership` ne peut pas redevenir effectif si le `User` reste globalement indisponible.

---

### 9. Vérifier le Workspace

Le système confirme que le `Workspace` :

- existe ;
- est actif ;
- accepte la réactivation ;
- n’a pas atteint une limite bloquante ;
- n’interdit pas le retour du membre ;
- permet le rôle cible ;
- accepte la source de réactivation.

---

### 10. Résoudre le Role effectif

Le système détermine le rôle cible.

```text
TargetRoleId =
provided RoleId
OR
current Membership.RoleId
```

Cette résolution doit être explicite dans le service applicatif.

---

### 11. Vérifier le Role effectif

Le système vérifie que le rôle cible :

- existe ;
- est actif ;
- appartient au même `Workspace` ;
- est attribuable ;
- reste compatible avec le membre ;
- respecte la hiérarchie ;
- respecte les politiques du `Workspace`.

Condition obligatoire :

```text
TargetRole.WorkspaceId = Membership.WorkspaceId
```

---

### 12. Autoriser l’acteur ou le workflow

Le système vérifie :

```text
Actor may reactivate TargetMembership
```

et, lorsque le rôle change :

```text
Actor may assign TargetRole
```

Un acteur autorisé à réactiver un membre avec son rôle existant ne doit pas automatiquement pouvoir lui attribuer un rôle plus privilégié.

---

### 13. Vérifier les limites du Workspace

La réactivation augmente à nouveau le nombre de membres actifs.

Le système peut devoir vérifier :

- la limite de membres ;
- les contraintes de licence ;
- les quotas de rôles ;
- les restrictions de domaine ;
- les règles organisationnelles ;
- les limites liées aux owners.

---

### 14. Vérifier les opérations concurrentes

Le système vérifie qu’aucune autre commande n’a modifié le `Membership`.

Concurrences principales :

- `RemoveMembership` ;
- une autre `ReactivateMembership` ;
- `ChangeMembershipRole` ;
- une nouvelle `SuspendMembership` ;
- une désactivation du `User` ;
- une désactivation du `Role`.

---

### 15. Enregistrer la réactivation

L’agrégat passe de :

```text
Suspended
```

à :

```text
Active
```

Il conserve :

- `MembershipId` ;
- `UserId` ;
- `WorkspaceId` ;
- l’historique de la suspension.

Il enregistre :

- le `RoleId` effectif ;
- `ReactivatedAt` ;
- `ReactivatedBy` ;
- `ReactivationSource` ;
- `ReactivationRequestId` ;
- `Resolution` ;
- `Reason`, le cas échéant ;
- `CaseReference`, le cas échéant.

---

### 16. Clôturer la période de suspension

La période de suspension active est fermée.

Exemple conceptuel :

```text
SuspensionPeriod
├── SuspendedAt
├── SuspendedBy
├── SuspensionReason
├── ExpectedEndAt
├── ReactivatedAt
├── ReactivatedBy
└── Resolution
```

Cette historisation permet de conserver plusieurs périodes de suspension successives.

---

### 17. Réinitialiser le contexte d’autorisation

La réactivation ne doit pas réutiliser aveuglément des autorisations mises en cache avant la suspension.

Le système doit :

- invalider les projections obsolètes ;
- recalculer les permissions effectives ;
- incrémenter une version d’autorisation ;
- réévaluer les sessions ;
- imposer une nouvelle authentification lorsque requis.

---

### 18. Produire l’événement

L’agrégat produit :

```text
MembershipReactivated
```

Les traitements de session, de notification et d’intégration restent séparés.

---

## Résultat attendu

Après une exécution réussie :

- le `Membership` existe toujours ;
- son état est `Active` ;
- son identité est inchangée ;
- son rôle effectif est valide ;
- la suspension précédente reste auditable ;
- la résolution est enregistrée ;
- les permissions peuvent redevenir effectives ;
- le contexte d’autorisation est réévalué ;
- aucun doublon n’est créé.

État conceptuel :

```text
Membership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── RoleId: validated active role
├── Status: Active
├── ReactivatedAt
├── ReactivatedBy
├── ReactivationSource
├── Resolution
└── Suspension history preserved
```

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` existe pour :

```text
UserId + WorkspaceId
```

La réactivation modifie l’entité existante.

---

### `IDN-INV-002`

Le `Membership` doit référencer un `User`, un `Workspace` et un `Role` valides.

---

### `IDN-INV-003`

Un `Membership` actif possède exactement un `Role`.

---

### `IDN-INV-004`

La transition autorisée est :

```text
Suspended -> Active
```

---

### `IDN-INV-005`

Le rôle effectif appartient au même `Workspace`.

```text
Membership.WorkspaceId = Role.WorkspaceId
```

---

### `IDN-INV-006`

La réactivation ne doit pas violer les règles de propriété du `Workspace`.

---

### `IDN-INV-010`

Les permissions redeviennent accessibles par le `Role`.

Elles ne sont jamais copiées directement sur le `Membership`.

---

### `IDN-INV-011`

Une `Session` ne peut utiliser le `Membership` qu’après réévaluation de son état et de son contexte d’autorisation.

---

### `IDN-INV-014`

L’autorisation est évaluée dans le contexte exact du `Workspace`.

---

### `IDN-INV-015`

Le `Workspace` doit autoriser la réactivation.

---

## Transition d’état

Transition autorisée :

```text
Suspended
    ↓
Active
```

Transitions interdites par cette commande :

```text
Removed
    ↓
Active
```

Cette transition relève de :

```text
RestoreMembership
```

```text
Active
    ↓
Active
```

hors reprise idempotente.

---

## Role après réactivation

Deux politiques principales sont possibles.

### Politique A — Conserver le Role

Le `Role` existant est conservé si :

- il existe toujours ;
- il est actif ;
- il reste attribuable ;
- aucune restriction n’impose une modification ;
- l’acteur est autorisé à réactiver ce niveau d’accès.

### Politique B — Fournir explicitement un Role

La commande reçoit un `RoleId`.

Cette politique permet :

- une réduction de privilèges ;
- une adaptation après changement organisationnel ;
- le remplacement d’un rôle désactivé ;
- une décision de sécurité ;
- une réintégration contrôlée.

---

## Politique recommandée

La commande peut accepter un `RoleId` facultatif.

Règle :

```text
RoleId provided
    -> validate and assign TargetRole

RoleId omitted
    -> validate and preserve current Role
```

La réactivation doit échouer si le rôle courant n’est plus valide et qu’aucun nouveau rôle n’est fourni.

---

## Changement de Role inclus dans la réactivation

Lorsque le rôle change pendant la réactivation, le changement fait partie de l’intention de réactivation.

L’événement `MembershipReactivated` peut contenir :

```text
PreviousRoleId
RoleId
```

Il n’est pas nécessaire de produire automatiquement :

```text
MembershipRoleChanged
```

car le membre n’était pas actif avant l’opération.

Cette décision doit rester cohérente avec la politique générale d’événements.

---

## Permissions effectives

Après réactivation, les permissions sont calculées par :

```text
Active Membership
↓
Active Role
↓
Permission
```

Les conditions complètes d’autorisation restent :

```text
Session is valid
AND
User is active
AND
Membership.Status = Active
AND
Role is active
AND
Permission is granted
```

La réactivation ne garantit pas à elle seule qu’une session particulière devient valide.

---

## Événement produit

### MembershipReactivated

La commande produit :

```text
MembershipReactivated
```

L’événement peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `PreviousRoleId`
- `RoleId`
- `ReactivatedAt`
- `ReactivatedBy`
- `ReactivationSource`
- `Resolution`
- `Reason`
- `CaseReference`
- `RequireNewAuthentication`
- `ReactivationRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- la liste complète des permissions ;
- des tokens de session ;
- des secrets ;
- des commentaires sensibles non nécessaires ;
- des détails confidentiels d’investigation.

---

## Événements non produits

La commande ne produit pas :

```text
MembershipCreated
MembershipRestored
MembershipRoleChanged
UserEnabled
SessionCreated
```

Des commandes distinctes peuvent être déclenchées pour :

```text
RevokeSession
RevokeAllUserSessions
RequireReauthentication
```

---

## Erreurs métier

### MembershipNotFound

Le `Membership` n’existe pas.

---

### MembershipAlreadyActive

Le `Membership` est déjà actif.

Cette erreur ne doit pas être retournée pour la reprise idempotente de la même demande.

---

### MembershipRemoved

Le `Membership` a été retiré.

Le workflow doit utiliser :

```text
RestoreMembership
```

---

### MembershipNotSuspended

L’état courant ne permet pas la réactivation.

---

### SuspensionNotResolved

La cause de la suspension n’est pas résolue.

---

### ReactivationForbidden

La politique interdit la réactivation.

---

### PermanentRestriction

Une restriction permanente bloque le retour.

---

### ReviewRequired

Une revue obligatoire n’a pas été effectuée.

---

### ReviewNotApproved

La revue existe, mais n’autorise pas la réactivation.

---

### CaseReferenceRequired

Une référence de dossier obligatoire est absente.

---

### InvalidResolution

La résolution fournie n’est pas compatible avec la suspension.

---

### UserNotFound

Le `User` n’existe pas.

---

### UserUnavailable

Le `User` n’est pas dans un état compatible avec le retour.

---

### WorkspaceNotFound

Le `Workspace` n’existe pas.

---

### WorkspaceUnavailable

Le `Workspace` n’accepte pas la réactivation.

---

### WorkspaceMemberLimitReached

La réactivation dépasserait la capacité autorisée.

---

### RoleNotFound

Le rôle effectif n’existe pas.

---

### RoleDisabled

Le rôle effectif est inactif.

---

### RoleBelongsToAnotherWorkspace

La condition suivante est fausse :

```text
Role.WorkspaceId = Membership.WorkspaceId
```

---

### RoleNotAssignable

Le rôle effectif ne peut pas être attribué.

---

### RoleRequired

Le rôle actuel n’est plus valide et aucun nouveau rôle n’a été fourni.

---

### ActorNotAuthorized

L’acteur ne peut pas réactiver ce membre.

---

### RoleAssignmentNotAuthorized

L’acteur peut réactiver le membre, mais ne peut pas attribuer le rôle demandé.

---

### ReactivationConflict

Une autre transition concurrente a modifié le `Membership`.

---

## Idempotence

`ReactivateMembership` doit être idempotente pour :

```text
MembershipId + ReactivationRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- appliquer une seconde transition ;
- modifier `ReactivatedAt` ;
- changer la résolution ;
- remplacer le rôle ;
- produire un nouvel événement métier ;
- répéter des notifications ;
- répéter des effets sur les sessions.

---

## Reprise après réponse perdue

Cas typique :

```text
ReactivateMembership succeeds

↓

Membership becomes Active

↓

Response is lost

↓

Caller retries
```

La seconde exécution doit retrouver :

- le même `ReactivationRequestId` ;
- le même `MembershipId` ;
- le même `RoleId` ;
- le même `ReactivatedAt` ;
- la même résolution.

Elle retourne le résultat initial.

---

## Réactivation déjà réalisée par une autre demande

Lorsque le `Membership` est déjà `Active` avec un autre `ReactivationRequestId`, aucun nouvel effet ne doit être produit.

Le système peut retourner :

```text
MembershipAlreadyActive
```

Il ne doit pas modifier la trace de la réactivation initiale.

---

## Concurrence

### ReactivateMembership contre RemoveMembership

Une seule transition doit gagner.

Résultats possibles :

```text
Active
```

ou :

```text
Removed
```

Une réactivation ne doit pas ressusciter un membre retiré par une transaction concurrente déjà validée.

---

### ReactivateMembership contre SuspendMembership

Une nouvelle suspension peut intervenir après une réactivation.

Les deux commandes doivent utiliser le versionnement de l’agrégat.

Une commande fondée sur une version obsolète doit échouer.

---

### ReactivateMembership contre ChangeMembershipRole

Si le changement de rôle est inclus dans la réactivation, une commande concurrente doit :

- s’appliquer après la réactivation ;
- ou échouer sur conflit.

La politique doit éviter deux décisions simultanées contradictoires sur le rôle.

---

### ReactivateMembership contre désactivation du User

Si le `User` devient indisponible pendant l’opération, la réactivation ne doit pas produire un accès effectif incohérent.

Selon la frontière transactionnelle :

- la commande échoue avant commit ;
- un événement `UserDisabled` rend immédiatement l’accès inutilisable ;
- le moteur d’autorisation bloque le contexte même si le `Membership` est `Active`.

---

### ReactivateMembership contre désactivation du Role

Si le rôle devient inactif pendant la réactivation, une seule décision cohérente doit être persistée.

Le `Membership` ne doit pas redevenir `Active` avec un rôle inutilisable sans politique explicite.

---

### Deux réactivations concurrentes

Deux commandes avec le même `ReactivationRequestId` sont dédupliquées.

Deux demandes différentes ne doivent produire qu’une seule transition :

```text
Suspended -> Active
```

Si elles demandent des rôles différents, une seule peut réussir.

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Suspended
+
verify suspension resolved
+
verify User
+
verify Workspace
+
verify Role
+
verify authorization
+
record Active
+
close suspension period
+
record domain event
```

Les états suivants sont interdits :

```text
Status = Active
AND
RoleId is invalid
```

```text
Status = Active
AND
suspension remains open
```

```text
MembershipReactivated published
AND
Membership remains Suspended
```

Une outbox transactionnelle peut garantir la publication fiable de l’événement.

---

## Résolution du motif de suspension

La commande doit évaluer la cause initiale, pas uniquement l’état `Suspended`.

Exemple insuffisant :

```text
Membership.Status = Suspended
    -> allow reactivation
```

Exemple correct :

```text
Membership.Status = Suspended
AND
Suspension conditions are resolved
AND
Reactivation authority is valid
    -> allow reactivation
```

---

## Suspension arrivée à échéance

Lorsque `ExpectedEndAt` est atteint, le membre ne doit pas nécessairement être réactivé automatiquement.

Le workflow recommandé est :

```text
ExpectedEndAt reached
↓
Review suspension
↓
Resolve conditions
↓
ReactivateMembership
```

L’échéance déclenche une réévaluation, pas une modification directe de l’agrégat.

---

## Réactivation automatique

Une réactivation automatique peut être autorisée lorsque la politique est suffisamment déterministe.

Exemple :

```text
SuspensionReason = TemporaryLeave
AND
ExpectedEndAt reached
AND
no blocking condition exists
```

Même dans ce cas, le scheduler doit exécuter :

```text
ReactivateMembership
```

avec :

- une source explicite ;
- une résolution ;
- un identifiant idempotent ;
- une nouvelle validation ;
- un audit complet.

---

## Réactivation après suspension de sécurité

Une suspension liée à la sécurité doit imposer des contrôles renforcés.

Exemples :

- vérification de l’identité ;
- réinitialisation des secrets ;
- révocation de toutes les sessions ;
- activation de l’authentification multifacteur ;
- validation par un opérateur ;
- clôture d’un dossier ;
- réduction temporaire du rôle.

La commande peut recevoir :

```text
RequireNewAuthentication = true
```

ou déclencher un workflow dédié.

---

## Sessions existantes

La réactivation ne doit pas réactiver implicitement d’anciennes sessions.

Une session ayant perdu son contexte pendant la suspension peut être :

- définitivement révoquée ;
- toujours techniquement valide, mais sans autorisation ;
- soumise à une nouvelle authentification ;
- liée à une ancienne version d’autorisation.

La politique recommandée est :

```text
do not restore previously revoked sessions
```

Les nouvelles autorisations doivent être obtenues après réévaluation.

---

## Réauthentification

Une nouvelle authentification peut être exigée lorsque :

- la suspension était liée à la sécurité ;
- les credentials ont été compromis ;
- la suspension a duré longtemps ;
- le rôle confère des privilèges élevés ;
- la politique du `Workspace` l’impose.

La réactivation du `Membership` et la création d’une nouvelle `Session` restent deux décisions distinctes.

---

## Version d’autorisation

Le `Membership` peut posséder une version d’autorisation :

```text
AuthorizationVersion
```

Lors de la suspension :

```text
AuthorizationVersion += 1
```

Lors de la réactivation :

```text
AuthorizationVersion += 1
```

Les sessions portant une ancienne version deviennent inutilisables.

Cette stratégie complète la vérification du statut courant.

---

## Relation avec SuspendMembership

Le parcours nominal est :

```text
Active
↓
SuspendMembership
↓
Suspended
↓
ReactivateMembership
↓
Active
```

Chaque période doit conserver :

- sa date de début ;
- son motif ;
- son origine ;
- sa date de fin ;
- sa résolution ;
- les acteurs impliqués.

---

## Relation avec RemoveMembership

Un `Membership` retiré ne peut pas être réactivé.

La distinction est :

```text
Suspended
    -> temporary block
    -> ReactivateMembership

Removed
    -> membership ended
    -> RestoreMembership
```

Une commande ne doit pas accepter les deux états pour simplifier l’implémentation.

---

## Relation avec ChangeMembershipRole

Trois stratégies sont possibles.

### Conserver le rôle puis le changer

```text
ReactivateMembership
↓
ChangeMembershipRole
```

Cette stratégie crée un état intermédiaire avec l’ancien rôle.

### Changer le rôle pendant la réactivation

```text
ReactivateMembership with RoleId
```

Cette stratégie est recommandée lorsque le nouveau rôle fait partie de la décision de retour.

### Changer le rôle avant la réactivation

Cette stratégie n’est pas recommandée si `ChangeMembershipRole` exige un `Membership` actif.

---

## Relation avec User

La réactivation d’un `Membership` concerne un seul `Workspace`.

Elle ne réactive pas globalement le `User`.

Si le `User` est désactivé, une commande distincte est nécessaire :

```text
EnableUser
```

L’ordre conceptuel est alors :

```text
Resolve global User restriction
↓
EnableUser
↓
ReactivateMembership
```

---

## Synchronisation externe

Un système externe peut signaler qu’un membre est de nouveau actif.

La politique doit préciser :

- le système faisant autorité ;
- la référence externe ;
- la priorité entre décisions locales et externes ;
- le traitement d’une suspension locale de sécurité ;
- la correspondance du rôle ;
- la gestion des conflits ;
- l’idempotence.

Une activation externe ne doit pas écraser une suspension locale plus prioritaire.

Exemple :

```text
ExternalDirectoryEnabled
AND
local SecurityReview still open
    -> Reactivation forbidden
```

---

## Notifications

Après `MembershipReactivated`, un handler peut :

- informer le membre ;
- informer les owners ;
- notifier la sécurité ;
- mettre à jour les projections ;
- recalculer les licences ;
- relancer l’onboarding ;
- demander une nouvelle authentification ;
- fermer une tâche de revue.

Les notifications ne font pas partie de la transaction principale.

---

## Visibilité de la résolution

Le produit doit définir quelles informations sont visibles par :

- le membre ;
- les owners ;
- les administrateurs ;
- le support ;
- la sécurité ;
- les systèmes externes.

Exemples :

```text
TemporaryLeaveEnded
```

peut être affiché au membre.

```text
RiskAccepted
```

peut rester réservé aux acteurs de sécurité.

Les commentaires internes ne doivent pas être exposés par défaut.

---

## Intégrations

Les systèmes externes peuvent consommer :

```text
MembershipReactivated
```

Exemples :

- provisioning ;
- annuaire ;
- facturation ;
- licences ;
- sécurité ;
- audit ;
- analytics ;
- notifications.

Les consommateurs doivent être idempotents.

Ils doivent distinguer :

```text
MembershipReactivated
```

de :

```text
MembershipRestored
```

La première reprend une relation suspendue.

La seconde rétablit une relation précédemment terminée.

---

## Sécurité

La commande doit garantir que :

- seuls les acteurs autorisés peuvent réactiver ;
- la cause initiale est résolue ;
- aucune restriction permanente n’est contournée ;
- le `User` est actif ;
- le `Workspace` accepte le retour ;
- le `Role` est valide ;
- le rôle appartient au bon `Workspace` ;
- un rôle privilégié n’est pas attribué sans autorisation ;
- les anciennes sessions ne retrouvent pas automatiquement leur accès ;
- les caches d’autorisation sont invalidés ;
- les motifs sensibles restent confidentiels ;
- les retries ne produisent aucun doublon.

---

## Confidentialité

L’événement et les réponses publiques doivent minimiser les données exposées.

Ils ne doivent pas révéler inutilement :

- le détail d’une investigation ;
- un risque de sécurité ;
- un commentaire administratif ;
- les permissions de l’acteur ;
- les autres appartenances du `User` ;
- les données internes du dossier.

Les consommateurs autorisés peuvent résoudre les informations nécessaires à partir des identifiants.

---

## Audit

Une réactivation réussie doit enregistrer :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- ancien `RoleId`
- rôle effectif
- `ReactivatedAt`
- `ReactivatedBy`
- `ReactivationSource`
- `Resolution`
- `Reason`
- `CaseReference`
- `ReactivationRequestId`
- `CorrelationId`
- politique appliquée aux sessions
- décision de réauthentification
- résultat

L’audit doit permettre de répondre à :

```text
why was the Membership suspended
what resolved the suspension
who authorized the reactivation
which Role became effective
when access became available again
whether new authentication was required
```

---

## Décisions de conception

### La réactivation nécessite une nouvelle décision métier

Une suspension ne disparaît pas automatiquement.

Le retour à `Active` exige une commande, une autorité et une résolution explicites.

---

### La cause initiale doit être réévaluée

Le statut `Suspended` ne suffit pas à déterminer que le retour est permis.

La condition ayant conduit à la suspension doit être levée ou acceptée.

---

### ReactivateMembership est distinct de RestoreMembership

La suspension conserve l’appartenance.

Le retrait y met fin.

Leur retour suit donc des commandes différentes.

---

### Le Role doit être revalidé

Même lorsqu’il est conservé, le rôle doit encore :

- exister ;
- être actif ;
- appartenir au `Workspace` ;
- être attribuable ;
- respecter les politiques actuelles.

---

### Le changement de Role peut faire partie de la réactivation

Lorsqu’un nouveau rôle est une condition du retour, il doit être appliqué atomiquement avec la réactivation.

---

### Les anciennes sessions ne sont pas restaurées

La réactivation du droit métier ne ressuscite pas les sessions révoquées.

Une nouvelle authentification peut être nécessaire.

---

### La période de suspension est conservée

La réactivation ferme la période active sans effacer :

- le motif ;
- l’acteur ;
- la date ;
- les décisions ;
- la résolution.

---

### Les restrictions permanentes priment

Une suspension ne peut être réactivée lorsqu’une interdiction durable reste active.

La levée de cette restriction relève d’une décision distincte.

---

### La date de fin attendue déclenche une revue

`ExpectedEndAt` ne constitue pas par elle-même une autorisation suffisante.

Elle indique le moment où la situation doit être réévaluée.

---

### MembershipReactivated est un événement métier distinct

Il permet aux consommateurs de comprendre qu’un accès temporairement bloqué redevient effectif.

Il ne doit pas être remplacé par un événement générique de mise à jour.

---

## Synthèse

`ReactivateMembership` rétablit l’accès d’un membre temporairement suspendu.

Elle garantit que :

- le `Membership` existe et est `Suspended` ;
- la cause de la suspension est résolue ;
- aucune restriction permanente ne bloque le retour ;
- le `User` est actif ;
- le `Workspace` accepte la réactivation ;
- le rôle effectif est valide et appartient au bon `Workspace` ;
- l’acteur ou le workflow est autorisé ;
- l’état devient `Active` ;
- la période de suspension est clôturée et conservée ;
- les permissions sont recalculées ;
- les anciennes sessions ne retrouvent pas automatiquement leur accès ;
- les retries et opérations concurrentes ne produisent aucun doublon.

Le résultat final est :

```text
Suspended Membership
    ↓
Active Membership
```

avec conservation de l’identité :

```text
same MembershipId
same UserId
same WorkspaceId
resolved suspension
validated Role
new authorization context
```