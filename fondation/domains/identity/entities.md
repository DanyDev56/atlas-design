---
id: IDN-ENTITIES
title: Entities
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-30

references:
  - README.md
  - mission.md
  - scope.md
  - model.md
  - aggregates.md
  - value-objects.md
  - glossary/User.md
  - glossary/Membership.md
---

# Entities

Ce document décrit les entités métier du domaine **Identity**.

Pour chaque entité, il précise son rôle, son cycle de vie, ses responsabilités, ses invariants et ses interactions avec les autres concepts du domaine.

Les définitions présentes dans ce document constituent la référence de modélisation du domaine.

---

# User

## Résumé

| Élément | Valeur |
|----------|--------|
| Type | Entity |
| Aggregate | `User` |
| Identifiant | `UserId` |
| Cycle de vie | Indépendant |
| Création | `CreateUser` |
| Suppression | Non (désactivation uniquement) |

---

## Description

Le `User` représente l'identité d'une personne dans Atlas.

Il constitue le point d'entrée de toutes les opérations d'authentification et d'identification.

Le `User` existe indépendamment de toute appartenance à un `Workspace`.

---

## Pourquoi cette entité existe

Le domaine doit être capable d'identifier une personne avant de connaître les espaces auxquels elle appartient.

Le `User` répond à ce besoin.

---

## Identité

L'identité d'un `User` est portée par un identifiant unique et immuable.

Cet identifiant est utilisé dans l'ensemble des domaines Atlas.

Il ne change jamais pendant toute la durée de vie du `User`.

---

## Attributs

Le `User` possède notamment :

- son identifiant ;
- son adresse e-mail ;
- son nom d'affichage ;
- son état ;
- ses dates de création et de mise à jour.

Les détails techniques de stockage ne sont pas définis dans ce document.

---

## Relations

| Relation | Cardinalité |
|----------|-------------|
| `Membership` | 0..* |
| `Session` | 0..* |
| `Invitation` | 0..* (destinataire) |

---

## Cycle de vie

Le cycle de vie d'un `User` est indépendant des autres entités.

Un `User` peut :

- être créé ;
- être authentifié ;
- rejoindre un ou plusieurs `Workspace` ;
- quitter tous les `Workspace` ;
- rester présent dans Atlas sans appartenir à aucun `Workspace`.

---

## Règles métier

Le `User` ne représente jamais une appartenance.

Toutes les autorisations sont déterminées par les `Membership` associés au `User`.

La suppression physique d'un `User` n'est pas autorisée afin de préserver l'intégrité des données historiques.

---

## Commandes concernées

Le `User` participe notamment aux commandes suivantes :

- `commands/CreateUser.md`
- `commands/UpdateUser.md`
- `commands/DisableUser.md`
- `commands/EnableUser.md`

---

## Événements produits

Le `User` peut produire les événements suivants :

- `events/UserCreated.md`
- `events/UserUpdated.md`
- `events/UserDisabled.md`
- `events/UserEnabled.md`

---

## Décisions de conception

Le `User` est volontairement indépendant du `Workspace`.

Cette séparation permet :

- d'appartenir à plusieurs organisations ;
- de changer d'organisation sans recréer un compte ;
- de mutualiser une identité unique dans l'ensemble d'Atlas.

---

# Membership

## Résumé

| Élément | Valeur |
|----------|--------|
| Type | Entity |
| Aggregate | `Membership` |
| Identifiant | `MembershipId` |
| Cycle de vie | Dépend d'un `User` et d'un `Workspace` |
| Création | `AcceptInvitation`, `CreateMembership` |
| Suppression | Oui (quitter ou retirer un membre) |

---

## Description

Le `Membership` représente l'appartenance d'un `User` à un `Workspace`.

Il constitue le lien entre l'identité d'une personne et un espace de travail. Toutes les autorisations accordées à un utilisateur dans un `Workspace` transitent par son `Membership`.

---

## Pourquoi cette entité existe

Le domaine doit permettre à une même personne d'appartenir à plusieurs `Workspace`, tout en conservant des rôles et des autorisations indépendants dans chacun d'eux.

Le `Membership` répond à ce besoin.

---

## Identité

L'identité d'un `Membership` est portée par un identifiant unique et immuable.

Chaque `Membership` représente une appartenance unique entre un `User` et un `Workspace`.

---

## Attributs

Le `Membership` possède notamment :

- son identifiant ;
- son état ;
- son rôle ;
- ses dates de création et de mise à jour.

Les détails techniques de stockage ne sont pas définis dans ce document.

---

## Relations

| Relation | Cardinalité |
|----------|-------------|
| `User` | 1 |
| `Workspace` | 1 |
| `Role` | 1 |

---

## Cycle de vie

Le cycle de vie d'un `Membership` est lié à celui d'un `Workspace`.

Un `Membership` peut :

- être créé ;
- être activé ;
- changer de `Role` ;
- être suspendu ;
- être supprimé.

La suppression d'un `Membership` retire l'accès du `User` au `Workspace` sans supprimer son identité.

---

## Règles métier

Le `Membership` représente toujours une appartenance à un unique `Workspace`.

Toutes les autorisations d'un utilisateur dans un `Workspace` sont déterminées par le `Role` attribué à son `Membership`.

La suppression d'un `Membership` ne supprime jamais le `User`.

---

## Commandes concernées

Le `Membership` participe notamment aux commandes suivantes :

- `commands/CreateMembership.md`
- `commands/AcceptInvitation.md`
- `commands/ChangeRole.md`
- `commands/SuspendMembership.md`
- `commands/RestoreMembership.md`
- `commands/RemoveMembership.md`

---

## Événements produits

Le `Membership` peut produire les événements suivants :

- `events/MembershipCreated.md`
- `events/MembershipActivated.md`
- `events/MembershipRoleChanged.md`
- `events/MembershipSuspended.md`
- `events/MembershipRestored.md`
- `events/MembershipRemoved.md`

---

## Décisions de conception

Le `Membership` est une entité indépendante du `User`.

Cette séparation permet :

- à un `User` d'appartenir à plusieurs `Workspace` ;
- d'attribuer un rôle différent dans chaque `Workspace` ;
- de gérer les accès sans modifier l'identité du `User` ;
- d'assurer une isolation complète des autorisations entre les différents `Workspace`.

---

# Role

## Résumé

| Élément | Valeur |
|----------|--------|
| Type | Entity |
| Aggregate | `Role` |
| Identifiant | `RoleId` |
| Périmètre | Un unique `Workspace` |
| Cycle de vie | Dépendant du `Workspace` |
| Création | Automatique ou manuelle |
| Suppression | Autorisée sous conditions |

---

## Description

Le `Role` représente un ensemble nommé de `Permission` applicable au sein d’un `Workspace`.

Il définit les actions qu’un `Membership` est autorisé à effectuer dans ce `Workspace`.

Chaque `Role` appartient à un seul `Workspace`. Deux `Workspace` peuvent donc posséder des rôles portant le même nom tout en leur associant des ensembles de `Permission` différents.

---

## Pourquoi cette entité existe

Le domaine doit permettre d’attribuer des autorisations cohérentes à plusieurs `Membership` sans gérer chaque `Permission` individuellement.

Le `Role` répond à ce besoin en regroupant des `Permission` sous une identité métier stable.

Sa modélisation comme entité permet également :

- de fournir des rôles système prêts à l’emploi ;
- de créer des rôles personnalisés ;
- de faire évoluer les autorisations d’un rôle sans réattribuer chaque `Membership` ;
- de distinguer plusieurs rôles portant un même nom dans des `Workspace` différents.

---

## Identité

L’identité d’un `Role` est portée par un `RoleId` unique et immuable.

Le nom du rôle ne constitue pas son identité. Il peut évoluer sans créer un nouveau `Role`.

Un `Role` reste toujours rattaché au même `Workspace` pendant toute sa durée de vie.

---

## Attributs

Le `Role` possède notamment :

- son identifiant ;
- le `Workspace` auquel il appartient ;
- son nom ;
- sa description ;
- son éventuelle clé système ;
- son caractère système ou personnalisé ;
- son statut ;
- ses dates de création et de mise à jour.

Les `Permission` associées au `Role` font partie de sa définition fonctionnelle.

Les détails techniques de stockage ne sont pas définis dans ce document.

---

## Types de rôle

Atlas distingue deux catégories de `Role`.

### Rôle système

Un rôle système est créé automatiquement par Atlas lors de la création d’un `Workspace`.

Il fournit une configuration d’autorisations immédiatement utilisable et peut bénéficier de protections particulières.

Exemples :

- `Owner`
- `Admin`
- `Member`
- `Viewer`

Le libellé affiché d’un rôle système ne constitue pas sa clé métier. Sa fonction est identifiée par une clé système stable.

### Rôle personnalisé

Un rôle personnalisé est créé dans un `Workspace` par un `Membership` disposant des autorisations nécessaires.

Il permet d’adapter les accès à l’organisation réelle du `Workspace`.

Exemples :

- `Accountant`
- `Sales`
- `ProjectManager`
- `ExternalContributor`

---

## Relations

| Relation | Cardinalité | Description |
|----------|-------------|-------------|
| `Workspace` | 1 | Possède et délimite le `Role`. |
| `Membership` | 0..* | Reçoit le `Role`. |
| `Permission` | 1..* | Compose les autorisations du `Role`. |

---

## Cycle de vie

Le cycle de vie d’un `Role` est lié à celui de son `Workspace`.

Un `Role` peut :

- être créé automatiquement avec le `Workspace` ;
- être créé manuellement ;
- être renommé ;
- recevoir de nouvelles `Permission` ;
- perdre certaines `Permission` ;
- être désactivé ;
- être réactivé ;
- être supprimé lorsqu’aucune règle métier ne l’interdit.

La suppression d’un `Workspace` entraîne la disparition de tous les `Role` qui lui appartiennent.

---

## Règles métier

Un `Role` appartient toujours à un unique `Workspace`.

Un `Role` ne peut être attribué qu’à un `Membership` appartenant au même `Workspace`.

Un rôle personnalisé est composé exclusivement de `Permission` reconnues par Atlas.

Un utilisateur ne peut pas créer librement de nouvelles `Permission`.

La modification d’un `Role` affecte tous les `Membership` auxquels il est attribué.

Un rôle système protégé ne peut pas être supprimé.

Le rôle représentant la propriété du `Workspace` ne peut pas perdre les autorisations indispensables à son administration.

Un `Role` utilisé par un ou plusieurs `Membership` ne peut pas être supprimé sans qu’une stratégie de réattribution valide soit appliquée.

---

## Commandes concernées

Le `Role` participe notamment aux commandes suivantes :

- `commands/CreateRole.md`
- `commands/RenameRole.md`
- `commands/UpdateRoleDescription.md`
- `commands/GrantPermissionToRole.md`
- `commands/RevokePermissionFromRole.md`
- `commands/DisableRole.md`
- `commands/EnableRole.md`
- `commands/DeleteRole.md`

Les rôles système peuvent également être créés par une opération interne lors de la création d’un `Workspace`.

---

## Événements produits

Le `Role` peut produire les événements suivants :

- `events/RoleCreated.md`
- `events/RoleRenamed.md`
- `events/RoleDescriptionUpdated.md`
- `events/PermissionGrantedToRole.md`
- `events/PermissionRevokedFromRole.md`
- `events/RoleDisabled.md`
- `events/RoleEnabled.md`
- `events/RoleDeleted.md`

---

## Décisions de conception

Le `Role` est modélisé comme une entité et non comme une énumération.

Cette décision permet :

- de créer des rôles personnalisés ;
- de modifier leur nom sans modifier leur identité ;
- de faire évoluer leurs `Permission` ;
- d’isoler leur définition dans chaque `Workspace` ;
- d’ajouter de nouveaux rôles sans modifier le code applicatif.

Les noms tels que `Owner`, `Admin`, `Member` et `Viewer` ne doivent pas être utilisés comme fondement direct des règles d’autorisation.

Les décisions d’accès reposent sur les `Permission` associées au `Role`, et non sur son nom affiché.

Les fonctions particulières d’un rôle système sont identifiées par une clé stable plutôt que par son libellé.

---

# Permission

## Résumé

| Élément | Valeur |
|----------|--------|
| Type | System concept |
| Nature | Immutable definition |
| Identifiant | `PermissionKey` |
| Périmètre | Global à Atlas |
| Cycle de vie | Géré par la plateforme |
| Création | Définie par Atlas |
| Suppression | Non, uniquement dépréciation |

---

## Description

La `Permission` représente une autorisation métier élémentaire reconnue par Atlas.

Elle décrit une capacité précise pouvant être accordée à un `Role`.

Une `Permission` est globale à la plateforme. Elle n’appartient pas à un `Workspace` et ne peut pas être créée librement par ses membres.

---

## Pourquoi ce concept existe

Le domaine doit disposer d’un vocabulaire d’autorisation stable, explicite et réutilisable.

La `Permission` répond à ce besoin en définissant les actions que la plateforme sait autoriser ou refuser.

Elle permet également de séparer :

- la définition d’une autorisation ;
- la composition d’un `Role` ;
- l’attribution de ce `Role` à un `Membership`.

---

## Identité

L’identité d’une `Permission` est portée par une `PermissionKey` unique et immuable.

Exemples :

```text
clients.read
clients.create
clients.update
clients.delete

quotes.read
quotes.create
quotes.send

workspace.members.read
workspace.members.manage
workspace.roles.read
workspace.roles.manage
```

La clé constitue le contrat stable de la `Permission`.

Son libellé ou sa description peuvent évoluer sans modifier son identité.

---

## Structure d’une clé

Une `PermissionKey` suit une structure hiérarchique explicite.

Format recommandé :

```text
<resource>.<action>
```

ou, lorsqu’un contexte intermédiaire est nécessaire :

```text
<domain>.<resource>.<action>
```

Exemples :

```text
clients.read
invoices.issue
workspace.members.manage
workspace.roles.manage
```

La clé utilise :

- des termes en anglais ;
- des lettres minuscules ;
- le point comme séparateur ;
- une action explicite ;
- aucun libellé d’interface utilisateur.

---

## Attributs

Une définition de `Permission` possède notamment :

- sa clé ;
- son nom lisible ;
- sa description ;
- son domaine fonctionnel ;
- son éventuel statut de dépréciation ;
- sa date d’introduction.

Ces informations décrivent la capacité reconnue par Atlas, sans la rattacher à un utilisateur ou à un espace de travail particulier.

---

## Relations

| Relation | Cardinalité | Description |
|----------|-------------|-------------|
| `Role` | 0..* | Regroupe la `Permission`. |
| `Membership` | Indirecte | Bénéficie de la `Permission` via son `Role`. |
| `Workspace` | Indirecte | Utilise la `Permission` dans la composition de ses `Role`. |

---

## Cycle de vie

Le cycle de vie d’une `Permission` est géré par Atlas.

Une `Permission` peut :

- être introduite avec une fonctionnalité ;
- être associée à des rôles système ;
- être ajoutée à des rôles personnalisés ;
- être dépréciée ;
- être remplacée par une ou plusieurs nouvelles `Permission`.

Une `Permission` publiée ne doit pas être supprimée brutalement lorsqu’elle est encore utilisée.

Sa dépréciation doit prévoir une stratégie de migration explicite.

---

## Règles métier

Une `Permission` est toujours définie par Atlas.

Un `Workspace` ne peut pas créer sa propre `Permission`.

Une `Permission` n’est jamais attribuée directement à un `User`.

Une `Permission` n’est jamais attribuée directement à un `Membership`.

Toute attribution passe par un `Role`.

Chaque `Permission` représente une seule capacité métier.

Une clé de `Permission` ne doit pas dépendre du nom d’un `Role`.

Une clé publiée ne doit pas être renommée sans stratégie de compatibilité.

La dépréciation d’une `Permission` ne doit pas invalider silencieusement les `Role` existants.

---

## Catégories fonctionnelles

Les `Permission` peuvent être regroupées par domaine fonctionnel pour faciliter leur compréhension et leur administration.

Exemples :

### Gestion des clients

```text
clients.read
clients.create
clients.update
clients.delete
```

### Gestion des devis

```text
quotes.read
quotes.create
quotes.update
quotes.send
quotes.accept
quotes.refuse
```

### Gestion des factures

```text
invoices.read
invoices.create
invoices.issue
invoices.mark-paid
invoices.void
```

### Administration du `Workspace`

```text
workspace.settings.read
workspace.settings.update
workspace.members.read
workspace.members.manage
workspace.roles.read
workspace.roles.manage
```

Ces regroupements sont pédagogiques et n’affectent pas l’identité des `Permission`.

---

## Évaluation des autorisations

Lorsqu’une action protégée est demandée, Atlas évalue les `Permission` du `Role` attribué au `Membership` actif.

Le raisonnement conceptuel est le suivant :

```text
User
  │
  ▼
Membership dans le Workspace ciblé
  │
  ▼
Role attribué
  │
  ▼
Permission requise
```

L’existence d’une `Session` authentifie le `User`, mais ne lui accorde aucune `Permission` par elle-même.

---

## Commandes concernées

La `Permission` n’accepte pas de commandes de création, de modification ou de suppression issues d’un utilisateur.

Elle intervient néanmoins dans les commandes suivantes :

- `commands/GrantPermissionToRole.md`
- `commands/RevokePermissionFromRole.md`
- `commands/CreateRole.md`
- `commands/UpdateRolePermissions.md`

Les opérations d’introduction ou de dépréciation d’une `Permission` relèvent de l’évolution interne de la plateforme Atlas.

---

## Événements produits

La `Permission` ne produit pas directement d’événement métier dans le cadre de l’administration d’un `Workspace`.

Les changements d’association entre un `Role` et une `Permission` peuvent produire :

- `events/PermissionGrantedToRole.md`
- `events/PermissionRevokedFromRole.md`

L’introduction ou la dépréciation d’une `Permission` peut faire l’objet d’événements techniques ou de plateforme, sans appartenir au cycle de vie métier d’un `Workspace`.

---

## Décisions de conception

La `Permission` n’est pas modélisée comme une entité administrable.

Cette décision garantit :

- un catalogue d’autorisations maîtrisé par Atlas ;
- une sémantique uniforme entre les `Workspace` ;
- une évaluation prévisible des accès ;
- l’impossibilité de créer des autorisations que la plateforme ne sait pas interpréter ;
- une meilleure stabilité des contrats d’API et du code applicatif.

Les utilisateurs administrent la composition des `Role`, jamais la définition des `Permission`.

La `PermissionKey` constitue le contrat technique et métier stable. Les libellés destinés à l’interface peuvent être traduits ou reformulés sans modifier ce contrat.

---

# Invitation

## Résumé

| Élément | Valeur |
|----------|--------|
| Type | Entity |
| Aggregate | `Invitation` |
| Identifiant | `InvitationId` |
| Périmètre | Un unique `Workspace` |
| Cycle de vie | Temporaire |
| Création | `CreateInvitation` |
| Suppression | Non (expiration ou révocation) |

---

## Description

L'`Invitation` représente une proposition adressée à une personne afin qu'elle rejoigne un `Workspace`.

Elle constitue un processus d'entrée dans un espace de travail et permet de préparer la création d'un futur `Membership`.

Une `Invitation` possède son propre cycle de vie, indépendant de celui du `User`.

---

## Pourquoi cette entité existe

Le domaine doit permettre d'inviter une personne sans lui accorder immédiatement un accès au `Workspace`.

L'`Invitation` répond à ce besoin en matérialisant cette proposition jusqu'à son acceptation, son refus, son expiration ou sa révocation.

---

## Identité

L'identité d'une `Invitation` est portée par un `InvitationId` unique et immuable.

Une `Invitation` reste toujours associée au même `Workspace` et au même destinataire pendant toute sa durée de vie.

---

## Attributs

L'`Invitation` possède notamment :

- son identifiant ;
- l'adresse e-mail du destinataire ;
- le `Workspace` concerné ;
- le `Role` qui sera attribué ;
- son état ;
- sa date d'expiration ;
- ses dates de création et de mise à jour.

Les détails techniques de stockage ne sont pas définis dans ce document.

---

## États

Une `Invitation` peut être dans l'un des états suivants :

| État | Description |
|------|-------------|
| `Pending` | L'invitation est active et peut être acceptée. |
| `Accepted` | L'invitation a été acceptée et un `Membership` a été créé. |
| `Declined` | Le destinataire a refusé l'invitation. |
| `Expired` | La date de validité est dépassée. |
| `Revoked` | L'invitation a été annulée avant son utilisation. |

---

## Relations

| Relation | Cardinalité | Description |
|----------|-------------|-------------|
| `Workspace` | 1 | Représente le `Workspace` à rejoindre. |
| `Role` | 1 | Sera attribué au futur `Membership`. |
| `User` | 0..1 | Correspond au destinataire lorsqu'il possède déjà un compte. |
| `Membership` | 0..1 | Est créé lors de l'acceptation de l'`Invitation`. |

---

## Cycle de vie

Une `Invitation` peut :

- être créée ;
- être envoyée ;
- être renvoyée ;
- être acceptée ;
- être refusée ;
- expirer ;
- être révoquée.

Son cycle de vie s'arrête dès qu'un de ces états finaux est atteint.

Une `Invitation` terminée ne peut jamais redevenir active.

---

## Règles métier

Une `Invitation` appartient toujours à un unique `Workspace`.

Une `Invitation` cible toujours une seule adresse e-mail.

Une `Invitation` prépare toujours l'attribution d'un unique `Role`.

L'acceptation d'une `Invitation` entraîne la création d'un `Membership`.

Une `Invitation` ne donne jamais accès au `Workspace` tant qu'elle n'a pas été acceptée.

Une `Invitation` expirée, refusée ou révoquée ne peut plus être utilisée.

---

## Commandes concernées

L'`Invitation` participe notamment aux commandes suivantes :

- `commands/CreateInvitation.md`
- `commands/SendInvitation.md`
- `commands/ResendInvitation.md`
- `commands/AcceptInvitation.md`
- `commands/DeclineInvitation.md`
- `commands/RevokeInvitation.md`
- `commands/ExpireInvitation.md`

---

## Événements produits

L'`Invitation` peut produire les événements suivants :

- `events/InvitationCreated.md`
- `events/InvitationSent.md`
- `events/InvitationResent.md`
- `events/InvitationAccepted.md`
- `events/InvitationDeclined.md`
- `events/InvitationRevoked.md`
- `events/InvitationExpired.md`

---

## Décisions de conception

L'`Invitation` est modélisée comme une entité afin de conserver l'historique complet du processus d'invitation.

Sa durée de vie est indépendante de celle du `User`.

Une personne peut être invitée avant même de posséder un compte Atlas.

Le `Membership` n'est créé qu'au moment de l'acceptation de l'`Invitation`.

Une `Invitation` terminée reste conservée afin d'assurer la traçabilité des accès accordés ou refusés.

---

# Session

## Résumé

| Élément | Valeur |
|----------|--------|
| Type | Entity |
| Aggregate | `Session` |
| Identifiant | `SessionId` |
| Périmètre | Global à Atlas |
| Cycle de vie | Temporaire |
| Création | `CreateSession` |
| Suppression | Oui (fin ou révocation) |

---

## Description

La `Session` représente une authentification active d'un `User`.

Elle matérialise une connexion valide à Atlas et permet d'identifier le `User` pendant toute la durée de son utilisation de la plateforme.

Une même personne peut disposer simultanément de plusieurs `Session`, par exemple depuis plusieurs appareils.

---

## Pourquoi cette entité existe

Le domaine doit être capable de gérer les connexions actives des utilisateurs indépendamment de leur identité.

La `Session` répond à ce besoin en représentant chaque authentification comme une entité possédant son propre cycle de vie.

Cette séparation permet notamment :

- de connecter plusieurs appareils ;
- de révoquer une seule connexion ;
- d'auditer les connexions actives ;
- d'améliorer la sécurité de la plateforme.

---

## Identité

L'identité d'une `Session` est portée par un `SessionId` unique et immuable.

Une `Session` est toujours associée à un unique `User`.

---

## Attributs

La `Session` possède notamment :

- son identifiant ;
- le `User` authentifié ;
- son état ;
- sa date de création ;
- sa date d'expiration ;
- sa dernière activité ;
- les informations de connexion (appareil, navigateur, adresse IP, etc.).

Les détails techniques de stockage ne sont pas définis dans ce document.

---

## États

Une `Session` peut être dans l'un des états suivants.

| État | Description |
|------|-------------|
| `Active` | La session est valide et peut être utilisée. |
| `Expired` | La session a expiré automatiquement. |
| `Revoked` | La session a été révoquée avant son expiration. |

Une fois terminée, une `Session` ne peut jamais redevenir active.

---

## Relations

| Relation | Cardinalité | Description |
|----------|-------------|-------------|
| `User` | 1 | Possède la `Session`. |

---

## Cycle de vie

Une `Session` peut :

- être créée ;
- être utilisée ;
- expirer automatiquement ;
- être révoquée ;
- être supprimée conformément à la politique de conservation des données.

Chaque authentification réussie crée une nouvelle `Session`.

---

## Règles métier

Une `Session` appartient toujours à un unique `User`.

Une `Session` authentifie un `User`, mais ne définit jamais ses autorisations.

Les autorisations sont déterminées à partir du `Membership` sélectionné dans le `Workspace` concerné.

La révocation d'une `Session` retire immédiatement son droit d'accès.

Une `Session` expirée ne peut plus être utilisée.

---

## Commandes concernées

La `Session` participe notamment aux commandes suivantes :

- `commands/CreateSession.md`
- `commands/RefreshSession.md`
- `commands/RevokeSession.md`
- `commands/RevokeAllSessions.md`

---

## Événements produits

La `Session` peut produire les événements suivants :

- `events/SessionCreated.md`
- `events/SessionRefreshed.md`
- `events/SessionRevoked.md`
- `events/SessionExpired.md`

---

## Décisions de conception

La `Session` est modélisée comme une entité indépendante afin de représenter explicitement chaque connexion active.

Cette décision permet :

- la gestion multi-appareils ;
- la révocation individuelle des connexions ;
- l'audit des authentifications ;
- une meilleure traçabilité des accès.

Une `Session` prouve uniquement l'identité du `User`.

Les autorisations sont toujours déterminées par le `Membership` actif dans le `Workspace` concerné.