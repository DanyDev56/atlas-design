---
id: IDN-CMD-ACCEPT-INVITATION
title: AcceptInvitation
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-30

aggregate: Invitation
workflow: InvitationAcceptance

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../invariants.md
  - ../permissions.md
  - ../workflows.md
  - ../events.md
  - CreateInvitation.md
  - DeclineInvitation.md
  - ExpireInvitation.md
  - ../commands/CreateMembership.md
  - ../commands/RestoreMembership.md
---

# AcceptInvitation

## Objectif

La commande `AcceptInvitation` permet au destinataire d’accepter une `Invitation` valide et de rejoindre le `Workspace` concerné.

L’acceptation produit ou réactive un `Membership` associé :

- au `User` destinataire ;
- au `Workspace` ciblé ;
- au `Role` prévu par l’`Invitation`.

La commande ne doit jamais accorder un accès à une autre personne, à un autre `Workspace` ou avec un autre `Role`.

---

## Agrégat concerné

La commande cible l’agrégat :

```text
Invitation
```

Cependant, le résultat métier complet implique également l’agrégat :

```text
Membership
```

La coordination entre ces agrégats relève du workflow :

```text
InvitationAcceptance
```

---

## Nature de l’opération

`AcceptInvitation` constitue une opération transversale.

Elle doit garantir la cohérence suivante :

```text
Invitation.Status = Accepted
AND
exactly one compatible Membership exists
```

Les états partiels suivants sont interdits :

```text
Invitation.Status = Accepted
AND
no Membership exists
```

```text
Invitation.Status = Pending
AND
a Membership was created from the Invitation
```

```text
Invitation.Status = Accepted
AND
multiple Memberships exist
```

---

## Acteur

La commande est demandée par le destinataire de l’`Invitation`.

L’acteur peut être :

- un `User` déjà authentifié ;
- une personne créant son compte pendant le parcours d’acceptation ;
- un `User` récemment authentifié après vérification de son adresse e-mail.

L’acteur administratif ayant créé l’`Invitation` ne peut pas l’accepter au nom du destinataire.

---

## Permission requise

Le destinataire n’a pas besoin de posséder une `Permission` préalable dans le `Workspace`.

L’`Invitation` valide constitue l’autorisation temporaire permettant de créer son premier `Membership`.

Cette autorisation est strictement limitée à :

- l’identité du destinataire ;
- l’`Invitation` concernée ;
- son `Workspace` ;
- son `Role` prévu ;
- sa durée de validité.

---

## Préconditions

Avant l’exécution de `AcceptInvitation`, les conditions suivantes doivent être satisfaites :

- l’`Invitation` existe ;
- son état est `Pending` ;
- elle n’est pas expirée ;
- elle n’est pas révoquée ;
- elle n’a pas été refusée ;
- elle n’a pas déjà été acceptée, sauf reprise idempotente ;
- le token présenté est valide ;
- le token correspond à cette `Invitation` ;
- l’identité du destinataire est vérifiée ;
- l’adresse du `User` correspond à l’adresse ciblée ;
- le `Workspace` existe et accepte encore l’opération ;
- le `Role` existe ;
- le `Role` est actif ;
- le `Role` appartient au `Workspace` ciblé ;
- le `Role` reste attribuable ;
- aucun `Membership` actif ou suspendu incompatible n’existe ;
- l’opération ne viole aucun invariant d’unicité.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationId` | `InvitationId` | Oui | Identifie l’`Invitation` à accepter. |
| `InvitationToken` | `InvitationToken` | Oui | Prouve le droit d’utiliser l’`Invitation`. |
| `AcceptedBy` | `UserId` | Oui | Identifie le `User` qui accepte. |
| `AcceptedAt` | Instant | Oui | Instant de référence de l’acceptation. |
| `AcceptanceRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `CorrelationId` | Identifiant | Non | Relie l’opération au parcours applicatif. |
| `AuthenticationContext` | `AuthenticationContext` | Non | Contexte de sécurité de l’acceptation. |
| `TermsAcceptance` | Données structurées | Non | Preuve d’acceptation de conditions lorsque requise. |

---

## Validation des données

### InvitationId

L’`InvitationId` doit identifier une `Invitation` existante.

Il ne constitue pas une preuve suffisante d’autorisation.

---

### InvitationToken

L’`InvitationToken` doit :

- être présent ;
- posséder un format valide ;
- correspondre au secret actif de l’`Invitation` ;
- ne pas avoir été invalidé par une rotation ;
- être comparé de manière sécurisée ;
- ne jamais être journalisé.

---

### AcceptedBy

Le `UserId` doit identifier un `User` :

- existant ;
- actif ;
- dont l’identité a été suffisamment vérifiée ;
- dont l’`EmailAddress` correspond au destinataire.

La condition suivante doit être vraie :

```text
normalize(User.EmailAddress)
=
normalize(Invitation.RecipientEmail)
```

---

### AcceptedAt

`AcceptedAt` doit être utilisé comme référence pour :

- l’évaluation de l’expiration ;
- l’ordre des événements ;
- la création ou la restauration du `Membership`.

La condition suivante doit être vraie :

```text
AcceptedAt < Invitation.ExpirationDate
```

---

### AcceptanceRequestId

L’`AcceptanceRequestId` doit :

- identifier une intention logique unique ;
- être stable lors des retries techniques ;
- permettre de restituer un résultat déjà obtenu ;
- ne contenir aucune donnée sensible.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Charger l’Invitation

Le workflow charge l’`Invitation` correspondant à l’`InvitationId`.

Si elle n’existe pas, l’opération échoue.

---

### 2. Vérifier le token

Le token fourni est comparé à la représentation sécurisée associée à l’`Invitation`.

La comparaison doit empêcher :

- les comparaisons temporelles exploitables ;
- l’utilisation d’un token précédent ;
- l’utilisation du token d’une autre `Invitation` ;
- l’exposition du secret dans les journaux.

---

### 3. Vérifier l’état

L’`Invitation` doit être dans l’état :

```text
Pending
```

Les états suivants interdisent une nouvelle acceptation :

```text
Declined
Expired
Revoked
```

L’état `Accepted` doit déclencher le traitement idempotent décrit plus loin.

---

### 4. Vérifier l’expiration

L’`Invitation` est inutilisable lorsque :

```text
AcceptedAt >= Invitation.ExpirationDate
```

Cette règle s’applique même si une tâche différée n’a pas encore enregistré l’état `Expired`.

---

### 5. Vérifier l’identité du destinataire

Le workflow vérifie que le `User` qui accepte contrôle l’adresse visée par l’`Invitation`.

Il est interdit :

- d’accepter avec un compte utilisant une autre adresse ;
- de transférer implicitement l’invitation ;
- de modifier l’adresse de destination pendant l’acceptation ;
- de laisser l’invitant choisir le compte bénéficiaire.

---

### 6. Vérifier le User

Le `User` doit être actif.

Un `User` désactivé ne peut pas créer ou restaurer un accès.

Lorsque le compte est créé pendant le parcours, sa création et la vérification de son identité doivent être terminées avant l’octroi du `Membership`, selon la politique de sécurité retenue.

---

### 7. Vérifier le Workspace

Le workflow demande au domaine propriétaire de confirmer que le `Workspace` :

- existe ;
- n’est pas supprimé ;
- accepte encore de nouveaux membres ;
- autorise l’acceptation de cette invitation.

L’`Invitation` ne peut pas contourner une fermeture ou une restriction ultérieure du `Workspace`.

---

### 8. Vérifier le Role

Le `Role` prévu doit :

- exister ;
- être actif ;
- appartenir au même `Workspace` ;
- rester attribuable ;
- respecter les éventuelles restrictions de propriété.

La condition suivante doit être vraie :

```text
Invitation.WorkspaceId = Role.WorkspaceId
```

Le workflow ne peut pas choisir silencieusement un autre `Role`.

---

### 9. Rechercher un Membership existant

Le workflow recherche un `Membership` correspondant à :

```text
UserId + WorkspaceId
```

Plusieurs situations sont possibles.

#### Aucun Membership

Un nouveau `Membership` doit être créé.

#### Membership actif

L’acceptation ne doit pas créer de doublon.

L’opération peut :

- être considérée comme déjà satisfaite lorsqu’il provient de cette même invitation ;
- être refusée lorsqu’il existe indépendamment de l’invitation.

#### Membership suspendu

L’acceptation ne doit pas lever silencieusement une suspension.

La commande est refusée, sauf workflow explicitement prévu.

#### Membership supprimé

Le produit doit appliquer une politique explicite :

- restaurer le `Membership` existant ;
- refuser l’acceptation ;
- exiger une validation administrative ;
- créer un nouveau cycle d’appartenance uniquement si le modèle l’autorise.

La politique recommandée est de restaurer l’entité existante lorsque cette restauration est autorisée et traçable.

---

### 10. Créer ou restaurer le Membership

Lorsque les conditions sont satisfaites, le workflow exécute l’une des intentions suivantes :

```text
CreateMembership
```

ou :

```text
RestoreMembership
```

Le `Membership` doit utiliser exactement :

```text
UserId = AcceptedBy
WorkspaceId = Invitation.WorkspaceId
RoleId = Invitation.RoleId
```

---

### 11. Marquer l’Invitation comme acceptée

Après confirmation de la création ou de la restauration du `Membership`, l’`Invitation` passe à l’état :

```text
Accepted
```

L’agrégat enregistre notamment :

- `AcceptedBy` ;
- `AcceptedAt` ;
- `MembershipId` résultant ;
- `AcceptanceRequestId`.

---

### 12. Invalider le token

Après acceptation réussie, l’`InvitationToken` devient définitivement inutilisable.

Aucune nouvelle opération sensible ne peut être autorisée avec ce token.

---

### 13. Produire les événements

Le processus produit selon le cas :

```text
MembershipCreated
InvitationAccepted
```

ou :

```text
MembershipRestored
InvitationAccepted
```

L’ordre logique recommandé est :

```text
MembershipCreated or MembershipRestored

↓

InvitationAccepted
```

Cet ordre exprime que l’acceptation est finalisée lorsque l’appartenance existe effectivement.

---

## Résultat attendu

Après une exécution réussie :

- l’`Invitation` est `Accepted` ;
- un seul `Membership` compatible existe ;
- le `Membership` est associé au destinataire ;
- le `Membership` appartient au bon `Workspace` ;
- le `Membership` possède le `Role` prévu ;
- le token est invalidé ;
- l’opération peut être rejouée sans créer de doublon.

État conceptuel :

```text
Invitation
├── Status: Accepted
├── AcceptedBy: UserId
├── AcceptedAt
├── MembershipId
└── Token: unusable
```

```text
Membership
├── UserId: Invitation recipient
├── WorkspaceId: Invitation Workspace
├── RoleId: Invitation Role
└── Status: Active
```

---

## Invariants concernés

### `IDN-INV-001`

Un `User` possède au maximum un `Membership` par `Workspace`.

---

### `IDN-INV-002`

Le `Membership` doit référencer un `User`, un `Workspace` et un `Role` compatibles.

---

### `IDN-INV-003`

Le `Membership` possède exactement un `Role` actif.

---

### `IDN-INV-005`

Le `Role`, le `Membership` et l’`Invitation` appartiennent au même `Workspace`.

---

### `IDN-INV-007`

Une `Invitation` ne produit qu’un seul `Membership`.

---

### `IDN-INV-008`

Seul le destinataire légitime peut accepter l’`Invitation`.

---

### `IDN-INV-009`

Une `Invitation` terminée ne peut plus être réutilisée.

---

### `IDN-INV-012`

Le token reste distinct de l’identifiant et devient inutilisable après acceptation.

---

### `IDN-INV-015`

Le `Workspace` doit toujours permettre l’opération.

---

## Événements produits

### MembershipCreated

Produit lorsqu’aucun `Membership` n’existait.

Il peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `RoleId`
- `CreatedAt`
- `Source`
- `InvitationId`
- `CorrelationId`

La source recommandée est :

```text
Invitation
```

---

### MembershipRestored

Produit lorsqu’un `Membership` supprimé est restauré.

Il peut contenir :

- `MembershipId`
- `UserId`
- `WorkspaceId`
- `RoleId`
- `RestoredAt`
- `RestorationSource`
- `InvitationId`
- `CorrelationId`

---

### InvitationAccepted

Produit lorsque le processus complet est finalisé.

Il peut contenir :

- `InvitationId`
- `WorkspaceId`
- `RoleId`
- `AcceptedBy`
- `AcceptedAt`
- `MembershipId`
- `AcceptanceRequestId`
- `CorrelationId`

Il ne doit contenir ni le token brut, ni le lien d’acceptation.

---

## Événements non produits

La commande ne produit pas :

```text
InvitationDeclined
InvitationExpired
InvitationRevoked
InvitationSent
```

Elle ne produit pas non plus un second `MembershipCreated` lors d’une reprise idempotente.

---

## Erreurs métier

### InvitationNotFound

L’`Invitation` n’existe pas.

---

### InvalidInvitationToken

Le token est absent, invalide ou ne correspond pas à cette `Invitation`.

La réponse publique ne doit pas nécessairement distinguer les différentes causes techniques.

---

### InvitationTokenSuperseded

Le token présenté a été remplacé par une rotation.

Cette erreur peut être ramenée publiquement à `InvalidInvitationToken`.

---

### InvitationExpired

L’échéance est atteinte ou dépassée.

---

### InvitationDeclined

L’`Invitation` a déjà été refusée.

---

### InvitationRevoked

L’`Invitation` a été révoquée.

---

### InvitationAlreadyAccepted

L’`Invitation` a déjà été acceptée.

Cette erreur peut ne pas être retournée lorsque la requête correspond à une reprise idempotente du même résultat.

---

### InvitationRecipientMismatch

Le `User` qui accepte ne correspond pas à l’adresse ciblée.

---

### UserNotFound

Le `User` demandé n’existe pas.

---

### UserDisabled

Le `User` ne peut pas recevoir de nouvel accès.

---

### UserEmailNotVerified

L’adresse du `User` n’a pas atteint le niveau de vérification requis.

---

### WorkspaceNotFound

Le `Workspace` n’existe plus.

---

### WorkspaceUnavailable

Le `Workspace` refuse désormais l’opération.

---

### RoleNotFound

Le `Role` prévu n’existe plus.

---

### RoleDisabled

Le `Role` n’est plus actif.

---

### RoleBelongsToAnotherWorkspace

Le `Role` ne correspond pas au `Workspace`.

---

### RoleNotAssignable

Le `Role` ne peut plus être attribué dans ce contexte.

---

### MembershipAlreadyExists

Un `Membership` incompatible existe déjà.

---

### MembershipSuspended

Le destinataire possède un `Membership` suspendu qui ne peut pas être réactivé par cette invitation.

---

### MembershipCannotBeRestored

Un `Membership` supprimé existe mais la politique interdit sa restauration.

---

### AcceptanceConflict

Une opération concurrente a modifié l’`Invitation` ou le `Membership`.

Le système doit ensuite rechercher un éventuel résultat idempotent avant de renvoyer cette erreur.

---

## Idempotence

`AcceptInvitation` doit être idempotente pour :

```text
InvitationId + AcceptanceRequestId
```

Une répétition de la même demande doit retourner le résultat initial sans :

- créer un second `Membership` ;
- restaurer plusieurs fois le même `Membership` ;
- produire un second effet métier ;
- régénérer le `Role` ;
- modifier la date d’acceptation initiale.

---

## Reprise après réponse perdue

Cas typique :

```text
AcceptInvitation succeeds

↓

Membership created

↓

Invitation accepted

↓

HTTP response is lost

↓

Client retries
```

La seconde requête doit retrouver :

- l’`Invitation` déjà acceptée ;
- le `MembershipId` résultant ;
- le même `AcceptanceRequestId`.

Elle retourne alors le succès initial.

---

## Acceptation déjà réalisée par une autre demande

Lorsque l’`Invitation` est déjà `Accepted` avec un autre `AcceptanceRequestId`, le système doit vérifier l’identité du résultat.

Si elle a produit le même `Membership` pour le même `User`, la réponse peut indiquer que l’invitation est déjà acceptée.

Elle ne doit jamais créer un autre `Membership`.

---

## Concurrence

Deux tentatives concurrentes d’acceptation doivent être protégées.

### Même User et même Invitation

Une seule acceptation logique est autorisée.

Le résultat doit être :

```text
one Accepted Invitation
one Membership
```

---

### Users différents

Une seule tentative peut correspondre au destinataire.

Toute tentative par une autre identité doit être refusée.

---

### AcceptInvitation et RevokeInvitation

Si les deux opérations sont concurrentes, une seule transition terminale doit gagner.

Résultats possibles :

```text
Accepted
```

ou :

```text
Revoked
```

Jamais les deux.

---

### AcceptInvitation et ExpireInvitation

L’instant métier détermine le résultat.

Si :

```text
AcceptedAt < ExpirationDate
```

et que l’acceptation remplit toutes les conditions, elle peut réussir.

Si :

```text
AcceptedAt >= ExpirationDate
```

elle doit échouer, même si la tâche d’expiration n’a pas encore persisté l’état.

---

## Atomicité

L'acceptation implique deux agrégats distincts mais constitue une seule décision
atomique. Identity 1.0 exige une visibilité tout-ou-rien du `Membership` actif et
de l'`Invitation` acceptée.

---

## Stratégie transactionnelle

Le traitement canonique est :

1. verrouiller ou versionner l’`Invitation` ;
2. vérifier son état et son token ;
3. vérifier l’unicité du `Membership` ;
4. créer ou restaurer le `Membership` ;
5. marquer l’`Invitation` comme acceptée ;
6. enregistrer les événements ;
7. valider la transaction.

La contrainte d’unicité suivante doit compléter la logique métier :

```text
UNIQUE(UserId, WorkspaceId)
```

---

Une implémentation distribuée doit fournir une garantie d'atomicité observable
équivalente. Elle ne publie aucun événement intermédiaire public et ne rend
jamais visible un seul des deux états finaux.

---

## Création du User pendant l’acceptation

Le parcours peut permettre à une personne sans compte de rejoindre Atlas.

Le workflow complet devient alors :

```text
Validate Invitation

↓

Create User

↓

Verify Email or Identity

↓

Create Membership

↓

Accept Invitation
```

La création du `User` ne doit pas être intégrée directement dans l’agrégat `Invitation`.

Elle relève d’un workflow d’onboarding plus large.

---

## Politique pour un Membership supprimé

Trois stratégies sont possibles.

### Refus strict

L’existence d’un ancien `Membership` supprimé empêche l’acceptation.

Une intervention administrative est requise.

---

### Restauration

Le `Membership` existant est restauré avec le `Role` prévu par l’`Invitation`.

Cette stratégie conserve l’identité et l’historique de la relation.

---

### Nouveau Membership

Une nouvelle entité est créée avec un nouveau `MembershipId`.

Cette approche complexifie l’unicité et l’historique.

Elle n’est recommandée que si le domaine distingue explicitement plusieurs cycles d’appartenance.

---

## Politique recommandée

La restauration du `Membership` existant est recommandée lorsque :

- son historique doit être préservé ;
- aucune restriction administrative ne l’interdit ;
- la suppression était fonctionnelle et non une interdiction permanente ;
- la nouvelle invitation constitue une autorisation explicite de retour.

L’événement produit doit rester :

```text
MembershipRestored
```

et non `MembershipCreated`.

---

## Attribution du Role

Le `Role` attribué doit être celui enregistré sur l’`Invitation`.

Il est interdit de :

- utiliser le rôle par défaut à la place ;
- attribuer un rôle de moindre privilège sans information explicite ;
- augmenter les privilèges pendant l’acceptation ;
- reprendre automatiquement l’ancien rôle d’un `Membership` supprimé.

Si le `Role` prévu n’est plus disponible, l’acceptation échoue.

Une nouvelle `Invitation` doit être créée avec un rôle valide.

---

## Sécurité

La commande doit garantir que :

- le token n’est jamais journalisé ;
- le token est vérifié par comparaison sécurisée ;
- l’identité du destinataire est contrôlée ;
- les tokens remplacés sont refusés ;
- le token est invalidé après acceptation ;
- l’acceptation est protégée contre les attaques par rejeu ;
- les erreurs publiques ne facilitent pas l’énumération des comptes ;
- aucune redirection non approuvée n’est utilisée après l’acceptation ;
- les limitations de fréquence protègent la validation des tokens.

---

## Protection contre les attaques par rejeu

Une nouvelle utilisation du même token après succès doit être sans effet.

Le système doit retrouver l’acceptation existante ou retourner un état terminal.

Le rejeu ne doit jamais :

- créer un autre `Membership` ;
- associer un autre `User` ;
- changer le `Role` ;
- prolonger la session du demandeur ;
- produire un nouvel événement fonctionnel.

---

## Confidentialité

Avant validation complète du token, la réponse ne doit pas révéler inutilement :

- l’existence du `Workspace` ;
- son nom ;
- le `Role` prévu ;
- l’identité de l’invitant ;
- l’existence d’un compte pour l’adresse ciblée.

Les informations nécessaires à l’écran d’acceptation peuvent être exposées uniquement après une validation suffisante du lien.

---

## Audit

Une acceptation réussie doit enregistrer :

- `InvitationId`
- `WorkspaceId`
- `RoleId`
- `UserId`
- `MembershipId`
- `AcceptedAt`
- `AcceptanceRequestId`
- contexte de sécurité pertinent
- origine du parcours
- résultat de l’opération

L’audit ne doit jamais contenir le token brut.

Une tentative refusée peut enregistrer :

- l’`InvitationId`, lorsque connu ;
- le type générique d’échec ;
- l’instant ;
- des éléments de sécurité minimisés ;
- l’identifiant de corrélation.

---

## Décisions de conception

### L’acceptation est coordonnée par un workflow

L’agrégat `Invitation` ne crée pas directement un `Membership`.

Le workflow coordonne les deux agrégats et préserve leurs frontières transactionnelles.

---

### L’Invitation constitue une autorisation temporaire ciblée

Elle n’accorde pas une permission générale.

Elle autorise uniquement la création ou la restauration d’une appartenance déterminée.

---

### Le Membership doit exister avant la finalisation

L’`Invitation` ne doit pas devenir `Accepted` sans preuve fiable que le `Membership` correspondant existe.

---

### Le destinataire est immuable

L’acceptation ne permet aucune correction ou substitution de l’adresse.

Une erreur de destinataire exige une révocation puis une nouvelle invitation.

---

### Le Role ne change pas pendant l’acceptation

L’acceptation applique exactement l’intention enregistrée lors de la création.

Cette règle garantit l’explicabilité et évite les changements silencieux d’autorisation.

---

### Le token est à usage terminal

Après acceptation, le token ne peut plus autoriser d’autre opération.

Son invalidation fait partie du succès métier.

---

### L’idempotence fait partie du modèle

L’idempotence n’est pas seulement une optimisation technique.

Elle protège l’invariant fondamental :

```text
one Invitation
produces at most one Membership
```

---

## Synthèse

`AcceptInvitation` transforme une autorisation temporaire en appartenance effective à un `Workspace`.

Elle garantit que :

- l’`Invitation` est valide et encore utilisable ;
- le token présenté est correct ;
- le bénéficiaire est le destinataire légitime ;
- le `Workspace` et le `Role` restent compatibles ;
- un seul `Membership` est créé ou restauré ;
- l’`Invitation` devient `Accepted` uniquement après succès ;
- le token devient inutilisable ;
- les retries et accès concurrents ne produisent aucun doublon.

Le résultat final doit toujours respecter :

```text
Accepted Invitation
+
exactly one compatible Membership
```
