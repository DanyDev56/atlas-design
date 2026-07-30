---
id: VISION-003
title: Product Principles
status: Stable
owner: Product
version: 1.0
last_updated: 2026-07-30
references:
  - mission.md
  - vision.md
  - anti-goals.md
  - ../constitution.md
---

# Principes produit

Ces principes orientent les décisions produit, UX et techniques.

Ils ne remplacent pas la Constitution. Ils la traduisent en règles de conception directement applicables.

---

## Principe 1 — L’action avant l’information

Atlas ne doit pas se contenter d’afficher des données.

Chaque information importante doit aider l’utilisateur à :

- comprendre une situation ;
- prendre une décision ;
- accomplir une action.

Un indicateur sans interprétation ou sans utilité claire doit être remis en question.

### Exemple

Préférer :

> Trois factures en retard représentent 4 200 €. Relancer les clients concernés.

À :

> Encours client : 4 200 €.

---

## Principe 2 — L’explication avant la prédiction

Une prédiction non expliquée ne crée pas suffisamment de confiance.

Atlas doit préciser :

- les données utilisées ;
- les facteurs déterminants ;
- le niveau de confiance ;
- les limites de l’analyse.

Une règle explicable et fiable est préférable à un modèle complexe impossible à justifier.

---

## Principe 3 — L’utilisateur reste responsable

Atlas peut :

- détecter ;
- analyser ;
- recommander ;
- préparer une action ;
- automatiser une action autorisée.

Atlas ne doit pas prendre silencieusement une décision importante à la place de l’utilisateur.

Le degré d’autonomie doit toujours être :

- explicite ;
- choisi ;
- réversible lorsque cela est possible.

---

## Principe 4 — Une donnée, plusieurs usages

Une information déjà connue ne doit pas être demandée une nouvelle fois sans nécessité.

Les données doivent pouvoir être réutilisées pour :

- préremplir les documents ;
- alimenter les analyses ;
- personnaliser les recommandations ;
- éviter les doubles saisies ;
- déclencher des automatisations.

Chaque nouvelle donnée demandée doit produire une valeur visible.

---

## Principe 5 — Les événements racontent l’activité

Les actions importantes doivent produire des événements métier.

Les événements permettent de :

- comprendre l’historique ;
- construire une timeline ;
- déclencher des analyses ;
- alimenter les notifications ;
- expliquer une recommandation ;
- mesurer les résultats.

L’état actuel ne suffit pas toujours. Atlas doit également comprendre comment cet état a été atteint.

---

## Principe 6 — La simplicité est une fonctionnalité

La complexité interne ne doit pas devenir une complexité utilisateur.

Chaque écran doit réduire la charge mentale.

Avant d’ajouter une option, il faut vérifier si Atlas peut :

- déduire la valeur ;
- proposer un défaut pertinent ;
- utiliser une configuration existante ;
- présenter progressivement la complexité ;
- supprimer une étape.

---

## Principe 7 — Une priorité claire vaut mieux qu’une longue liste

Atlas ne doit pas submerger l’utilisateur de recommandations.

Il doit mettre en évidence les actions ayant le meilleur rapport entre :

- impact ;
- urgence ;
- confiance ;
- effort.

Le produit doit privilégier une priorité claire à dix alertes concurrentes.

---

## Principe 8 — L’incertitude doit être visible

Atlas ne présente jamais une estimation comme une certitude.

Lorsqu’une information est incomplète ou ambiguë, Atlas doit :

- le signaler ;
- expliquer les limites ;
- réduire son niveau de confiance ;
- éviter les formulations affirmatives trompeuses.

---

## Principe 9 — Les règles métier priment sur la technologie

Le comportement d’Atlas doit être défini par le métier, pas par les contraintes accidentelles d’un framework ou d’une bibliothèque.

Les technologies peuvent évoluer.

Les concepts métier doivent rester compréhensibles et cohérents.

---

## Principe 10 — La confiance se construit dans les détails

La confiance dépend notamment de :

- la fiabilité des calculs ;
- la précision des termes ;
- la stabilité du comportement ;
- la traçabilité ;
- la sécurité ;
- la performance ;
- la qualité des messages d’erreur.

Une fonctionnalité sophistiquée ne compense pas un comportement imprévisible.