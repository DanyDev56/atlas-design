---
id: IDN-CMD-TRANSFER-MEMBERSHIP-ROLE
title: TransferMembershipRole
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-31

aggregate:
  - Membership

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../value-objects.md
  - ../invariants.md
  - ../workflows.md
  - ../permissions.md
  - ../events.md
  - ChangeMembershipRole.md
  - RemoveMembership.md
  - LeaveWorkspace.md
---

# TransferMembershipRole

## Objectif

La commande `TransferMembershipRole` transfère atomiquement un rôle détenu par un `Membership` source vers un `Membership` cible appartenant au même `Workspace`.

Dans le modèle actuel, chaque `Membership` actif possède exactement un `Role`.

Le transfert ne peut donc pas produire :

```text
Source Membership
    -> no Role
```

Il doit produire simultanément :

```text
Source Membership
    Current transferred Role
        ↓
    Source replacement Role
```

et :

```text
Target Membership
    Current Role
        ↓
    Transferred Role
```

Exemple principal :

```text
Before
------

Alice: Owner
Bob:   Member
```

```text
TransferMembershipRole
SourceMembershipId = Alice
TargetMembershipId = Bob
TransferredRoleId = Owner
SourceReplacementRoleId = Member
```

```text
After
-----

Alice: Member
Bob:   Owner
```

L’ensemble de la transition est atomique.

Aucun état intermédiaire ne doit être observable.

---

## Signification métier

`TransferMembershipRole` exprime la décision suivante :

```text
A responsibility represented by a Role
is transferred from one Membership
to another Membership
```

La commande ne représente pas seulement deux modifications techniques de `RoleId`.

Elle exprime une seule décision métier portant sur :

- un membre source ;
- un membre cible ;
- une responsabilité transférée ;
- le nouveau rôle de la source ;
- l’autorité permettant le transfert ;
- la continuité de gouvernance ;
- la cohérence des autorisations.

---

## Cas d’usage principal

Le cas principal est le transfert d’ownership :

```text
Owner A
Member B
    ↓
TransferMembershipRole
    ↓
Member A
Owner B
```

Cette commande évite d’orchestrer séparément :

```text
ChangeMembershipRole(B, Owner)
↓
ChangeMembershipRole(A, Member)
```

Même si cette séquence peut préserver l’invariant lorsqu’elle est exécutée dans cet ordre, elle représente deux décisions et peut échouer entre les deux étapes.

`TransferMembershipRole` garantit une seule décision atomique.

---

## Autres cas d’usage

La commande peut également transférer des rôles représentant une responsabilité exclusive ou structurante :

```text
BillingAdministrator
TechnicalAdministrator
SecurityAdministrator
ComplianceManager
PrimaryContact
WorkspaceAdministrator
CustomExclusiveRole
```

Exemples :

```text
Alice: BillingAdministrator
Bob:   Member
    ↓
Alice: Member
Bob:   BillingAdministrator
```

```text
Alice: SecurityAdministrator
Bob:   TechnicalAdministrator
    ↓
Alice: TechnicalAdministrator
Bob:   SecurityAdministrator
```

Le second exemple constitue un échange de rôles.

---

## Limite conceptuelle

Tous les rôles ne représentent pas nécessairement une responsabilité transférable.

Un rôle ordinaire tel que :

```text
Member
```

peut être attribué simultanément à de nombreux membres.

Il n’est donc pas toujours pertinent de parler de transfert.

La commande doit être réservée aux cas où le domaine reconnaît une intention réelle de transfert, notamment lorsque le rôle est :

- exclusif ;
- limité en nombre ;
- structurel ;
- privilégié ;
- associé à une responsabilité nominative ;
- soumis à une continuité obligatoire ;
- explicitement marqué comme transférable.

Pour une simple modification indépendante de deux membres, utiliser :

```text
ChangeMembershipRole
```

---

## Différence avec ChangeMembershipRole

### ChangeMembershipRole

Modifie un seul `Membership`.

```text
Membership A
Role X
    ↓
Role Y
```

### TransferMembershipRole

Modifie deux `Membership` dans une même décision atomique.

```text
Source Membership
Transferred Role
    ↓
Replacement Role
```

```text
Target Membership
Current Role
    ↓
Transferred Role
```

La commande de transfert est pertinente lorsque les deux changements sont indissociables.

---

## Ownership comme cas d'usage

Une commande spécialisée d'ownership a été rejetée au profit de ce contrat
générique.

`TransferMembershipRole` généralise la décision :

```text
Ownership transfer
    = transfer of an Owner Role
```

Le cas owner reste soumis à des politiques renforcées, mais ne nécessite pas une
commande de domaine structurellement différente. L'interface peut présenter un
parcours dédié sans créer un second contrat métier.

---

## Agrégats concernés

La commande modifie deux agrégats `Membership` :

- le `SourceMembership` ;
- le `TargetMembership`.

Elle consulte également :

- le `TransferredRole` ;
- le `SourceReplacementRole` ;
- le rôle actuel du membre cible ;
- le `Workspace` ;
- l’acteur ;
- les politiques d’attribution ;
- les règles de transfert ;
- les restrictions de sécurité ;
- les responsabilités associées.

---

## Conséquence sur la frontière d’agrégat

La commande modifie deux instances d’un même type d’agrégat.

Elle nécessite donc une coordination transactionnelle explicite.

Deux options principales sont possibles :

### Transaction multi-agrégats locale

Appropriée lorsque les deux `Membership` sont stockés dans la même base transactionnelle.

```text
load SourceMembership
+
load TargetMembership
+
validate
+
modify both
+
commit once
```

### Agrégat ou service de gouvernance

Une ressource de coordination au niveau du `Workspace` protège les opérations sensibles.

Exemple :

```text
WorkspaceMembershipGovernance
```

Cette coordination est particulièrement utile pour les transferts d’ownership.

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur autorisé ;
- le membre source ;
- un workflow de gouvernance ;
- un workflow de succession ;
- un processus de sécurité ;
- un processus de conformité ;
- un système externe faisant autorité ;
- un processus d’administration exceptionnelle.

L’autorité dépend du rôle transféré.

---

## Consentement de la source

Le transfert peut être :

### Volontaire

Le membre source demande ou approuve le transfert.

Exemple :

```text
Owner A transfers ownership to B
```

### Administratif

Un acteur autorisé transfère la responsabilité sans demande directe de la source.

Exemple :

```text
Security administrator transfers a compromised privileged Role
```

### Automatisé

Une politique ou une source externe impose le transfert.

Exemple :

```text
ExternalDirectorySynchronization
```

La commande doit enregistrer la nature du consentement.

---

## Consentement de la cible

Certains rôles peuvent être attribués sans consentement explicite.

D’autres peuvent exiger une acceptation.

Exemples de rôles pouvant exiger une acceptation :

```text
Owner
LegalRepresentative
BillingResponsible
ComplianceManager
SecurityAdministrator
```

La politique peut imposer :

```text
TargetAcceptanceRequired = true
```

Dans ce cas, le transfert immédiat ne doit pas être exécuté sans une acceptation valide.

Un workflow en deux temps peut être préférable :

```text
ProposeMembershipRoleTransfer
↓
AcceptMembershipRoleTransfer
↓
CompleteMembershipRoleTransfer
```

`TransferMembershipRole` représente ici l’application effective d’un transfert déjà approuvé.

---

## Permission requise

Permission canonique :

```text
workspace.members.transfer-role
```

La possession de cette permission est nécessaire mais non suffisante.

L’autorisation doit vérifier :

```text
Actor may act on SourceMembership
AND
Actor may act on TargetMembership
AND
Actor may remove TransferredRole from SourceMembership
AND
Actor may assign TransferredRole to TargetMembership
AND
Actor may assign SourceReplacementRole to SourceMembership
```

Le transfert d'ownership, d'un rôle privilégié ou exclusif, ou du propre rôle de
l'acteur est gouverné par les politiques contextuelles et les approbations ; ces
cas ne créent pas de permissions supplémentaires en 1.0.

---

## Autorisation du membre source

Lorsque le membre source initie lui-même le transfert :

```text
Actor.UserId = SourceMembership.UserId
```

la politique doit déterminer s’il peut :

- transférer son rôle ;
- choisir le membre cible ;
- choisir son rôle de remplacement ;
- transférer un rôle privilégié ;
- finaliser sans approbation supplémentaire.

Pour l’ownership, une authentification récente est recommandée.

---

## Self-promotion de la cible

La cible ne doit pas pouvoir contourner les règles de self-promotion.

Si :

```text
Actor.UserId = TargetMembership.UserId
```

et que le rôle transféré augmente ses privilèges, le transfert doit être refusé sauf workflow explicitement approuvé.

Erreur possible :

```text
CannotTransferPrivilegedRoleToSelf
```

---

## Préconditions

Avant l’exécution, les conditions suivantes doivent être satisfaites :

- le `SourceMembership` existe ;
- le `TargetMembership` existe ;
- les deux memberships sont distincts ;
- les deux memberships appartiennent au même `Workspace` ;
- les deux memberships sont `Active` ;
- les deux `User` sont actifs ;
- le rôle actuel de la source est le rôle transféré ;
- le rôle transféré existe ;
- le rôle transféré est actif ;
- le rôle transféré est transférable ;
- le rôle transféré appartient au même `Workspace` ;
- le rôle de remplacement de la source existe ;
- le rôle de remplacement est actif ;
- il est attribuable à la source ;
- il appartient au même `Workspace` ;
- il est différent du rôle transféré, sauf échange particulier explicitement modélisé ;
- le rôle actuel de la cible existe ;
- l’acteur est autorisé ;
- la source peut perdre le rôle transféré ;
- la cible peut recevoir le rôle transféré ;
- la source peut recevoir le rôle de remplacement ;
- les règles de hiérarchie sont respectées ;
- les règles de séparation des responsabilités sont respectées ;
- les limites du `Workspace` sont respectées ;
- les consentements requis sont présents ;
- les authentifications renforcées sont valides ;
- aucune restriction de sécurité ne bloque l’opération ;
- les responsabilités dépendantes sont transférables ;
- l’invariant du dernier owner reste satisfait ;
- la demande est idempotente ;
- les versions concurrentes sont valides.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `SourceMembershipId` | `MembershipId` | Oui | Identifie le membre qui perd le rôle transféré. |
| `TargetMembershipId` | `MembershipId` | Oui | Identifie le membre qui reçoit le rôle transféré. |
| `TransferredRoleId` | `RoleId` | Oui | Identifie le rôle transféré. |
| `SourceReplacementRoleId` | `RoleId` | Oui | Identifie le rôle attribué à la source après le transfert. |
| `TransferredBy` | `UserId` ou `SystemActor` | Oui | Identifie l’acteur ou le workflow. |
| `TransferredAt` | Instant | Oui | Date l’application effective du transfert. |
| `TransferReason` | `MembershipRoleTransferReason` | Oui | Motif structuré du transfert. |
| `TransferSource` | `MembershipRoleTransferSource` | Oui | Origine du transfert. |
| `TransferRequestId` | Identifiant | Oui | Identifie la demande de façon idempotente. |

Données facultatives ou conditionnelles :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `ExpectedSourceVersion` | Version | Recommandé | Version attendue de la source. |
| `ExpectedTargetVersion` | Version | Recommandé | Version attendue de la cible. |
| `SourceConsentId` | Identifiant | Conditionnel | Référence le consentement de la source. |
| `TargetAcceptanceId` | Identifiant | Conditionnel | Référence l’acceptation de la cible. |
| `ApprovalId` | Identifiant | Conditionnel | Référence une approbation. |
| `CaseReference` | Identifiant | Conditionnel | Référence un dossier métier. |
| `ExternalReference` | Identifiant | Conditionnel | Référence une opération externe. |
| `TransferPlanId` | Identifiant | Conditionnel | Référence un plan de transfert de responsabilités. |
| `CorrelationId` | Identifiant | Non | Relie l’opération à un workflow. |
| `RequireSourceReauthentication` | Booléen | Non | Exige une nouvelle authentification de la source. |
| `RequireTargetReauthentication` | Booléen | Non | Exige une nouvelle authentification de la cible. |
| `RevokeSourceSessions` | Booléen | Non | Révoque les sessions contextualisées de la source. |
| `RevokeTargetSessions` | Booléen | Non | Révoque les sessions contextualisées de la cible. |

---

## MembershipRoleTransferSource

Valeurs recommandées :

```text
VoluntaryTransfer
WorkspaceGovernance
ManualAdministration
SecurityWorkflow
ComplianceWorkflow
ExternalSynchronization
SuccessionWorkflow
AdministrativeRecovery
SystemProvisioning
```

La source influence :

- les permissions requises ;
- les consentements ;
- les approbations ;
- les références obligatoires ;
- les notifications ;
- la réauthentification ;
- l’audit ;
- les possibilités de restauration.

---

## MembershipRoleTransferReason

Valeurs recommandées :

```text
OwnershipTransfer
ResponsibilityTransfer
OrganizationalChange
RoleHandover
EmploymentChange
ContractChange
SecurityDecision
ComplianceDecision
TemporaryReplacement
PermanentReplacement
AdministrativeCorrection
ExternalDirectoryUpdate
Succession
Other
```

Le motif doit exprimer la décision métier.

---

## Rôle transférable

Le rôle expose une `RoleTransferPolicy` dont la propriété `Transferability` peut
notamment valoir :

```text
NotTransferable
Transferable
TransferableWithAcceptance
TransferableWithApproval
TransferableOnlyBySystem
TransferableOnlyByOwner
```

La politique et la `RoleAssignmentPolicy` peuvent également imposer :

```text
MaximumActiveAssignments
RequiresHumanAssignee
RequiredAuthenticationLevel
RequiredAcceptance
```

L'exclusivité est dérivée de `MaximumActiveAssignments = 1` et n'est jamais une
seconde source de vérité.

---

## Rôle exclusif

Un rôle exclusif ne peut être détenu que par un seul membre actif dans le `Workspace`.

Exemple :

```text
MaximumActiveAssignments = 1
```

Le transfert atomique est particulièrement adapté à ce cas.

Sans atomicité, une séquence pourrait produire temporairement :

```text
0 holder
```

ou :

```text
2 holders
```

selon l’ordre des opérations.

---

## Ownership

Le rôle owner n’est pas nécessairement exclusif.

Un `Workspace` peut avoir plusieurs owners.

Le transfert d’ownership signifie ici :

```text
Target becomes Owner
AND
Source ceases to be Owner
```

Ce n’est pas une obligation structurelle du rôle owner.

Un owner peut aussi promouvoir un autre owner sans perdre son propre rôle en utilisant :

```text
ChangeMembershipRole
```

`TransferMembershipRole` est utilisé lorsque la perte du rôle par la source fait partie de la même décision.

---

## Traitement métier

### 1. Charger le SourceMembership

Le système charge le `Membership` source.

S’il n’existe pas, la commande échoue.

---

### 2. Charger le TargetMembership

Le système charge le `Membership` cible.

S’il n’existe pas, la commande échoue.

---

### 3. Vérifier que la source et la cible sont distinctes

Condition obligatoire :

```text
SourceMembershipId != TargetMembershipId
```

Un transfert vers le même membre n’exprime aucune transition.

---

### 4. Vérifier le même Workspace

Condition obligatoire :

```text
SourceMembership.WorkspaceId
=
TargetMembership.WorkspaceId
```

Un rôle contextualisé ne peut pas être transféré entre deux `Workspace`.

---

### 5. Vérifier les états des Membership

Les deux memberships doivent être :

```text
Active
```

Un transfert ne peut pas cibler ou utiliser directement un membre :

```text
Suspended
Removed
```

Pour une source suspendue, une décision administrative peut utiliser une autre orchestration.

Pour une cible retirée, utiliser d’abord :

```text
RestoreMembership
```

---

### 6. Vérifier les User

Les deux `User` doivent être actifs et disponibles.

Un `User` désactivé ne peut pas recevoir un rôle actif.

---

### 7. Vérifier l’idempotence

Le système recherche une opération déjà exécutée avec :

```text
WorkspaceId + TransferRequestId
```

ou, plus précisément :

```text
SourceMembershipId
+
TargetMembershipId
+
TransferRequestId
```

Si elle existe, le résultat initial est retourné sans nouvel effet.

---

### 8. Charger le rôle actuel de la source

Le rôle actuel doit correspondre à :

```text
TransferredRoleId
```

Condition :

```text
SourceMembership.RoleId = TransferredRoleId
```

Sinon, la commande échoue avec :

```text
SourceDoesNotHoldTransferredRole
```

Cette vérification empêche l’utilisation d’une décision obsolète.

---

### 9. Charger le rôle actuel de la cible

Le rôle actuel de la cible doit être résolu afin de :

- calculer les permissions perdues ;
- vérifier la hiérarchie ;
- vérifier les incompatibilités ;
- conserver l’historique ;
- déterminer la classification du transfert.

---

### 10. Charger le TransferredRole

Le rôle transféré doit :

- exister ;
- être actif ;
- appartenir au `Workspace` ;
- être transférable ;
- permettre la source du transfert ;
- être assignable à la cible.

---

### 11. Charger le SourceReplacementRole

Le rôle de remplacement doit :

- exister ;
- être actif ;
- appartenir au même `Workspace` ;
- être assignable à la source ;
- être compatible avec la source ;
- respecter les restrictions du transfert.

---

### 12. Vérifier la transition de la source

La transition suivante doit être autorisée :

```text
TransferredRole
↓
SourceReplacementRole
```

Le système vérifie :

- la perte de privilèges ;
- la hiérarchie ;
- la self-demotion ;
- les responsabilités restantes ;
- les restrictions de rôle protégé ;
- les quotas ;
- la séparation des responsabilités.

---

### 13. Vérifier la transition de la cible

La transition suivante doit être autorisée :

```text
TargetCurrentRole
↓
TransferredRole
```

Le système vérifie :

- l’éligibilité ;
- la promotion ;
- les privilèges acquis ;
- les approbations ;
- la MFA ;
- l’authentification récente ;
- la séparation des responsabilités ;
- les limites du `Workspace`.

---

### 14. Charger le contexte de l’acteur

Le système charge :

- l’identité de l’acteur ;
- son `Membership`, le cas échéant ;
- son rôle ;
- ses permissions ;
- son niveau d’autorité ;
- ses capacités de délégation ;
- ses restrictions ;
- son contexte d’authentification.

---

### 15. Autoriser le transfert global

Le système vérifie que l’acteur peut initier un transfert du rôle concerné.

Exemple :

```text
Actor has workspace.members.transfer-role
```

---

### 16. Autoriser la perte du rôle par la source

Le système vérifie que l’acteur peut retirer le rôle transféré à la source.

Cette règle est distincte de l’autorité permettant de l’attribuer à la cible.

---

### 17. Autoriser l’attribution à la cible

Le système vérifie que l’acteur peut attribuer le rôle transféré au membre cible.

Exemple pour owner :

```text
Actor has workspace.members.transfer-role
AND ownership policy allows the transfer
```

---

### 18. Autoriser le rôle de remplacement

Le système vérifie que l’acteur peut attribuer le `SourceReplacementRole` au membre source.

Il serait incorrect de valider le rôle reçu par la cible mais pas celui reçu par la source.

---

### 19. Vérifier les règles de self-management

Cas possibles :

#### Source initiatrice

```text
Actor = SourceMembership.User
```

Le transfert peut être autorisé si la source peut céder son rôle.

#### Cible initiatrice

```text
Actor = TargetMembership.User
```

Une augmentation de privilèges doit être refusée sans approbation externe.

#### Acteur tiers

L’acteur doit posséder l’autorité sur les deux membres et les deux transitions.

---

### 20. Vérifier le consentement de la source

Lorsque requis, `SourceConsentId` doit être :

- valide ;
- non expiré ;
- lié à la source ;
- lié au rôle transféré ;
- lié à la cible ;
- lié au rôle de remplacement ;
- lié à la version courante ;
- non réutilisé.

---

### 21. Vérifier l’acceptation de la cible

Lorsque le rôle l’exige, `TargetAcceptanceId` doit être :

- valide ;
- lié à la cible ;
- lié au rôle transféré ;
- lié au `Workspace` ;
- non expiré ;
- accordé sur la version attendue ;
- non consommé.

---

### 22. Vérifier les approbations

Certains transferts peuvent exiger :

- l’approbation d’un autre owner ;
- l’approbation de sécurité ;
- une validation juridique ;
- une approbation du support ;
- une approbation du fournisseur d’identité.

L’approbation doit porter sur les paramètres exacts du transfert.

---

### 23. Vérifier la readiness

Un transfert de rôle peut nécessiter le transfert de responsabilités externes.

Le workflow peut obtenir :

```text
RoleTransferReadiness
```

Valeurs possibles :

```text
Ready
ReadyWithWarnings
Blocked
RequiresTransferPlan
RequiresAcceptance
RequiresApproval
```

---

### 24. Vérifier le TransferPlan

Lorsque `TransferPlanId` est requis, le plan doit confirmer que :

- les responsabilités ont été identifiées ;
- la cible peut les recevoir ;
- les dépendances critiques sont prêtes ;
- les secrets peuvent être rotés ;
- les intégrations peuvent être mises à jour ;
- les approbations en cours seront réassignées ;
- les ressources exclusives conserveront un responsable.

---

### 25. Vérifier l’invariant du dernier Owner

Dans un transfert d’ownership :

```text
Source: Owner -> non-Owner
Target: non-Owner -> Owner
```

Le nombre final d’owners actifs est généralement inchangé.

```text
ActiveOwnerCountAfterTransfer
=
ActiveOwnerCountBeforeTransfer
```

Cependant, l’invariant doit tout de même être évalué sur l’état final.

Condition :

```text
ActiveOwnerCountAfterTransfer >= 1
```

La commande ne doit jamais exposer un état intermédiaire dans lequel la source a perdu le rôle avant que la cible ne l’ait reçu.

---

### 26. Vérifier les limites du rôle

Le transfert doit respecter :

```text
MinimumActiveAssignments
MaximumActiveAssignments
Exclusivity
HumanAssigneeRequirement
RoleAssignmentPolicy
```

L’état final, et non un état intermédiaire, est la référence.

---

### 27. Vérifier la séparation des responsabilités

Les rôles après transfert peuvent créer des incompatibilités.

La source doit être vérifiée avec son rôle de remplacement.

La cible doit être vérifiée avec le rôle transféré.

---

### 28. Vérifier les versions concurrentes

Les versions suivantes doivent correspondre :

```text
SourceMembership.Version = ExpectedSourceVersion
```

```text
TargetMembership.Version = ExpectedTargetVersion
```

La coordination doit également protéger les règles de gouvernance du `Workspace`.

---

### 29. Acquérir les verrous dans un ordre stable

Pour éviter les deadlocks, les agrégats doivent être verrouillés dans un ordre déterministe.

Exemple :

```text
min(SourceMembershipId, TargetMembershipId)
↓
max(SourceMembershipId, TargetMembershipId)
↓
Workspace governance lock
```

Ou :

```text
Workspace governance lock
↓
Membership locks ordered by MembershipId
```

Une seule convention doit être appliquée partout.

---

### 30. Fermer les périodes de rôle actuelles

Le système clôture :

```text
Source current Role period
```

et :

```text
Target current Role period
```

à :

```text
TransferredAt
```

---

### 31. Ouvrir les nouvelles périodes de rôle

Le système ouvre :

```text
SourceReplacementRole period
```

et :

```text
TransferredRole period for Target
```

au même instant métier.

---

### 32. Modifier les deux Membership

Transition atomique :

```text
SourceMembership.RoleId
=
SourceReplacementRoleId
```

```text
TargetMembership.RoleId
=
TransferredRoleId
```

Les deux `Membership` restent :

```text
Active
```

---

### 33. Incrémenter les versions d’autorisation

Le système incrémente :

```text
SourceMembership.AuthorizationVersion
```

et :

```text
TargetMembership.AuthorizationVersion
```

Les anciens contextes d’autorisation deviennent obsolètes.

---

### 34. Enregistrer l’événement

Le système enregistre :

```text
MembershipRoleTransferCompleted
```

dans la même transaction que les deux modifications.

---

### 35. Commit atomique

Le commit doit rendre visibles simultanément :

```text
Source has replacement Role
AND
Target has transferred Role
```

Aucun consommateur ne doit observer une moitié du transfert.

---

## Résultat attendu

Après succès :

```text
SourceMembership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── Status: Active
├── PreviousRoleId: TransferredRoleId
├── RoleId: SourceReplacementRoleId
└── AuthorizationVersion: incremented
```

```text
TargetMembership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── Status: Active
├── PreviousRoleId: TargetPreviousRoleId
├── RoleId: TransferredRoleId
└── AuthorizationVersion: incremented
```

---

## État final conceptuel

```text
Before
------

Source: TransferredRole
Target: TargetPreviousRole
```

```text
After
-----

Source: SourceReplacementRole
Target: TransferredRole
```

avec :

```text
same Workspace
both Memberships Active
no intermediate state
```

---

## Échange de rôles

Le transfert peut produire un échange lorsque :

```text
SourceReplacementRoleId
=
TargetPreviousRoleId
```

Exemple :

```text
Before
------

Alice: Owner
Bob:   Administrator
```

```text
After
-----

Alice: Administrator
Bob:   Owner
```

Ce cas est particulièrement naturel.

---

## Remplacement différent du rôle de la cible

Le rôle de remplacement de la source peut être différent du rôle précédent de la cible.

Exemple :

```text
Before
------

Alice: Owner
Bob:   Viewer
```

```text
After
-----

Alice: Member
Bob:   Owner
```

Le rôle `Viewer` n’est alors attribué à personne par cette commande.

Il ne s’agit pas d’un échange, mais bien d’un transfert avec réaffectation de la source.

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` existe par :

```text
UserId + WorkspaceId
```

La commande ne crée aucun `Membership`.

---

### `IDN-INV-003`

Chaque `Membership` actif possède exactement un `Role`.

Cet invariant explique pourquoi `SourceReplacementRoleId` est obligatoire.

---

### `IDN-INV-005`

Tous les rôles attribués appartiennent au même `Workspace`.

```text
SourceMembership.WorkspaceId
=
TargetMembership.WorkspaceId
=
TransferredRole.WorkspaceId
=
SourceReplacementRole.WorkspaceId
```

---

### `IDN-INV-006`

Le `Workspace` conserve au moins un owner actif.

```text
ActiveOwnerCountAfterTransfer >= 1
```

---

### `IDN-INV-010`

Les permissions sont toujours obtenues uniquement par le rôle courant.

---

### `IDN-INV-011`

Les sessions doivent réévaluer leur contexte après le transfert.

---

### `IDN-INV-014`

Toutes les autorisations sont évaluées dans le même `Workspace`.

---

## Événement produit

### MembershipRoleTransferCompleted

La commande produit un événement représentant la décision atomique complète :

```text
MembershipRoleTransferCompleted
```

L’événement peut contenir :

- `WorkspaceId`
- `SourceMembershipId`
- `SourceUserId`
- `SourcePreviousRoleId`
- `SourceRoleId`
- `TargetMembershipId`
- `TargetUserId`
- `TargetPreviousRoleId`
- `TargetRoleId`
- `TransferredRoleId`
- `TransferredAt`
- `TransferredBy`
- `TransferReason`
- `TransferSource`
- `SourceConsentId`
- `TargetAcceptanceId`
- `ApprovalId`
- `CaseReference`
- `ExternalReference`
- `TransferPlanId`
- `TransferRequestId`
- `CorrelationId`

---

## Pourquoi un événement unique

Le transfert représente une seule décision métier.

Produire uniquement :

```text
MembershipRoleChanged for Source
MembershipRoleChanged for Target
```

peut masquer la relation entre les deux modifications.

L’événement unique permet aux consommateurs de comprendre que :

```text
these two Role changes are one atomic transfer
```

---

## Événements dérivés

### Stratégie canonique — événement de transfert uniquement

Les projections interprètent directement :

```text
MembershipRoleTransferCompleted
```

Les projections internes peuvent dériver deux changements unitaires, sans
publier de `MembershipRoleChanged` supplémentaire. Le fait public conserve ainsi
l'atomicité sémantique du transfert.

---

## Recommandation événementielle

Produire l'événement métier unique :

```text
MembershipRoleTransferCompleted
```

Puis permettre aux projections internes de dériver les changements unitaires lorsque nécessaire.

Éviter de traiter les événements unitaires comme deux décisions indépendantes.

---

## Événements non produits

La commande ne produit pas :

```text
MembershipCreated
MembershipRemoved
MembershipLeft
MembershipSuspended
MembershipRestored
RoleCreated
PermissionGranted
WorkspaceOwnershipTransferred
```

Un événement métier spécialisé supplémentaire ne doit être ajouté que si un consommateur possède réellement ce concept.

---

## Erreurs métier

### SourceMembershipNotFound

Le membre source n’existe pas.

---

### TargetMembershipNotFound

Le membre cible n’existe pas.

---

### SourceAndTargetMustDiffer

La source et la cible sont identiques.

---

### MembershipsBelongToDifferentWorkspaces

Les deux memberships n’appartiennent pas au même `Workspace`.

---

### SourceMembershipNotActive

La source n’est pas active.

---

### TargetMembershipNotActive

La cible n’est pas active.

---

### SourceUserUnavailable

Le `User` source n’est pas actif ou disponible.

---

### TargetUserUnavailable

Le `User` cible n’est pas actif ou disponible.

---

### TransferredRoleNotFound

Le rôle transféré n’existe pas.

---

### SourceReplacementRoleNotFound

Le rôle de remplacement n’existe pas.

---

### SourceDoesNotHoldTransferredRole

Le rôle actuel de la source ne correspond pas au rôle annoncé.

---

### TransferredRoleDisabled

Le rôle transféré est inactif.

---

### SourceReplacementRoleDisabled

Le rôle de remplacement est inactif.

---

### RoleBelongsToAnotherWorkspace

L’un des rôles appartient à un autre `Workspace`.

---

### RoleNotTransferable

Le rôle ne peut pas être transféré.

---

### TransferModeMismatch

La source de l’opération n’est pas compatible avec le mode de transfert du rôle.

---

### TargetNotEligibleForTransferredRole

La cible ne remplit pas les conditions du rôle transféré.

---

### SourceNotEligibleForReplacementRole

La source ne remplit pas les conditions du rôle de remplacement.

---

### ActorNotAuthorized

L’acteur ne peut pas exécuter ce transfert.

---

### CannotRemoveRoleFromSource

L’acteur ne peut pas retirer le rôle à la source.

---

### CannotAssignRoleToTarget

L’acteur ne peut pas attribuer le rôle à la cible.

---

### CannotAssignReplacementRoleToSource

L’acteur ne peut pas attribuer le rôle de remplacement à la source.

---

### CannotTransferPrivilegedRoleToSelf

La cible tente de se promouvoir elle-même par le transfert.

---

### SourceConsentRequired

Le consentement de la source est obligatoire.

---

### SourceConsentInvalid

Le consentement fourni est invalide ou expiré.

---

### TargetAcceptanceRequired

L’acceptation de la cible est obligatoire.

---

### TargetAcceptanceInvalid

L’acceptation fournie est invalide ou expirée.

---

### ApprovalRequired

Une approbation est nécessaire.

---

### ApprovalInvalid

L’approbation ne couvre pas exactement le transfert demandé.

---

### RecentAuthenticationRequired

Une authentification récente est nécessaire.

---

### MfaRequired

Une authentification multifacteur est exigée.

---

### RoleTransferBlocked

Une responsabilité ou une politique bloque le transfert.

---

### TransferPlanRequired

Un plan de transfert est obligatoire.

---

### TransferPlanInvalid

Le plan est invalide, expiré ou incomplet.

---

### SeparationOfDutiesViolation

L’état final crée une incompatibilité de responsabilités.

---

### WorkspaceMustHaveActiveOwner

L’état final laisserait le `Workspace` sans owner actif.

---

### RoleAssignmentLimitExceeded

Le nombre maximal d’attributions du rôle serait dépassé.

---

### RoleExclusivityViolation

L’état final violerait l’exclusivité du rôle.

---

### HumanAssigneeRequired

Le rôle exige une identité humaine éligible.

---

### SourceVersionConflict

La source a changé depuis la décision initiale.

---

### TargetVersionConflict

La cible a changé depuis la décision initiale.

---

### WorkspaceGovernanceConflict

Une opération concurrente a modifié les conditions de gouvernance.

---

### IdempotencyConflict

Le même `TransferRequestId` a été réutilisé avec des données différentes.

---

## Idempotence

La commande doit être idempotente pour :

```text
WorkspaceId + TransferRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- réappliquer les rôles ;
- recréer les périodes ;
- modifier `TransferredAt` ;
- produire un nouvel événement ;
- répéter les notifications ;
- répéter les révocations ;
- réécrire les consentements ;
- modifier les références d’audit.

---

## Empreinte idempotente

L’empreinte de la demande doit inclure au minimum :

```text
SourceMembershipId
TargetMembershipId
TransferredRoleId
SourceReplacementRoleId
TransferReason
TransferSource
```

Une modification de l’un de ces éléments avec le même `TransferRequestId` produit :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
Transfer succeeds
↓
both Memberships are updated
↓
response is lost
↓
caller retries
```

Le retry retourne exactement :

- les mêmes rôles précédents ;
- les mêmes rôles finaux ;
- le même `TransferredAt` ;
- les mêmes versions finales ;
- le même événement logique.

---

## Concurrence

### Deux transferts depuis la même source

Exemple :

```text
Alice Owner -> Bob
```

et :

```text
Alice Owner -> Charlie
```

Une seule commande peut réussir.

La seconde doit échouer avec :

```text
SourceVersionConflict
```

ou :

```text
SourceDoesNotHoldTransferredRole
```

---

### Deux transferts vers la même cible

Deux rôles peuvent être transférés simultanément vers la même cible.

Comme le modèle autorise un seul rôle par `Membership`, une seule modification peut gagner.

La seconde doit être réévaluée.

---

### Transfert contre ChangeMembershipRole sur la source

Si le changement de rôle de la source gagne en premier, le transfert échoue.

Il ne doit pas retirer un rôle que la source ne possède plus.

---

### Transfert contre ChangeMembershipRole sur la cible

Si le rôle cible change avant le commit, le transfert doit échouer ou être entièrement réévalué.

Le rôle précédent de la cible fait partie de la décision et de l’audit.

---

### Transfert contre SuspendMembership

Si la source ou la cible est suspendue avant le commit, le transfert échoue.

Si le transfert gagne, la suspension doit être réévaluée sur le nouveau rôle.

---

### Transfert contre RemoveMembership

Si l’un des membres est retiré avant le commit, le transfert échoue.

Si le transfert gagne, le retrait doit réévaluer :

- le rôle final ;
- la protection de l’owner ;
- les responsabilités.

---

### Transfert contre LeaveWorkspace

Si la source ou la cible quitte avant le commit, le transfert échoue.

Une confirmation de départ antérieure devient obsolète après le transfert.

---

### Deux transferts d’ownership concurrents

État initial :

```text
Alice: Owner
Bob:   Member
Carol: Member
```

Deux demandes :

```text
Alice -> Bob
```

```text
Alice -> Carol
```

Une seule peut réussir.

---

### Transferts croisés

Commandes concurrentes :

```text
Alice role X -> Bob
```

```text
Bob role Y -> Alice
```

Les verrous doivent être pris dans un ordre stable afin d’éviter un deadlock.

---

## Atomicité

L’opération suivante doit être atomique :

```text
verify SourceMembership
+
verify TargetMembership
+
verify TransferredRole
+
verify SourceReplacementRole
+
verify actor
+
verify consent
+
verify acceptance
+
verify approval
+
verify readiness
+
verify final invariants
+
close both current Role periods
+
assign SourceReplacementRole to Source
+
assign TransferredRole to Target
+
increment both AuthorizationVersions
+
record MembershipRoleTransferCompleted
```

---

## États intermédiaires interdits

```text
Source has lost TransferredRole
AND
Target has not received it
```

```text
Target has received exclusive Role
AND
Source still holds it
```

```text
Source Membership has no Role
```

```text
Workspace has no active Owner
```

```text
one Membership updated
AND
the other remains unchanged
```

```text
event published
AND
only one aggregate committed
```

---

## Outbox transactionnelle

Une outbox transactionnelle est recommandée.

Le même commit doit inclure :

```text
SourceMembership update
TargetMembership update
Role period updates
Idempotency record
Domain event record
```

La publication vers les consommateurs intervient après le commit.

---

## Rollback

Si une validation ou une écriture échoue, aucune partie du transfert ne doit être persistée.

```text
all changes committed
OR
no change committed
```

Aucune compensation métier ne doit être nécessaire pour restaurer un transfert partiellement appliqué dans la même base transactionnelle.

---

## Systèmes distribués

Si les deux memberships ne peuvent pas être modifiés dans une même transaction locale, le modèle devient beaucoup plus complexe.

Une saga pourrait produire temporairement des incohérences.

Pour les rôles critiques et l’ownership, la recommandation est de conserver les données nécessaires dans une frontière transactionnelle commune.

Une saga ne doit être utilisée que si le domaine accepte explicitement un état intermédiaire.

---

## AuthorizationVersion

Les deux memberships doivent recevoir une nouvelle version d’autorisation.

Exemple :

```text
Source.AuthorizationVersion: 7 -> 8
Target.AuthorizationVersion: 12 -> 13
```

Les sessions possédant les anciennes versions deviennent obsolètes.

---

## Permissions effectives

Après commit :

```text
SourcePermissions
=
Permissions(SourceReplacementRole)
```

```text
TargetPermissions
=
Permissions(TransferredRole)
```

Les anciennes permissions ne doivent pas être fusionnées avec les nouvelles.

---

## Réduction de privilèges de la source

Lorsque la source perd des privilèges, l’effet doit être immédiat.

Le système doit notamment invalider :

- caches ;
- claims ;
- tokens contextualisés ;
- décisions d’accès mémorisées ;
- sessions sensibles ;
- connexions temps réel ;
- capacités administratives.

---

## Augmentation de privilèges de la cible

La cible ne doit utiliser les nouveaux privilèges qu’après :

- commit du transfert ;
- actualisation du contexte d’autorisation ;
- validation d’une éventuelle réauthentification ;
- renouvellement des claims ;
- respect des politiques du rôle.

---

## Sessions

La politique recommandée est :

```text
increment AuthorizationVersion for both Memberships
+
invalidate contextual authorization caches
+
revoke or refresh sensitive sessions
```

---

## Sessions de la source

Pour une perte de rôle privilégié :

```text
RevokeSourceSessions = true
```

peut être imposé par la politique.

Les sessions révoquées ne sont jamais restaurées automatiquement.

---

## Sessions de la cible

Pour l’acquisition d’un rôle privilégié, la cible peut devoir se réauthentifier.

La commande ne doit pas modifier rétroactivement une session existante sans contrôle.

---

## Tokens multi-Workspace

Le transfert ne doit affecter que les contextes correspondant au `Workspace`.

Les accès aux autres `Workspace` restent inchangés, sauf décision de sécurité globale.

---

## Historique des rôles

Le système doit fermer deux périodes et en ouvrir deux nouvelles.

```text
Source previous Role period
    EndedAt = TransferredAt
```

```text
Source replacement Role period
    StartedAt = TransferredAt
```

```text
Target previous Role period
    EndedAt = TransferredAt
```

```text
Target transferred Role period
    StartedAt = TransferredAt
```

---

## Cohérence temporelle

Pour chaque `Membership` actif :

- une seule période de rôle est ouverte ;
- aucune période ne se chevauche ;
- aucun trou temporel n’existe ;
- les deux transitions utilisent le même `TransferredAt` ;
- un retry ne crée aucune période supplémentaire.

---

## Responsabilités externes

Le rôle peut être associé à des responsabilités appartenant à d’autres bounded contexts.

Exemples :

- ownership de ressources ;
- approbation de paiements ;
- administration d’intégrations ;
- signature ;
- contact principal ;
- gestion de clés ;
- administration de domaine ;
- responsabilité réglementaire.

`Identity` ne doit pas reproduire ces modèles.

Il peut consommer :

```text
RoleTransferReadiness
```

et publier l’événement final.

---

## Effets externes

Après le transfert, des handlers peuvent :

- transférer des ressources ;
- mettre à jour les approbateurs ;
- modifier les contacts ;
- retirer des groupes externes à la source ;
- ajouter des groupes à la cible ;
- révoquer des licences ;
- attribuer des licences ;
- faire tourner des secrets ;
- mettre à jour des intégrations ;
- notifier les parties concernées.

Ces effets doivent être idempotents.

---

## Ordre des effets externes

Le domaine interne devient immédiatement la source de vérité.

Ordre recommandé :

```text
atomic Role transfer committed
↓
internal authorization invalidated
↓
critical external privileges removed from Source
↓
critical external privileges granted to Target
↓
secondary integrations updated
```

Lorsque la continuité externe doit être stricte, un plan de transfert doit être préparé avant la commande.

---

## Échec d’une intégration externe

Le transfert interne peut réussir alors qu’une intégration échoue.

Le système doit prévoir :

- retry ;
- outbox ;
- dead-letter queue ;
- alerte ;
- état de provisioning ;
- réconciliation ;
- escalade de sécurité.

Le domaine ne doit pas annuler silencieusement le transfert.

---

## Notification de la source

La source peut recevoir :

- confirmation du transfert ;
- date d’effet ;
- nouveau rôle ;
- conséquences sur ses accès ;
- sessions révoquées ;
- responsabilités restantes ;
- modalités de contestation ou support.

---

## Notification de la cible

La cible peut recevoir :

- confirmation du rôle reçu ;
- date d’effet ;
- nouvelles responsabilités ;
- exigences de réauthentification ;
- actions à effectuer ;
- règles de sécurité associées.

---

## Notification des Owners

Un transfert d’ownership doit généralement être notifié aux autres owners actifs.

Cette notification permet :

- la transparence de gouvernance ;
- la détection d’actions non autorisées ;
- la continuité opérationnelle ;
- l’audit.

---

## Notification de sécurité

Les transferts suivants peuvent déclencher une alerte renforcée :

```text
Owner
SecurityAdministrator
ComplianceManager
BillingAdministrator
Role with destructive permissions
```

---

## Confidentialité

L’événement public et les notifications ne doivent pas exposer inutilement :

- les permissions détaillées ;
- les commentaires internes ;
- les raisons de sécurité confidentielles ;
- les données personnelles ;
- les détails d’une enquête ;
- les facteurs d’authentification ;
- les secrets transférés.

---

## Audit

Un transfert réussi doit enregistrer :

- `WorkspaceId`
- `SourceMembershipId`
- `SourceUserId`
- rôle précédent de la source
- rôle final de la source
- `TargetMembershipId`
- `TargetUserId`
- rôle précédent de la cible
- rôle final de la cible
- rôle transféré
- classification du transfert
- `TransferredAt`
- `TransferredBy`
- `TransferReason`
- `TransferSource`
- `SourceConsentId`
- `TargetAcceptanceId`
- `ApprovalId`
- `CaseReference`
- `ExternalReference`
- `TransferPlanId`
- `TransferRequestId`
- `CorrelationId`
- versions précédentes et finales
- versions d’autorisation précédentes et finales
- politique de session appliquée
- résultat des vérifications
- résultat final

---

## Questions auxquelles l’audit doit répondre

```text
which Role was transferred
who previously held it
who received it
which Role the Source received afterward
who authorized the transfer
why the transfer occurred
whether Source consented
whether Target accepted
which approvals were used
whether ownership changed
whether sessions were revoked
whether external responsibilities were prepared
```

---

## Classification du transfert

Valeurs possibles :

```text
OwnershipTransfer
ExclusiveRoleTransfer
PrivilegedRoleTransfer
StandardRoleTransfer
RoleExchange
ResponsibilityHandover
EmergencyTransfer
ExternalSynchronization
```

La classification peut être calculée à partir :

- du rôle ;
- des transitions ;
- de la source ;
- du motif ;
- du caractère exclusif ;
- des privilèges.

---

## Sécurité

La commande doit garantir que :

- la source possède réellement le rôle transféré ;
- la cible est distincte ;
- les deux membres sont actifs ;
- les deux membres appartiennent au même `Workspace` ;
- le rôle est transférable ;
- le rôle de remplacement est valide ;
- l’acteur peut exécuter les deux transitions ;
- la self-promotion est contrôlée ;
- les consentements sont valides ;
- les approbations sont valides ;
- le dernier owner reste protégé ;
- les règles de séparation des responsabilités sont respectées ;
- les permissions anciennes deviennent immédiatement obsolètes ;
- les deux agrégats sont modifiés atomiquement ;
- les retries ne dupliquent aucun effet.

---

## Décisions de conception

### Le transfert modifie deux Membership

Le transfert est une seule décision métier affectant deux agrégats.

---

### SourceReplacementRoleId est obligatoire

Chaque `Membership` actif doit posséder exactement un rôle.

La source ne peut donc pas perdre son rôle sans recevoir un remplacement.

---

### Le rôle courant de la cible est remplacé

Le modèle actuel ne permet pas plusieurs rôles simultanés par `Membership`.

La cible perd donc son rôle courant et reçoit le rôle transféré.

---

### Le rôle transféré doit être détenu par la source

La commande n’est pas une simple attribution.

Condition obligatoire :

```text
SourceMembership.RoleId = TransferredRoleId
```

---

### Le transfert est limité au même Workspace

Un rôle contextualisé ne traverse jamais les frontières de `Workspace`.

---

### Le transfert est atomique

Les deux changements doivent réussir ou échouer ensemble.

---

### L’ownership est un cas particulier

Aucune commande de domaine spécifique n’est nécessaire pour la structure générale.

Des politiques plus fortes s’appliquent lorsque :

```text
TransferredRole.SystemType = Owner
```

---

### Le transfert n’est pas nécessaire pour une simple promotion

Pour ajouter un owner sans rétrograder l’owner actuel, utiliser :

```text
ChangeMembershipRole
```

sur la cible.

---

### Un événement métier unique représente la décision

L’événement recommandé est :

```text
MembershipRoleTransferCompleted
```

---

### Les sessions des deux membres sont réévaluées

Les deux contextes d’autorisation changent.

---

### Les responsabilités externes restent dans leurs bounded contexts

Identity consomme une readiness et publie le résultat.

---

## Cas limites

### La cible possède déjà le rôle transféré

Dans le modèle à un rôle, cela signifie :

```text
TargetMembership.RoleId = TransferredRoleId
```

Le transfert n’a pas de sens puisque le rôle est déjà attribué à la cible.

Erreur :

```text
TargetAlreadyHasTransferredRole
```

---

### La source reçoit le même rôle qu’elle transfère

Si :

```text
SourceReplacementRoleId = TransferredRoleId
```

la source ne perd pas le rôle.

Il ne s’agit donc pas d’un transfert.

Erreur :

```text
InvalidSourceReplacementRole
```

---

### Le rôle transféré n’est pas exclusif

Le transfert peut rester valide s’il représente une responsabilité réelle.

Cependant, une simple attribution suivie d’une rétrogradation indépendante peut être plus claire.

La politique du rôle doit indiquer si la commande est pertinente.

---

### Le rôle de remplacement est celui de la cible

Le transfert devient un échange naturel.

```text
Source: Owner
Target: Administrator
```

devient :

```text
Source: Administrator
Target: Owner
```

---

### La cible est le dernier autre Owner

Exemple :

```text
Source: Owner
Target: Owner
```

Un transfert d’owner vers un membre déjà owner n’exprime aucun déplacement utile du rôle transféré.

Il peut néanmoins rétrograder la source.

Ce cas doit plutôt utiliser :

```text
ChangeMembershipRole
```

sur la source.

---

### La source est suspendue juste avant le commit

Le transfert échoue.

---

### La cible est désactivée juste avant le commit

Le transfert échoue.

---

### Le rôle transféré est désactivé pendant l’opération

Le transfert doit détecter le conflit.

---

### Le rôle de remplacement est archivé pendant l’opération

Le transfert doit échouer.

---

### Le consentement expire pendant l’opération

La validité doit être vérifiée dans la décision transactionnelle.

---

### La cible retire son acceptation

Si le retrait d’acceptation gagne avant le commit, le transfert échoue.

Après commit, un changement ultérieur nécessite une nouvelle commande.

---

### La source quitte immédiatement après le transfert

`LeaveWorkspace` évalue le nouveau rôle de la source.

---

### La cible quitte immédiatement après le transfert

`LeaveWorkspace` doit vérifier les responsabilités et le dernier owner sur le nouvel état.

---

### Transfert temporaire

Un transfert temporaire ne doit pas automatiquement planifier le retour dans cette commande.

Le retour doit être effectué par une nouvelle décision explicite.

Une date indicative peut déclencher une revue :

```text
ExpectedReturnAt
↓
Review
↓
TransferMembershipRole
```

Aucun retour automatique sans réévaluation.

---

### Compte de service comme cible

Le rôle peut interdire les comptes de service.

Pour owner, la recommandation est d’exiger un `User` humain actif si la gouvernance humaine est nécessaire.

---

### Invitation en attente comme cible

Une `Invitation` n’est pas un `Membership` actif.

Elle ne peut pas recevoir directement le rôle par cette commande.

Le destinataire doit d’abord accepter l’invitation et obtenir un `Membership`.

---

## Checklist de validation

Avant commit :

```text
Source Membership exists
Target Membership exists
Source differs from Target
Both Memberships belong to same Workspace
Both Memberships are Active
Both Users are active
Source holds Transferred Role
Transferred Role exists
Transferred Role is active
Transferred Role is transferable
Source Replacement Role exists
Source Replacement Role is active
Both Roles belong to Workspace
Actor is authorized for complete transfer
Source transition is authorized
Target transition is authorized
Source consent is valid when required
Target acceptance is valid when required
Approval is valid when required
Authentication requirements are satisfied
Transfer readiness is valid
Transfer plan is complete when required
Separation of duties is respected
Role assignment limits are respected
Last active Owner remains
Source version is current
Target version is current
Workspace governance state is current
Idempotency is verified
Both updates can commit atomically
Event can be recorded atomically
```

---

## Synthèse

`TransferMembershipRole` transfère atomiquement une responsabilité représentée par un rôle entre deux membres actifs du même `Workspace`.

Elle garantit que :

- la source possède réellement le rôle transféré ;
- la cible est active et éligible ;
- la source reçoit un rôle de remplacement explicite ;
- aucun membre actif ne reste sans rôle ;
- les deux transitions sont autorisées ;
- les consentements et approbations sont validés ;
- les règles de gouvernance sont respectées ;
- l’invariant du dernier owner n’est jamais violé ;
- aucun état intermédiaire incohérent n’est observable ;
- les permissions des deux membres sont immédiatement réévaluées ;
- les sessions et caches deviennent obsolètes ;
- les deux changements sont enregistrés comme une seule décision métier ;
- les retries et les opérations concurrentes restent sûrs.

Le résultat final est :

```text
Source Membership
Transferred Role
    ↓
Source Replacement Role
```

et simultanément :

```text
Target Membership
Previous Role
    ↓
Transferred Role
```

avec :

```text
same Workspace
both Memberships Active
one atomic business decision
no transient ownership gap
```
