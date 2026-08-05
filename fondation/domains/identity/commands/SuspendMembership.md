---
id: IDN-CMD-SUSPEND-MEMBERSHIP
title: SuspendMembership
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
  - CreateMembership.md
  - RestoreMembership.md
  - ReactivateMembership.md
  - RemoveMembership.md
  - ChangeMembershipRole.md
---

# SuspendMembership

## Objectif

La commande `SuspendMembership` bloque temporairement l’accès d’un membre à un `Workspace` tout en conservant son appartenance.

Elle fait passer le `Membership` de :

```text
Active
```

à :

```text
Suspended
```

La suspension conserve :

- le même `MembershipId` ;
- le même `UserId` ;
- le même `WorkspaceId` ;
- le `Role` actuellement attribué ;
- l’historique du membre ;
- la possibilité d’une réactivation ultérieure.

Elle ne supprime pas le `Membership`.

---

## Signification métier

Un `Membership` suspendu représente une relation toujours existante, mais temporairement inutilisable.

```text
Membership still exists
AND
access is temporarily blocked
```

La suspension peut être utilisée notamment pour :

- une mesure administrative temporaire ;
- une investigation de sécurité ;
- une absence prolongée ;
- un défaut de conformité ;
- une restriction temporaire ;
- une synchronisation externe ;
- un blocage automatique lié à une politique.

---

## Différence avec RemoveMembership

`SuspendMembership` et `RemoveMembership` expriment deux intentions différentes.

### SuspendMembership

```text
Active
    ↓
Suspended
```

La relation entre le `User` et le `Workspace` est conservée.

Le retour s’effectue avec :

```text
ReactivateMembership
```

### RemoveMembership

```text
Active
    ↓
Removed
```

L’appartenance effective prend fin.

Le retour nécessite :

```text
RestoreMembership
```

La distinction doit rester explicite dans :

- le modèle ;
- les commandes ;
- les événements ;
- les permissions ;
- l’audit ;
- les intégrations.

---

## Agrégat concerné

`Membership`

Le `Membership` constitue la racine de l’agrégat modifié.

La commande peut consulter :

- le `User` ;
- le `Workspace` ;
- le `Role` de l’acteur ;
- les autres owners actifs du `Workspace` ;
- les restrictions de sécurité applicables.

Ces éléments ne sont pas directement modifiés par cette commande.

---

## Acteur

La commande peut être demandée par :

- un `User` autorisé à gérer les membres ;
- un owner du `Workspace` ;
- un administrateur ;
- un workflow de sécurité ;
- un processus de conformité ;
- un système externe faisant autorité ;
- un processus système explicitement habilité.

L’acteur ne peut pas être ambigu.

Une origine générique telle que :

```text
System
```

doit être remplacée par une identité plus précise, par exemple :

```text
SecurityPolicyEngine
DirectorySynchronization
ComplianceWorkflow
AdministrativeOperator
```

---

## Permission requise

La permission canonique est :

```text
workspace.members.suspend
```

L’autorisation doit tenir compte de :

```text
Actor
Workspace
TargetMembership
TargetRole
SuspensionReason
SuspensionSource
```

Le droit de suspendre certains membres ne signifie pas nécessairement le droit de suspendre :

- un owner ;
- un membre de même niveau ;
- le dernier owner actif ;
- son propre `Membership` ;
- un membre protégé par une politique.

---

## Cas sans permission utilisateur directe

Certains workflows peuvent suspendre un membre sans acteur humain disposant d’une permission ordinaire.

Exemples :

```text
SecurityPolicyEngine
ExternalDirectorySynchronization
ComplianceWorkflow
AutomatedRiskControl
```

Leur autorité doit être :

- explicitement configurée ;
- limitée à un périmètre précis ;
- réévaluée à chaque opération ;
- auditée ;
- indépendante d’un simple détail technique.

---

## Préconditions

Avant l’exécution de `SuspendMembership`, les conditions suivantes doivent être satisfaites :

- le `Membership` existe ;
- son état est `Active` ;
- le `User` existe ;
- le `Workspace` existe ;
- l’acteur ou le workflow est autorisé ;
- la suspension est permise pour ce membre ;
- le membre n’est pas protégé contre cette suspension ;
- le `Workspace` conservera au moins un owner actif ;
- la demande ne contourne pas une procédure spécifique ;
- le motif est valide ;
- l’éventuelle durée est cohérente ;
- aucune transition concurrente n’a déjà gagné ;
- l’opération respecte les règles de self-management ;
- les contraintes de sécurité sont satisfaites.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `MembershipId` | `MembershipId` | Oui | Identifie le membre à suspendre. |
| `SuspendedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’origine de la suspension. |
| `SuspendedAt` | Instant | Oui | Date la suspension métier. |
| `Reason` | `MembershipSuspensionReason` | Oui | Motif structuré de la suspension. |
| `SuspensionSource` | `MembershipSuspensionSource` | Oui | Indique le parcours ayant déclenché l’opération. |
| `SuspensionRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `ExpectedEndAt` | Instant | Non | Date indicative ou normative de fin de suspension. |
| `Comment` | Texte court | Non | Précision interne facultative. |
| `CaseReference` | Identifiant | Non | Référence un dossier de sécurité ou de conformité. |
| `CorrelationId` | Identifiant | Non | Relie l’opération au workflow appelant. |
| `InvalidateSessions` | Booléen | Non | Exprime une politique explicite d’invalidation des sessions. |

---

## MembershipSuspensionReason

Valeurs recommandées :

```text
AdministrativeDecision
SecurityReview
PolicyViolation
ComplianceReview
TemporaryLeave
ExternalDirectoryDisabled
PaymentRestriction
AccountRisk
UserRequestedPause
Other
```

Le motif doit exprimer le sens métier de la suspension.

Il ne doit pas être utilisé uniquement comme détail technique.

---

## MembershipSuspensionSource

Valeurs recommandées :

```text
ManualAdministration
SecurityWorkflow
ComplianceWorkflow
ExternalSynchronization
WorkspacePolicy
UserRequest
SystemProvisioning
```

La source influence :

- l’autorité requise ;
- la visibilité du motif ;
- la durée ;
- les conditions de réactivation ;
- les notifications ;
- l’audit ;
- les intégrations.

---

## Validation des données

### MembershipId

Le `MembershipId` doit identifier un `Membership` existant.

La commande agit sur une appartenance précise.

Elle ne doit pas cibler indistinctement toutes les appartenances d’un `User`.

---

### SuspendedBy

Lorsque `SuspendedBy` est un `UserId`, l’acteur doit :

- exister ;
- être actif ;
- posséder un `Membership` actif dans le même `Workspace` ;
- posséder la permission requise ;
- être autorisé à suspendre le `Role` cible ;
- respecter les restrictions de hiérarchie.

Lorsque l’acteur est un `SystemActor`, son périmètre doit être explicitement défini.

---

### SuspendedAt

`SuspendedAt` représente l’instant métier d’effet de la suspension.

Il doit :

- être fourni par une abstraction `Clock` ;
- rester inchangé lors des retries ;
- être cohérent avec l’état courant ;
- servir de référence à l’audit et aux projections.

---

### Reason

Le motif doit appartenir au catalogue prévu.

Certaines valeurs peuvent exiger :

- un commentaire ;
- un dossier associé ;
- un niveau d’autorisation supérieur ;
- une durée maximale ;
- une revue obligatoire avant réactivation.

---

### SuspensionSource

La source doit être reconnue et autorisée.

Exemple :

```text
SuspensionSource = ExternalSynchronization
```

peut exiger une référence externe stable.

---

### SuspensionRequestId

Le `SuspensionRequestId` doit :

- identifier une intention logique unique ;
- rester stable pendant les retries ;
- permettre de restituer le résultat initial ;
- ne contenir aucune donnée sensible ;
- être unique dans le périmètre de déduplication.

---

### ExpectedEndAt

Lorsque renseigné :

```text
ExpectedEndAt > SuspendedAt
```

La date peut être :

- indicative ;
- obligatoire ;
- utilisée par un workflow automatique.

La politique doit préciser si l’échéance déclenche automatiquement une réactivation.

La recommandation est de ne pas réactiver implicitement un membre sans nouvelle validation métier.

---

### Comment

Le commentaire doit :

- respecter une longueur maximale ;
- être traité comme une donnée non fiable ;
- être protégé contre les injections ;
- ne contenir aucun secret ;
- respecter les règles de visibilité et de conservation.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Charger le Membership

Le système charge le `Membership` identifié par `MembershipId`.

S’il n’existe pas, la commande échoue.

---

### 2. Vérifier l’état courant

Le `Membership` doit être :

```text
Active
```

Cas possibles :

#### Active

La suspension peut continuer.

#### Suspended

La commande déclenche le comportement idempotent ou retourne `MembershipAlreadySuspended`.

#### Removed

La suspension n’a plus de sens.

Le membre n’appartient plus effectivement au `Workspace`.

---

### 3. Vérifier l’idempotence

Le système recherche une suspension déjà appliquée avec :

```text
MembershipId + SuspensionRequestId
```

Si elle existe, le résultat initial est retourné sans nouvel effet.

---

### 4. Charger le contexte d’autorisation

Le système récupère les informations nécessaires pour évaluer :

- l’acteur ;
- le `Workspace` ;
- le `Role` de l’acteur ;
- le `Role` du membre cible ;
- les restrictions spéciales ;
- les owners actifs restants.

---

### 5. Autoriser l’acteur ou le workflow

Pour un acteur humain, le système vérifie notamment :

```text
Actor has workspace.members.suspend
```

et :

```text
Actor may suspend TargetRole
```

Pour un workflow système, il vérifie :

- son identité ;
- sa source ;
- son périmètre ;
- la nature du motif ;
- les limitations configurées.

---

### 6. Vérifier les règles de hiérarchie

Le domaine peut appliquer des règles telles que :

```text
Actor.RoleLevel > Target.RoleLevel
```

ou une politique plus explicite fondée sur les permissions.

La hiérarchie ne doit pas être supposée si le modèle de rôles ne la définit pas.

La suspension d'un owner utilise toujours `workspace.members.suspend`, complété
par la politique d'ownership et la protection du dernier owner actif.

---

### 7. Vérifier le self-suspension

La politique doit préciser si un membre peut suspendre son propre `Membership`.

Cas possibles :

- interdit ;
- autorisé comme pause volontaire ;
- autorisé uniquement pour certains rôles ;
- autorisé via une commande distincte.

Recommandation :

Une pause volontaire importante mérite une commande explicite telle que :

```text
PauseOwnMembership
```

afin de ne pas confondre une mesure administrative avec une décision personnelle.

---

### 8. Protéger le dernier owner actif

La suspension ne doit jamais laisser le `Workspace` sans owner actif.

Condition obligatoire :

```text
ActiveOwnerCountAfterSuspension >= 1
```

Lorsque le membre cible est owner, le système doit vérifier le nombre d’autres owners actifs.

Les états suivants ne doivent pas être comptés comme owners actifs :

```text
Suspended
Removed
Disabled User
```

---

### 9. Vérifier les protections spécifiques

Certains `Membership` peuvent être temporairement ou durablement protégés.

Exemples :

- owner fondateur ;
- compte de service critique ;
- membre requis par un contrat ;
- représentant légal ;
- membre protégé par une procédure de sécurité.

La protection doit être explicite et auditable.

---

### 10. Vérifier les opérations concurrentes

Le système vérifie qu’aucune autre transition n’a déjà modifié le `Membership`.

Concurrences principales :

- `RemoveMembership` ;
- `ChangeMembershipRole` ;
- une autre `SuspendMembership` ;
- une désactivation du `User` ;
- un changement du dernier owner.

---

### 11. Enregistrer la suspension

L’agrégat passe de :

```text
Active
```

à :

```text
Suspended
```

Il conserve :

- `MembershipId` ;
- `UserId` ;
- `WorkspaceId` ;
- `RoleId`.

Il enregistre notamment :

- `SuspendedAt` ;
- `SuspendedBy` ;
- `Reason` ;
- `SuspensionSource` ;
- `ExpectedEndAt`, le cas échéant ;
- `SuspensionRequestId` ;
- `CaseReference`, le cas échéant.

---

### 12. Invalider les autorisations effectives

Dès la suspension, le `Membership` ne doit plus permettre l’accès au `Workspace`.

La chaîne suivante devient non valide pour ce contexte :

```text
Session
↓
User
↓
Suspended Membership
↓
Role
↓
Permission
```

Le contrôle d’autorisation doit exiger :

```text
Membership.Status = Active
```

---

### 13. Invalider ou réévaluer les sessions

Selon la politique de sécurité, le système doit :

- invalider toutes les sessions liées au `Workspace` ;
- forcer leur réévaluation ;
- incrémenter une version d’autorisation ;
- supprimer les caches d’autorisation ;
- fermer les connexions actives sensibles.

La suspension du `Membership` ne doit pas laisser une session utiliser des permissions mises en cache.

---

### 14. Produire l’événement

L’agrégat produit :

```text
MembershipSuspended
```

Les actions sur les sessions, notifications et intégrations peuvent être traitées par des handlers fiables.

---

## Résultat attendu

Après une exécution réussie :

- le `Membership` existe toujours ;
- son état est `Suspended` ;
- son `MembershipId` est inchangé ;
- son `UserId` est inchangé ;
- son `WorkspaceId` est inchangé ;
- son `RoleId` est conservé ;
- il ne confère plus aucune permission effective ;
- la suspension est datée et justifiée ;
- les sessions ne peuvent plus utiliser cet accès ;
- une réactivation explicite reste possible.

État conceptuel :

```text
Membership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── RoleId: unchanged
├── Status: Suspended
├── SuspendedAt
├── SuspendedBy
├── SuspensionReason
├── SuspensionSource
├── ExpectedEndAt
└── SuspensionRequestId
```

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` existe pour :

```text
UserId + WorkspaceId
```

La suspension ne crée aucune nouvelle appartenance.

---

### `IDN-INV-002`

Le `Membership` conserve ses références valides.

---

### `IDN-INV-003`

Le `Membership` suspendu conserve exactement un `Role`.

---

### `IDN-INV-004`

La transition autorisée est :

```text
Active -> Suspended
```

---

### `IDN-INV-005`

Le `Role` reste attaché au même `Workspace`.

---

### `IDN-INV-006`

Le `Workspace` conserve au moins un owner actif.

---

### `IDN-INV-010`

Aucune permission n’est supprimée du `Role`.

L’ineffectivité des permissions résulte du statut `Suspended`.

---

### `IDN-INV-011`

Une `Session` ne doit pas autoriser un contexte dont le `Membership` n’est pas actif.

---

### `IDN-INV-014`

L’autorisation est évaluée dans le contexte exact du `Workspace`.

---

## Transition d’état

Transition autorisée :

```text
Active
    ↓
Suspended
```

Transitions interdites par cette commande :

```text
Removed
    ↓
Suspended
```

```text
Suspended
    ↓
Suspended
```

hors reprise idempotente.

```text
Suspended
    ↓
Active
```

Cette transition relève de :

```text
ReactivateMembership
```

---

## Role pendant la suspension

Le `Role` est conservé.

La suspension ne doit pas :

- supprimer le rôle ;
- attribuer un rôle temporaire ;
- remplacer le rôle par défaut ;
- modifier directement les permissions ;
- dégrader silencieusement l’accès.

Le rôle redevient potentiellement effectif lors de la réactivation, sous réserve d’une nouvelle validation.

---

## Pourquoi conserver le Role

La conservation permet :

- de préserver l’historique ;
- de comprendre le niveau d’accès suspendu ;
- de préparer la réactivation ;
- d’éviter une transition secondaire inutile ;
- de distinguer suspension et changement de rôle.

Cependant, `ReactivateMembership` doit vérifier que le rôle est toujours :

- actif ;
- attribuable ;
- compatible avec le `Workspace`.

---

## Permission effective

Un `Membership` suspendu ne confère aucune `Permission`.

La règle d’autorisation doit être :

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

La simple présence d’un `RoleId` ne suffit pas.

---

## Événement produit

### MembershipSuspended

La commande produit :

```text
MembershipSuspended
```

L’événement peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `RoleId`
- `SuspendedAt`
- `SuspendedBy`
- `Reason`
- `SuspensionSource`
- `ExpectedEndAt`
- `CaseReference`
- `SuspensionRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- la liste complète des permissions ;
- des secrets ;
- des tokens de session ;
- des commentaires sensibles non nécessaires ;
- des données personnelles excessives.

---

## Événements non produits

La commande ne produit pas :

```text
MembershipRemoved
MembershipRoleChanged
MembershipReactivated
UserDisabled
SessionRevoked
```

Un handler peut déclencher des commandes de révocation de sessions, mais celles-ci restent des opérations distinctes.

---

## Erreurs métier

### MembershipNotFound

Le `Membership` n’existe pas.

---

### MembershipAlreadySuspended

Le `Membership` est déjà suspendu.

Cette erreur ne doit pas être retournée pour la reprise idempotente de la même demande.

---

### MembershipRemoved

Le `Membership` a déjà été retiré.

---

### MembershipNotActive

L’état courant ne permet pas la suspension.

---

### ActorNotAuthorized

L’acteur ne dispose pas de l’autorité requise.

---

### TargetRoleProtected

Le rôle du membre cible ne peut pas être suspendu par cet acteur.

---

### CannotSuspendLastOwner

La suspension laisserait le `Workspace` sans owner actif.

---

### CannotSuspendSelf

La politique interdit à l’acteur de suspendre son propre `Membership`.

---

### MembershipProtected

Une protection métier ou réglementaire interdit la suspension ordinaire.

---

### SuspensionReasonInvalid

Le motif n’est pas reconnu.

---

### SuspensionCaseRequired

Le motif exige une référence de dossier absente.

---

### InvalidExpectedEndAt

La date de fin attendue n’est pas postérieure à la suspension.

---

### WorkspaceNotFound

Le `Workspace` n’existe pas ou ne peut pas être résolu.

---

### WorkspaceUnavailable

Le contexte du `Workspace` empêche l’évaluation ou l’opération.

---

### SuspensionConflict

Une autre transition concurrente a modifié le `Membership`.

---

## Idempotence

`SuspendMembership` doit être idempotente pour :

```text
MembershipId + SuspensionRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- modifier `SuspendedAt` ;
- remplacer le motif ;
- changer la source ;
- produire un nouvel événement métier ;
- répéter des notifications ;
- répéter des invalidations non idempotentes.

---

## Reprise après réponse perdue

Cas typique :

```text
SuspendMembership succeeds

↓

Membership becomes Suspended

↓

Response is lost

↓

Caller retries
```

La seconde exécution doit retrouver :

- le même `SuspensionRequestId` ;
- le même `SuspendedAt` ;
- le même motif ;
- le même état.

Elle retourne le résultat initial.

---

## Nouvelle suspension sur un Membership déjà suspendu

Une demande avec un nouvel identifiant ne doit pas remplacer silencieusement la suspension existante.

Le système doit utiliser une commande distincte pour modifier ses conditions, par exemple :

```text
UpdateMembershipSuspension
```

ou exiger :

```text
ReactivateMembership
↓
SuspendMembership
```

pour créer une nouvelle période de suspension.

La modification silencieuse du motif ou de la date est interdite.

---

## Concurrence

### SuspendMembership contre RemoveMembership

Une seule transition doit gagner.

Résultats possibles :

```text
Suspended
```

ou :

```text
Removed
```

La suppression ne doit pas être appliquée à partir d’une version obsolète sans revalidation.

---

### SuspendMembership contre ChangeMembershipRole

Les commandes concurrentes sont sérialisées par la version du `Membership`.

#### Suspension gagne en premier

Le changement de rôle doit échouer si la politique interdit de modifier un membre suspendu.

#### Changement de rôle gagne en premier

La suspension s’applique ensuite au nouveau rôle, après revalidation des règles de protection.

La politique recommandée est de refuser `ChangeMembershipRole` sur un membre suspendu sauf besoin explicite.

---

### SuspendMembership contre ReactivateMembership

Une réactivation ne peut normalement cibler qu’un état `Suspended`.

Si les deux commandes sont concurrentes, une seule version doit être validée.

L’état final doit être cohérent et auditable.

---

### SuspendMembership contre désignation d’un autre owner

La vérification du dernier owner doit être transactionnellement fiable.

Deux suspensions concurrentes de deux owners ne doivent pas toutes deux conclure qu’un autre owner restera actif.

Le système doit utiliser :

- un verrou adapté ;
- une sérialisation ;
- une contrainte métier coordonnée ;
- ou une transaction au niveau du `Workspace`.

---

### Deux suspensions concurrentes

Deux commandes avec le même `SuspensionRequestId` doivent être dédupliquées.

Deux demandes différentes ne doivent produire qu’une seule transition :

```text
Active -> Suspended
```

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Active
+
verify authorization
+
protect last owner
+
record Suspended
+
record suspension metadata
+
record domain event
```

Les états suivants sont interdits :

```text
Status = Suspended
AND
SuspendedAt is missing
```

```text
Status = Suspended
AND
Reason is missing
```

```text
MembershipSuspended published
AND
Membership remains Active
```

Une outbox transactionnelle peut garantir la publication fiable de l’événement.

---

## Problème de cohérence avec le dernier owner

La règle du dernier owner implique potentiellement plusieurs agrégats `Membership`.

La vérification naïve suivante est insuffisante :

```text
count active owners
then suspend
```

Deux transactions pourraient lire le même nombre avant de suspendre simultanément.

Stratégies possibles :

- verrouiller une ressource de coordination du `Workspace` ;
- maintenir un compteur fortement cohérent ;
- utiliser une transaction sérialisable ;
- confier l’opération à un agrégat ou workflow propriétaire de la règle ;
- appliquer une contrainte persistante adaptée.

La stratégie choisie doit garantir :

```text
Workspace always has at least one active owner
```

---

## Sessions

La suspension doit avoir un effet immédiat sur les autorisations.

Stratégies possibles :

### Révocation de toutes les sessions du User

Simple, mais affecte potentiellement d’autres `Workspace`.

### Révocation des sessions contextualisées

Appropriée si les sessions sont liées à un `Workspace`.

### Version d’autorisation

Chaque session porte une version comparée à celle du `Membership` ou du `User`.

### Réévaluation à chaque requête

Le moteur d’autorisation vérifie l’état courant du `Membership`.

---

## Politique recommandée pour les sessions

Combiner :

```text
Membership.Status checked during authorization
```

avec :

```text
authorization cache invalidation
```

et, pour les cas sensibles :

```text
session revocation
```

La suspension ne doit pas dépendre uniquement de l’expiration naturelle d’un token.

---

## Suspension temporaire

Une suspension peut posséder une date indicative :

```text
ExpectedEndAt
```

Cette date ne signifie pas nécessairement :

```text
automatic reactivation
```

Une réactivation automatique présente des risques lorsque :

- le motif est de sécurité ;
- la situation n’a pas été revue ;
- le rôle a changé ;
- le `User` a été désactivé ;
- le `Workspace` a évolué.

La recommandation est :

```text
ExpectedEndAt reached
↓
raise review
↓
ReactivateMembership explicitly
```

---

## Réactivation automatique

Si le produit exige une réactivation automatique, elle doit passer par :

```text
ReactivateMembership
```

avec :

- une source explicite ;
- une nouvelle validation ;
- un identifiant idempotent ;
- une vérification du `User` ;
- une vérification du `Workspace` ;
- une vérification du `Role` ;
- une vérification des restrictions.

Le scheduler ne doit pas modifier directement le statut.

---

## Suspension liée à User

La suspension d’un `Membership` concerne un seul `Workspace`.

Elle ne doit pas désactiver globalement le `User`.

```text
SuspendMembership
    -> one Workspace context

DisableUser
    -> all Identity contexts
```

Lorsque le risque concerne le compte entier, une commande sur `User` doit être utilisée.

---

## Suspension liée à une synchronisation externe

Un système externe peut indiquer qu’un membre n’est temporairement plus autorisé.

La politique doit définir :

- le système faisant autorité ;
- la clé externe ;
- la durée ;
- la réactivation ;
- le traitement d’une suppression externe ;
- la résolution des conflits avec les décisions locales ;
- la priorité entre sources.

Une synchronisation ne doit pas écraser silencieusement une suspension locale de sécurité.

---

## Relation avec ReactivateMembership

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

`ReactivateMembership` doit vérifier que :

- le motif de suspension est résolu ;
- le `User` est actif ;
- le `Workspace` est disponible ;
- le `Role` est toujours valide ;
- l’acteur est autorisé ;
- aucune restriction ne demeure.

---

## Relation avec RemoveMembership

Un `Membership` suspendu peut-il être retiré ?

Deux politiques sont possibles.

### Politique A — Retrait autorisé depuis Suspended

```text
Suspended
    ↓
Removed
```

Cette politique permet de clôturer définitivement une relation suspendue.

### Politique B — Réactivation préalable obligatoire

```text
Suspended
↓
Active
↓
Removed
```

Cette politique ajoute une transition artificielle.

Recommandation :

Autoriser `RemoveMembership` depuis `Suspended`, sous réserve d’une règle explicite dans cette commande.

---

## Relation avec ChangeMembershipRole

La politique recommandée est :

```text
ChangeMembershipRole requires Active Membership
```

Modifier le rôle d’un membre suspendu peut masquer la décision de réactivation future.

Si un nouveau rôle est nécessaire, il peut être fourni lors de :

```text
ReactivateMembership
```

à condition que le modèle de cette commande le permette.

---

## Notifications

Après `MembershipSuspended`, un handler peut :

- informer le membre ;
- informer les owners ;
- notifier la sécurité ;
- arrêter certains workflows ;
- mettre à jour les projections ;
- invalider les caches ;
- révoquer les sessions ;
- ouvrir une tâche de revue.

La visibilité du motif doit dépendre de sa sensibilité.

---

## Visibilité du motif

Le produit doit définir si le motif est visible par :

- le membre suspendu ;
- les owners ;
- les administrateurs ;
- le support ;
- la sécurité ;
- les systèmes externes.

Exemples :

```text
TemporaryLeave
```

peut être affiché au membre.

```text
SecurityReview
```

peut nécessiter un message plus générique.

Le commentaire interne ne doit pas être exposé par défaut.

---

## Intégrations

Les systèmes externes peuvent consommer :

```text
MembershipSuspended
```

Exemples :

- provisioning ;
- annuaire ;
- facturation ;
- sécurité ;
- audit ;
- analytics ;
- contrôle de licence ;
- notifications.

Les consommateurs doivent être idempotents.

Ils ne doivent pas traduire automatiquement toute suspension en suppression définitive.

---

## Sécurité

La commande doit garantir que :

- seuls les acteurs autorisés peuvent suspendre ;
- le périmètre du `Workspace` est respecté ;
- un owner protégé ne peut pas être suspendu illégitimement ;
- le dernier owner actif est conservé ;
- les sessions ne continuent pas à utiliser l’accès ;
- les caches d’autorisation sont invalidés ;
- les motifs sensibles restent confidentiels ;
- la suspension ne modifie pas directement les permissions du rôle ;
- les retries ne produisent aucun doublon.

---

## Confidentialité

L’événement et les réponses publiques doivent minimiser les données exposées.

Ils ne doivent pas révéler inutilement :

- le détail d’une investigation ;
- un signal de fraude ;
- un commentaire interne ;
- les autres membres du `Workspace` ;
- la hiérarchie complète des rôles ;
- les permissions de l’acteur.

Un message générique peut être présenté au membre lorsque le motif est sensible.

---

## Audit

Une suspension réussie doit enregistrer :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `RoleId`
- `SuspendedAt`
- `SuspendedBy`
- `Reason`
- `SuspensionSource`
- `ExpectedEndAt`
- `CaseReference`
- `SuspensionRequestId`
- `CorrelationId`
- décision d’invalidation des sessions
- résultat

L’audit doit permettre de répondre à :

```text
who suspended the Membership
why it was suspended
which authority allowed it
when access stopped
whether sessions were invalidated
what is required before reactivation
```

---

## Décisions de conception

### La suspension conserve l’appartenance

Le membre reste associé au `Workspace`.

Seule l’effectivité de son accès est interrompue.

---

### Le Role est conservé

La suspension n’est pas un changement de rôle.

Elle bloque temporairement l’utilisation des permissions du rôle existant.

---

### Suspended ne confère aucune Permission

Le moteur d’autorisation doit exiger un `Membership` actif.

La présence d’un rôle ne suffit pas.

---

### La suspension est explicite et auditable

Elle doit toujours posséder :

- un acteur ;
- un instant ;
- un motif ;
- une source ;
- un identifiant idempotent.

---

### La réactivation nécessite une nouvelle commande

La suspension ne disparaît pas silencieusement.

Le retour à `Active` doit être exprimé par :

```text
ReactivateMembership
```

---

### La date de fin attendue ne réactive pas automatiquement

Elle peut déclencher une revue, mais pas restaurer l’accès sans validation.

---

### Le dernier owner est protégé

Une suspension ne peut jamais laisser le `Workspace` sans owner actif.

Cette règle nécessite une stratégie de concurrence explicite.

---

### Les sessions sont traitées comme une responsabilité distincte

La suspension modifie le droit métier.

Les commandes ou handlers de session rendent ce changement effectif dans les accès déjà ouverts.

---

### Suspension et désactivation du User restent distinctes

La suspension est locale à un `Workspace`.

La désactivation d’un `User` agit globalement.

---

## Synthèse

`SuspendMembership` bloque temporairement l’accès d’un membre tout en conservant son appartenance et son historique.

Elle garantit que :

- le `Membership` existe et est `Active` ;
- l’acteur ou le workflow est autorisé ;
- le membre cible peut être suspendu ;
- le dernier owner actif est protégé ;
- l’état devient `Suspended` ;
- le `Role` est conservé ;
- aucune permission n’est effective pendant la suspension ;
- les sessions et caches ne conservent pas l’ancien accès ;
- la suspension est datée, motivée et traçable ;
- une réactivation explicite reste possible ;
- les retries et opérations concurrentes ne créent aucun effet incohérent.

Le résultat final est :

```text
Active Membership
    ↓
Suspended Membership
```

avec conservation de la relation :

```text
same MembershipId
same UserId
same WorkspaceId
same RoleId
temporary access block
```
