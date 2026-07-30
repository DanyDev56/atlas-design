---
id: DOMAIN-IDENTITY-SCOPE
title: Identity Scope
status: Draft
owner: Product
version: 1.0
last_updated: 2026-07-30

references:
  - README.md
  - mission.md
  - model.md
---

# Périmètre

> Le domaine **Identity** est responsable de l'identité des utilisateurs et du contrôle d'accès au produit.
>
> Il définit **qui peut accéder à quoi**, mais jamais **ce qui est fait** après cet accès.

---

# Objectif

Ce document définit précisément les responsabilités du domaine.

Il permet de déterminer :

- ce que le domaine possède ;
- ce qu'il ne possède jamais ;
- les informations qu'il expose aux autres domaines ;
- les limites à respecter lors des développements futurs.

---

# Responsabilités

Le domaine **Identity** est responsable des concepts suivants.

## `User`

Gestion de l'identité d'un utilisateur.

Le domaine est propriétaire :

- des informations d'identité ;
- des informations d'authentification ;
- de l'état du compte.

---

## `Membership`

Gestion de l'appartenance d'un utilisateur à un `Workspace`.

Le domaine est propriétaire :

- du lien entre un `User` et un `Workspace` ;
- du rôle associé ;
- de l'état du membre.

---

## `Role`

Définition des rôles disponibles dans un `Workspace`.

Le domaine est responsable :

- de leur création ;
- de leur évolution ;
- de leur attribution.

---

## `Permission`

Définition des autorisations.

Le domaine décide :

- quelles permissions existent ;
- quels rôles les possèdent.

Il ne décide jamais comment ces permissions sont utilisées dans les règles métier.

---

## `Invitation`

Gestion complète du cycle de vie des invitations.

Le domaine est responsable :

- de leur émission ;
- de leur acceptation ;
- de leur expiration ;
- de leur révocation.

---

## `Session`

Gestion des sessions ouvertes.

Le domaine décide :

- quand une session est créée ;
- quand elle expire ;
- quand elle est révoquée.

---

# Ce que le domaine possède

Identity est propriétaire des données suivantes.

| Concept | Propriétaire |
|----------|--------------|
| `User` | Identity |
| `Membership` | Identity |
| `Role` | Identity |
| `Permission` | Identity |
| `Invitation` | Identity |
| `Session` | Identity |

Aucun autre domaine ne peut modifier directement ces données.

---

# Ce que le domaine ne possède jamais

Les concepts suivants appartiennent toujours à d'autres domaines.

| Concept | Domaine propriétaire |
|----------|----------------------|
| `Workspace` | Workspace |
| `Client` | CRM |
| `Opportunity` | CRM |
| `Quote` | Billing |
| `Invoice` | Billing |
| `Payment` | Billing |
| `Recommendation` | Advisor |
| `Signal` | Advisor |
| `BusinessHealth` | Business Health |
| `Notification` | Notifications |

Identity peut uniquement référencer ces concepts lorsque cela est nécessaire.

---

# Responsabilités interdites

Les responsabilités suivantes ne doivent jamais être ajoutées au domaine.

## Gestion des clients

Le domaine ne connaît jamais les `Client`.

---

## Gestion commerciale

Le domaine ne connaît jamais les `Opportunity`.

---

## Facturation

Le domaine ne crée jamais de `Quote`, d'`Invoice` ou de `Payment`.

---

## Analyse métier

Le domaine ne calcule jamais de `BusinessHealth`.

---

## Recommandations

Le domaine ne génère jamais de `Recommendation`.

---

## Notifications

Le domaine peut produire des événements.

Il ne décide jamais de la manière dont les notifications sont envoyées.

---

# Informations exposées

Les autres domaines peuvent utiliser les informations suivantes.

## Concernant un `User`

- son identifiant ;
- son état ;
- ses informations publiques.

---

## Concernant un `Membership`

- le `Workspace` associé ;
- le `Role` ;
- les `Permission` accordées.

---

## Concernant une `Session`

- son état ;
- sa validité.

---

# Informations volontairement cachées

Les autres domaines n'ont pas besoin de connaître :

- les mots de passe ;
- les mécanismes d'authentification ;
- les tokens ;
- les secrets de sécurité ;
- les historiques de connexion.

Ces éléments restent strictement internes au domaine.

---

# Dépendances

Le domaine **Identity** ne dépend d'aucun domaine métier.

Il peut utiliser des composants techniques (base de données, fournisseur OAuth, serveur SMTP…), mais aucun autre domaine fonctionnel.

---

# Relations avec les autres domaines

| Domaine | Relation |
|----------|----------|
| `Workspace` | Référence un `Workspace` via un `Membership`. |
| CRM | Vérifie les autorisations avant toute opération. |
| Billing | Vérifie les autorisations avant toute opération. |
| Advisor | Vérifie les autorisations avant toute opération. |
| Notifications | Consomme les événements produits par Identity. |

---

# Critères d'appartenance

Avant d'ajouter une nouvelle fonctionnalité à **Identity**, les questions suivantes doivent être posées.

## Cette fonctionnalité concerne-t-elle l'identité d'un utilisateur ?

Si oui, elle appartient probablement à Identity.

---

## Concerne-t-elle l'authentification ?

Si oui, elle appartient à Identity.

---

## Concerne-t-elle une autorisation ?

Si oui, elle appartient probablement à Identity.

---

## Concerne-t-elle une donnée métier ?

Si oui, elle n'appartient probablement pas à Identity.

---

## La fonctionnalité peut-elle être réutilisée par tous les domaines ?

Si oui, elle appartient probablement à Identity.

---

# Philosophie

> **Le domaine Identity protège l'accès au produit, mais ne participe jamais à l'exécution des règles métier.**

Son rôle est de fournir un cadre de confiance commun à l'ensemble d'Atlas.

---

# Références

- `README.md`
- `mission.md`
- `model.md`
- `entities.md`
- `permissions.md`