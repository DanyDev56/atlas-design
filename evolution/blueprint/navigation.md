---
id: BLUEPRINT-004
title: Navigation mobile-first
status: In Review
owner: Product and Design
version: 1.0
last_updated: 2026-08-06
---

# Navigation mobile-first

## Modèle mental utilisateur

Les domaines restent internes. L'interface emploie cinq destinations stables :

| RouteKey racine | Libellé | Contenu |
|---|---|---|
| `home` | Aujourd'hui | synthèse, santé et priorité |
| `customers` | Clients | clients, contacts, opportunités |
| `sales` | Ventes | devis, factures, paiements, avoirs |
| `advice` | Conseils | recommandations et historique |
| `more` | Plus | analyses, notifications, paramètres |

Sur mobile, une barre basse expose les quatre premières destinations et `Plus`.
Sur grand écran, la même hiérarchie devient une barre latérale ; les RouteKeys et
l'ordre logique restent identiques.

## Règles

- une action principale flottante dépend de la destination, jamais plus d'une ;
- retour restaure filtre, tri et défilement de la liste ;
- les listes utilisent une vue compacte avant tout tableau horizontal ;
- les détails s'ouvrent en page, pas dans un panneau exigeant une grande largeur ;
- la zone tactile minimale est 44 × 44 px et le focus clavier reste visible ;
- chaque écran possède chargement, vide, erreur, hors-ligne et accès refusé ;
- les badges notifient un état, jamais une navigation parallèle.

## Routes secondaires MVP

```text
home
customers.list | customers.detail | customers.opportunities
sales.quotes | sales.invoices | sales.payments | sales.credit-notes
advice.current | advice.history | advice.detail
more.analytics | more.notifications | more.settings.workspace
more.settings.members | more.settings.roles | more.settings.integrations
```

Projects, Automations et Marketplace ne sont pas exposés au MVP. Analytics et
Business Health alimentent `Aujourd'hui` ; leurs détails restent accessibles via
`Plus` sans révéler l'architecture interne.

## Deep links et notifications

Une notification transporte une RouteKey enregistrée dans le registre global et
des identifiants opaques autorisés. Si la cible n'est plus accessible, Atlas
ouvre la destination racine avec une explication, jamais une page blanche.
