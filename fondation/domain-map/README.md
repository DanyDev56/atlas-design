---
title: Domain Map
status: Draft
owner: Product
last_updated: 2026-07-29
---

# Domain Map

## Objectif

Le Domain Map décrit comment les domaines d'Atlas collaborent.

Il ne décrit pas les classes, les bases de données ou les API.

Il décrit uniquement les responsabilités métier et les échanges entre domaines.

Chaque domaine doit pouvoir évoluer sans casser les autres.

---

## Pourquoi

Lorsque le produit grandit, les dépendances deviennent le principal facteur de complexité.

Le Domain Map sert à :

- limiter le couplage ;
- éviter les dépendances circulaires ;
- clarifier les responsabilités ;
- documenter les flux métier.

---

## Principe

Un domaine est propriétaire de ses données.

Les autres domaines ne les modifient jamais directement.

Ils réagissent aux événements métier.