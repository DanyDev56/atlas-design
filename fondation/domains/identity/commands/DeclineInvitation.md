---
id: IDN-CMD-DECLINE-INVITATION
title: DeclineInvitation
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-07-30

aggregate: Invitation

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../invariants.md
  - ../events.md
  - AcceptInvitation.md
  - RevokeInvitation.md
  - ExpireInvitation.md
---

# DeclineInvitation

## Objectif

La commande `DeclineInvitation` permet au destinataire de refuser explicitement une `Invitation`.

Elle met fin au cycle de vie de l’`Invitation` sans créer de `Membership`.

Après son refus, l’`Invitation` ne peut plus être :

- acceptée ;
- renvoyée ;
- réactivée ;
- utilisée pour rejoindre le `Workspace`.

---

## Agrégat concerné

`Invitation`

L’`Invitation` constitue la racine de l’agrégat modifié.

Aucun autre agrégat n’est modifié par cette commande.

---

## Acteur

La commande est demandée par le destinataire de l’`Invitation`.

L’acteur peut être :

- un `User` authentifié correspondant au destinataire ;
- une personne non encore authentifiée présentant un `InvitationToken` valide ;
- un `User` authentifié après avoir suivi le lien d’invitation.

L’invitant ou un administrateur ne doit pas utiliser cette commande pour annuler une invitation.

L’annulation administrative relève de :

```text
RevokeInvitation
```

---

## Permission requise

Aucune `Permission` préalable dans le `Workspace` n’est requise.

Le droit de refuser découle de la capacité à prouver que l’acteur est le destinataire légitime de l’`Invitation`.

Cette preuve repose sur :

- un `InvitationToken` valide ;
- une identité authentifiée correspondant à l’adresse ciblée ;
- ou une combinaison de ces mécanismes selon la politique de sécurité.

---

## Préconditions

Avant l’exécution de `DeclineInvitation`, les conditions suivantes doivent être satisfaites :

- l’`Invitation` existe ;
- son état est `Pending` ;
- elle n’a pas été acceptée ;
- elle n’a pas déjà été refusée, sauf reprise idempotente ;
- elle n’a pas été révoquée ;
- elle n’est pas expirée ;
- le token présenté est valide lorsque requis ;
- l’acteur correspond au destinataire ;
- aucun processus d’acceptation finalisé n’a créé de `Membership`.

Une invitation temporellement expirée doit être traitée comme `Expired`, même si cet état n’a pas encore été persisté.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationId` | `InvitationId` | Oui | Identifie l’`Invitation` à refuser. |
| `DeclinedAt` | Instant | Oui | Instant de référence du refus. |
| `DeclineRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |

Selon le parcours de sécurité :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationToken` | `InvitationToken` | Conditionnel | Prouve le droit d’utiliser l’`Invitation`. |
| `DeclinedBy` | `UserId` | Conditionnel | Identifie le `User` qui refuse. |
| `AuthenticationContext` | `AuthenticationContext` | Non | Décrit le contexte de sécurité. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `Reason` | `InvitationDeclineReason` | Non | Motif structuré du refus. |
| `Comment` | Texte court | Non | Commentaire facultatif du destinataire. |
| `CorrelationId` | Identifiant | Non | Référence du parcours appelant. |

---

## InvitationDeclineReason

Le motif peut prendre des valeurs telles que :

```text
NotInterested
UnexpectedInvitation
WrongWorkspace
WrongRole
AlreadyMember
SecurityConcern
Other
```

Le motif est facultatif.

Il sert principalement à :

- améliorer l’expérience produit ;
- détecter les invitations erronées ;
- fournir un retour à l’invitant ;
- identifier d’éventuels abus.

Il ne modifie pas le résultat métier principal.

---

## Validation des données

### InvitationId

L’`InvitationId` doit identifier une `Invitation` existante.

Il ne constitue pas à lui seul une preuve d’autorisation.

---

### InvitationToken

Lorsqu’il est requis, le token doit :

- être présent ;
- posséder un format valide ;
- correspondre au secret actif de l’`Invitation` ;
- ne pas avoir été remplacé ;
- être comparé de manière sécurisée ;
- ne jamais être journalisé.

---

### DeclinedBy

Lorsque le refus est associé à un `User`, celui-ci doit correspondre au destinataire.

La condition suivante doit être vraie :

```text
normalize(User.EmailAddress)
=
normalize(Invitation.RecipientEmail)
```

---

### DeclinedAt

`DeclinedAt` doit être antérieur à l’échéance.

```text
DeclinedAt < Invitation.ExpirationDate
```

Lorsque cette condition est fausse, l’`Invitation` doit être considérée comme expirée.

---

### DeclineRequestId

Le `DeclineRequestId` doit :

- identifier une seule intention logique ;
- rester stable pendant les retries techniques ;
- permettre de retrouver un résultat déjà obtenu ;
- ne contenir aucune donnée personnelle ou sensible.

---

### Reason

Le motif doit appartenir au catalogue prévu lorsqu’un type structuré est utilisé.

Une valeur libre ne doit pas être utilisée pour piloter automatiquement des décisions d’autorisation.

---

### Comment

Lorsqu’il est autorisé, le commentaire doit :

- respecter une longueur maximale ;
- être traité comme une donnée utilisateur non fiable ;
- être protégé contre les injections ;
- ne contenir aucun secret ;
- respecter la politique de conservation.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Charger l’Invitation

Le système charge l’`Invitation` identifiée par `InvitationId`.

Si elle n’existe pas, la commande échoue.

---

### 2. Vérifier la preuve d’accès

Le système vérifie que le demandeur est autorisé à agir sur l’`Invitation`.

Selon le parcours, cela implique :

- la validation du `InvitationToken` ;
- la vérification du `User` authentifié ;
- la correspondance de son adresse e-mail ;
- ou plusieurs de ces contrôles.

---

### 3. Vérifier l’état

L’`Invitation` doit être dans l’état :

```text
Pending
```

Les états suivants interdisent un nouveau refus :

```text
Accepted
Expired
Revoked
```

L’état `Declined` déclenche le traitement idempotent.

---

### 4. Vérifier l’expiration

Le refus ne peut être enregistré comme transition principale lorsque :

```text
DeclinedAt >= Invitation.ExpirationDate
```

Dans ce cas, l’état métier correct est :

```text
Expired
```

Le domaine ne doit pas dépendre de l’exécution préalable d’un scheduler.

---

### 5. Vérifier l’identité du destinataire

Le système vérifie que l’acteur correspond à la personne invitée.

Il est interdit :

- de refuser l’invitation d’un autre destinataire ;
- d’agir avec un token appartenant à une autre invitation ;
- de remplacer l’adresse ciblée ;
- de transférer implicitement le droit de refus.

---

### 6. Vérifier l’absence d’acceptation finalisée

Le système vérifie que l’`Invitation` n’a pas déjà produit un `Membership`.

Une acceptation finalisée ne peut pas être annulée par `DeclineInvitation`.

Lorsque le destinataire souhaite quitter le `Workspace` après acceptation, une commande portant sur `Membership` doit être utilisée.

---

### 7. Enregistrer le refus

L’agrégat passe de :

```text
Pending
```

à :

```text
Declined
```

Il enregistre notamment :

- `DeclinedAt` ;
- `DeclinedBy`, lorsque connu ;
- `Reason`, lorsque fourni ;
- `DeclineRequestId`.

---

### 8. Invalider le token

Après le refus, l’`InvitationToken` devient définitivement inutilisable.

Il ne peut plus autoriser :

- une acceptation ;
- un nouveau refus ;
- un renvoi ;
- une autre opération sensible.

---

### 9. Produire l’événement

L’agrégat produit :

```text
InvitationDeclined
```

Les notifications éventuelles à l’invitant relèvent d’un handler ou d’un workflow séparé.

---

## Résultat attendu

Après une exécution réussie :

- l’`Invitation` est `Declined` ;
- le refus est daté ;
- l’identité du destinataire est enregistrée lorsque disponible ;
- le token est invalidé ;
- aucun `Membership` n’est créé ;
- aucun accès au `Workspace` n’est accordé ;
- l’opération peut être rejouée sans effet supplémentaire.

État conceptuel :

```text
Invitation
├── Status: Declined
├── DeclinedAt
├── DeclinedBy
├── DeclineReason
├── DeclineRequestId
└── Token: unusable
```

---

## Invariants concernés

### `IDN-INV-007`

L’`Invitation` ne peut produire qu’un seul résultat d’acceptation.

Un refus ne crée aucun `Membership`.

---

### `IDN-INV-008`

Seul le destinataire légitime peut refuser l’`Invitation`.

---

### `IDN-INV-009`

`Declined` est un état terminal.

L’`Invitation` ne peut pas revenir à `Pending`.

---

### `IDN-INV-012`

Le token reste distinct de l’identifiant et devient inutilisable après le refus.

---

## Transition d’état

Transition autorisée :

```text
Pending
    ↓
Declined
```

Transitions interdites :

```text
Accepted
    ↓
Declined
```

```text
Expired
    ↓
Declined
```

```text
Revoked
    ↓
Declined
```

```text
Declined
    ↓
Pending
```

---

## Événement produit

### InvitationDeclined

La commande produit :

```text
InvitationDeclined
```

L’événement peut contenir :

- `InvitationId`
- `WorkspaceId`
- `RecipientEmailFingerprint`, uniquement si nécessaire
- `DeclinedBy`
- `DeclinedAt`
- `Reason`
- `DeclineRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- l’`InvitationToken` ;
- le lien complet ;
- un secret de session ;
- des informations non nécessaires sur le `Workspace`.

---

## Événements non produits

Cette commande ne produit pas :

```text
MembershipCreated
MembershipRemoved
InvitationAccepted
InvitationRevoked
InvitationExpired
```

Elle ne supprime pas l’`Invitation`.

Elle enregistre un état terminal auditable.

---

## Erreurs métier

### InvitationNotFound

L’`Invitation` demandée n’existe pas.

---

### InvalidInvitationToken

Le token est absent, incorrect ou ne correspond pas à l’`Invitation`.

Les différentes causes peuvent être regroupées dans la réponse publique.

---

### InvitationTokenSuperseded

Le token présenté a été remplacé.

Cette erreur peut être exposée publiquement comme `InvalidInvitationToken`.

---

### InvitationAlreadyAccepted

L’`Invitation` a déjà été acceptée.

Elle ne peut plus être refusée.

---

### InvitationAlreadyDeclined

L’`Invitation` a déjà été refusée.

Cette erreur ne doit pas être retournée lorsque la demande correspond à une reprise idempotente du même résultat.

---

### InvitationExpired

L’échéance est atteinte ou dépassée.

---

### InvitationRevoked

L’`Invitation` a été révoquée par un acteur autorisé.

---

### InvitationRecipientMismatch

L’acteur authentifié ne correspond pas au destinataire.

---

### MembershipAlreadyCreated

Un `Membership` a déjà été créé à partir de l’`Invitation`.

Cette situation indique normalement une acceptation finalisée ou un état incohérent à investiguer.

---

### DeclineConflict

Une autre transition terminale a gagné pendant l’exécution concurrente.

---

### InvalidDeclineReason

Le motif fourni n’appartient pas au catalogue attendu.

---

## Idempotence

`DeclineInvitation` doit être idempotente pour :

```text
InvitationId + DeclineRequestId
```

La répétition de la même demande doit retourner le résultat initial sans :

- modifier `DeclinedAt` ;
- enregistrer un autre motif ;
- produire un nouvel événement métier ;
- invalider une seconde fois le token ;
- créer un effet secondaire supplémentaire.

---

## Reprise après réponse perdue

Cas typique :

```text
DeclineInvitation succeeds

↓

Invitation becomes Declined

↓

Response is lost

↓

Client retries
```

La seconde exécution doit retrouver :

- l’état `Declined` ;
- le même `DeclineRequestId` ;
- la date de refus initiale.

Elle retourne alors le succès initial.

---

## Refus déjà réalisé par une autre demande

Lorsque l’`Invitation` est déjà `Declined` avec un autre `DeclineRequestId`, le système doit distinguer :

- une répétition logique ;
- une nouvelle tentative sur un état terminal.

Le résultat public peut simplement indiquer que l’`Invitation` n’est plus active.

Aucun nouvel effet métier ne doit être produit.

---

## Concurrence

### DeclineInvitation contre AcceptInvitation

Une seule transition terminale doit réussir.

Résultats possibles :

```text
Accepted
```

ou :

```text
Declined
```

Jamais les deux.

La stratégie peut utiliser :

- un verrou transactionnel ;
- un contrôle de version optimiste ;
- une mise à jour conditionnelle sur `Status = Pending` ;
- une contrainte de transition.

---

### DeclineInvitation contre RevokeInvitation

Une seule transition terminale peut être persistée.

Le résultat dépend de l’opération validée en premier.

```text
Declined
```

ou :

```text
Revoked
```

---

### DeclineInvitation contre ExpireInvitation

L’instant métier détermine le résultat.

Lorsque :

```text
DeclinedAt < ExpirationDate
```

le refus peut réussir si l’état est encore `Pending`.

Lorsque :

```text
DeclinedAt >= ExpirationDate
```

l’Invitation doit être `Expired`.

---

### Deux refus concurrents

Deux appels correspondant à la même intention doivent être dédupliqués.

Deux appels distincts ne doivent malgré tout produire qu’une seule transition :

```text
Pending -> Declined
```

---

## Atomicité

La commande ne modifie qu’un agrégat.

La transition suivante doit être atomique :

```text
verify Pending
+
record Declined
+
invalidate token
+
record event
```

L’état suivant est interdit :

```text
Status = Declined
AND
token remains usable
```

---

## Token

Le token doit être invalidé dans la même unité atomique que la transition vers `Declined`.

Selon l’implémentation, cela peut consister à :

- supprimer la représentation sécurisée ;
- marquer le secret comme consommé ;
- modifier une version de token ;
- enregistrer un instant d’invalidation.

Le refus ne doit jamais générer un nouveau token.

---

## Relation avec Membership

`DeclineInvitation` n’agit pas sur `Membership`.

Elle ne doit pas :

- suspendre un `Membership` existant ;
- supprimer un `Membership` ;
- restaurer un `Membership` ;
- quitter un `Workspace`.

Après acceptation, le refus de l’invitation n’est plus une action pertinente.

Le départ du `Workspace` doit être exprimé par une commande dédiée au cycle de vie du `Membership`.

---

## Relation avec RevokeInvitation

Les deux commandes conduisent à un état terminal, mais expriment des intentions différentes.

### DeclineInvitation

```text
Actor: Recipient
Meaning: I do not accept this invitation
```

### RevokeInvitation

```text
Actor: Inviter or authorized administrator
Meaning: This invitation is no longer authorized
```

Ces intentions doivent rester distinctes pour :

- l’audit ;
- les notifications ;
- l’analyse produit ;
- la compréhension du cycle de vie.

---

## Relation avec ExpireInvitation

`DeclineInvitation` résulte d’une décision explicite du destinataire.

`ExpireInvitation` résulte de l’écoulement du temps.

```text
Declined
    -> explicit recipient decision

Expired
    -> temporal invalidation
```

La distinction doit être conservée même si les deux états interdisent toute acceptation future.

---

## Notifications

Après `InvitationDeclined`, un handler peut :

- informer l’invitant ;
- mettre à jour une projection d’administration ;
- arrêter les relances programmées ;
- enregistrer une métrique ;
- détecter un motif de sécurité.

L’envoi de notification ne fait pas partie de la transaction métier principale.

Un échec de notification ne doit pas annuler le refus.

---

## Visibilité du motif

Le produit doit définir si le motif ou le commentaire est visible par :

- l’invitant ;
- les administrateurs du `Workspace` ;
- les équipes de support ;
- uniquement le destinataire ;
- personne en dehors de l’audit.

Le commentaire libre ne doit pas être rendu visible par défaut sans politique explicite.

Le motif `SecurityConcern` peut nécessiter un traitement restreint.

---

## Sécurité

La commande doit garantir que :

- le token n’est jamais journalisé ;
- la comparaison du token est sécurisée ;
- seul le destinataire peut refuser ;
- le token devient inutilisable après succès ;
- les tentatives sont limitées en fréquence ;
- les réponses publiques ne facilitent pas l’énumération ;
- un commentaire utilisateur est traité comme une donnée non fiable ;
- aucune information interne du `Workspace` n’est révélée avant validation suffisante.

---

## Confidentialité

Avant validation de la preuve d’accès, le système ne doit pas révéler inutilement :

- l’existence de l’`Invitation` ;
- le nom du `Workspace` ;
- le `Role` prévu ;
- l’identité de l’invitant ;
- l’état d’un compte correspondant à l’adresse.

Après validation, seules les informations nécessaires au refus doivent être exposées.

---

## Audit

Un refus réussi doit être traçable.

L’audit peut contenir :

- `InvitationId`
- `WorkspaceId`
- `DeclinedBy`, lorsque connu
- `DeclinedAt`
- `Reason`
- `DeclineRequestId`
- origine du parcours
- contexte de sécurité minimisé
- résultat

Il ne doit jamais contenir :

- le token brut ;
- le lien complet ;
- des secrets d’authentification.

Les commentaires libres doivent respecter les règles de conservation et de visibilité prévues.

---

## Décisions de conception

### Le refus est une transition terminale

Une `Invitation` refusée ne peut pas être réactivée.

Pour inviter à nouveau la même personne, il faut créer une nouvelle `Invitation`.

Cette règle garantit un historique explicite.

---

### Le destinataire est le seul acteur métier du refus

Un administrateur ne refuse pas une invitation au nom du destinataire.

Il la révoque.

La distinction reflète correctement l’intention métier.

---

### Aucun Membership n’est modifié

Le refus concerne uniquement une autorisation temporaire qui n’a pas été consommée.

Il n’a aucun effet sur les appartenances existantes.

---

### Le token est invalidé immédiatement

Une invitation refusée ne doit pas rester techniquement exploitable.

L’invalidation du token fait partie intégrante de la transition.

---

### Le motif est facultatif

Le destinataire peut refuser sans justification.

Le domaine ne doit pas conditionner le refus à la saisie d’un motif.

---

### Le refus ne supprime pas l’Invitation

L’entité est conservée avec l’état `Declined`.

Cela permet :

- l’audit ;
- l’idempotence ;
- la compréhension du parcours ;
- la prévention des rejeux ;
- l’analyse des invitations refusées.

---

## Synthèse

`DeclineInvitation` permet au destinataire de refuser explicitement une `Invitation` encore active.

Elle garantit que :

- l’acteur est le destinataire légitime ;
- l’`Invitation` est encore `Pending` ;
- elle n’est pas expirée ;
- une seule transition terminale est appliquée ;
- l’état devient `Declined` ;
- le token devient inutilisable ;
- aucun `Membership` n’est créé ou modifié ;
- les retries ne produisent aucun effet supplémentaire.

Le résultat final est :

```text
Pending Invitation
    ↓
Declined Invitation
```

avec une décision explicite, terminale et auditable du destinataire.
