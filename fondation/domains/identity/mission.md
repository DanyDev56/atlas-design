---
id: DOMAIN-IDENTITY-MISSION
title: Identity Mission
status: Draft
owner: Product
version: 1.0
last_updated: 2026-07-30

references:
  - README.md
  - scope.md
  - ../../constitution.md
---

# Mission

> Garantir que chaque action effectuée dans Atlas puisse être attribuée à un utilisateur authentifié, appartenant à un ou plusieurs `Workspace` et disposant des autorisations nécessaires.

---

# Objectif

Le domaine **Identity** fournit les fondations de sécurité de l'ensemble du produit.

Sa mission consiste à répondre à trois questions essentielles :

1. **Qui est l'utilisateur ?**
2. **À quels `Workspace` appartient-il ?**
3. **Que peut-il faire ?**

Tous les autres domaines reposent sur ces informations pour appliquer leurs propres règles métier.

Identity ne décide jamais du comportement fonctionnel des autres domaines ; il fournit uniquement les informations nécessaires pour sécuriser leurs opérations.

---

# Pourquoi ce domaine existe

Sans un domaine dédié à l'identité et au contrôle d'accès :

- chaque domaine devrait gérer ses propres utilisateurs ;
- les permissions seraient dupliquées ;
- les règles d'autorisation deviendraient incohérentes ;
- la sécurité dépendrait de chaque implémentation.

Centraliser ces responsabilités permet :

- une authentification unique ;
- un modèle d'autorisation homogène ;
- une meilleure évolutivité ;
- une maintenance simplifiée.

Identity constitue donc un **socle transversal** sur lequel repose l'ensemble du produit.

---

# Les questions auxquelles Identity répond

Le domaine est responsable de répondre aux questions suivantes.

## Qui est cet utilisateur ?

À partir d'une identité, Identity est capable de déterminer :

- son existence ;
- son état (actif, suspendu...) ;
- ses informations d'authentification.

---

## À quels `Workspace` appartient-il ?

Un utilisateur peut appartenir à plusieurs `Workspace`.

Cette appartenance est représentée par un `Membership`.

Identity est responsable de maintenir cette relation.

---

## Quel est son rôle ?

Chaque `Membership` possède un `Role`.

Le rôle détermine les autorisations accordées dans un `Workspace`.

---

## Quelles actions sont autorisées ?

Identity vérifie les `Permission` associées au `Role`.

Les autres domaines peuvent alors déterminer si une opération est autorisée.

Ils ne définissent jamais eux-mêmes les permissions.

---

# Les questions auxquelles Identity ne répond jamais

Les sujets suivants appartiennent à d'autres domaines.

Identity ne sait jamais :

- quels clients possède un utilisateur ;
- quels devis il a créés ;
- quelles factures il peut consulter ;
- quel est son chiffre d'affaires ;
- quelles recommandations lui sont proposées ;
- quel est le score de son activité.

Ces informations sont détenues par leurs domaines respectifs.

---

# Valeur apportée au produit

Le domaine **Identity** apporte plusieurs bénéfices fondamentaux.

## Sécurité

Toutes les décisions d'autorisation reposent sur une source unique.

---

## Cohérence

Les mêmes règles d'accès sont appliquées dans tout Atlas.

---

## Traçabilité

Chaque action peut être associée à un utilisateur identifié.

---

## Évolutivité

Les mécanismes d'authentification et d'autorisation peuvent évoluer indépendamment des autres domaines.

Par exemple :

- MFA ;
- Passkeys ;
- SSO ;
- OAuth ;
- SCIM.

Aucun autre domaine n'a besoin d'être modifié pour intégrer ces évolutions.

---

# Principes directeurs

Le domaine **Identity** applique les principes suivants.

## Authentification avant autorisation

Une identité doit être vérifiée avant toute décision d'accès.

---

## Autorisation explicite

Une action est interdite tant qu'une `Permission` ne l'autorise pas explicitement.

---

## Responsabilité unique

Identity détermine les droits.

Les autres domaines appliquent leurs règles métier.

---

## Séparation des responsabilités

Identity ne contient aucune logique métier liée :

- aux clients ;
- à la facturation ;
- au CRM ;
- aux recommandations ;
- aux indicateurs métier.

---

# Philosophie

> **Identity fournit la confiance nécessaire pour savoir qui agit et avec quels droits, sans jamais intervenir dans les décisions métier des autres domaines.**

Cette séparation garantit une architecture simple, cohérente et durable.

---

# Références

- `README.md`
- `scope.md`
- `model.md`
- `../../constitution.md`