---
id: IDN-VALUE-OBJECTS
title: Value Objects
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-30

references:
  - README.md
  - model.md
  - entities.md
  - aggregates.md
  - relationships.md
  - invariants.md
  - foundation/language/naming-rules.md
  - foundation/language/domain-language.md
---

# Value Objects

Ce document définit les objets de valeur du domaine **Identity**.

Un `Value Object` représente une valeur métier dépourvue d'identité propre.

Il est défini uniquement par son contenu et reste immuable pendant toute sa durée d'utilisation.

---

# Principes

Les objets de valeur du domaine respectent les principes suivants :

- ils ne possèdent pas d'identité métier propre ;
- deux objets contenant les mêmes valeurs sont considérés comme égaux ;
- ils sont immuables ;
- ils valident leur propre cohérence ;
- ils ne peuvent pas exister dans un état invalide ;
- toute modification produit une nouvelle instance ;
- ils expriment explicitement une intention métier.

Un objet de valeur ne doit pas être réduit à une chaîne de caractères ou à un identifiant primitif lorsque sa signification métier impose des règles particulières.

---

# Vue d'ensemble

| Objet de valeur | Responsabilité |
|-----------------|----------------|
| `UserId` | Identifier un `User`. |
| `MembershipId` | Identifier un `Membership`. |
| `RoleId` | Identifier un `Role`. |
| `InvitationId` | Identifier une `Invitation`. |
| `SessionId` | Identifier une `Session`. |
| `WorkspaceId` | Référencer un `Workspace` appartenant au domaine externe `Workspace`. |
| `EmailAddress` | Représenter une adresse e-mail normalisée et valide. |
| `DisplayName` | Représenter le nom visible d'un `User`. |
| `RoleName` | Représenter le nom visible d'un `Role`. |
| `RoleDescription` | Représenter la description facultative d'un `Role`. |
| `SystemRoleKey` | Identifier la fonction stable d'un rôle système. |
| `PermissionKey` | Identifier une autorisation reconnue par Atlas. |
| `InvitationToken` | Représenter le secret permettant d'utiliser une `Invitation`. |
| `ExpirationDate` | Représenter une échéance future. |
| `SessionToken` | Représenter le secret associé à une `Session`. |
| `AuthenticationContext` | Représenter les informations contextuelles d'une authentification. |

---

# Identifiants métier

Les identifiants du domaine sont modélisés comme des objets de valeur distincts.

Cette séparation interdit l'utilisation accidentelle de l'identifiant d'un concept à la place d'un autre.

Par exemple, un `UserId` ne peut pas être utilisé comme un `MembershipId`, même si leurs représentations techniques sont identiques.

---

## UserId

> Le `UserId` identifie de manière unique et immuable un `User`.

### Responsabilités

Le `UserId` est responsable de :

- distinguer un `User` de tous les autres ;
- fournir une référence stable vers son identité ;
- empêcher toute confusion avec les autres identifiants du domaine.

### Règles

- il est obligatoire ;
- il est unique à l'échelle d'Atlas ;
- il est immuable ;
- il ne contient aucune information métier interprétable ;
- il ne doit pas être dérivé de l'adresse e-mail du `User`.

### Égalité

Deux `UserId` sont égaux lorsqu'ils possèdent exactement la même valeur.

---

## MembershipId

> Le `MembershipId` identifie de manière unique et immuable un `Membership`.

### Règles

- il est obligatoire ;
- il est unique ;
- il est immuable ;
- il ne révèle ni le `UserId`, ni le `WorkspaceId` ;
- il ne doit pas être construit par concaténation d'autres identifiants.

L'unicité de la relation entre un `User` et un `Workspace` est une règle métier distincte de l'identité du `Membership`.

---

## RoleId

> Le `RoleId` identifie de manière unique et immuable un `Role`.

### Règles

- il est obligatoire ;
- il est unique ;
- il est immuable ;
- il est indépendant du nom du `Role` ;
- il reste inchangé lorsque le rôle est renommé ;
- il ne constitue pas une clé système.

Le nom `Admin` ou `Owner` ne doit jamais être utilisé comme identifiant d'un `Role`.

---

## InvitationId

> L'`InvitationId` identifie de manière unique et immuable une `Invitation`.

### Règles

- il est obligatoire ;
- il est unique ;
- il est immuable ;
- il est distinct de l'`InvitationToken` ;
- il peut être exposé dans les contrats internes sans permettre l'utilisation de l'`Invitation`.

L'`InvitationId` identifie l'entité.

L'`InvitationToken` autorise une opération sensible sur cette entité.

---

## SessionId

> Le `SessionId` identifie de manière unique et immuable une `Session`.

### Règles

- il est obligatoire ;
- il est unique ;
- il est immuable ;
- il est distinct du `SessionToken` ;
- il ne doit pas contenir de données personnelles ;
- il ne doit pas permettre de déduire le `UserId`.

---

## WorkspaceId

> Le `WorkspaceId` référence un `Workspace` appartenant au domaine externe `Workspace`.

### Responsabilités

Le `WorkspaceId` permet au domaine `Identity` de référencer un `Workspace` sans dépendre de son état interne.

### Règles

- il est obligatoire dans tout concept rattaché à un `Workspace` ;
- il est immuable ;
- il ne peut pas être remplacé au cours de la vie d'un `Membership`, d'un `Role` ou d'une `Invitation` ;
- sa validité métier doit être vérifiée auprès du domaine propriétaire lorsque cela est nécessaire.

Le domaine `Identity` ne crée pas et ne modifie pas le `WorkspaceId`.

---

# EmailAddress

> L'`EmailAddress` représente une adresse e-mail valide et normalisée.

L'adresse e-mail constitue le principal moyen d'identification et de communication d'un `User` dans le domaine `Identity`.

Elle peut également identifier le destinataire d'une `Invitation` avant la création d'un `User`.

---

## Valeur

Une `EmailAddress` contient une adresse e-mail complète.

Exemple :

```text
daniel@example.com
```

---

## Normalisation

Avant toute comparaison ou conservation, une adresse e-mail doit être normalisée.

La normalisation minimale comprend :

- la suppression des espaces situés avant et après la valeur ;
- la mise en minuscules de la partie domaine ;
- la validation de la structure générale de l'adresse.

Atlas ne doit pas appliquer de transformation spécifique à un fournisseur de messagerie.

Par exemple, les variantes suivantes ne doivent pas être automatiquement fusionnées :

```text
daniel@example.com
daniel+atlas@example.com
```

Même lorsqu'un fournisseur les distribue vers la même boîte de réception, elles restent deux valeurs distinctes pour Atlas.

---

## Règles

Une `EmailAddress` :

- est obligatoire lorsqu'elle identifie un `User` ;
- est obligatoire pour le destinataire d'une `Invitation` ;
- doit respecter une structure d'adresse e-mail valide ;
- ne peut pas être vide ;
- ne peut pas contenir d'espace interne ;
- ne doit pas être utilisée sans normalisation préalable ;
- doit être comparée selon sa forme normalisée.

---

## Égalité

Deux `EmailAddress` sont égales lorsque leurs représentations normalisées sont identiques.

---

## Confidentialité

Une `EmailAddress` constitue une donnée personnelle.

Elle ne doit pas :

- apparaître inutilement dans les journaux techniques ;
- être incluse en clair dans une URL ;
- être utilisée comme secret ;
- être exposée à un autre `Workspace` sans justification métier.

---

# DisplayName

> Le `DisplayName` représente le nom visible d'un `User` dans Atlas.

Le `DisplayName` est destiné à l'affichage dans l'interface et dans les échanges entre utilisateurs.

Il ne constitue pas une identité unique.

---

## Exemples

```text
Daniel Goulard
Daniel
D. Goulard
```

---

## Règles

Un `DisplayName` :

- ne peut pas être vide ;
- est nettoyé de ses espaces superflus ;
- possède une longueur maximale définie par la plateforme ;
- peut être partagé par plusieurs `User` ;
- peut évoluer sans modifier l'identité du `User` ;
- ne doit pas être utilisé comme clé de recherche unique ;
- ne doit pas être utilisé pour déterminer des autorisations.

---

## Égalité

Deux `DisplayName` identiques ne signifient jamais qu'ils désignent le même `User`.

---

# RoleName

> Le `RoleName` représente le nom visible d'un `Role` dans un `Workspace`.

Il fournit un libellé compréhensible par les membres du `Workspace`.

---

## Exemples

```text
Owner
Admin
Accountant
External Contributor
```

---

## Règles

Un `RoleName` :

- ne peut pas être vide ;
- est nettoyé de ses espaces superflus ;
- possède une longueur maximale ;
- peut évoluer sans modifier le `RoleId` ;
- n'est unique que dans le périmètre d'un `Workspace`, lorsque cette règle est retenue ;
- ne doit jamais déterminer directement les autorisations ;
- ne doit pas remplacer une `SystemRoleKey`.

Les règles d'accès reposent sur les `Permission`, jamais sur le contenu du `RoleName`.

---

# RoleDescription

> La `RoleDescription` explique la fonction d'un `Role` dans un `Workspace`.

Elle aide les administrateurs à comprendre l'objectif d'un rôle avant de l'attribuer.

---

## Règles

Une `RoleDescription` :

- est facultative ;
- peut être vide lorsqu'aucune description n'est nécessaire ;
- possède une longueur maximale ;
- ne définit aucune règle d'autorisation ;
- peut évoluer sans affecter les `Membership` associés ;
- ne doit pas être interprétée par le système.

---

# SystemRoleKey

> La `SystemRoleKey` identifie la fonction stable d'un rôle système fourni par Atlas.

Elle permet de reconnaître un rôle protégé sans dépendre de son nom visible.

---

## Exemples

```text
workspace_owner
workspace_admin
workspace_member
workspace_viewer
```

---

## Règles

Une `SystemRoleKey` :

- est définie exclusivement par Atlas ;
- est unique à l'échelle de la plateforme ;
- est immuable ;
- utilise des caractères minuscules ;
- utilise le caractère `_` comme séparateur ;
- ne peut pas être créée librement par un utilisateur ;
- ne peut pas être modifiée depuis un `Workspace` ;
- est absente pour un rôle entièrement personnalisé.

Une `SystemRoleKey` ne constitue pas une `PermissionKey`.

Elle identifie une fonction système particulière, tandis qu'une `PermissionKey` identifie une capacité élémentaire.

---

# PermissionKey

> La `PermissionKey` identifie une autorisation métier reconnue par Atlas.

Elle constitue le contrat stable utilisé pour composer les `Role` et évaluer les accès.

---

## Format

Le format recommandé est :

```text
<resource>.<action>
```

Lorsqu'un contexte intermédiaire est nécessaire :

```text
<domain>.<resource>.<action>
```

Exemples :

```text
clients.read
quotes.send
invoices.issue
workspace.members.manage
workspace.roles.manage
```

---

## Règles

Une `PermissionKey` :

- est définie exclusivement par Atlas ;
- est unique à l'échelle de la plateforme ;
- est immuable après publication ;
- utilise exclusivement des termes en anglais ;
- utilise des lettres minuscules ;
- utilise le point comme séparateur ;
- représente une capacité métier unique ;
- ne dépend jamais du nom d'un `Role` ;
- ne contient aucun identifiant de `Workspace` ;
- ne peut pas être créée par un utilisateur.

---

## Actions recommandées

Les actions doivent utiliser un vocabulaire explicite et stable.

Exemples :

```text
read
create
update
delete
manage
send
issue
accept
decline
revoke
```

Une action générique comme `manage` doit être réservée aux cas où elle représente volontairement un ensemble cohérent d'opérations.

---

## Dépréciation

Une `PermissionKey` publiée ne doit pas être renommée ou supprimée sans stratégie de migration.

Lorsqu'elle devient obsolète, elle peut être marquée comme dépréciée puis remplacée progressivement.

---

# InvitationToken

> L'`InvitationToken` est un secret temporaire permettant d'utiliser une `Invitation`.

Il permet au destinataire de prouver qu'il possède le moyen d'accès transmis avec l'`Invitation`.

---

## Responsabilités

L'`InvitationToken` est responsable de :

- rendre une `Invitation` utilisable par son destinataire ;
- protéger l'opération d'acceptation ou de refus ;
- empêcher l'utilisation d'une invitation sans preuve de possession.

---

## Règles

Un `InvitationToken` :

- est généré de manière aléatoire et imprévisible ;
- possède une entropie suffisante ;
- est associé à une seule `Invitation` ;
- ne peut pas être réutilisé après acceptation, refus, expiration ou révocation ;
- possède une durée de validité limitée ;
- ne doit pas être enregistré en clair lorsqu'un condensat sécurisé suffit ;
- ne doit jamais apparaître dans les journaux ;
- ne doit pas être transmis à un tiers ;
- doit être comparé de manière sécurisée.

---

## Distinction avec InvitationId

| Concept | Responsabilité |
|---------|----------------|
| `InvitationId` | Identifier l'`Invitation`. |
| `InvitationToken` | Autoriser l'utilisation de l'`Invitation`. |

La connaissance de l'`InvitationId` ne doit pas suffire à accepter une `Invitation`.

---

# SessionToken

> Le `SessionToken` est un secret permettant de prouver l'utilisation légitime d'une `Session`.

Il est distinct de l'identifiant métier `SessionId`.

---

## Règles

Un `SessionToken` :

- est généré de manière aléatoire et imprévisible ;
- possède une entropie suffisante ;
- est associé à une seule `Session` ;
- possède une durée de validité limitée ;
- devient inutilisable après révocation de la `Session` ;
- ne doit jamais être exposé dans les journaux ;
- ne doit pas être stocké en clair lorsqu'une alternative sécurisée existe ;
- doit être renouvelé selon la politique de sécurité d'Atlas ;
- ne doit contenir aucune donnée personnelle lisible.

---

## Distinction avec SessionId

| Concept | Responsabilité |
|---------|----------------|
| `SessionId` | Identifier une `Session`. |
| `SessionToken` | Prouver le droit d'utiliser cette `Session`. |

---

# ExpirationDate

> L'`ExpirationDate` représente la date et l'heure après lesquelles une valeur temporaire ne peut plus être utilisée.

Elle est notamment utilisée par :

- l'`Invitation` ;
- la `Session` ;
- les secrets temporaires liés à l'authentification.

---

## Règles

Une `ExpirationDate` :

- représente un instant précis ;
- utilise une référence temporelle non ambiguë ;
- est comparée à l'heure de référence de la plateforme ;
- ne doit pas dépendre du fuseau horaire de l'interface ;
- doit être future au moment de la création du concept temporaire ;
- devient définitivement dépassée lorsque cet instant est atteint.

L'affichage peut être adapté au fuseau horaire de l'utilisateur, mais la valeur conservée doit rester indépendante de cet affichage.

---

## Évaluation

Une valeur temporaire est expirée lorsque :

```text
current_time >= expiration_date
```

L'expiration ne dépend pas obligatoirement de l'exécution préalable d'une commande `ExpireInvitation` ou `ExpireSession`.

La commande ou le traitement d'expiration peut officialiser un changement d'état, mais l'utilisation doit déjà être refusée dès que l'échéance est atteinte.

---

# AuthenticationContext

> L'`AuthenticationContext` représente les informations contextuelles connues lors de la création ou de l'utilisation d'une `Session`.

Il fournit des éléments utiles à la sécurité, à l'audit et à l'affichage des connexions actives.

---

## Composition

Un `AuthenticationContext` peut notamment contenir :

- le type d'appareil ;
- le nom du navigateur ;
- le système d'exploitation ;
- l'adresse IP observée ;
- une localisation approximative dérivée ;
- l'instant de l'authentification ;
- la méthode d'authentification utilisée.

Toutes ces informations ne sont pas nécessairement disponibles.

---

## Règles

Un `AuthenticationContext` :

- est immuable pour l'événement d'authentification qu'il décrit ;
- ne constitue jamais une preuve absolue de l'identité physique d'une personne ;
- ne détermine pas directement les autorisations ;
- ne doit pas contenir plus de données que nécessaire ;
- respecte la politique de conservation des données ;
- peut être partiellement masqué dans l'interface ;
- ne doit pas être utilisé comme identifiant unique d'un appareil.

---

## Utilisation

L'`AuthenticationContext` peut être utilisé pour :

- aider un `User` à reconnaître ses connexions ;
- détecter une activité inhabituelle ;
- produire des journaux d'audit ;
- appliquer des contrôles de sécurité supplémentaires.

Il ne remplace jamais la `Session`, le `User` ou les mécanismes d'authentification.

---

# États et énumérations

Les états des entités ne sont pas nécessairement des objets de valeur indépendants.

Ils constituent généralement des ensembles fermés de valeurs autorisées.

---

## UserStatus

Le `UserStatus` peut prendre les valeurs suivantes :

| Valeur | Description |
|--------|-------------|
| `Active` | Le `User` peut utiliser Atlas. |
| `Disabled` | Le `User` ne peut plus ouvrir ou utiliser une `Session`. |

Un `UserStatus` ne définit aucune appartenance à un `Workspace`.

---

## MembershipStatus

Le `MembershipStatus` peut prendre les valeurs suivantes :

| Valeur | Description |
|--------|-------------|
| `Active` | Le `Membership` permet l'accès au `Workspace`. |
| `Suspended` | L'accès est temporairement interdit. |
| `Removed` | L'appartenance est terminée. |

Un `Membership` supprimé fonctionnellement reste conservé lorsque la traçabilité l'exige.

---

## RoleStatus

Le `RoleStatus` peut prendre les valeurs suivantes :

| Valeur | Description |
|--------|-------------|
| `Active` | Le `Role` peut être attribué et utilisé. |
| `Disabled` | Le `Role` ne peut plus être attribué et son utilisation doit être encadrée. |
| `Deleted` | Le `Role` est retiré du modèle actif. |

La suppression fonctionnelle d'un `Role` ne doit pas rompre l'historique des actions passées.

---

## InvitationStatus

L'`InvitationStatus` peut prendre les valeurs suivantes :

| Valeur | Description |
|--------|-------------|
| `Pending` | L'`Invitation` peut encore être utilisée. |
| `Accepted` | L'`Invitation` a produit un `Membership`. |
| `Declined` | Le destinataire a refusé l'`Invitation`. |
| `Expired` | Sa période de validité est dépassée. |
| `Revoked` | Elle a été annulée par un membre autorisé. |

Les états `Accepted`, `Declined`, `Expired` et `Revoked` sont terminaux.

---

## SessionStatus

Le `SessionStatus` peut prendre les valeurs suivantes :

| Valeur | Description |
|--------|-------------|
| `Active` | La `Session` est utilisable. |
| `Expired` | Sa durée de validité est dépassée. |
| `Revoked` | Elle a été invalidée explicitement. |

Les états `Expired` et `Revoked` sont terminaux.

---

# Objets exclus

Les concepts suivants ne doivent pas être modélisés comme des objets de valeur du domaine `Identity`.

## User

Le `User` possède une identité et un cycle de vie propre.

Il constitue une entité.

---

## Membership

Le `Membership` possède une identité, un état et un cycle de vie.

Il constitue une entité.

---

## Role

Le `Role` possède une identité stable indépendante de son nom et peut évoluer dans le temps.

Il constitue une entité.

---

## Invitation

L'`Invitation` possède une identité et un cycle de vie temporaire.

Elle constitue une entité.

---

## Session

La `Session` possède une identité, un état et un cycle de vie.

Elle constitue une entité.

---

## Permission

La `Permission` est une définition système immuable identifiée par une `PermissionKey`.

Elle ne constitue pas une entité administrable du domaine.

La `PermissionKey` est l'objet de valeur utilisé par les agrégats.

---

# Règles d'implémentation

La documentation ne prescrit pas une technologie particulière, mais les implémentations doivent respecter les propriétés suivantes :

- construction impossible avec des données invalides ;
- immutabilité après création ;
- comparaison fondée sur les valeurs ;
- absence de méthode de modification interne ;
- sérialisation explicite ;
- validation centralisée dans l'objet ;
- aucune dépendance à l'infrastructure ;
- aucune dépendance à un framework applicatif.

Exemple conceptuel :

```php
final readonly class PermissionKey
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if (!preg_match('/^[a-z]+(?:\.[a-z-]+)+$/', $normalized)) {
            throw new InvalidPermissionKey();
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
```

Cet exemple illustre les propriétés attendues sans constituer un contrat d'implémentation définitif.

---

# Décisions de conception

Les identifiants sont modélisés comme des objets de valeur distincts afin de renforcer la sécurité de typage et d'exprimer clairement les références métier.

Les secrets tels que `InvitationToken` et `SessionToken` sont séparés des identifiants afin de distinguer :

- l'identification d'une ressource ;
- l'autorisation d'utiliser cette ressource.

Les noms affichés sont séparés des clés système afin de permettre :

- la traduction ;
- la personnalisation ;
- l'évolution des libellés ;
- la stabilité des règles métier.

Les états utilisent des ensembles fermés de valeurs afin d'empêcher les transitions vers des situations inconnues.

Les informations contextuelles d'authentification sont regroupées dans un `AuthenticationContext` afin de ne pas disperser les données de sécurité dans l'entité `Session`.

---

# Synthèse

Les objets de valeur du domaine `Identity` expriment explicitement les valeurs utilisées par les entités et les agrégats.

Ils garantissent notamment :

- l'intégrité des identifiants ;
- la validité des adresses e-mail ;
- la stabilité des clés d'autorisation ;
- la séparation entre identifiants et secrets ;
- la cohérence des échéances ;
- l'immutabilité des données métier.

Un objet de valeur doit toujours rendre le modèle plus explicite.

Lorsqu'une valeur possède des règles, un vocabulaire métier ou un risque de confusion, elle ne doit pas rester un simple type primitif.