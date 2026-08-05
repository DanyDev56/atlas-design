---
id: IDN-CMD-RESEND-INVITATION
title: ResendInvitation
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
  - ../permissions.md
  - ../integrations.md
  - ../events.md
  - SendInvitation.md
  - RevokeInvitation.md
  - ExpireInvitation.md
---

# ResendInvitation

## Objectif

La commande `ResendInvitation` demande une nouvelle transmission d’une `Invitation` précédemment envoyée ou dont une première transmission a échoué.

Elle exprime une nouvelle intention métier explicite.

Elle se distingue :

- d’un retry technique automatique ;
- de la création d’une nouvelle `Invitation` ;
- de la réactivation d’une `Invitation` terminée.

Cette commande ne crée aucun `Membership`.

---

## Agrégat concerné

`Invitation`

L’`Invitation` constitue la racine de l’agrégat modifié.

La commande vérifie que l’entité reste utilisable et qu’une nouvelle transmission est autorisée.

---

## Acteur

La commande peut être demandée par :

- le `User` ayant créé l’`Invitation` ;
- un autre `User` autorisé à gérer les invitations du `Workspace` ;
- un processus système explicitement autorisé ;
- un workflow de relance automatique prévu par la politique produit.

L’origine de la demande doit être traçable.

---

## Permission requise

Lorsqu'elle est initiée par un `User`, la permission canonique est :

```text
workspace.members.invite
```

Une relance automatique doit être exécutée par un processus système identifié et limité à ce cas d’usage.

---

## Préconditions

Avant l’exécution de `ResendInvitation`, les conditions suivantes doivent être satisfaites :

- l’`Invitation` existe ;
- son état est `Pending` ;
- elle n’est pas expirée ;
- elle n’est pas révoquée ;
- elle n’a pas été acceptée ;
- elle n’a pas été refusée ;
- au moins une demande d’envoi antérieure existe, sauf politique contraire ;
- le délai minimum entre deux transmissions est respecté ;
- le nombre maximal de relances n’est pas dépassé ;
- le `Workspace` reste disponible ;
- le `Role` prévu reste actif et attribuable ;
- le destinataire ne possède pas déjà un `Membership` dans ce `Workspace` ;
- l’acteur est autorisé ;
- aucun autre renvoi équivalent n’est en cours.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationId` | `InvitationId` | Oui | Identifie l’`Invitation` à renvoyer. |
| `RequestedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’origine de la relance. |
| `RequestedAt` | Instant | Oui | Instant de la nouvelle demande. |
| `Channel` | `InvitationDeliveryChannel` | Oui | Canal de transmission demandé. |
| `ResendRequestId` | Identifiant | Oui | Identifie de manière idempotente la demande de renvoi. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `Reason` | `InvitationResendReason` | Non | Motif de la relance. |
| `Locale` | Locale | Non | Langue du nouveau message. |
| `CorrelationId` | Identifiant | Non | Référence du workflow appelant. |
| `RotateToken` | Booléen | Non | Demande une rotation du token lorsque la politique l’autorise. |

---

## InvitationResendReason

Le motif peut notamment prendre les valeurs suivantes :

```text
RecipientRequested
DeliveryFailed
ManualReminder
AutomaticReminder
SecurityRotation
```

Le motif facilite :

- l’audit ;
- les limitations de fréquence ;
- l’analyse des échecs ;
- l’application de politiques différentes selon le contexte.

Il ne doit pas modifier les invariants fondamentaux de l’`Invitation`.

---

## Validation des données

### InvitationId

L’`InvitationId` doit identifier une `Invitation` existante.

Il reste distinct de l’`InvitationToken`.

---

### ResendRequestId

Le `ResendRequestId` doit :

- identifier une seule intention de relance ;
- être unique dans le périmètre de l’`Invitation` ;
- permettre de dédupliquer les répétitions techniques ;
- ne contenir aucune donnée sensible.

---

### RequestedAt

`RequestedAt` est utilisé pour vérifier :

- l’expiration ;
- le délai entre deux envois ;
- les limites de fréquence ;
- la cohérence temporelle de la tentative.

---

### Channel

Le canal doit être pris en charge.

Le changement de canal est possible uniquement si le destinataire dispose d’une destination valide pour ce canal.

Le canal ne peut pas modifier l’identité du destinataire.

---

### RotateToken

Lorsque `RotateToken` est demandé :

- la politique de sécurité doit autoriser la rotation ;
- l’ancien token doit être invalidé ;
- un nouveau token doit être généré avant la nouvelle transmission ;
- une tentative d’acceptation avec l’ancien token doit échouer.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Charger l’Invitation

Le système charge l’`Invitation` identifiée par `InvitationId`.

Si elle n’existe pas, la commande échoue.

---

### 2. Vérifier son état

L’`Invitation` doit être dans l’état :

```text
Pending
```

Une invitation dans l’un des états suivants ne peut pas être renvoyée :

```text
Accepted
Declined
Expired
Revoked
```

Une nouvelle entité doit être créée lorsqu’une invitation terminée doit être remplacée.

---

### 3. Vérifier l’échéance

La condition suivante doit être vraie :

```text
RequestedAt < Invitation.ExpirationDate
```

Si l’échéance est atteinte, la commande doit échouer ou déclencher le workflow d’expiration.

Elle ne peut pas prolonger implicitement la durée de validité.

---

### 4. Vérifier l’historique d’envoi

Le système vérifie qu’une première transmission a déjà été demandée ou tentée.

La commande `ResendInvitation` ne doit normalement pas être utilisée pour le premier envoi.

Lorsque l’`Invitation` n’a jamais été envoyée, `SendInvitation` constitue la commande appropriée.

---

### 5. Vérifier le délai minimum

Le système compare `RequestedAt` à la dernière demande ou au dernier envoi confirmé.

Exemple conceptuel :

```text
RequestedAt >= LastSendRequestedAt + MinimumResendDelay
```

Le délai exact relève de la politique produit et de sécurité.

---

### 6. Vérifier le nombre de relances

Le système vérifie que le nombre maximal de relances autorisées n’est pas dépassé.

Le calcul peut distinguer :

- les demandes manuelles ;
- les relances automatiques ;
- les retries techniques ;
- les échecs de fournisseur.

Un retry technique ne doit pas nécessairement compter comme une nouvelle relance métier.

---

### 7. Vérifier le contexte métier

Le système vérifie que :

- le `Workspace` existe encore ;
- il accepte toujours l’arrivée du destinataire ;
- le `Role` existe ;
- le `Role` est actif ;
- le `Role` appartient au `Workspace` ;
- le `Role` peut toujours être attribué ;
- aucun `Membership` n’a été créé entre-temps.

Une invitation devenue sans objet ne doit pas être renvoyée.

---

### 8. Autoriser l’acteur

Pour une demande manuelle, l’autorisation doit être évaluée dans le contexte exact du `Workspace`.

L’acteur doit posséder la permission requise et être autorisé à attribuer le `Role` prévu.

---

### 9. Vérifier l’absence de demande concurrente

Le système vérifie qu’aucune demande de transmission équivalente n’est déjà en cours.

La même valeur de `ResendRequestId` doit produire le même résultat logique.

---

### 10. Décider de la rotation du token

Selon la politique de sécurité, le renvoi peut :

- conserver l’`InvitationToken` existant ;
- générer un nouveau token et invalider l’ancien.

La décision ne doit pas être implicite ou dépendre du fournisseur de messagerie.

---

### 11. Enregistrer la relance

L’agrégat enregistre les informations nécessaires :

- instant de la demande ;
- acteur ;
- motif ;
- canal ;
- numéro de relance ;
- éventuelle rotation du token.

Le statut métier principal reste :

```text
Pending
```

---

### 12. Produire l’événement

L’agrégat produit :

```text
InvitationResendRequested
```

L’infrastructure traite ensuite la transmission selon les mêmes principes que pour `SendInvitation`.

---

## Résultat attendu

Après une exécution réussie :

- l’`Invitation` reste `Pending` ;
- une nouvelle tentative métier est enregistrée ;
- son compteur de relances est incrémenté ;
- la demande possède un identifiant idempotent ;
- le token est conservé ou renouvelé selon la politique ;
- `InvitationResendRequested` est produit ;
- aucun `Membership` n’est créé ;
- l’échéance n’est pas prolongée implicitement.

Exemple conceptuel :

```text
Invitation
├── Status: Pending
├── ResendCount: previous + 1
├── LastResendRequestedAt: RequestedAt
├── LastResendRequestedBy: RequestedBy
├── LastResendReason: Reason
└── ExpirationDate: unchanged
```

---

## Invariants concernés

### `IDN-INV-001`

Aucun renvoi ne doit être effectué lorsqu’un `Membership` existe déjà pour le destinataire dans le `Workspace`.

---

### `IDN-INV-005`

Le `Role` prévu reste rattaché au même `Workspace`.

---

### `IDN-INV-008`

L’`Invitation` reste destinée à la même personne.

Le renvoi ne permet pas de modifier l’`EmailAddress`.

---

### `IDN-INV-009`

Une `Invitation` terminée ne peut pas être renvoyée.

---

### `IDN-INV-012`

Le token et l’identifiant restent distincts.

En cas de rotation, l’ancien secret devient inutilisable.

---

### `IDN-INV-014`

L’autorisation de l’acteur est évaluée dans le `Workspace` de l’`Invitation`.

---

### `IDN-INV-015`

Le `Workspace` doit toujours permettre l’opération.

---

## Événements produits

### InvitationResendRequested

La commande produit :

```text
InvitationResendRequested
```

L’événement peut contenir :

- `InvitationId`
- `WorkspaceId`
- `RoleId`
- `RequestedBy`
- `RequestedAt`
- `Channel`
- `Reason`
- `ResendNumber`
- `TokenRotated`
- `ResendRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- l’`InvitationToken` brut ;
- l’ancien token ;
- un lien d’acceptation complet ;
- un secret du fournisseur.

---

### InvitationTokenRotated

Lorsque la rotation du token constitue un fait métier ou de sécurité suffisamment important, un événement distinct peut être produit :

```text
InvitationTokenRotated
```

Il peut contenir :

- `InvitationId`
- `RotatedAt`
- `RotatedBy`
- `Reason`

Il ne contient jamais les valeurs du token précédent ou du nouveau token.

Cette séparation est recommandée si la rotation doit être auditée ou déclencher d’autres traitements.

---

### InvitationSent

Après confirmation de prise en charge par l’infrastructure, le système produit :

```text
InvitationSent
```

avec le numéro de tentative correspondant.

---

## Événements non produits

La commande ne produit pas :

```text
InvitationCreated
InvitationAccepted
InvitationDeclined
InvitationExpired
InvitationRevoked
MembershipCreated
```

Elle ne recrée pas l’`Invitation`.

---

## Erreurs métier

### InvitationNotFound

L’`Invitation` n’existe pas.

---

### InvitationNotPending

L’`Invitation` n’est plus dans l’état `Pending`.

---

### InvitationNeverSent

Aucune première demande d’envoi n’existe.

`SendInvitation` doit être utilisé à la place.

---

### InvitationExpired

L’échéance est atteinte ou dépassée.

---

### InvitationAlreadyAccepted

L’`Invitation` a déjà été acceptée.

---

### InvitationDeclined

Le destinataire a refusé l’`Invitation`.

---

### InvitationRevoked

L’`Invitation` a été révoquée.

---

### MembershipAlreadyExists

Le destinataire possède désormais un `Membership` dans le `Workspace`.

L’`Invitation` est devenue sans objet.

---

### WorkspaceUnavailable

Le `Workspace` ne permet plus l’opération.

---

### RoleUnavailable

Le `Role` prévu est désactivé, supprimé ou non attribuable.

---

### RoleBelongsToAnotherWorkspace

Le `Role` ne correspond pas au `Workspace` de l’`Invitation`.

---

### ActorNotAuthorized

L’acteur ne peut pas relancer cette `Invitation`.

---

### ResendTooSoon

Le délai minimal entre deux transmissions n’est pas respecté.

---

### ResendLimitExceeded

Le nombre maximal de relances est atteint.

---

### ResendAlreadyInProgress

Une demande de relance équivalente est déjà en cours.

---

### UnsupportedDeliveryChannel

Le canal demandé n’est pas pris en charge.

---

### TokenRotationNotAllowed

La rotation demandée n’est pas autorisée dans ce contexte.

---

## Idempotence

`ResendInvitation` doit être idempotente pour une même valeur de :

```text
InvitationId + ResendRequestId
```

La répétition de la même commande doit :

- retourner le résultat initial ;
- ne pas incrémenter une nouvelle fois le compteur ;
- ne pas générer un nouveau token supplémentaire ;
- ne pas produire une nouvelle transmission logique ;
- ne pas produire un nouvel événement métier équivalent.

Une nouvelle intention de relance doit utiliser un nouveau `ResendRequestId`.

---

## Concurrence

Deux demandes concurrentes doivent être distinguées selon leur identité.

### Même ResendRequestId

Elles représentent la même intention.

Le résultat doit être dédupliqué.

```text
one logical resend
```

---

### ResendRequestId différents

Elles représentent potentiellement deux intentions distinctes.

Le système doit néanmoins appliquer :

- le délai minimum ;
- le nombre maximal de relances ;
- la détection d’un envoi déjà en cours ;
- les règles de fréquence.

Une seule demande peut être acceptée lorsque les deux seraient trop rapprochées.

---

## Retry technique

Un retry technique ne constitue pas un `ResendInvitation`.

Il intervient après une demande déjà acceptée lorsque l’infrastructure n’a pas pu terminer le traitement.

Exemple :

```text
InvitationResendRequested
        │
        ▼
Provider timeout
        │
        ▼
Technical retry
```

Ce retry doit conserver :

- le même `ResendRequestId` ;
- le même numéro de tentative métier ;
- la même décision de rotation ;
- la même intention fonctionnelle.

Il ne doit pas incrémenter le compteur de relances métier.

---

## Rotation du InvitationToken

### Conservation du token

Conserver le token présente les avantages suivants :

- tous les liens déjà transmis restent valides ;
- le traitement est plus simple ;
- un retard de réception ne rend pas un ancien message inutilisable.

Cette stratégie est adaptée lorsque le token n’est pas suspecté d’être compromis.

---

### Rotation du token

La rotation présente les avantages suivants :

- les anciens liens deviennent invalides ;
- une exposition potentielle est neutralisée ;
- la relance peut repartir avec un nouveau secret.

Elle est adaptée notamment lorsque :

- le destinataire signale un lien exposé ;
- une anomalie de sécurité est détectée ;
- la politique impose une rotation après plusieurs relances ;
- le canal de transmission précédent est considéré comme compromis.

---

### Règle recommandée

Le renvoi standard conserve le token.

La rotation doit être explicite et réservée à un motif de sécurité ou à une politique clairement définie.

Une rotation ne modifie pas :

- l’`InvitationId` ;
- le destinataire ;
- le `WorkspaceId` ;
- le `RoleId` ;
- l’échéance ;
- l’état `Pending`.

---

## Expiration

`ResendInvitation` ne prolonge pas automatiquement l’`ExpirationDate`.

Cette décision évite qu’une invitation reste active indéfiniment grâce à des relances successives.

Lorsque la durée restante est insuffisante, le produit peut :

- refuser la relance ;
- avertir l’acteur ;
- proposer de révoquer puis recréer une nouvelle `Invitation`.

Une commande distincte serait nécessaire pour modifier explicitement l’échéance si cette capacité était retenue.

---

## Limitation de fréquence

La politique doit distinguer au minimum :

- retry technique ;
- relance manuelle ;
- relance automatique ;
- demande du destinataire.

Exemple de politique conceptuelle :

```text
Technical retry
    -> controlled by infrastructure retry policy

Manual resend
    -> minimum delay between requests

Automatic reminder
    -> fixed schedule and maximum count

Recipient requested
    -> dedicated rate limit
```

Les valeurs précises ne doivent pas être codées dans la documentation métier générale lorsqu’elles relèvent de la configuration opérationnelle.

---

## Relances automatiques

Une politique de relance automatique peut prévoir :

- une première relance après un délai défini ;
- une dernière relance avant expiration ;
- un nombre maximal de rappels ;
- l’arrêt immédiat après acceptation, refus, révocation ou expiration.

Le scheduler ne doit pas appeler la commande lorsqu’aucune action n’est nécessaire.

Avant chaque relance, les préconditions complètes doivent être réévaluées.

---

## Sécurité

La commande doit garantir que :

- le token brut n’est jamais journalisé ;
- une rotation invalide effectivement l’ancien token ;
- aucune relance n’est envoyée vers une nouvelle adresse ;
- les limites de fréquence empêchent le spam ;
- l’acteur ne peut pas utiliser la relance pour attribuer un autre `Role` ;
- les liens sont produits par un composant sécurisé ;
- les événements ne contiennent aucun secret exploitable.

---

## Confidentialité

Le renvoi ne doit pas révéler davantage d’informations que le premier envoi.

Le message peut rappeler :

- le nom du `Workspace` ;
- l’identité visible de l’invitant ;
- le `Role` prévu ;
- l’échéance ;
- l’action attendue.

Il ne doit pas exposer :

- les autres membres ;
- les autres invitations ;
- les détails internes des autorisations ;
- des données métier du `Workspace` ;
- l’historique complet des relances.

---

## Audit

Chaque relance doit être traçable.

L’audit peut contenir :

- `InvitationId`
- `WorkspaceId`
- `RequestedBy`
- `RequestedAt`
- `Reason`
- `Channel`
- `ResendRequestId`
- numéro de relance
- rotation du token
- résultat de transmission
- identifiant du fournisseur
- code d’échec non sensible

L’audit ne doit contenir aucun token brut.

---

## Décisions de conception

### ResendInvitation représente une nouvelle intention

Une relance n’est pas un simple détail technique.

Elle peut être demandée :

- par un utilisateur ;
- par le destinataire ;
- par un workflow automatique ;
- après un échec connu.

Elle mérite donc une commande et un événement dédiés.

---

### Les retries techniques restent invisibles métier

Les retries dus à un timeout, à une panne réseau ou à une indisponibilité temporaire ne doivent pas produire de nouvelle relance métier.

Ils poursuivent la même demande logique.

---

### L’échéance reste stable

Une relance ne prolonge pas silencieusement la validité de l’`Invitation`.

Cela garantit un cycle de vie borné et prévisible.

---

### Le destinataire et le Role restent immuables

`ResendInvitation` ne permet pas de modifier :

- `RecipientEmail`
- `WorkspaceId`
- `RoleId`

Une modification de l’un de ces éléments exige la révocation de l’ancienne `Invitation` puis la création d’une nouvelle.

---

### La rotation du token est explicite

La rotation ne doit pas être une conséquence invisible de chaque renvoi.

Elle doit découler :

- d’une demande explicite ;
- d’une politique de sécurité ;
- d’un motif traçable.

---

### Le statut métier reste Pending

Une relance ne constitue pas un nouvel état principal.

L’`Invitation` reste utilisable selon le même cycle :

```text
Pending
    ├── Accepted
    ├── Declined
    ├── Expired
    └── Revoked
```

Les informations de transmission restent une dimension séparée.

---

## Synthèse

`ResendInvitation` exprime une nouvelle demande métier de transmission d’une `Invitation` existante.

Elle garantit que :

- l’`Invitation` reste `Pending` et non expirée ;
- son contexte métier est encore valide ;
- l’acteur est autorisé ;
- les limites de fréquence sont respectées ;
- les retries techniques ne sont pas confondus avec les relances métier ;
- l’échéance n’est pas prolongée implicitement ;
- la rotation du token est explicite et sécurisée ;
- aucune relance concurrente ou dupliquée n’est produite.

La commande produit `InvitationResendRequested`.

Elle ne crée ni une nouvelle `Invitation`, ni un `Membership`.
