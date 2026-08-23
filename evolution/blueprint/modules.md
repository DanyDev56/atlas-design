---
id: BPT-004
title: Product Modules
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-23

references:
  - README.md
  - product-map.md
  - dashboard.md
  - navigation.md
  - backoffice.md
  - ../roadmap/mvp-scope.md
  - ../../fondation/domains/README.md
  - ../../fondation/decisions/ADR-004-operator-control-plane.md
---

# Modules du produit

## Bounded contexts du MVP

| Domaine | Responsabilité visible dans le MVP | Exposition principale |
|---|---|---|
| Identity | inscription, authentification, sessions, memberships, rôles et autorisation | connexion, profil, membres et rôles. |
| Workspace | identité de l'activité, préférences, identité de facturation et cycle de vie | onboarding et paramètres Workspace. |
| CRM | clients, contacts, opportunités et activités commerciales | espace CRM et pipeline. |
| Billing | devis, factures, paiements, avoirs et documents financiers | espace Billing et vues publiques bornées. |
| Analytics | faits, métriques, fraîcheur et snapshots déterministes | explications intégrées ; pas de navigation autonome requise. |
| Business Health | interprétation versionnée de la santé récente | synthèse et explication Business Health. |
| Advisor | priorités, recommandations, preuves et décisions utilisateur | espace Advisor et priorité du Dashboard. |
| Notifications | inbox personnelle, préférences et remise des priorités importantes | inbox globale et paramètres personnels. |

Ces noms désignent des frontières de propriété. Leur déploiement peut rester
modulaire dans un même processus au MVP.

---

## Surfaces applicatives

### Dashboard

Le Dashboard compose les lectures publiques des domaines. Il ne s'agit pas
d'un bounded context et il ne possède ni score, ni montant, ni Recommendation.
Son contrat détaillé se trouve dans [`dashboard.md`](dashboard.md).

### Onboarding

L'onboarding orchestre les intentions Identity et Workspace jusqu'à un
Workspace actif. Il propose ensuite la saisie initiale ou l'import historique
défini dans [`historical-import.md`](historical-import.md). Il ne possède aucun
Client, Quote, Invoice, Payment ou résultat d'import.

### Settings

Settings regroupe des écrans appartenant à Identity, Workspace et
Notifications. La proximité de navigation ne crée aucune propriété partagée.

### Back-office opérateur — proposition post-MVP

Le back-office n'appartient pas à la navigation client et n'est pas un
Dashboard doté de droits supplémentaires. `ADR-004` propose un contexte de
support `Operations` pour les dossiers support, la cohorte beta, les demandes
de données, les approbations et l'audit privilégié. Il compose uniquement les
contrats publics des domaines et ne possède aucune vérité CRM, Billing,
Analytics, Advisor ou Subscriptions.

Tant que l'ADR reste `Proposed`, `Operations` n'est pas ajouté à la liste
normative des bounded contexts et aucune implémentation ne doit anticiper son
acceptation.

---

## Modules différés

| Module | Statut | Condition de réouverture |
|---|---|---|
| Projects | Post-MVP | besoin prouvé qui ne peut pas être porté par CRM sans déformer son modèle. |
| Automation | Post-MVP | boucle MVP stable, catalogue d'actions borné, consentement, audit et politique de rollback définis. |
| Integrations | Post-MVP | connecteur prioritaire validé par une recherche utilisateur et un contrat de données. |
| Public API | Post-MVP | contrats internes stables, modèle d'application cliente et politique de dépréciation validés. |
| Marketplace | Post-MVP | gouvernance des extensions, sécurité et modèle économique établis. |

Les ports techniques d'e-mail, rendu de document ou stockage ne créent pas un
module Integrations visible par l'utilisateur.
