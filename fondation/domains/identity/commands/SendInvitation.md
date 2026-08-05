---
id: IDN-CMD-SEND-INVITATION
title: SendInvitation
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
  - CreateInvitation.md
  - ResendInvitation.md
---

# SendInvitation

## Objectif

La commande `SendInvitation` demande la transmission d’une `Invitation` active à son destinataire.

Elle rend disponible au système de communication l’ensemble des informations nécessaires à l’envoi du message contenant le lien d’invitation.

Cette commande ne crée pas l’`Invitation`.

Elle ne crée pas non plus de `Membership`.

---

## Agrégat concerné

`Invitation`

L’`Invitation` constitue la racine de l’agrégat concerné.

La commande vérifie que son état autorise une tentative d’envoi et enregistre l’intention de transmission.

---

## Acteur

La commande peut être demandée par :

- le `User` ayant créé l’`Invitation` ;
- un autre `User` autorisé à gérer les invitations du `Workspace` ;
- un workflow automatique après `InvitationCreated` ;
- un processus système chargé de reprendre un envoi interrompu.

L’acteur peut donc être :

```text
User
```

ou :

```text
System
```

Son identité ou son origine doit rester traçable.

---

## Permission requise

Lorsqu'elle est demandée par un `User`, la permission canonique est :

```text
workspace.members.invite
```

Lorsqu’elle est déclenchée automatiquement après `InvitationCreated`, l’autorisation découle du workflow ayant déjà validé la création.

Le processus système ne doit pas contourner les règles de validité de l’`Invitation`.

---

## Préconditions

Avant l’exécution de `SendInvitation`, les conditions suivantes doivent être satisfaites :

- l’`Invitation` existe ;
- son état est `Pending` ;
- elle n’est pas expirée ;
- elle n’est pas révoquée ;
- elle n’a pas été acceptée ;
- elle n’a pas été refusée ;
- son `Workspace` existe encore et autorise l’opération ;
- son `Role` prévu reste valide ;
- son adresse e-mail de destination est valide ;
- un moyen de transmission compatible est disponible ;
- l’acteur est autorisé lorsque la commande est initiée manuellement.

La possibilité de renvoyer une invitation déjà transmise relève de `ResendInvitation`.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationId` | `InvitationId` | Oui | Identifie l’`Invitation` à transmettre. |
| `RequestedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’origine de la demande. |
| `RequestedAt` | Instant | Oui | Instant de la demande d’envoi. |
| `Channel` | `InvitationDeliveryChannel` | Oui | Canal de transmission demandé. |

Le canal initial recommandé est :

```text
Email
```

Des canaux supplémentaires pourront être ajoutés sans modifier le sens métier de la commande.

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `Locale` | Locale | Non | Langue du message. |
| `MessageTemplate` | `TemplateKey` | Non | Modèle de communication à utiliser. |
| `CorrelationId` | Identifiant | Non | Permet de relier la commande au workflow appelant. |

---

## Validation des données

### InvitationId

L’`InvitationId` doit identifier une `Invitation` existante.

Il ne doit pas être confondu avec l’`InvitationToken`.

---

### RequestedBy

Lorsque l’acteur est un `User`, son identité doit être connue et son autorisation évaluée dans le `Workspace` de l’`Invitation`.

Lorsque l’acteur est le système, le processus appelant doit être explicitement autorisé.

---

### RequestedAt

`RequestedAt` doit représenter l’instant de référence utilisé pour vérifier :

- l’expiration ;
- les délais entre deux tentatives ;
- les limitations de fréquence ;
- l’ordre des événements.

---

### Channel

Le canal demandé doit être pris en charge par Atlas.

Pour une transmission par e-mail, l’`EmailAddress` du destinataire doit être disponible.

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

Les états suivants interdisent l’envoi :

```text
Accepted
Declined
Expired
Revoked
```

---

### 3. Vérifier l’échéance

La condition suivante doit être vraie :

```text
RequestedAt < Invitation.ExpirationDate
```

Une invitation temporellement expirée ne peut pas être envoyée, même si son état persistant n’a pas encore été mis à jour en `Expired`.

---

### 4. Vérifier le contexte

Le système vérifie que :

- le `Workspace` existe encore ;
- le `Workspace` accepte toujours cette invitation ;
- le `Role` prévu existe ;
- le `Role` est actif ;
- le `Role` appartient toujours au même `Workspace`.

Une `Invitation` ne doit pas être transmise lorsqu’elle ne pourra manifestement plus être acceptée.

---

### 5. Vérifier l’autorisation

Lorsque la demande est manuelle, le système vérifie que l’acteur peut gérer les invitations dans le `Workspace`.

Cette vérification doit être contextualisée par :

```text
UserId
WorkspaceId
PermissionKey
```

---

### 6. Préparer la transmission

Le système prépare les informations nécessaires au message :

- adresse du destinataire ;
- nom du `Workspace` ;
- nom du `Role` prévu ;
- identité visible de l’invitant, lorsque pertinente ;
- date d’expiration ;
- lien d’acceptation ;
- langue ;
- modèle de message.

Le domaine ne produit pas lui-même le contenu HTML ou textuel final.

Cette responsabilité appartient au composant de communication.

---

### 7. Produire la demande d’envoi

L’agrégat produit :

```text
InvitationSendRequested
```

Cet événement indique que le domaine autorise et demande une tentative de transmission.

Il ne signifie pas encore que le message a été pris en charge avec succès.

---

### 8. Confier la demande à l’infrastructure

Un gestionnaire applicatif transmet la demande à l’infrastructure de communication.

Cette étape peut utiliser :

- une boîte d’envoi transactionnelle ;
- une file de messages ;
- un fournisseur d’e-mail ;
- un service interne de notification.

L’agrégat ne dépend pas directement de cette infrastructure.

---

### 9. Confirmer la prise en charge

Lorsque le système de communication confirme la prise en charge, une opération distincte enregistre le résultat et produit :

```text
InvitationSent
```

Cette confirmation peut être appliquée par :

- une commande interne ;
- un handler d’événement ;
- un workflow technique ;
- un retour synchrone du fournisseur.

---

## Résultat attendu

Après l’acceptation de la commande métier :

- l’`Invitation` reste `Pending` ;
- une tentative de transmission est autorisée ;
- un événement `InvitationSendRequested` est produit ;
- aucune nouvelle `Invitation` n’est créée ;
- aucun `Membership` n’est créé ;
- aucun succès de livraison finale n’est supposé.

Après confirmation de la prise en charge par l’infrastructure :

- la date du dernier envoi est enregistrée ;
- le nombre de tentatives peut être incrémenté ;
- `InvitationSent` est produit.

---

## État de l’Invitation

L’envoi ne modifie pas l’état métier principal de l’`Invitation`.

Elle reste :

```text
Pending
```

L’information d’envoi est orthogonale à son statut métier.

Une modélisation possible est :

```text
Invitation
├── Status: Pending
├── DeliveryStatus
├── LastSendRequestedAt
├── LastSentAt
├── SendAttempts
└── ExpirationDate
```

---

## Statut de transmission

Un statut de transmission distinct peut prendre les valeurs suivantes :

| Valeur | Description |
|--------|-------------|
| `NotRequested` | Aucun envoi n’a encore été demandé. |
| `Requested` | Une demande d’envoi est en cours de traitement. |
| `AcceptedByProvider` | Le message a été accepté par le système de communication. |
| `Failed` | La dernière tentative de transmission a échoué. |

Ces valeurs ne doivent pas être confondues avec :

```text
Pending
Accepted
Declined
Expired
Revoked
```

qui décrivent le cycle de vie métier de l’`Invitation`.

---

## Invariants concernés

### `IDN-INV-005`

Le `Role` prévu et l’`Invitation` appartiennent au même `Workspace`.

---

### `IDN-INV-008`

L’`Invitation` reste liée à son destinataire initial.

Le canal d’envoi ne peut pas modifier l’adresse ciblée.

---

### `IDN-INV-009`

Une `Invitation` terminée ne peut plus être envoyée.

---

### `IDN-INV-012`

L’`InvitationToken` reste distinct de l’`InvitationId` et ne doit jamais apparaître dans les journaux ou événements persistants.

---

### `IDN-INV-014`

L’autorisation est évaluée dans le `Workspace` concerné.

---

### `IDN-INV-015`

Le `Workspace` référencé doit toujours permettre l’opération.

---

## Événements produits

### InvitationSendRequested

La commande produit immédiatement :

```text
InvitationSendRequested
```

Cet événement peut contenir :

- `InvitationId`
- `WorkspaceId`
- `RecipientEmail`
- `RoleId`
- `RequestedBy`
- `RequestedAt`
- `Channel`
- `Locale`
- `CorrelationId`

Il ne doit pas contenir :

- l’`InvitationToken` brut ;
- un mot de passe ;
- un secret de session ;
- le contenu final du message lorsqu’il contient des informations sensibles.

---

### InvitationSent

`InvitationSent` est produit uniquement après confirmation que le système de communication a accepté la transmission.

Il peut contenir :

- `InvitationId`
- `Channel`
- `ProviderMessageId`
- `SentAt`
- `AttemptNumber`
- `CorrelationId`

Le `ProviderMessageId` est une référence technique facultative.

Il ne devient pas l’identité métier de l’`Invitation`.

`InvitationSendRequested` est un flux interne restreint : l'adresse brute y est
admise uniquement parce que le canal de communication en a besoin. Elle est
exclue de toute exposition publique et soumise à la rétention minimale.

---

## Événements non produits

Cette commande ne produit pas :

```text
InvitationAccepted
InvitationDeclined
InvitationExpired
MembershipCreated
```

Elle ne doit pas non plus produire `InvitationDelivered` sans preuve explicite fournie par le canal de communication.

---

## Erreurs métier

### InvitationNotFound

L’`Invitation` demandée n’existe pas.

---

### InvitationNotPending

L’`Invitation` n’est plus dans l’état `Pending`.

---

### InvitationExpired

L’échéance est atteinte ou dépassée.

---

### InvitationAlreadyAccepted

L’`Invitation` a déjà été acceptée.

---

### InvitationDeclined

L’`Invitation` a été refusée.

---

### InvitationRevoked

L’`Invitation` a été révoquée.

---

### WorkspaceUnavailable

Le `Workspace` ne permet plus l’opération.

---

### RoleUnavailable

Le `Role` prévu n’existe plus, est désactivé ou ne peut plus être attribué.

---

### RoleBelongsToAnotherWorkspace

Le `Role` ne correspond pas au `Workspace` de l’`Invitation`.

---

### ActorNotAuthorized

L’acteur n’est pas autorisé à envoyer l’`Invitation`.

---

### UnsupportedDeliveryChannel

Le canal demandé n’est pas pris en charge.

---

### DeliveryRateLimitExceeded

Une limite de fréquence empêche une nouvelle tentative.

---

### SendAlreadyInProgress

Une demande équivalente est déjà en cours de traitement.

Cette erreur peut être utilisée lorsque le système interdit plusieurs demandes concurrentes.

---

## Erreurs techniques

Les erreurs suivantes ne sont pas des erreurs métier de l’agrégat :

- indisponibilité du fournisseur d’e-mail ;
- échec DNS ;
- délai d’attente réseau ;
- file de messages indisponible ;
- erreur de rendu du template ;
- rejet SMTP.

Elles doivent être traduites en résultat de transmission et gérées par l’infrastructure ou le workflow.

Elles ne doivent pas transformer automatiquement l’`Invitation` en état terminal.

---

## Idempotence

`SendInvitation` doit être idempotente pour une même demande logique.

Une répétition causée par :

- un retry applicatif ;
- un timeout ;
- une rediffusion de message ;
- une reprise après incident ;

ne doit pas entraîner plusieurs envois non contrôlés.

Le comportement recommandé est :

```text
InvitationId + SendRequestId
```

constitue la clé d’idempotence de la tentative.

Une nouvelle demande explicite d’envoi utilise un nouvel identifiant de tentative ou passe par `ResendInvitation`.

---

## Concurrence

Deux commandes `SendInvitation` concurrentes pour la même `Invitation` ne doivent pas déclencher deux transmissions accidentelles.

La protection peut reposer sur :

- un statut `Requested` ;
- une clé d’idempotence ;
- une boîte d’envoi transactionnelle ;
- une contrainte d’unicité ;
- un verrou applicatif limité ;
- une déduplication côté consommateur.

Le résultat attendu est :

```text
one logical request
```

même lorsque plusieurs traitements techniques tentent de l’exécuter.

---

## Limitation de fréquence

Le système doit limiter les envois afin de prévenir :

- le spam ;
- les abus ;
- les boucles de retry ;
- la dégradation de réputation du domaine d’envoi ;
- la surcharge du fournisseur.

La politique peut notamment définir :

- un délai minimum entre deux envois ;
- un nombre maximal de tentatives par heure ;
- un nombre maximal de tentatives sur la durée de vie de l’`Invitation` ;
- une limite par acteur ;
- une limite par destinataire ;
- une limite par `Workspace`.

Les seuils précis relèvent de la politique de sécurité et d’exploitation.

---

## Sécurité

La commande doit respecter les règles suivantes :

- ne jamais journaliser l’`InvitationToken` brut ;
- ne jamais inclure le token brut dans un événement métier persistant ;
- limiter la durée de validité du lien ;
- utiliser un canal sécurisé ;
- éviter d’exposer des informations inutiles sur les membres du `Workspace` ;
- empêcher les redirections ouvertes dans le lien ;
- éviter toute possibilité d’énumération des comptes ;
- protéger la génération du lien contre les injections.

Le lien peut contenir le token nécessaire à l’usage de l’`Invitation`, mais sa construction relève d’un composant de sécurité dédié.

---

## Confidentialité

Le message ne doit contenir que les informations nécessaires à la compréhension de l’invitation.

Il peut inclure :

- le nom du `Workspace` ;
- le nom visible de l’invitant ;
- le `Role` prévu ;
- la date d’expiration ;
- l’action attendue.

Il ne doit pas révéler :

- la liste des membres ;
- les autres invitations ;
- des informations internes au `Workspace` ;
- les `Permission` détaillées, sauf besoin produit explicite ;
- des secrets techniques.

---

## Audit

La commande et son résultat doivent être traçables.

L’audit peut enregistrer :

- `InvitationId`
- `WorkspaceId`
- acteur ou processus demandeur
- canal
- instant de la demande
- instant de prise en charge
- résultat
- numéro de tentative
- identifiant du fournisseur
- code d’échec non sensible

L’audit ne doit jamais contenir le token brut.

---

## Décisions de conception

### L’envoi ne change pas le statut métier principal

Une `Invitation` envoyée reste `Pending`.

Le statut `Pending` signifie :

> L’`Invitation` peut encore être acceptée, refusée, révoquée ou expirer.

Il ne signifie pas :

> Aucun e-mail n’a encore été envoyé.

Ces deux dimensions doivent rester séparées.

---

### Demande et confirmation sont distinctes

`InvitationSendRequested` signifie :

> Le domaine a demandé la transmission.

`InvitationSent` signifie :

> Le système de communication a accepté la transmission.

Aucun de ces événements ne garantit nécessairement que le message est arrivé dans la boîte de réception.

---

### Aucun envoi direct depuis l’agrégat

L’agrégat `Invitation` ne dépend pas :

- d’un fournisseur SMTP ;
- d’une API d’e-mail ;
- d’un moteur de template ;
- d’une file de messages.

Il exprime uniquement l’intention et les règles métier.

---

### Le token brut n’appartient pas aux événements persistants

Un `InvitationToken` brut est un secret à durée limitée.

Sa diffusion dans un journal d’événements augmenterait inutilement la surface d’exposition.

Le composant chargé de construire le message doit obtenir le secret par un mécanisme sécurisé et contrôlé.

---

### InvitationSent ne signifie pas InvitationDelivered

Une confirmation du fournisseur peut uniquement garantir que le message a été accepté pour traitement.

Les concepts suivants restent distincts :

```text
Requested
Sent
Delivered
Opened
Accepted
```

Seul `Accepted` appartient obligatoirement au cycle de vie métier de l’`Invitation`.

Les statuts de livraison avancés sont optionnels et relèvent principalement de l’intégration de communication.

---

## Synthèse

`SendInvitation` demande la transmission d’une `Invitation` existante et encore utilisable.

Elle garantit que :

- l’`Invitation` existe ;
- elle est `Pending` ;
- elle n’est pas expirée ;
- son contexte reste valide ;
- l’acteur est autorisé ;
- la tentative est dédupliquée ;
- le secret reste protégé ;
- l’échec technique ne corrompt pas son cycle de vie métier.

La commande produit `InvitationSendRequested`.

`InvitationSent` n’est enregistré qu’après confirmation de prise en charge par l’infrastructure de communication.
