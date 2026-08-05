---
id: DOMAIN-IDENTITY-README
title: Identity Domain
status: In Review
owner: Product
version: 1.3.0
last_updated: 2026-08-05

references:
  - ../../vision/mission.md
  - ../../vision/vision.md
  - ../../constitution.md
  - ../../language/glossary.md
---

# Identity

> Le domaine **Identity** est responsable de l'identité des utilisateurs et du contrôle d'accès à Atlas.
>
> Il détermine **qui est un utilisateur**, **à quels `Workspace` il appartient** et **ce qu'il est autorisé à faire**.
>
> Il ne connaît jamais les données métier des autres domaines.

---

# Objectif

Le domaine **Identity** centralise toutes les règles relatives :

- à l'identité des utilisateurs ;
- à l'authentification ;
- aux autorisations ;
- aux rôles ;
- aux permissions ;
- aux invitations ;
- aux sessions.

Tous les autres domaines s'appuient sur **Identity** pour savoir **qui effectue une action** et **si cette action est autorisée**.

---

# Résumé du domaine

| Propriété | Valeur |
|-----------|--------|
| Type | Core Domain |
| Criticité | Critique |
| Inclus dans le MVP | Oui |
| Dépend de | Contrat public de `Workspace` |
| Utilisé par | Tous les domaines |

Le contrat documentaire Identity 1.0 est `In Review`. Ses quality gates et sa
traçabilité complète sont publiées dans
[`consolidation-matrix.md`](consolidation-matrix.md). Le passage à `Stable`
attend une implémentation et des tests de domaine conformes.

---

# Langage ubiquitaire

Les concepts suivants constituent le vocabulaire officiel du domaine.

Ils doivent être utilisés tels quels dans :

- le code ;
- les événements ;
- les commandes ;
- les API ;
- les tests ;
- les diagrammes.

## Entités

- `User`
- `Membership`
- `Role`
- `Permission`
- `Invitation`
- `Session`

## Commands

Le catalogue des commandes actuellement documentées se trouve dans
[`commands/README.md`](commands/README.md).

Une commande utilise le nom `Membership` lorsqu'elle modifie l'appartenance ou
le rôle contextuel d'un utilisateur.

## Events

Les événements décrivent des faits passés avec le vocabulaire officiel.

Exemples :

- `MembershipCreated` ;
- `MembershipRoleChanged` ;
- `InvitationAccepted` ;
- `RolePermissionGranted` ;
- `SessionCreated` ;
- `SessionRevoked`.

Le catalogue normatif et les règles de confidentialité sont définis dans
[`events.md`](events.md).

---

# Responsabilités

Le domaine **Identity** est propriétaire de :

- `User`
- `Membership`
- `Role`
- `Invitation`
- `Session`

Il opère le registre global des `Permission` et leurs affectations aux `Role`.
Chaque domaine fonctionnel reste propriétaire du sens des capacités qu'il
déclare.

Il est également responsable de :

- l'authentification ;
- la gestion des sessions ;
- la gestion des rôles ;
- la gestion des permissions ;
- la gestion des invitations ;
- la vérification des autorisations.

---

# Hors périmètre

Le domaine **Identity** ne possède jamais les données des autres domaines.

Par exemple :

- `Workspace`
- `Client`
- `Opportunity`
- `Quote`
- `Invoice`
- `Payment`
- `Recommendation`
- `BusinessHealth`

Il peut faire référence à ces concepts, mais leur cycle de vie est géré ailleurs.

---

# Modèle conceptuel

```mermaid
flowchart LR

User --> Membership
Membership --> Role
Role --> Permission

Membership -. référence .-> Workspace
```

Le cœur du domaine est le `Membership`.

Un `User` n'appartient jamais directement à un `Workspace`.

L'appartenance est toujours matérialisée par un `Membership`, qui associe un `User`, un `Workspace` et un `Role`.

Cette modélisation permet notamment :

- plusieurs `Workspace` par `User` ;
- plusieurs membres par `Workspace` ;
- une gestion propre des invitations ;
- une évolution future du modèle d'autorisation.

---

# Dépendances

Le domaine **Identity** ne dépend d'aucune donnée opérationnelle de CRM, Billing,
Advisor ou Business Health.

Il utilise toutefois le contrat public de `Workspace` pour valider qu'un espace
référencé existe et reste utilisable. Il ne possède jamais son cycle de vie.

Les autres domaines utilisent le contrat public d'Identity pour authentifier un
acteur et évaluer ses autorisations.

```mermaid
flowchart TD

Identity --> WorkspaceContract["Workspace public contract"]
CRM --> Identity
Billing --> Identity
Advisor --> Identity
Notifications --> Identity
BusinessHealth --> Identity
```

---

# Principes de conception

## Source de vérité

Le domaine **Identity** est la source officielle concernant :

- les utilisateurs ;
- les rôles ;
- le registre des permissions et leurs affectations ;
- les sessions.

Aucun autre domaine ne doit dupliquer ces informations.

---

## Principe du moindre privilège

Les permissions sont toujours accordées explicitement.

Un utilisateur ne reçoit jamais plus de droits que nécessaire.

---

## Appartenance explicite

L'accès à un `Workspace` passe toujours par un `Membership`.

Il n'existe aucun accès implicite.

---

## Autorisation par permissions

Les autorisations sont déterminées à partir des `Permission`.

Les autres domaines ne doivent jamais contenir de logique d'autorisation codée en dur.

---

# Philosophie

> **Identity sait qui est un utilisateur et ce qu'il est autorisé à faire, jamais les actions métier qu'il réalise.**

Si une fonctionnalité nécessite de connaître des informations comme un `Client`, une `Invoice` ou une `Recommendation`, elle appartient probablement à un autre domaine.

---

# Contenu du domaine

| Document | Description |
|----------|-------------|
| [`mission.md`](mission.md) | Explique pourquoi le domaine existe. |
| [`scope.md`](scope.md) | Définit précisément son périmètre. |
| [`model.md`](model.md) | Présente le modèle métier du domaine. |
| [`glossary/`](glossary/) | Définit les concepts officiels. |
| [`entities.md`](entities.md) | Décrit les entités métier. |
| [`aggregates.md`](aggregates.md) | Définit les agrégats. |
| [`relationships.md`](relationships.md) | Documente les relations et cardinalités. |
| [`value-objects.md`](value-objects.md) | Catalogue les objets valeur. |
| [`value-objects/`](value-objects/) | Détaille les politiques de rôle complexes. |
| [`commands/`](commands/) | Décrit les commandes actuellement spécifiées. |
| [`events.md`](events.md) | Catalogue les faits métier produits. |
| [`permissions.md`](permissions.md) | Définit le catalogue et l'évaluation des permissions. |
| [`workflows.md`](workflows.md) | Décrit les orchestrations transversales. |
| [`api.md`](api.md) | Définit le contrat public d'Identity. |
| [`integrations.md`](integrations.md) | Documente les ports et garanties externes. |
| [`invariants.md`](invariants.md) | Regroupe les règles métier immuables. |
| [`decision-record.md`](decision-record.md) | Explique les décisions de conception. |
| [`future.md`](future.md) | Isole les extensions hors Identity 1.0. |
| [`consolidation-matrix.md`](consolidation-matrix.md) | Suit les quality gates du domaine. |

---

# Références

- [`mission.md`](mission.md)
- [`scope.md`](scope.md)
- [`model.md`](model.md)
- [`glossary/`](glossary/)
- [`events.md`](events.md)
- [`permissions.md`](permissions.md)
- [`workflows.md`](workflows.md)
- [`api.md`](api.md)
- [`../../constitution.md`](../../constitution.md)
- [`../../language/glossary.md`](../../language/glossary.md)
