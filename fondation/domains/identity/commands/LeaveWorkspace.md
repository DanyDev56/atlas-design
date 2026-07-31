---
id: IDN-CMD-LEAVE-WORKSPACE
title: LeaveWorkspace
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
  - ../workflows.md
  - ../permissions.md
  - ../events/MembershipLeft.md
  - CreateMembership.md
  - RestoreMembership.md
  - SuspendMembership.md
  - ReactivateMembership.md
  - ChangeMembershipRole.md
  - RemoveMembership.md
---

# LeaveWorkspace

## Objectif

La commande `LeaveWorkspace` permet à un membre de quitter volontairement un `Workspace`.

Elle fait passer son `Membership` de :

```text
Active
```

vers :

```text
Removed
```

Le départ conserve :

- le même `MembershipId` ;
- le même `UserId` ;
- le même `WorkspaceId` ;
- le dernier `RoleId` connu ;
- l’historique de l’appartenance ;
- les changements de rôle antérieurs ;
- les périodes d’activité ;
- la possibilité d’une restauration future explicite.

Le départ ne supprime pas physiquement le `Membership`.

---

## Signification métier

`LeaveWorkspace` exprime l’intention suivante :

```text
the Member voluntarily ends their own participation
in the Workspace
```

L’acteur et la cible représentent le même `User`.

```text
Actor.UserId = Membership.UserId
```

Après le départ :

- le membre n’appartient plus effectivement au `Workspace` ;
- son rôle n’accorde plus aucune permission ;
- ses sessions ne peuvent plus utiliser ce contexte ;
- il ne compte plus parmi les membres actifs ;
- il ne compte plus parmi les owners actifs ;
- son historique reste conservé ;
- ses responsabilités doivent avoir été transférées ou résolues.

---

## Différence avec RemoveMembership

### LeaveWorkspace

Le membre décide lui-même de quitter.

```text
Member
↓
LeaveWorkspace
↓
Removed
```

### RemoveMembership

Un acteur administratif ou un workflow système met fin à l’appartenance.

```text
Administrator or System
↓
RemoveMembership
↓
Removed
```

Les deux commandes peuvent produire le même état final, mais elles ne représentent pas la même décision métier.

Elles diffèrent notamment sur :

- l’acteur ;
- la permission ;
- la confirmation ;
- le motif ;
- les notifications ;
- l’audit ;
- les règles de départ d’un owner ;
- le transfert de responsabilités ;
- le délai de rétractation éventuel ;
- les intégrations.

---

## Différence avec DeleteUser

`LeaveWorkspace` agit sur une seule appartenance.

```text
User
├── Membership in Workspace A
├── Membership in Workspace B
└── Membership in Workspace C
```

Quitter `Workspace A` ne doit pas affecter les appartenances aux autres `Workspace`.

La commande ne doit pas :

- supprimer le `User` ;
- désactiver le compte global ;
- retirer les autres `Membership` ;
- supprimer toutes les sessions sans distinction ;
- effacer les données globales du `User`.

---

## Différence avec SuspendMembership

`LeaveWorkspace` met fin à l’appartenance.

```text
Active
↓
Removed
```

`SuspendMembership` conserve l’appartenance mais bloque temporairement l’accès.

```text
Active
↓
Suspended
```

Un membre qui souhaite seulement interrompre temporairement son activité ne devrait pas nécessairement quitter le `Workspace`.

---

## Agrégat concerné

`Membership`

Le `Membership` constitue la racine de l’agrégat modifié.

La commande peut consulter :

- le `User` ;
- le `Workspace` ;
- le `Role` actuel ;
- les autres owners actifs ;
- les responsabilités détenues ;
- les délégations actives ;
- les ressources dont le membre est propriétaire ;
- les workflows en attente ;
- les politiques de départ ;
- les règles de rétention ;
- les restrictions de sécurité.

Ces éléments ne sont pas tous modifiés dans la transaction de l’agrégat.

---

## Acteur

L’acteur est nécessairement le membre concerné.

Condition fondamentale :

```text
Actor.UserId = Membership.UserId
```

La commande ne doit pas permettre à un administrateur de simuler un départ volontaire.

Un administrateur utilise :

```text
RemoveMembership
```

Un workflow système ne devrait pas utiliser `LeaveWorkspace`, sauf mécanisme explicite agissant au nom du membre avec une preuve de consentement.

---

## Permission requise

Le départ volontaire ne devrait pas dépendre d’une permission administrative telle que :

```text
workspace.members.remove
```

La capacité à quitter relève de l’appartenance elle-même.

La condition d’accès recommandée est :

```text
Actor owns Membership
AND
Membership.Status = Active
```

Une capability explicite peut néanmoins être utilisée :

```text
workspace.members.leave
```

Cette capability doit être implicitement accordée au membre actif et ne pas dépendre d’un `Role` administrable ordinaire.

---

## Pourquoi le départ ne doit pas dépendre du Role

Si la permission de quitter était accordée par le `Role`, un administrateur pourrait retirer cette permission et emprisonner techniquement un membre dans le `Workspace`.

La possibilité de quitter relève d’une règle structurelle du produit, sous réserve :

- de l’invariant du dernier owner ;
- des obligations légales ;
- des responsabilités bloquantes ;
- des workflows de fermeture ;
- des restrictions contractuelles explicites.

---

## Préconditions

Avant l’exécution de `LeaveWorkspace`, les conditions suivantes doivent être satisfaites :

- le `Membership` existe ;
- le `Membership` appartient à l’acteur ;
- son état est `Active` ;
- le `User` est authentifié ;
- la session est valide ;
- une authentification récente est disponible lorsque requise ;
- le `Workspace` existe ;
- le `Workspace` permet le départ ;
- le membre n’est pas le dernier owner actif ;
- les responsabilités bloquantes ont été transférées ou résolues ;
- les délégations critiques ont été révoquées ou transférées ;
- les ressources nécessitant un propriétaire ont un successeur ;
- la confirmation de départ est valide ;
- aucune opération concurrente incompatible n’a déjà gagné ;
- la demande est idempotente ;
- les éventuelles obligations de préavis sont satisfaites.

---

## État source autorisé

La transition nominale est :

```text
Active
↓
Removed
```

Un membre suspendu ne devrait pas utiliser `LeaveWorkspace` par défaut.

Le départ d’un membre suspendu soulève une question d’autorité :

- peut-il encore exercer une décision sur son appartenance ?
- sa suspension autorise-t-elle uniquement la perte d’accès ?
- le retrait doit-il être traité administrativement ?
- existe-t-il une investigation en cours ?

La politique recommandée est :

```text
Suspended Membership
    -> cannot invoke LeaveWorkspace
```

Un membre suspendu peut demander son départ par un workflow distinct, puis un acteur autorisé exécute :

```text
RemoveMembership
```

Cette règle évite qu’un membre contourne :

- une enquête ;
- une obligation de conservation ;
- une procédure de sécurité ;
- une décision de conformité.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `MembershipId` | `MembershipId` | Oui | Identifie l’appartenance quittée. |
| `LeftBy` | `UserId` | Oui | Identifie le membre qui quitte. |
| `LeftAt` | Instant | Oui | Date la fin volontaire de l’appartenance. |
| `LeaveRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |
| `Confirmation` | `LeaveConfirmation` | Oui | Prouve que le membre confirme son départ. |

Données facultatives ou conditionnelles :

| Donnée | Type | Obligatoire | Description |
|---|---|---:|---|
| `Reason` | `MembershipLeaveReason` | Non | Motif structuré facultatif. |
| `Comment` | Texte court | Non | Commentaire facultatif du membre. |
| `ReplacementMembershipId` | `MembershipId` | Conditionnel | Identifie le membre reprenant certaines responsabilités. |
| `TransferPlanId` | Identifiant | Conditionnel | Référence un plan de transfert validé. |
| `CorrelationId` | Identifiant | Non | Relie le départ à un workflow plus large. |
| `RevokeAllWorkspaceSessions` | Booléen | Non | Demande la révocation des sessions liées au `Workspace`. |
| `RequestedEffectiveAt` | Instant | Non | Date future demandée lorsque le départ différé est autorisé. |

---

## MembershipLeaveReason

Valeurs possibles :

```text
NoLongerNeeded
OrganizationChange
ProjectCompleted
EmploymentEnded
ContractEnded
PersonalDecision
SwitchingWorkspace
PrivacyConcern
Other
```

Le motif peut être facultatif.

Le membre ne doit pas être obligé de fournir un détail personnel pour exercer son droit de départ, sauf exigence métier ou légale clairement définie.

---

## LeaveConfirmation

`LeaveConfirmation` représente une preuve explicite que le membre comprend les conséquences du départ.

Elle peut contenir :

```text
ConfirmationId
ConfirmedAt
AuthenticationContext
ExpectedMembershipVersion
AcknowledgedConsequences
```

Elle ne doit pas contenir :

- un mot de passe en clair ;
- un token de session brut ;
- un secret d’authentification ;
- des données inutiles.

---

## Conséquences à confirmer

La confirmation peut rappeler que le départ entraîne :

- la perte immédiate de l’accès ;
- la perte du rôle courant ;
- l’impossibilité d’utiliser les ressources du `Workspace` ;
- la révocation des sessions contextualisées ;
- le transfert ou la perte de certaines responsabilités ;
- l’éventuelle nécessité d’une nouvelle invitation pour revenir ;
- la conservation de certaines traces d’audit.

---

## Authentification récente

Une authentification récente peut être exigée lorsque :

- le membre est owner ;
- le rôle est privilégié ;
- le départ provoque des transferts sensibles ;
- le `Workspace` est fortement réglementé ;
- des données critiques sont concernées ;
- le départ est irréversible pendant une certaine période.

Condition conceptuelle :

```text
CurrentTime - LastStrongAuthenticationAt
<= AllowedAuthenticationAge
```

---

## Traitement métier

### 1. Charger le Membership

Le système charge le `Membership` identifié par `MembershipId`.

S’il n’existe pas, la commande échoue.

---

### 2. Vérifier que l’acteur possède le Membership

La condition suivante doit être vraie :

```text
Membership.UserId = LeftBy
```

et :

```text
AuthenticatedUserId = LeftBy
```

Sinon, la commande échoue.

---

### 3. Vérifier l’état courant

Le `Membership` doit être :

```text
Active
```

#### Active

Le départ peut continuer.

#### Suspended

La commande échoue.

#### Removed

Le système vérifie l’idempotence.

S’il ne s’agit pas de la même demande, il retourne :

```text
MembershipAlreadyLeft
```

---

### 4. Vérifier l’idempotence

Le système recherche une opération déjà appliquée avec :

```text
MembershipId + LeaveRequestId
```

Si elle existe, le résultat initial est retourné sans nouvel effet.

---

### 5. Vérifier la session

La session utilisée pour demander le départ doit :

- être valide ;
- appartenir au `User` ;
- ne pas être expirée ;
- ne pas être révoquée ;
- permettre l’accès au `Workspace` ciblé ;
- porter une version d’autorisation compatible.

---

### 6. Vérifier la confirmation

Le système vérifie que la confirmation :

- correspond au `Membership` ;
- correspond au `User` ;
- est récente ;
- n’a pas déjà été consommée pour une autre opération ;
- porte la version attendue du `Membership` ;
- couvre les conséquences requises ;
- n’a pas expiré.

---

### 7. Vérifier l’authentification récente

Lorsque la politique l’exige, le système vérifie une preuve d’authentification forte récente.

Exemples :

- saisie du mot de passe ;
- passkey ;
- MFA ;
- revalidation SSO ;
- confirmation par dispositif sécurisé.

La commande ne reçoit pas nécessairement le secret lui-même.

Elle reçoit une preuve ou un contexte validé par le mécanisme d’authentification.

---

### 8. Charger le Workspace

Le système charge le contexte du `Workspace` :

- état ;
- politique de départ ;
- règles de gouvernance ;
- owners actifs ;
- obligations contractuelles ;
- restrictions de fermeture ;
- besoins de transfert.

---

### 9. Vérifier que le Workspace permet le départ

Le départ peut être temporairement bloqué dans certaines situations explicites :

- fermeture transactionnelle en cours ;
- migration critique ;
- gel légal ;
- investigation nécessitant une procédure spécifique ;
- processus de transfert obligatoire ;
- relation contractuelle non résolue.

Un `Workspace` ne doit toutefois pas bloquer arbitrairement le départ sans justification métier claire.

---

### 10. Identifier le Role actuel

Le système charge le rôle actuel afin de déterminer :

- si le membre est owner ;
- les responsabilités potentielles ;
- le niveau de confirmation requis ;
- les transferts nécessaires ;
- les notifications ;
- les conséquences de sécurité.

---

### 11. Déterminer si le membre compte comme Owner actif

Condition conceptuelle :

```text
Membership.Status = Active
AND
User.Status = Active
AND
Role.SystemType = Owner
```

La qualité d’owner ne doit pas être déterminée par le seul nom du rôle.

---

### 12. Protéger le dernier Owner actif

Si le membre est owner, la commande doit vérifier :

```text
ActiveOwnerCountAfterLeave >= 1
```

Le départ suivant est autorisé :

```text
Owner A
Owner B

Owner A leaves
```

Le départ suivant est interdit :

```text
Owner A
Member B

Owner A leaves
```

L’erreur commune recommandée est :

```text
WorkspaceMustHaveActiveOwner
```

---

### 13. Expliquer le blocage au dernier Owner

Lorsqu’un membre est le dernier owner, le système doit proposer un parcours métier cohérent :

```text
Promote another Member to Owner
↓
Transfer responsibilities
↓
LeaveWorkspace
```

ou :

```text
CloseWorkspace
```

si le membre souhaite mettre fin au `Workspace`.

Le système ne doit pas contourner l’invariant en autorisant un owner inexistant.

---

### 14. Vérifier les responsabilités bloquantes

Le membre peut détenir des responsabilités incompatibles avec un départ immédiat.

Exemples :

- seul responsable de facturation ;
- seul administrateur d’une intégration ;
- propriétaire exclusif de ressources ;
- approbateur unique ;
- responsable légal ;
- détenteur d’un secret opérationnel ;
- signataire obligatoire ;
- gestionnaire d’un domaine ;
- responsable de conformité ;
- owner d’un workflow critique.

---

### 15. Obtenir une décision de LeaveReadiness

Le workflow peut obtenir une décision :

```text
LeaveReadiness
```

Valeurs possibles :

```text
Ready
ReadyWithWarnings
Blocked
RequiresTransfer
RequiresNoticePeriod
```

Cette décision peut agréger les réponses d’autres bounded contexts sans copier leurs modèles internes dans `Identity`.

---

### 16. Traiter les blocages

Si la décision est :

```text
Blocked
```

la commande échoue.

Si elle est :

```text
RequiresTransfer
```

un `ReplacementMembershipId` ou un `TransferPlanId` valide est exigé.

Si elle est :

```text
ReadyWithWarnings
```

la confirmation doit couvrir les avertissements concernés.

---

### 17. Vérifier le remplaçant

Lorsque `ReplacementMembershipId` est fourni, le remplaçant doit :

- exister ;
- être actif ;
- appartenir au même `Workspace` ;
- être différent du membre sortant ;
- être éligible ;
- disposer du rôle nécessaire ;
- avoir accepté les responsabilités lorsque requis.

---

### 18. Vérifier le plan de transfert

Un `TransferPlanId` peut référencer un workflow externe qui confirme que :

- les ressources ont un nouveau propriétaire ;
- les approbations ont été déléguées ;
- les intégrations ont un nouvel administrateur ;
- les responsabilités légales ont été transférées ;
- les secrets ont été rotés ;
- les tâches en attente ont été réassignées.

Le plan doit être :

- valide ;
- non expiré ;
- lié au bon `Membership` ;
- lié au bon `Workspace` ;
- dans un état permettant le départ.

---

### 19. Vérifier un éventuel préavis

Certains produits peuvent autoriser un départ différé.

Exemple :

```text
RequestedEffectiveAt > CurrentTime
```

Dans ce cas, `LeaveWorkspace` peut soit :

- planifier un workflow ;
- créer une intention de départ ;
- ou refuser la date future et exiger une commande distincte.

La recommandation est de séparer :

```text
RequestWorkspaceLeave
```

de :

```text
LeaveWorkspace
```

si un préavis réel existe.

`LeaveWorkspace` représente alors l’application effective du départ.

---

### 20. Vérifier les opérations concurrentes

Le système doit détecter les concurrences avec :

- `ChangeMembershipRole` ;
- `SuspendMembership` ;
- `RemoveMembership` ;
- une autre `LeaveWorkspace` ;
- `DisableUser` ;
- promotion d’un autre owner ;
- fermeture du `Workspace` ;
- transfert de responsabilités ;
- modification du rôle courant.

---

### 21. Fermer la période active

La période d’appartenance active est clôturée à `LeftAt`.

Exemple :

```text
MembershipActivePeriod
├── StartedAt
├── StartedBy
├── StartSource
├── EndedAt = LeftAt
├── EndedBy = LeftBy
├── EndSource = VoluntaryLeave
└── EndReason
```

---

### 22. Enregistrer le départ

L’agrégat passe de :

```text
Active
```

à :

```text
Removed
```

Il conserve :

- `MembershipId` ;
- `UserId` ;
- `WorkspaceId` ;
- dernier `RoleId` ;
- historique.

Il enregistre :

- `LeftAt` ;
- `LeftBy` ;
- `LeaveReason`, le cas échéant ;
- `LeaveRequestId` ;
- `ReplacementMembershipId`, le cas échéant ;
- `TransferPlanId`, le cas échéant ;
- `CorrelationId`.

---

### 23. Rendre les permissions ineffectives

Dès le commit :

```text
Membership.Status = Removed
```

La chaîne d’autorisation devient invalide.

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

Aucune permission du dernier rôle ne reste effective.

---

### 24. Invalider le contexte d’autorisation

Le système doit :

- incrémenter `AuthorizationVersion` ;
- invalider les caches ;
- invalider les claims ;
- révoquer les sessions contextualisées ;
- interrompre les connexions sensibles ;
- empêcher toute nouvelle opération dans ce `Workspace`.

---

### 25. Produire l’événement

L’agrégat produit :

```text
MembershipLeft
```

Les transferts externes, notifications et nettoyages restent hors de la transaction principale.

---

## Résultat attendu

Après une exécution réussie :

- le `Membership` existe toujours ;
- son état est `Removed` ;
- le membre n’appartient plus au `Workspace` ;
- le dernier rôle est conservé comme historique ;
- aucune permission n’est effective ;
- les sessions liées au contexte ne fonctionnent plus ;
- les responsabilités bloquantes sont résolues ;
- le dernier owner est protégé ;
- le départ volontaire est explicitement auditable.

État conceptuel :

```text
Membership
├── MembershipId: unchanged
├── UserId: unchanged
├── WorkspaceId: unchanged
├── RoleId: last known Role
├── Status: Removed
├── LeftAt
├── LeftBy
├── LeaveReason
├── LeaveRequestId
├── ReplacementMembershipId
├── TransferPlanId
└── History preserved
```

---

## Invariants concernés

### `IDN-INV-001`

Un seul `Membership` existe pour :

```text
UserId + WorkspaceId
```

Le départ ne crée ni ne supprime physiquement l’entité.

---

### `IDN-INV-002`

Le `Membership` conserve ses identifiants.

---

### `IDN-INV-004`

La transition autorisée est :

```text
Active -> Removed
```

---

### `IDN-INV-006`

Le `Workspace` conserve toujours au moins un owner actif tant qu’il reste actif.

```text
ActiveOwnerCountAfterLeave >= 1
```

---

### `IDN-INV-010`

Un `Membership` retiré n’accorde aucune permission.

---

### `IDN-INV-011`

Une `Session` ne peut pas autoriser un contexte dont le `Membership` est `Removed`.

---

### `IDN-INV-014`

Le départ affecte uniquement le contexte du `Workspace` ciblé.

---

## Transition d’état

Transition autorisée :

```text
Active
↓
Removed
```

Transitions interdites :

```text
Suspended
↓
Removed
```

via cette commande, selon la politique recommandée.

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

## Événement produit

### MembershipLeft

La commande produit :

```text
MembershipLeft
```

L’événement peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `LastRoleId`
- `LeftAt`
- `LeftBy`
- `Reason`
- `ReplacementMembershipId`
- `TransferPlanId`
- `LeaveRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- les tokens ;
- les secrets ;
- les commentaires privés ;
- les permissions détaillées ;
- les motifs personnels non nécessaires ;
- les détails internes des ressources transférées.

---

## Pourquoi MembershipLeft est distinct de MembershipRemoved

Même si les deux événements aboutissent à :

```text
Membership.Status = Removed
```

ils expriment des causes différentes.

### MembershipLeft

```text
voluntary decision by the Member
```

### MembershipRemoved

```text
administrative or system decision
```

Cette distinction est utile pour :

- l’audit ;
- les notifications ;
- les analytics ;
- les règles de retour ;
- les intégrations RH ;
- la sécurité ;
- les métriques de churn ;
- l’expérience utilisateur.

---

## Événements non produits

La commande ne produit pas :

```text
MembershipRemoved
MembershipSuspended
MembershipRoleChanged
UserDeleted
SessionRevoked
WorkspaceClosed
```

Des handlers peuvent déclencher des commandes distinctes pour les sessions ou intégrations.

---

## Erreurs métier

### MembershipNotFound

Le `Membership` n’existe pas.

---

### MembershipDoesNotBelongToActor

Le `Membership` n’appartient pas au `User` authentifié.

---

### MembershipAlreadyLeft

Le `Membership` est déjà `Removed`.

Cette erreur ne s’applique pas à la reprise idempotente de la même demande.

---

### MembershipSuspended

Le membre est suspendu et ne peut pas utiliser ce parcours.

---

### MembershipNotActive

L’état courant ne permet pas le départ volontaire.

---

### InvalidSession

La session utilisée n’est pas valide.

---

### RecentAuthenticationRequired

Une authentification récente est nécessaire.

---

### InvalidLeaveConfirmation

La confirmation est absente, expirée ou incompatible.

---

### LeaveConfirmationExpired

La confirmation a dépassé sa durée de validité.

---

### MembershipVersionChanged

Le `Membership` a changé depuis la confirmation.

Une nouvelle confirmation est requise.

---

### WorkspaceMustHaveActiveOwner

Le départ laisserait le `Workspace` sans owner actif.

---

### LeaveBlockedByResponsibilities

Des responsabilités critiques empêchent le départ.

---

### ReplacementMembershipRequired

Un remplaçant est obligatoire.

---

### ReplacementMembershipNotFound

Le remplaçant n’existe pas.

---

### ReplacementMembershipNotActive

Le remplaçant n’est pas actif.

---

### ReplacementMembershipBelongsToAnotherWorkspace

Le remplaçant appartient à un autre `Workspace`.

---

### ReplacementMembershipNotEligible

Le remplaçant ne peut pas recevoir les responsabilités.

---

### TransferPlanRequired

Un plan de transfert est obligatoire.

---

### TransferPlanNotFound

Le plan de transfert n’existe pas.

---

### TransferPlanInvalid

Le plan ne correspond pas au membre ou au `Workspace`.

---

### TransferPlanIncomplete

Le transfert n’est pas terminé.

---

### NoticePeriodRequired

Un préavis doit être respecté.

---

### WorkspaceLeaveForbidden

Le `Workspace` est dans un état qui interdit temporairement ce parcours.

---

### LegalHoldConflict

Une obligation légale impose une procédure distincte.

---

### LeaveConflict

Une opération concurrente a modifié le `Membership`.

---

### IdempotencyConflict

Le même `LeaveRequestId` a été réutilisé avec des données différentes.

---

## Idempotence

`LeaveWorkspace` doit être idempotente pour :

```text
MembershipId + LeaveRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- appliquer un second départ ;
- modifier `LeftAt` ;
- remplacer le motif ;
- fermer à nouveau la période active ;
- publier un nouvel événement ;
- répéter les notifications ;
- répéter les révocations non idempotentes.

---

## Reprise après réponse perdue

Cas typique :

```text
LeaveWorkspace succeeds

↓

Membership becomes Removed

↓

Response is lost

↓

Caller retries
```

La seconde exécution doit retrouver :

- le même `LeaveRequestId` ;
- le même `LeftAt` ;
- le même dernier rôle ;
- le même transfert ;
- la même version finale.

Elle retourne le résultat initial.

---

## Réutilisation incorrecte de LeaveRequestId

Exemple initial :

```text
LeaveRequestId = ABC
ReplacementMembershipId = M1
```

Nouvelle requête :

```text
LeaveRequestId = ABC
ReplacementMembershipId = M2
```

Le système doit retourner :

```text
IdempotencyConflict
```

---

## Concurrence

### LeaveWorkspace contre RemoveMembership

Une seule transition doit gagner.

Si `LeaveWorkspace` gagne, l’événement est :

```text
MembershipLeft
```

Si `RemoveMembership` gagne, l’événement est :

```text
MembershipRemoved
```

La seconde commande doit détecter que le `Membership` est déjà `Removed`.

Elle ne doit pas réécrire la cause de fin d’appartenance.

---

### LeaveWorkspace contre SuspendMembership

Si la suspension gagne avant le départ, `LeaveWorkspace` échoue.

Si le départ gagne, la suspension échoue car le membre est déjà retiré.

---

### LeaveWorkspace contre ChangeMembershipRole

Si le rôle change après la confirmation mais avant le commit, la commande doit être réévaluée.

Cela est particulièrement critique si le membre devient owner.

La confirmation peut porter :

```text
ExpectedMembershipVersion
```

afin d’obliger une nouvelle confirmation après modification.

---

### LeaveWorkspace contre promotion vers Owner

Un membre peut confirmer son départ comme `Member`, puis être promu `Owner`.

Le départ ne doit pas être appliqué sur une décision obsolète.

Le versionnement doit provoquer :

```text
MembershipVersionChanged
```

---

### LeaveWorkspace contre promotion d’un autre Owner

État initial :

```text
Owner A
Member B
```

A souhaite partir.

B est promu owner simultanément.

Le départ de A peut devenir valide après la promotion.

Le système doit :

- sérialiser les décisions ;
- ou demander un retry après la promotion ;
- ou utiliser un workflow de transfert.

---

### Deux owners quittant simultanément

État initial :

```text
Owner A
Owner B
```

A et B demandent tous deux leur départ.

Une seule commande peut réussir.

La coordination doit garantir :

```text
ActiveOwnerCount >= 1
```

après commit.

---

### LeaveWorkspace contre DisableUser

Les deux opérations peuvent réussir avec des portées différentes.

Le départ met fin à une appartenance.

La désactivation rend le `User` indisponible globalement.

L’audit doit conserver les deux intentions si elles ont réellement été exécutées.

---

### LeaveWorkspace contre fermeture du Workspace

Si la fermeture devient effective, le workflow de fermeture peut prendre la priorité.

La commande doit soit :

- échouer avec un état explicite ;
- être absorbée par le workflow ;
- ou être enregistrée avant la fermeture selon l’ordre transactionnel.

---

## Cohérence concurrente du dernier Owner

Comme pour `ChangeMembershipRole`, `SuspendMembership` et `RemoveMembership`, une simple lecture du nombre d’owners est insuffisante.

État initial :

```text
Owner A
Owner B
```

Deux transactions lisent :

```text
ActiveOwnerCount = 2
```

Puis les deux quittent.

Sans coordination, le résultat serait :

```text
ActiveOwnerCount = 0
```

Ce résultat est interdit.

---

## Stratégie recommandée

Utiliser une coordination commune au niveau du `Workspace` :

```text
lock Workspace governance resource
↓
recalculate active Owners
↓
validate invariant
↓
apply LeaveWorkspace
```

La stratégie doit être commune à toutes les commandes affectant le nombre d’owners actifs.

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Membership Active
+
verify actor owns Membership
+
verify confirmation
+
verify recent authentication
+
protect last Owner
+
verify LeaveReadiness
+
verify transfer
+
record Removed
+
close active period
+
increment AuthorizationVersion
+
record MembershipLeft
```

Les états suivants sont interdits :

```text
Membership.Status = Removed
AND
LeftAt is missing
```

```text
MembershipLeft published
AND
Membership remains Active
```

```text
Workspace has no active Owner
```

```text
Membership removed
AND
critical transfer was not validated
```

Une outbox transactionnelle est recommandée.

---

## Frontière transactionnelle

Le changement du statut du `Membership` doit être transactionnel.

Les effets suivants peuvent être asynchrones :

- notification ;
- suppression de groupes externes ;
- révocation de licences ;
- nettoyage de préférences ;
- mise à jour d’analytics ;
- suppression de destinataires ;
- archivage de données secondaires.

Les transferts critiques doivent être validés avant le départ ou coordonnés par un workflow fiable.

---

## Responsabilités et autres bounded contexts

`Identity` ne doit pas posséder le détail de toutes les ressources du membre.

Il peut consommer des décisions telles que :

```text
CanMembershipLeave
```

ou :

```text
LeaveReadiness
```

Les autres bounded contexts restent propriétaires de :

- leurs ressources ;
- leurs règles de transfert ;
- leurs propriétaires ;
- leurs approbations ;
- leurs contraintes réglementaires.

---

## LeaveReadiness

Structure conceptuelle possible :

```text
LeaveReadiness
├── Status
├── BlockingReasonCodes
├── WarningCodes
├── RequiresReplacement
├── TransferPlanId
├── ValidUntil
└── EvaluatedAt
```

La décision doit être suffisamment récente pour éviter un départ basé sur un état obsolète.

---

## Durée de validité de la readiness

Une décision peut être limitée dans le temps :

```text
CurrentTime <= LeaveReadiness.ValidUntil
```

Elle peut également être invalidée par :

- une nouvelle ressource assignée ;
- une nouvelle approbation ;
- un changement de rôle ;
- une nouvelle responsabilité ;
- une modification du plan de transfert.

---

## Owner et transfert d’ownership

Un owner qui n’est pas le dernier peut quitter sans nécessairement transférer son rôle.

Exemple :

```text
Owner A
Owner B
```

A quitte.

B reste owner.

En revanche, si A détient des responsabilités exclusives, celles-ci doivent être transférées indépendamment du nombre d’owners.

---

## Dernier Owner

Le dernier owner dispose de deux parcours possibles :

### Transférer l’ownership

```text
Promote another Membership to Owner
↓
LeaveWorkspace
```

### Fermer le Workspace

```text
CloseWorkspace
```

Le départ ne doit pas implicitement fermer le `Workspace`.

---

## Owner fondateur

Le produit doit préciser si un owner fondateur peut quitter.

Politiques possibles :

### Départ autorisé

Il peut quitter si un autre owner actif demeure.

### Transfert de qualité fondatrice

Une commande spécialisée doit transférer cette qualité.

### Départ interdit

Le `Workspace` doit être fermé ou transféré par une procédure juridique.

Cette règle doit rester distincte de l’invariant du dernier owner.

---

## Sessions

Après le départ, les sessions doivent perdre l’accès au `Workspace`.

La politique recommandée combine :

```text
Membership.Status check
+
AuthorizationVersion increment
+
targeted contextual session revocation
```

Les sessions concernant d’autres `Workspace` peuvent rester valides.

---

## Session courante

La session utilisée pour quitter peut rester valide globalement, mais son contexte du `Workspace` doit être supprimé.

Exemple :

```text
Session remains valid for User
AND
Workspace A context is revoked
AND
Workspace B context remains available
```

L’interface doit rediriger le membre vers :

- un autre `Workspace` ;
- une page de sélection ;
- un écran sans `Workspace` ;
- un parcours de création ou d’invitation.

---

## Tokens et claims

Si les tokens contiennent :

```text
WorkspaceId
MembershipId
RoleId
Permissions
AuthorizationVersion
```

ils deviennent obsolètes après le départ.

Le système ne doit pas attendre leur expiration naturelle.

---

## Notifications

Après `MembershipLeft`, un handler peut :

- confirmer le départ au membre ;
- informer les owners ;
- informer les responsables concernés ;
- notifier les systèmes de provisioning ;
- déclencher des nettoyages ;
- mettre à jour les projections ;
- calculer des métriques de churn.

---

## Notification au membre

La notification peut confirmer :

- le `Workspace` quitté ;
- la date d’effet ;
- la fin d’accès ;
- les modalités de retour ;
- les informations de support ;
- la conservation éventuelle de données.

Elle ne doit pas exposer de détails internes inutiles.

---

## Notification aux Owners

Les owners peuvent être informés :

- du départ ;
- du dernier rôle ;
- des transferts prévus ;
- des responsabilités nécessitant encore une action ;
- de l’état du provisioning externe.

Le motif personnel du départ ne doit pas nécessairement être communiqué.

---

## Retour après départ

Le retour ne réactive pas automatiquement le `Membership`.

Il nécessite :

```text
RestoreMembership
```

souvent déclenché après :

```text
CreateInvitation
↓
SendInvitation
↓
AcceptInvitation
↓
RestoreMembership
```

La restauration utilise le même `MembershipId`.

---

## Nouvelle invitation après départ

Une nouvelle invitation peut cibler le même `User` et le même `Workspace`.

Le système doit détecter le `Membership` retiré et choisir :

```text
RestoreMembership
```

plutôt que :

```text
CreateMembership
```

---

## Délai de rétractation

Certains produits peuvent autoriser une annulation rapide du départ.

Deux modèles sont possibles.

### Modèle immédiat

Le départ est effectif immédiatement.

Un retour exige une restauration.

### Modèle différé

Le départ passe par un état intermédiaire :

```text
LeaveScheduled
```

ou :

```text
PendingRemoval
```

Le modèle actuel ne possède pas cet état.

La recommandation est donc de conserver un départ immédiat et d’utiliser `RestoreMembership` en cas de retour.

---

## Confidentialité

Le membre doit pouvoir quitter sans fournir un motif détaillé inutile.

Le domaine doit minimiser :

- les commentaires ;
- les informations personnelles ;
- les données émotionnelles ;
- les causes de départ ;
- les informations contractuelles non nécessaires.

Le motif peut être conservé sous forme structurée et facultative.

---

## Audit

Un départ réussi doit enregistrer :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- dernier `RoleId`
- `LeftAt`
- `LeftBy`
- `LeaveReason`
- `LeaveRequestId`
- `ReplacementMembershipId`
- `TransferPlanId`
- `CorrelationId`
- résultat de la confirmation
- type d’authentification récente
- résultat de `LeaveReadiness`
- version d’autorisation précédente
- nouvelle version d’autorisation
- politique appliquée aux sessions
- résultat final

---

## Questions auxquelles l’audit doit répondre

```text
which Member voluntarily left
which Workspace was left
when access ended
which Role was previously held
whether the Member was an Owner
whether the last-Owner invariant was checked
which responsibilities were transferred
which confirmation was used
whether recent authentication was required
which sessions lost access
```

---

## Rétention

Le départ met fin à l’accès.

Il ne détermine pas à lui seul la suppression des données historiques.

```text
LeaveWorkspace
    -> end Membership access
```

```text
RetentionPolicy
    -> determine data conservation
```

L’historique peut être nécessaire pour :

- l’audit ;
- la sécurité ;
- la conformité ;
- les factures ;
- les contrats ;
- les événements passés ;
- les références métier.

---

## Demande d’effacement

Le membre peut demander l’effacement de certaines données après son départ.

Ce traitement relève d’un workflow distinct.

Il doit distinguer :

- les données du `User` ;
- les données du `Membership` ;
- les données métier ;
- les données d’audit ;
- les obligations légales ;
- les données pseudonymisables ;
- les données devant être conservées.

---

## Sécurité

La commande doit garantir que :

- le membre ne peut quitter que son propre `Membership` ;
- la session est valide ;
- la confirmation est explicite ;
- une authentification récente est exigée lorsque nécessaire ;
- le dernier owner actif est protégé ;
- les responsabilités critiques sont résolues ;
- les permissions cessent immédiatement ;
- les anciennes sessions ne conservent pas le contexte ;
- les retries ne produisent aucun doublon ;
- les informations personnelles restent minimisées.

---

## Décisions de conception

### LeaveWorkspace est distinct de RemoveMembership

Les deux commandes expriment des intentions différentes malgré un même état final.

---

### L’acteur et la cible sont identiques

Condition obligatoire :

```text
Actor.UserId = Membership.UserId
```

---

### Le départ ne dépend pas d’une permission administrative

La capacité à quitter est structurelle, sous réserve des invariants et obligations applicables.

---

### Le départ exige une confirmation explicite

La perte d’accès ne doit pas résulter d’une action ambiguë ou accidentelle.

---

### Une authentification récente peut être exigée

Cette exigence est particulièrement pertinente pour les owners et rôles privilégiés.

---

### Le départ d’un membre suspendu est refusé par défaut

Un workflow administratif distinct traite ce cas afin de ne pas contourner une investigation ou une restriction.

---

### Le dernier Owner ne peut pas quitter

Le membre doit d’abord :

- promouvoir un autre owner ;
- ou fermer le `Workspace`.

---

### L’erreur commune est WorkspaceMustHaveActiveOwner

Elle exprime l’invariant partagé avec les autres commandes de gouvernance.

---

### Les responsabilités bloquantes sont résolues avant le départ

Le domaine peut utiliser une décision de `LeaveReadiness`.

---

### Le Membership est conservé

Le statut devient `Removed`.

Aucune suppression physique n’est réalisée.

---

### Le dernier Role reste historique

Il n’accorde plus aucune permission.

---

### Le retour utilise RestoreMembership

Une nouvelle appartenance n’est pas créée pour le même couple :

```text
UserId + WorkspaceId
```

---

### MembershipLeft est un événement dédié

Il exprime le départ volontaire et ne doit pas être remplacé par :

```text
MembershipRemoved
```

ou :

```text
MembershipUpdated
```

---

## Cas limites

### Le membre confirme puis devient Owner

La confirmation devient obsolète.

Une nouvelle confirmation est requise.

---

### Le membre confirme puis reçoit une responsabilité critique

La `LeaveReadiness` doit être recalculée.

---

### Le dernier autre Owner est suspendu avant le commit

Le départ doit échouer avec :

```text
WorkspaceMustHaveActiveOwner
```

---

### Une invitation Owner est en attente

Elle ne compte pas comme owner actif.

---

### Un compte de service reste Owner

La politique doit préciser s’il satisfait l’invariant.

La recommandation est d’exiger au moins un owner humain actif lorsque la gouvernance humaine est nécessaire.

---

### Le membre est le seul responsable d’une intégration externe

Le départ est bloqué ou exige un transfert.

---

### Le transfert réussit mais le départ échoue

Le workflow doit pouvoir :

- conserver le transfert ;
- l’annuler ;
- ou demander une nouvelle tentative.

Cette décision appartient au workflow orchestrateur.

---

### Le départ réussit mais le nettoyage externe échoue

Le `Membership` reste `Removed`.

Le système externe entre dans un état de retry ou d’incident.

---

### Le membre quitte son seul Workspace

Le `User` reste valide.

Il possède simplement :

```text
zero Active Membership
```

Le produit peut proposer :

- création d’un `Workspace` ;
- acceptation d’une invitation ;
- attente d’un nouvel accès ;
- suppression du compte via un workflow distinct.

---

### Le membre quitte un Workspace mais garde une Session globale

La session reste utilisable pour les autres contextes autorisés.

---

### Le membre demande immédiatement à revenir

Le retour utilise une invitation ou une restauration explicite.

Le départ initial n’est pas effacé de l’historique.

---

## Checklist de validation

Avant commit, la commande doit confirmer :

```text
Membership exists
Membership belongs to authenticated User
Membership is Active
Session is valid
LeaveRequest is idempotent
Confirmation is valid
Membership version matches confirmation
Recent authentication is valid when required
Workspace allows leave
Current Role is resolved
Last active Owner remains
LeaveReadiness is current
Critical responsibilities are resolved
Replacement is valid when required
TransferPlan is complete when required
Concurrency version is valid
Authorization invalidation can be recorded
MembershipLeft can be persisted
```

---

## Synthèse

`LeaveWorkspace` permet à un membre actif de mettre volontairement fin à sa propre appartenance.

Elle garantit que :

- l’acteur agit sur son propre `Membership` ;
- le membre est actif ;
- la session et la confirmation sont valides ;
- une authentification récente est obtenue lorsque nécessaire ;
- le dernier owner actif est protégé ;
- les responsabilités critiques sont transférées ou résolues ;
- le `Membership` devient `Removed` ;
- l’identité et l’historique sont conservés ;
- le dernier rôle reste historique mais ineffectif ;
- les permissions cessent immédiatement ;
- les sessions perdent le contexte concerné ;
- le départ est distingué d’un retrait administratif ;
- les retries et opérations concurrentes restent cohérents.

Le résultat final est :

```text
Active Membership
    ↓
Voluntary leave
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
voluntary departure explicitly recorded
```