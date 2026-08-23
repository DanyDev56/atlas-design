---
id: BPT-009
title: MVP Dashboard Composition
status: In Review
owner: Product and Engineering
version: 1.1.0
last_updated: 2026-08-06

references:
  - README.md
  - modules.md
  - navigation.md
  - ../roadmap/mvp-scope.md
  - ../../fondation/domain-map/ownership.md
  - ../../fondation/domains/crm/api.md
  - ../../fondation/domains/billing/api.md
  - ../../fondation/domains/analytics/api.md
  - ../../fondation/domains/business-health/api.md
  - ../../fondation/domains/advisor/api.md
  - ../../fondation/domains/notifications/api.md
---

# Dashboard du MVP

## Statut architectural

Le Dashboard est une surface applicative de composition. Ce n'est pas un
bounded context et il ne possède aucune vérité métier.

Il peut posséder un read model de composition jetable pour réduire la latence,
mais ce read model :

- ne devient jamais la source d'un statut, montant, score ou priorité ;
- conserve la provenance, la version et la fraîcheur de chaque donnée ;
- est reconstructible depuis les contrats publics ;
- ne déclenche aucune commande inter-domaine en son propre nom.

Le Dashboard ne lit aucun stockage privé et ne recalcule ni métrique Analytics,
ni score Business Health, ni rang Advisor, ni compteur Notifications.

---

## Composition MVP

| Zone | Source publique | Contenu | Action principale |
|---|---|---|---|
| Priorité du jour | `getAdvisorOverview` | Recommendation principale, priorité, explication courte, alternatives. | Ouvrir Advisor ou naviguer vers l'action allowlistée. |
| Santé de l'activité | `getCurrentBusinessHealth` | score ou statut, bande, tendance, fiabilité, zone d'attention. | Ouvrir l'explication Business Health. |
| Cycle commercial | `getPipeline` | volumes CRM par état et opportunités nécessitant une attention. | Ouvrir le pipeline CRM. |
| Facturation | `listInvoices` et lectures de solde bornées | factures récentes, impayées ou en retard ; aucun total recalculé localement. | Ouvrir Billing ou la facture propriétaire. |
| Activité mesurée | `getAnalyticsOverview` | métriques strictement nécessaires, période et fraîcheur. | Ouvrir l'explication Analytics intégrée. |
| Inbox | `getUnreadNotificationCount` | nombre non lu fourni par Notifications. | Ouvrir l'inbox personnelle. |

La surface initiale privilégie la décision : la priorité Advisor et son
explication précèdent les données de gestion. Une activité sans données affiche
un chemin de démarrage utile plutôt qu'un score ou une Recommendation inventés.

Ce chemin propose soit la première saisie, soit l'import historique. Pendant ou
après un import, la composition peut afficher la progression, les compteurs
validés, les Clients, les soldes et les périodes couvertes depuis leurs contrats
propriétaires. Cette checklist n'est pas une Recommendation Advisor.

---

## Enveloppe de vue

Chaque zone expose au minimum :

```text
DashboardWidget
  SourceDomain
  SourceViewVersion
  Data | NoData | InsufficientData | Unavailable
  ObservedAt
  Freshness
  ActionTarget?
```

`Unavailable` décrit une panne de lecture. Il ne doit jamais être rendu comme
`NoData`. La composition ne choisit pas un instant global artificiel : chaque
zone affiche la fraîcheur de sa propre source.

### Sémantique de la fraîcheur Analytics

La fraîcheur mesure la capacité de la projection à prouver qu'elle a traité les
événements connus. Elle ne mesure pas le temps écoulé depuis la dernière saisie
CRM ou Billing. L'inactivité d'un Workspace n'est donc pas une dégradation.

À chaque publication, Analytics réévalue les faits dépendants du temps à
l'`AsOf` du snapshot : une facture impayée peut devenir en retard sans nouvel
événement, un encours reste ouvert et les observations sortent naturellement de
leurs fenêtres glissantes. Un backlog non consommé ou une reconstruction
incomplète bloque la publication de la nouvelle vue. Le Dashboard conserve
alors la dernière vue valide et rend explicitement son état, sans transformer
les preuves existantes en `NoData` ou en couverture nulle.

---

## Autorisation et isolation

- le principal et le `WorkspaceId` sont résolus avant toute composition ;
- chaque lecture reste autorisée par son domaine propriétaire avec sa permission
  exacte ;
- une zone non autorisée est absente, sans révéler son contenu ni l'existence
  d'une ressource ;
- le Dashboard ne transforme pas un rôle `Owner`, `Admin` ou `Member` en droits
  implicites ;
- le changement de Workspace invalide la composition courante et toutes les
  requêtes en vol sont bornées au contexte initial.

Le chargement peut être parallèle, mais aucune réponse d'un autre Workspace ne
peut être fusionnée dans la vue courante.

---

## Dégradation partielle

| État | Comportement attendu |
|---|---|
| Advisor sans Recommendation | Afficher « aucune priorité proposée » et la date de la dernière évaluation. |
| Business Health `InsufficientData` | Expliquer les données manquantes et proposer les actions de collecte possédées par CRM/Billing. |
| Business Health `Limited` | Afficher score, couverture, facteurs absents et limites sans formulation de certitude globale. |
| Import en cours | Afficher progression et reprise ; ne pas présenter une génération Analytics intermédiaire comme complète. |
| Analytics en reconstruction ou événements non traités | Conserver la dernière vue valide et signaler le traitement en cours, ou afficher `Unavailable` si aucune vue sûre n'existe. |
| Billing indisponible | Masquer les montants, conserver les autres zones et proposer un retry borné. |
| Notifications indisponible | Ne pas inventer un compteur zéro ; afficher l'inbox comme temporairement indisponible. |
| Workspace restreint | Abandonner les lectures ordinaires et afficher uniquement l'état d'accès autorisé. |

Une zone en échec ne bloque pas les autres, sauf si Identity ou Workspace ne
permet plus d'établir un contexte d'accès sûr.

---

## Commandes depuis le Dashboard

Les actions du Dashboard sont des navigations ou des délégations explicites au
module propriétaire. Celui-ci :

1. recharge la ressource et sa version ;
2. réautorise l'utilisateur ;
3. présente la confirmation éventuelle ;
4. exécute sa propre commande ;
5. publie son propre événement.

Le Dashboard n'enregistre donc jamais directement un paiement, ne gagne pas une
Opportunity, ne clôture pas une Recommendation et ne marque pas une
Notification comme lue sans passer par le contrat propriétaire.

---

## Hors MVP

- personnalisation libre des widgets ;
- requêtes analytiques arbitraires ;
- comparaison multi-workspaces ;
- actions automatisées ;
- prévisions ou synthèses génératives ;
- export et partage public du Dashboard.
