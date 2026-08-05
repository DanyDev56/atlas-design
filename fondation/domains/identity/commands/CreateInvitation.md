---
id: IDN-CMD-CREATE-INVITATION
title: CreateInvitation
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
  - ../events.md
  - SendInvitation.md
---

# CreateInvitation

## Objectif

La commande `CreateInvitation` crée une nouvelle `Invitation` destinée à permettre à une personne de rejoindre un `Workspace`.

Elle enregistre l’intention d’inviter une adresse e-mail avec un `Role` déterminé.

Cette commande ne transmet pas encore l’`Invitation` au destinataire.

---

## Agrégat concerné

`Invitation`

`Invitation` constitue la racine de l’agrégat modifié par cette commande.

---

## Acteur

La commande peut être demandée par :

- un `User` authentifié disposant de la `Permission` requise dans le `Workspace` ;
- un processus système explicitement autorisé ;
- un workflow de création de `Workspace`, lorsque le produit prévoit l’invitation automatique de membres initiaux.

L’acteur doit être identifié.

---

## Permission requise

La permission canonique est :

```text
workspace.members.invite
```

---

## Préconditions

Avant l’exécution de `CreateInvitation`, les conditions suivantes doivent être satisfaites :

- le `Workspace` existe ;
- le `Workspace` accepte de nouveaux membres ;
- l’acteur est autorisé à inviter dans ce `Workspace` ;
- l’adresse e-mail du destinataire est valide ;
- le `Role` existe ;
- le `Role` est actif ;
- le `Role` appartient au `Workspace` ciblé ;
- le `Role` peut être attribué par l’acteur ;
- le destinataire ne possède pas déjà un `Membership` incompatible dans ce `Workspace` ;
- aucune `Invitation` active incompatible n’existe déjà pour cette adresse et ce `Workspace`.

La politique concernant les invitations concurrentes doit être appliquée avant la création.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationId` | `InvitationId` | Oui | Identifiant de la nouvelle `Invitation`. |
| `WorkspaceId` | `WorkspaceId` | Oui | `Workspace` que le destinataire est invité à rejoindre. |
| `RecipientEmail` | `EmailAddress` | Oui | Adresse e-mail du destinataire. |
| `RoleId` | `RoleId` | Oui | `Role` prévu pour le futur `Membership`. |
| `InvitedBy` | `UserId` | Oui | `User` à l’origine de l’`Invitation`. |
| `ExpirationDate` | `ExpirationDate` | Oui | Date limite d’utilisation de l’`Invitation`. |
| `CreateInvitationRequestId` | `RequestId` | Oui | Clé d'idempotence de l'intention. |
| `RequestedAt` | Instant | Oui | Instant auquel la commande est demandée. |

Selon les besoins du produit, la commande peut également recevoir :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `PersonalMessage` | Texte | Non | Message facultatif destiné au futur e-mail. |
| `Locale` | Locale | Non | Langue préférée pour les communications. |

Les données liées à la communication ne doivent pas modifier la signification métier principale de l’`Invitation`.

---

## Validation des données

### InvitationId

L’`InvitationId` doit :

- être valide ;
- ne pas déjà identifier une autre `Invitation` ;
- être distinct de l’`InvitationToken`.

---

### WorkspaceId

Le `WorkspaceId` doit :

- référencer un `Workspace` existant ;
- désigner le contexte dans lequel l’acteur est autorisé ;
- rester inchangé pendant tout le cycle de vie de l’`Invitation`.

---

### RecipientEmail

L’adresse doit :

- être transformable en `EmailAddress` valide ;
- être normalisée avant toute comparaison ;
- ne pas être vide ;
- ne pas être modifiée après la création.

---

### RoleId

Le `RoleId` doit référencer un `Role` :

- existant ;
- actif ;
- attribuable ;
- appartenant au même `Workspace`.

La condition suivante doit être vraie :

```text
Role.WorkspaceId = Invitation.WorkspaceId
```

---

### ExpirationDate

L’`ExpirationDate` doit être strictement postérieure à `RequestedAt`.

```text
ExpirationDate > RequestedAt
```

La durée maximale d’une invitation doit respecter la politique de sécurité de la plateforme.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Résoudre le Workspace

Le système vérifie que le `Workspace` ciblé existe et peut recevoir de nouvelles invitations.

---

### 2. Autoriser l’acteur

Le système vérifie que l’acteur possède la `Permission` nécessaire dans le `Workspace`.

L’autorisation doit être évaluée dans le contexte exact du `WorkspaceId` demandé.

---

### 3. Normaliser le destinataire

L’adresse fournie est transformée en `EmailAddress`.

Toutes les comparaisons suivantes utilisent sa valeur normalisée.

---

### 4. Vérifier le Membership existant

Le système recherche un éventuel `Membership` pour la paire :

```text
RecipientUserId + WorkspaceId
```

lorsque l’adresse correspond déjà à un `User`.

Si un `Membership` actif ou suspendu existe, la commande est refusée.

Le comportement face à un `Membership` supprimé dépend de la politique de restauration documentée.

---

### 5. Vérifier les Invitations existantes

Le système recherche une `Invitation` active pour :

```text
RecipientEmail + WorkspaceId
```

Lorsqu’une invitation active existe déjà, la commande doit appliquer la politique choisie :

- retourner l’`Invitation` existante ;
- refuser la création ;
- révoquer explicitement l’ancienne puis en créer une nouvelle.

La création silencieuse d’un doublon est interdite.

---

### 6. Vérifier le Role

Le système vérifie que le `Role` :

- appartient au `Workspace` ;
- est actif ;
- peut être attribué ;
- n’enfreint aucune règle de délégation.

Par exemple, un administrateur ne doit pas nécessairement pouvoir inviter un nouveau propriétaire si cette élévation est réservée aux propriétaires existants.

---

### 7. Créer l’Invitation

Une nouvelle `Invitation` est créée avec l’état :

```text
Pending
```

Elle contient notamment :

- son `InvitationId` ;
- le `WorkspaceId` ;
- l’`EmailAddress` du destinataire ;
- le `RoleId` prévu ;
- le `UserId` de l’acteur ;
- sa date de création ;
- son échéance.

---

### 8. Générer le secret

Un `InvitationToken` est généré pour permettre l’utilisation future de l’`Invitation`.

Le token brut ne doit pas être exposé dans l’événement métier persistant.

Une représentation protégée peut être associée à l’agrégat ou à son infrastructure de sécurité.

---

### 9. Produire l’événement

Lorsque la création réussit, l’agrégat produit :

```text
InvitationCreated
```

La transmission de l’invitation est déclenchée séparément par `SendInvitation` ou par un workflow réagissant à cet événement.

---

## Résultat attendu

Après une exécution réussie :

- une unique `Invitation` existe ;
- son état est `Pending` ;
- elle cible l’adresse e-mail demandée ;
- elle référence le `Workspace` demandé ;
- elle prépare l’attribution du `Role` demandé ;
- elle possède une échéance valide ;
- elle peut être envoyée ;
- aucun `Membership` n’a encore été créé.

État conceptuel :

```text
Invitation
├── Status: Pending
├── WorkspaceId
├── RecipientEmail
├── RoleId
├── InvitedBy
├── CreatedAt
└── ExpirationDate
```

---

## Invariants concernés

La commande doit préserver notamment :

### `IDN-INV-001`

Un `User` possède au maximum un `Membership` par `Workspace`.

La commande ne crée pas elle-même de `Membership`, mais ne doit pas préparer une invitation manifestement incompatible avec une appartenance existante.

---

### `IDN-INV-005`

Le `Role` prévu et l’`Invitation` appartiennent au même `Workspace`.

---

### `IDN-INV-008`

L’`Invitation` reste attachée à un destinataire déterminé.

---

### `IDN-INV-009`

Une nouvelle `Invitation` commence nécessairement dans l’état `Pending`.

---

### `IDN-INV-012`

L’`InvitationId` et l’`InvitationToken` restent distincts.

---

### `IDN-INV-014`

L’autorisation de l’acteur est évaluée dans le `Workspace` ciblé.

---

### `IDN-INV-015`

Le `Workspace` référencé existe et accepte l’opération.

---

## Événements produits

### InvitationCreated

La commande produit :

```text
InvitationCreated
```

L’événement indique qu’une nouvelle `Invitation` métier a été créée.

Il peut notamment contenir :

- `InvitationId`
- `WorkspaceId`
- `RecipientEmailFingerprint`
- `RoleId`
- `InvitedBy`
- `CreatedAt`
- `ExpirationDate`

Il ne doit contenir ni l'`InvitationToken` brut ni l'adresse e-mail brute. Le
workflow d'envoi relit l'adresse depuis un port protégé.

---

## Événements non produits

Cette commande ne produit pas :

```text
InvitationSent
MembershipCreated
InvitationAccepted
```

La création et l’envoi constituent deux actions différentes.

L’existence d’une `Invitation` ne signifie pas que le destinataire a reçu un message.

---

## Erreurs métier

La commande peut échouer avec les erreurs suivantes.

### WorkspaceNotFound

Le `Workspace` demandé n’existe pas.

---

### WorkspaceUnavailable

Le `Workspace` existe mais n’accepte pas de nouvelle invitation.

---

### ActorNotAuthorized

L’acteur ne possède pas la `Permission` requise dans le `Workspace`.

---

### InvalidEmailAddress

L’adresse du destinataire ne peut pas former une `EmailAddress` valide.

---

### RoleNotFound

Le `Role` demandé n’existe pas.

---

### RoleDisabled

Le `Role` demandé n’est pas actif.

---

### RoleBelongsToAnotherWorkspace

Le `Role` appartient à un autre `Workspace`.

---

### RoleNotAssignable

Le `Role` existe mais l’acteur ne peut pas l’attribuer.

---

### MembershipAlreadyExists

Le destinataire possède déjà un `Membership` incompatible dans ce `Workspace`.

---

### InvitationAlreadyExists

Une `Invitation` active existe déjà pour cette adresse et ce `Workspace`.

Cette erreur est utilisée uniquement si la politique choisie consiste à refuser les doublons.

---

### InvalidExpirationDate

L’échéance n’est pas future ou dépasse la durée maximale autorisée.

---

### InvitationIdAlreadyExists

L’`InvitationId` demandé est déjà utilisé.

---

## Idempotence

`CreateInvitation` utilise `CreateInvitationRequestId` comme clé d'idempotence.

Deux exécutions identiques pourraient théoriquement créer deux entités différentes.

Cependant, le domaine doit protéger l’unicité métier suivante :

```text
RecipientEmail + WorkspaceId + ActiveStatus
```

Le comportement canonique est le suivant :

- une seconde demande portant le même `CreateInvitationRequestId` et la même
  empreinte retourne le résultat initial ;
- une demande sans clé d’idempotence est refusée lorsqu’une invitation active équivalente existe ;
- aucune seconde `Invitation` active n’est créée silencieusement.

La clé reste une métadonnée de commande et ne devient pas l'identité métier de
l'agrégat.

---

## Concurrence

Deux commandes concurrentes pour la même paire :

```text
RecipientEmail + WorkspaceId
```

ne doivent pas créer deux `Invitation` actives.

La protection doit combiner :

- une vérification métier ;
- une contrainte adaptée en persistance ;
- une stratégie transactionnelle ;
- une gestion explicite du conflit.

Le résultat autorisé est :

```text
exactly one active Invitation
```

---

## Autorisation et délégation

Le droit d’inviter un membre ne signifie pas nécessairement le droit d’attribuer tous les `Role`.

La politique d'autorisation combine :

```text
Actor has workspace.members.invite
AND TargetRole.RoleAssignmentPolicy allows Invitation
AND ownership policy allows TargetRole when it is Owner
```

Exemple de règle :

- un `Admin` peut inviter un `Member` ;
- un `Admin` ne peut pas inviter un `Owner` ;
- seul un `Owner` peut accorder la propriété du `Workspace`.

Ces règles sont des politiques contextuelles ; elles ne créent pas de clés de
permission supplémentaires.

---

## Sécurité

La commande doit respecter les exigences suivantes :

- ne jamais journaliser l’`InvitationToken` brut ;
- ne pas exposer inutilement l’existence d’un `User` correspondant à l’adresse ;
- éviter les réponses permettant l’énumération des comptes ;
- limiter le nombre de créations par acteur, adresse ou `Workspace` ;
- enregistrer les informations d’audit nécessaires ;
- ne pas envoyer d’e-mail directement depuis l’agrégat.

---

## Audit

Une création réussie doit être traçable.

Les informations d’audit peuvent inclure :

- l’`InvitationId` ;
- le `WorkspaceId` ;
- le `UserId` de l’acteur ;
- l’adresse e-mail du destinataire selon les règles de protection des données ;
- le `RoleId` prévu ;
- la date de création ;
- le résultat de l’opération.

Les secrets ne doivent jamais apparaître dans l’audit.

---

## Décisions de conception

### Création séparée de l’envoi

`CreateInvitation` et `SendInvitation` sont deux commandes distinctes.

Cette séparation permet :

- de conserver une `Invitation` avant son envoi ;
- de réessayer un envoi sans recréer l’entité ;
- de distinguer les erreurs métier des erreurs de communication ;
- de tracer précisément la création et la transmission ;
- de supporter plusieurs canaux de notification.

---

### Le Role est fixé à la création

Le `Role` prévu est choisi lors de la création.

Il ne doit pas être remplacé silencieusement au moment de l’acceptation.

Toute modification ultérieure doit passer par une commande explicite, si le produit autorise cette fonctionnalité.

Une autre approche consiste à révoquer l’ancienne `Invitation` et à en créer une nouvelle.

---

### Le destinataire est immuable

L’adresse e-mail ciblée ne peut pas être corrigée directement sur une `Invitation` existante.

En cas d’erreur :

1. l’ancienne `Invitation` est révoquée ;
2. une nouvelle `Invitation` est créée.

Cette décision préserve la traçabilité et empêche le transfert ambigu d’un secret.

---

### Aucun Membership anticipé

La commande ne crée pas de `Membership`.

Le `Membership` n’existe qu’après l’acceptation effective de l’`Invitation`.

Cette séparation évite d’accorder un accès avant le consentement ou la vérification du destinataire.

---

## Synthèse

`CreateInvitation` matérialise l’intention d’accueillir une personne dans un `Workspace`.

Elle garantit que :

- le contexte existe ;
- l’acteur est autorisé ;
- le destinataire est valide ;
- le `Role` est compatible ;
- aucun doublon actif n’est créé ;
- l’`Invitation` commence dans l’état `Pending` ;
- aucun accès n’est encore accordé.

La commande produit `InvitationCreated`, mais ne signifie ni que l’invitation a été envoyée, ni qu’elle a été acceptée.
