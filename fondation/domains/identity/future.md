---
id: IDN-FUTURE
title: Identity Future
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - consolidation-matrix.md
  - decision-record.md
---

# Évolutions futures d'Identity

Ce document isole les capacités qui ne font pas partie du contrat Identity 1.0.

Une extension listée ici ne doit pas modifier implicitement les invariants ou
les contrats 1.0. Son introduction nécessite une décision formelle et une mise à
jour de la matrice de consolidation.

---

## Plusieurs rôles par Membership

Identity 1.0 conserve exactement un `Role` courant par `Membership` actif.

Une future extension pourrait introduire :

- `RoleGrant` ;
- `AdditionalRoleAssignment` ;
- `TemporaryPrivilegeGrant`.

Elle devra redéfinir la résolution des permissions, les conflits, la séparation
des responsabilités et l'historique des affectations.

---

## Rôles globaux de plateforme

Les rôles 1.0 appartiennent toujours à un seul `Workspace`.

Les responsabilités d'administration globale de la plateforme relèveront d'un
modèle distinct et ne devront pas contourner l'autorisation contextuelle des
workspaces.

---

## Identités non humaines

Identity 1.0 crée uniquement des `HumanUser`.

Les concepts suivants sont réservés pour une version future :

- `ServiceAccount` ;
- `MachineIdentity` ;
- identité d'intégration ;
- agent autonome.

Ils n'utiliseront pas automatiquement le cycle de vie, les sessions ou les
méthodes d'authentification d'un utilisateur humain.

---

## Fédération et provisioning externe

Les adaptateurs SAML, OIDC entreprise, SCIM et la synchronisation d'annuaires
sont reportés.

Les champs et politiques déjà prévus pour une source externe constituent des
points d'extension. Ils ne rendent pas ces workflows actifs en 1.0.

---

## Localisation des rôles

Il reste à décider si les traductions des noms et descriptions appartiennent :

- au `Role` ;
- à une projection ;
- à un catalogue produit ;
- à un mécanisme de surcharge par workspace.

Identity 1.0 conserve un nom canonique unique et une description non localisée.

---

## Exigences continues et remédiation

Les politiques 1.0 peuvent déclarer des exigences continues, mais aucun moteur
générique de remédiation n'est inclus.

Une version future devra définir :

- la fréquence de réévaluation ;
- le propriétaire des plans de remédiation ;
- les délais de grâce ;
- les événements de non-conformité ;
- les effets sur les sessions et memberships.

---

## Historique et projections avancées

Identity 1.0 conserve les événements et les données d'audit nécessaires.

Les projections suivantes sont futures :

- historique complet des versions de politique ;
- analyse des chemins de privilèges ;
- graphe temporel des autorisations ;
- détection comportementale avancée ;
- score de risque d'identité.

---

## Authentification avancée

Les passkeys, facteurs matériels, politiques adaptatives et restrictions
géographiques avancées pourront enrichir les preuves d'authentification.

Elles devront conserver les contrats fondamentaux suivants :

- aucune credential brute dans le domaine ou les événements ;
- niveau d'assurance explicite ;
- preuve bornée et non rejouable ;
- révocation et audit indépendants.
