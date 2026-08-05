---
id: IDN-INVARIANTS
title: Invariants
status: In Review
owner: Product
version: 2.1.0
last_updated: 2026-08-05

references:
  - README.md
  - model.md
  - entities.md
  - aggregates.md
  - relationships.md
  - value-objects.md
  - workflows.md
  - commands/
  - events.md
---

# Invariants

Ce document définit les invariants métier du domaine **Identity**.

Un invariant est une règle qui doit rester vraie en permanence, quel que soit le chemin d'exécution utilisé pour modifier le domaine.

Une commande ne peut être validée que si tous les invariants concernés sont respectés.

---

# Principes

Les invariants du domaine respectent les principes suivants :

- ils expriment des vérités métier stables ;
- ils ne dépendent pas d'un écran ou d'un cas d'utilisation particulier ;
- ils doivent être vérifiés avant toute modification irréversible ;
- ils ne peuvent pas être contournés par une API, une importation ou une tâche automatique ;
- ils s'appliquent de manière cohérente à toutes les interfaces du produit ;
- ils doivent être testables ;
- ils doivent être rattachés à un agrégat ou à un processus métier clairement identifié.

---

# Classification

Les invariants sont classés selon leur portée.

| Catégorie | Description |
|-----------|-------------|
| Invariant local | Peut être garanti par un seul agrégat. |
| Invariant transversal | Implique plusieurs agrégats ou domaines. |
| Invariant d'unicité | Empêche l'existence de doublons métier. |
| Invariant de sécurité | Protège l'accès, l'identité ou les secrets. |
| Invariant de cycle de vie | Contraint les états et transitions possibles. |

---

# Vue d'ensemble

| Identifiant | Invariant |
|-------------|-----------|
| `IDN-INV-001` | Un `User` possède au maximum un `Membership` par `Workspace`. |
| `IDN-INV-002` | Un `Membership` référence toujours un `User`, un `Workspace` et un `Role` compatibles. |
| `IDN-INV-003` | Un `Membership` possède exactement un `Role` actif. |
| `IDN-INV-004` | Un `Role` ne contient que des `Permission` reconnues par Atlas. |
| `IDN-INV-005` | Un `Role` et les `Membership` qui l'utilisent appartiennent au même `Workspace`. |
| `IDN-INV-006` | Un `Workspace` actif possède toujours au moins un propriétaire actif. |
| `IDN-INV-007` | Une `Invitation` ne peut produire qu'un seul `Membership`. |
| `IDN-INV-008` | Une `Invitation` ne peut être acceptée que par son destinataire. |
| `IDN-INV-009` | Une `Invitation` terminée ne peut plus être utilisée. |
| `IDN-INV-010` | Une `Session` n'est utilisable que si elle est active, non expirée et rattachée à un `User` actif. |
| `IDN-INV-011` | Une `Permission` n'est jamais attribuée directement à un `User` ou à un `Membership`. |
| `IDN-INV-012` | Un secret d'accès ne doit jamais être traité comme un identifiant métier. |
| `IDN-INV-013` | La désactivation d'un `User` invalide son accès à Atlas. |
| `IDN-INV-014` | Les opérations sensibles doivent être autorisées dans le contexte d'un `Workspace`. |
| `IDN-INV-015` | Toute référence externe vers un `Workspace` doit désigner un `Workspace` existant et utilisable. |
| `IDN-INV-016` | Une adresse e-mail principale normalisée identifie au maximum un `User` non retiré. |
| `IDN-INV-017` | Un `User` actif possède une adresse e-mail principale vérifiée. |
| `IDN-INV-018` | Un `User` retiré ne peut jamais redevenir actif. |
| `IDN-INV-019` | Un `Role` archivé est terminal et ne participe plus aux autorisations courantes. |
| `IDN-INV-020` | Le graphe de permissions explicites d'un `Role` reste valide. |
| `IDN-INV-021` | Les politiques d'attribution et de transfert d'un `Role` restent valides. |
| `IDN-INV-022` | Une `Session` expirée ou révoquée est terminale. |

---

# Invariants du User

## IDN-INV-001 — Unicité du Membership par Workspace

> Un `User` possède au maximum un `Membership` pour un même `Workspace`.

La paire suivante doit être unique :

```text
UserId + WorkspaceId
```

Il est interdit de créer deux `Membership` actifs, suspendus ou historiques représentant la même relation métier sans stratégie explicite de réactivation.

### Motivation

Cet invariant garantit qu'un `User` :

- ne possède qu'une seule appartenance dans un `Workspace` ;
- ne reçoit qu'un seul `Role` actif dans ce contexte ;
- ne cumule pas artificiellement des autorisations via plusieurs `Membership`.

### Portée

Invariant transversal impliquant :

- `User`
- `Membership`
- `Workspace`

### Application

Cet invariant doit être vérifié lors de :

- `CreateMembership`
- `AcceptInvitation`
- `RestoreMembership`
- importations de membres
- synchronisations externes

### Comportement attendu

Lorsqu'un `Membership` supprimé existe déjà, le processus doit décider explicitement entre :

- restaurer le `Membership` existant ;
- refuser l'opération ;
- créer un nouveau cycle d'appartenance selon une décision documentée.

La création silencieuse d'un doublon est interdite.

---

## IDN-INV-013 — Désactivation du User

> Un `User` désactivé ne peut plus utiliser Atlas.

La désactivation d'un `User` doit empêcher :

- la création de nouvelles `Session` ;
- l'utilisation des `Session` existantes ;
- l'exécution d'actions dans un `Workspace`.

### Conséquences

Les `Membership` du `User` peuvent rester conservés pour la traçabilité.

La désactivation du `User` ne supprime pas automatiquement :

- ses `Membership` ;
- son historique ;
- ses invitations passées ;
- les événements qu'il a produits.

### Portée

Invariant de sécurité impliquant :

- `User`
- `Session`
- contrôle d'autorisation

### Application

Cet invariant doit être vérifié :

- à l'authentification ;
- lors de la résolution d'une `Session` ;
- avant l'autorisation d'une commande métier.

---

## IDN-INV-016 — Unicité de l'adresse e-mail principale

> Une adresse e-mail principale normalisée identifie au maximum un `User` non
> retiré.

La comparaison utilise la valeur normalisée d'`EmailAddress`.

```text
UNIQUE(NormalizedPrimaryEmailAddress)
WHERE UserStatus != Removed
```

Une politique de rétention peut continuer à réserver l'adresse après retrait
afin d'empêcher une réutilisation abusive. Cette politique doit être explicite.

### Application

- `CreateUser` ;
- `ChangeUserEmail` ;
- fusion ou import d'identités.

---

## IDN-INV-017 — Adresse principale vérifiée

> Un `User` actif possède une adresse e-mail principale dont la propriété a été
> prouvée.

```text
User.Status = Active
implies
User.EmailVerificationStatus = Verified
```

La création commence en `PendingVerification`. Seule une preuve valide permet la
transition initiale vers `Active`.

---

## IDN-INV-018 — Retrait terminal du User

> Un `User` retiré ne peut jamais redevenir actif.

Le retrait :

- rend toutes les sessions inutilisables ;
- interdit toute nouvelle authentification ;
- interdit toute nouvelle appartenance ;
- conserve l'identité technique nécessaire à l'audit ;
- ne restaure jamais automatiquement les anciens memberships.

Une nouvelle inscription après la fin de la politique de rétention crée une
nouvelle identité selon une décision explicite. Elle ne réactive pas le `User`
retiré.

---

# Invariants du Membership

## IDN-INV-002 — Cohérence des références

> Un `Membership` référence toujours un `User`, un `Workspace` et un `Role` compatibles.

Pour tout `Membership` :

```text
Membership.UserId must reference an existing User
Membership.WorkspaceId must reference an existing Workspace
Membership.RoleId must reference an existing Role
Membership.Role.WorkspaceId = Membership.WorkspaceId
```

### Motivation

Un `Membership` n'a de sens que lorsque ses trois références forment une relation cohérente.

### Portée

Invariant transversal impliquant :

- `Membership`
- `User`
- `Workspace`
- `Role`

### Application

Cet invariant doit être vérifié lors de :

- la création d'un `Membership` ;
- l'acceptation d'une `Invitation` ;
- la modification de son `Role` ;
- sa restauration.

---

## IDN-INV-003 — Un seul Role actif

> Un `Membership` possède exactement un `Role` actif.

Un `Membership` ne peut pas :

- posséder plusieurs `Role` simultanément ;
- être actif sans `Role` ;
- référencer un `Role` supprimé ;
- référencer un `Role` appartenant à un autre `Workspace`.

### Motivation

Le modèle d'autorisation repose sur une attribution unique et explicable.

L'ensemble des `Permission` du membre est obtenu exclusivement à partir de ce `Role`.

### Portée

Invariant local au `Membership`, avec validation externe du `Role`.

### Application

Cet invariant doit être vérifié lors de :

- `CreateMembership`
- `ChangeMembershipRole`
- `AcceptInvitation`
- `RestoreMembership`

---

## IDN-INV-005 — Cohérence du Workspace

> Un `Membership` et son `Role` appartiennent toujours au même `Workspace`.

La relation suivante doit toujours être vraie :

```text
Membership.WorkspaceId = Role.WorkspaceId
```

### Conséquence

Un `Role` ne peut jamais être réutilisé directement dans un autre `Workspace`.

Même deux `Role` portant le même nom dans deux espaces différents restent deux entités distinctes.

### Portée

Invariant transversal entre `Membership` et `Role`.

---

## IDN-INV-006 — Présence d'un propriétaire actif

> Un `Workspace` actif possède toujours au moins un `Membership` propriétaire actif.

Une opération ne doit jamais avoir pour résultat :

```text
active_owner_count = 0
```

### Opérations concernées

Cet invariant doit être protégé lors de :

- la suppression d'un `Membership` propriétaire ;
- la suspension d'un propriétaire ;
- le changement de son `Role` ;
- la désactivation du `Role` propriétaire ;
- la désactivation du dernier `User` propriétaire ;
- la suppression ou révocation d'un accès administratif.

### Comportement attendu

Avant de retirer les droits du dernier propriétaire, le système doit exiger qu'un autre `Membership` actif reçoive le rôle propriétaire.

### Cas particulier

La suppression définitive du `Workspace` peut lever cet invariant dans le cadre d'un workflow dédié.

### Portée

Invariant transversal impliquant :

- `Workspace`
- `Membership`
- `Role`
- `User`

---

# Invariants du Role

## IDN-INV-004 — Catalogue de Permission fermé

> Un `Role` ne contient que des `Permission` reconnues par Atlas.

Chaque `PermissionKey` associée à un `Role` doit exister dans le catalogue officiel de la plateforme.

Un utilisateur ne peut pas :

- créer une nouvelle `PermissionKey` ;
- modifier la signification d'une `Permission` ;
- injecter une clé inconnue ;
- utiliser une clé dépréciée lorsque son usage est interdit.

### Portée

Invariant local au `Role`, validé à partir d'un catalogue système.

### Application

Cet invariant doit être vérifié lors de :

- `CreateRole`
- `GrantPermissionToRole`
- `GrantPermissionToRole`
- `RevokePermissionFromRole`
- importations de rôles
- migrations de permissions

---

## Protection des rôles système

> Les caractéristiques protégées d'un rôle système ne peuvent pas être altérées par un utilisateur.

Selon le rôle concerné, les restrictions peuvent inclure :

- interdiction de suppression ;
- interdiction de modification de la `SystemRoleKey` ;
- interdiction de retirer certaines `Permission` minimales ;
- interdiction de désactivation ;
- interdiction de renommage fonctionnel.

Les restrictions précises doivent être définies pour chaque rôle système.

### Exemple

Le rôle identifié par :

```text
workspace_owner
```

doit toujours conserver les capacités minimales nécessaires à la propriété du `Workspace`.

---

## Archivage d'un Role utilisé

> Un `Role` utilisé par un ou plusieurs `Membership` courants ne peut pas être
> archivé sans traitement préalable.

Avant l'archivage, les `Membership` concernés doivent être :

- réaffectés à un autre `Role` ;
- suspendus selon une règle explicite ;
- supprimés dans le cadre d'un workflow autorisé.

L'archivage conserve les références historiques mais ne doit laisser aucun
`Membership` courant dépendre du rôle.

---

## IDN-INV-019 — Archivage terminal du Role

> Un `Role` archivé ne peut plus être attribué, transféré, activé ou modifié et
> ne fournit plus aucune permission effective.

```text
Role.Status = Archived
implies
EffectivePermissions = empty
```

L'archivage conserve l'identité, les métadonnées, les affectations historiques
et les événements du rôle. Identity 1.0 ne possède pas d'état `Deleted` ou
`Removed` pour le `Role`.

### Application

- `ArchiveRole` ;
- `EnableRole` et `DisableRole` ;
- toutes les commandes de modification du `Role` ;
- résolution des permissions.

---

## IDN-INV-020 — Graphe de permissions valide

> Les permissions explicites d'un `Role` forment toujours un ensemble valide
> vis-à-vis du catalogue global.

L'ensemble doit respecter :

- les dépendances obligatoires ;
- les incompatibilités ;
- les permissions minimales imposées aux rôles système ;
- l'état `Active` de chaque permission utilisée ;
- les règles de source de contrôle.

`GrantPermissionToRole` et `RevokePermissionFromRole` recalculent et valident
l'ensemble complet avant commit. Aucune cascade implicite n'est autorisée.

---

## IDN-INV-021 — Politiques du Role valides

> Tout `Role` possède une `RoleAssignmentPolicy` et une `RoleTransferPolicy`
> valides et compatibles avec son type, son état et sa source de contrôle.

Les politiques sont fournies dès `CreateRole`. Une modification remplace une
politique complète de manière atomique ; aucun état partiel n'est observable.

---

# Invariants de Permission

## IDN-INV-011 — Attribution indirecte uniquement

> Une `Permission` est toujours obtenue par l'intermédiaire d'un `Role`.

Les relations suivantes sont interdites :

```text
User -> Permission
Membership -> Permission
Session -> Permission
```

La seule chaîne autorisée est :

```text
User
  -> Membership
  -> Role
  -> Permission
```

### Motivation

Cet invariant garantit :

- l'explicabilité des droits ;
- la cohérence des autorisations ;
- l'absence d'exceptions individuelles invisibles ;
- la centralisation de la gestion des accès.

### Portée

Invariant structurel du modèle d'autorisation.

---

## Stabilité des PermissionKey

> Une `PermissionKey` publiée ne change pas silencieusement de signification.

Une évolution incompatible doit utiliser :

- une nouvelle `PermissionKey` ;
- une dépréciation documentée ;
- une stratégie de migration ;
- une période de compatibilité lorsque nécessaire.

Il est interdit de conserver la même clé tout en modifiant radicalement la capacité qu'elle représente.

---

# Invariants de l'Invitation

## IDN-INV-007 — Acceptation unique

> Une `Invitation` ne peut produire qu'un seul `Membership`.

L'acceptation d'une même `Invitation` doit être idempotente du point de vue métier.

Après une acceptation réussie :

- aucun second `Membership` ne peut être créé ;
- aucun second événement métier de création ne doit produire un effet supplémentaire ;
- toute nouvelle tentative doit retourner le résultat existant ou être refusée proprement.

### Portée

Invariant transversal impliquant :

- `Invitation`
- `Membership`

### Concurrence

Deux tentatives simultanées d'acceptation ne doivent pas créer deux `Membership`.

La protection doit exister au niveau :

- métier ;
- transactionnel ;
- persistance.

---

## IDN-INV-008 — Destinataire légitime

> Une `Invitation` ne peut être acceptée que par son destinataire.

La personne qui accepte doit prouver qu'elle contrôle l'`EmailAddress` ciblée par l'`Invitation`.

Lorsque le `User` est déjà authentifié :

```text
User.EmailAddress = Invitation.EmailAddress
```

selon les règles de normalisation du domaine.

Lorsque le destinataire ne possède pas encore de compte, la création ou la vérification du `User` doit préserver cette correspondance.

### Interdictions

Il est interdit :

- d'accepter une invitation pour un autre compte ;
- de transférer implicitement une invitation vers une autre adresse ;
- de modifier le destinataire après création.

Une nouvelle `Invitation` doit être créée en cas de changement d'adresse.

---

## IDN-INV-009 — États terminaux

> Une `Invitation` terminée ne peut plus être utilisée.

Les états suivants sont terminaux :

- `Accepted`
- `Declined`
- `Expired`
- `Revoked`

Depuis un état terminal, aucune transition vers `Pending` n'est autorisée.

### Transitions interdites

```text
Accepted -> Pending
Declined -> Pending
Expired -> Pending
Revoked -> Pending
```

Le renvoi d'une invitation ne réactive pas une invitation terminée.

Une nouvelle entité `Invitation` doit être créée.

---

## Validité temporelle

> Une `Invitation` ne peut pas être acceptée après son échéance.

La condition suivante doit être vraie au moment de l'acceptation :

```text
current_time < expiration_date
```

L'absence d'un traitement ayant préalablement modifié l'état en `Expired` ne rend pas l'invitation utilisable.

L'expiration est effective dès que l'échéance est atteinte.

---

## Cohérence du Role prévu

> Le `Role` prévu par une `Invitation` doit être actif et appartenir au `Workspace` ciblé.

Au moment de l'acceptation :

```text
Invitation.WorkspaceId = Role.WorkspaceId
Role.Status = Active
```

Si le `Role` n'est plus utilisable, l'acceptation doit être interrompue.

Le système ne doit pas choisir silencieusement un autre `Role`.

---

# Invariants de la Session

## IDN-INV-010 — Session utilisable

> Une `Session` n'est utilisable que si toutes ses conditions de validité sont satisfaites.

Une `Session` est utilisable uniquement lorsque :

```text
Session.Status = Active
current_time < Session.ExpirationDate
User.Status = Active
SessionToken is valid
```

L'échec d'une seule condition suffit à refuser l'accès.

---

## IDN-INV-022 — Terminalité de la Session

> Une `Session` `Expired` ou `Revoked` ne peut jamais redevenir `Active`, être
> rafraîchie ou être élevée.

Une nouvelle authentification crée une nouvelle `SessionId`. L'expiration
temporelle est effective même avant la matérialisation asynchrone de l'état
`Expired`.

---

## Révocation immédiate

> Une `Session` révoquée devient immédiatement inutilisable.

Après révocation :

- son token ne doit plus être accepté ;
- elle ne peut pas être rafraîchie ;
- elle ne peut pas redevenir active ;
- une nouvelle authentification est nécessaire.

La propagation de la révocation doit respecter les exigences de sécurité de la plateforme.

---

## Expiration effective

> Une `Session` expirée est inutilisable même si son état persistant indique encore `Active`.

La vérification temporelle prévaut sur un traitement différé d'expiration.

```text
current_time >= expiration_date
```

entraîne un refus d'accès.

Un traitement ultérieur peut officialiser la transition vers `Expired`.

---

## User unique

> Une `Session` reste associée au même `User` pendant toute sa durée de vie.

Il est interdit :

- de transférer une `Session` ;
- de remplacer son `UserId` ;
- de réutiliser une session après changement d'identité ;
- de convertir la session d'un compte en celle d'un autre.

---

# Invariants de sécurité

## IDN-INV-012 — Séparation entre identifiants et secrets

> Un identifiant métier ne constitue jamais une preuve d'autorisation.

Les couples suivants doivent rester distincts :

| Identifiant | Secret |
|-------------|--------|
| `InvitationId` | `InvitationToken` |
| `SessionId` | `SessionToken` |

La connaissance d'un identifiant ne doit jamais suffire à :

- accepter une `Invitation` ;
- utiliser une `Session` ;
- révoquer une ressource sensible ;
- contourner une vérification d'identité.

### Conséquences

Les secrets doivent :

- être imprévisibles ;
- être protégés au stockage ;
- ne pas apparaître dans les journaux ;
- être invalidés après utilisation ou révocation ;
- être comparés de manière sécurisée.

---

## IDN-INV-014 — Autorisation contextualisée

> Toute opération liée à un `Workspace` doit être autorisée dans ce `Workspace`.

Une autorisation valide dans un `Workspace` ne s'applique pas automatiquement à un autre.

L'évaluation doit porter sur :

```text
UserId
WorkspaceId
MembershipStatus
RoleId
PermissionKey
```

### Exemple

Un `User` possédant :

```text
workspace.members.change-role
```

dans le `Workspace A` ne peut pas gérer les membres du `Workspace B` sans `Membership` autorisé dans ce second espace.

---

## Refus par défaut

> Toute opération non explicitement autorisée est refusée.

L'absence d'une `Permission` requise doit produire un refus.

Le système ne doit pas inférer une autorisation à partir :

- du nom du `Role` ;
- de l'ancienneté du `User` ;
- de son adresse e-mail ;
- d'une interface masquée ;
- d'une convention non documentée.

---

## Session indépendante des autorisations

> Une `Session` authentifie un `User`, mais ne garantit aucune autorisation métier.

La présence d'une `Session` active prouve uniquement que l'identité du `User` a été authentifiée.

L'accès à une ressource dépend ensuite :

- du `Workspace` ciblé ;
- du `Membership` ;
- de son état ;
- du `Role` ;
- des `Permission`.

---

# Invariants liés au Workspace

## IDN-INV-015 — Référence externe valide

> Toute référence vers un `Workspace` doit désigner un espace existant et utilisable.

Lors de la création d'un concept lié à un `Workspace`, le domaine doit vérifier selon le besoin que celui-ci :

- existe ;
- n'est pas supprimé ;
- accepte encore l'opération demandée ;
- appartient au bon périmètre fonctionnel.

### Concepts concernés

- `Membership`
- `Role`
- `Invitation`

### Portée

Invariant transversal entre le domaine `Identity` et le domaine `Workspace`.

---

## Réaction à la fermeture d'un Workspace

> Lorsqu'un `Workspace` devient inutilisable, aucun nouvel accès ne peut y être accordé.

Selon le cycle de vie défini par le domaine `Workspace`, cela peut empêcher :

- la création d'un `Membership` ;
- l'envoi d'une `Invitation` ;
- la création d'un `Role` ;
- l'utilisation des `Membership` existants.

Le comportement exact doit être coordonné par une intégration explicite.

---

# Invariants de cycle de vie

## User

Transitions conceptuelles autorisées :

```text
PendingVerification -> Active
Active              -> Disabled
Disabled            -> Active
Active              -> Removed
Disabled            -> Removed
```

La réactivation est possible uniquement si la politique de sécurité l'autorise.
`Removed` est terminal.

---

## Membership

Transitions conceptuelles autorisées :

```text
Active -> Suspended
Suspended -> Active
Active -> Removed
Suspended -> Removed
```

La restauration depuis `Removed` doit être une décision métier explicite.

Elle ne doit pas être confondue avec une simple modification technique d'état.

---

## Role

Transitions conceptuelles autorisées :

```text
Active -> Disabled
Disabled -> Active
Active -> Archived
Disabled -> Archived
```

Un `Role` archivé ne redevient pas actif.

Une recréation éventuelle produit un nouveau `RoleId`. Identity 1.0 ne définit
pas d'état `Deleted` ou `Removed` pour le `Role`.

---

## Invitation

Transitions conceptuelles autorisées :

```text
Pending -> Accepted
Pending -> Declined
Pending -> Expired
Pending -> Revoked
```

Aucune transition ne sort d'un état terminal.

---

## Session

Transitions conceptuelles autorisées :

```text
Active -> Expired
Active -> Revoked
```

Aucune transition ne permet de revenir à `Active`.

---

# Invariants d'unicité

Les contraintes suivantes doivent être garanties à la fois par le domaine et, lorsque possible, par la persistance.

| Contrainte | Périmètre |
|------------|-----------|
| `UserId` unique | Atlas |
| `MembershipId` unique | Atlas |
| `RoleId` unique | Atlas |
| `InvitationId` unique | Atlas |
| `SessionId` unique | Atlas |
| `PermissionKey` unique | Atlas |
| `SystemRoleKey` unique | Atlas |
| `UserId + WorkspaceId` unique | Domaine `Identity` |
| `RoleName` normalisé unique | Un `Workspace`, si retenu |
| Invitation active par e-mail et Workspace | Un `Workspace`, selon politique métier |

---

# Invitations concurrentes

Le domaine doit définir une politique explicite concernant plusieurs invitations actives pour la même paire :

```text
EmailAddress + WorkspaceId
```

La politique recommandée est :

> Il ne peut exister qu'une seule `Invitation` active pour une même adresse e-mail dans un même `Workspace`.

Lorsqu'une invitation active existe déjà, une nouvelle demande doit :

- renvoyer l'invitation existante ;
- remplacer explicitement celle-ci ;
- ou être refusée.

La création silencieuse de plusieurs invitations concurrentes est déconseillée.

---

# Atomicité des processus transversaux

Certaines opérations impliquent plusieurs agrégats.

Leur cohérence doit être assurée par un workflow ou un service de domaine.

---

## Acceptation d'une Invitation

L'opération logique comprend :

1. vérifier l'`Invitation` ;
2. vérifier le destinataire ;
3. vérifier le `Workspace` ;
4. vérifier le `Role` ;
5. vérifier l'absence de `Membership` incompatible ;
6. créer ou restaurer le `Membership` ;
7. marquer l'`Invitation` comme acceptée ;
8. publier les événements correspondants.

Le résultat final doit respecter :

```text
Invitation.Status = Accepted
AND
exactly one Membership exists
```

Il est interdit de terminer avec :

```text
Invitation.Status = Accepted
AND
no Membership exists
```

ou :

```text
Invitation.Status = Pending
AND
a Membership was created from it
```

---

## Retrait du dernier propriétaire

L'opération logique comprend :

1. compter les propriétaires actifs ;
2. déterminer si le `Membership` ciblé est propriétaire ;
3. refuser si aucun autre propriétaire actif n'existe ;
4. appliquer la modification ;
5. publier l'événement correspondant.

La vérification et la modification doivent être protégées contre les accès concurrents.

---

## Suppression d'un Role

L'opération logique comprend :

1. vérifier que le `Role` peut être supprimé ;
2. identifier les `Membership` qui l'utilisent ;
3. exiger une réattribution ou un traitement explicite ;
4. vérifier la conservation d'au moins un propriétaire ;
5. supprimer fonctionnellement le `Role`.

Aucune référence active vers le `Role` supprimé ne doit subsister.

---

# Validation et responsabilité

Chaque invariant doit être appliqué au niveau approprié.

| Type d'invariant | Responsable principal |
|------------------|-----------------------|
| État interne d'une entité | Agrégat |
| Format d'une valeur | `Value Object` |
| Existence d'un agrégat référencé | Service applicatif ou domaine |
| Unicité métier | Domaine et persistance |
| Coordination multi-agrégats | Workflow ou Process Manager |
| Autorisation | Service de politique |
| Référence à un domaine externe | Port d'intégration |

---

# Défense en profondeur

Un invariant critique ne doit pas dépendre d'une seule couche.

Exemple pour l'unicité du `Membership` :

1. vérification dans le service applicatif ;
2. règle explicite dans le domaine ;
3. contrainte unique en base de données ;
4. gestion des conflits de concurrence ;
5. test automatisé.

La base de données ne remplace pas la règle métier.

La règle métier ne remplace pas les protections transactionnelles.

---

# Gestion des violations

Lorsqu'un invariant ne peut pas être respecté, l'opération doit échouer explicitement.

Une violation ne doit jamais :

- être corrigée silencieusement ;
- produire un état partiel ;
- choisir arbitrairement une autre valeur ;
- être ignorée sous prétexte de résilience technique.

Exemples d'erreurs métier :

```text
MembershipAlreadyExists
RoleBelongsToAnotherWorkspace
LastWorkspaceOwnerCannotBeRemoved
InvitationAlreadyCompleted
InvitationExpired
InvitationRecipientMismatch
UnknownPermission
SessionExpired
UserDisabled
```

Les noms définitifs des erreurs sont documentés dans les contrats de commandes et d'API.

---

# Observabilité

Les violations d'invariants doivent être observables sans exposer de données sensibles.

Les journaux peuvent inclure :

- l'identifiant de la commande ;
- les identifiants métier concernés ;
- le type d'invariant violé ;
- l'instant ;
- le résultat de l'opération.

Ils ne doivent pas inclure :

- `SessionToken` ;
- `InvitationToken` ;
- mot de passe ;
- secret d'authentification ;
- donnée personnelle inutile.

---

# Tests obligatoires

Chaque invariant doit être couvert au minimum par :

- un test nominal ;
- un test de violation ;
- un test de transition d'état lorsque pertinent ;
- un test de concurrence pour les invariants exposés aux courses ;
- un test d'intégration pour les contraintes de persistance.

Les invariants les plus critiques sont notamment :

- unicité du `Membership` ;
- protection du dernier propriétaire ;
- acceptation unique d'une `Invitation` ;
- révocation immédiate d'une `Session` ;
- cohérence du `Role` et du `Workspace` ;
- attribution indirecte des `Permission`.

---

# Synthèse

Les invariants du domaine `Identity` garantissent que :

- une personne ne possède qu'une appartenance par `Workspace` ;
- toute appartenance possède un `Role` cohérent ;
- les autorisations proviennent exclusivement des `Role` ;
- un `Workspace` conserve toujours un propriétaire actif ;
- une `Invitation` ne peut être utilisée qu'une seule fois par son destinataire ;
- une `Session` n'est valide que pour un `User` actif ;
- les secrets restent distincts des identifiants ;
- les références vers le domaine `Workspace` restent cohérentes ;
- les transitions d'état sont explicites et contrôlées.

Un invariant n'est pas une recommandation.

Toute opération qui ne peut pas le préserver doit être refusée.
