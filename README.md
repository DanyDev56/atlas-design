# Atlas Design

Atlas est un système de pilotage quotidien destiné aux indépendants et aux
petites entreprises de services.

Il transforme les données opérationnelles de l'activité en compréhension, puis
cette compréhension en actions utiles, expliquées et mesurables.

```text
Gérer
  ↓
Comprendre
  ↓
Décider
  ↓
Agir
  ↓
Mesurer
```

---

## Statut du dépôt

Ce dépôt contient le référentiel de conception d'Atlas.

Le projet est en cours de consolidation. Les documents marqués `Stable`
constituent les règles les plus durables. Les documents `Draft` peuvent encore
évoluer et ne doivent pas être considérés isolément comme des contrats
d'implémentation définitifs.

---

## Commencer la lecture

1. [Constitution](fondation/constitution.md)
2. [Mission](fondation/vision/mission.md)
3. [Vision](fondation/vision/vision.md)
4. [Principes produit](fondation/vision/principles.md)
5. [Anti-objectifs](fondation/vision/anti-goals.md)
6. [Stratégie produit](fondation/product/product-strategy.md)
7. [Persona principal](fondation/product/personnas/persona-primary.md)
8. [Périmètre du MVP](evolution/roadmap/mvp-scope.md)
9. [Product Language](fondation/language/README.md)
10. [Domain Map](fondation/domain-map/README.md)

---

## Organisation

### Fondation

[`fondation/`](fondation/README.md) contient les règles structurantes et durables :

- vision et principes ;
- stratégie produit et utilisateurs cibles ;
- langage officiel ;
- domaines et invariants ;
- décisions structurantes.

### Évolution

[`evolution/`](evolution/README.md) contient les documents opérationnels amenés
à changer plus fréquemment :

- MVP et roadmap ;
- blueprint courant ;
- métriques ;
- gouvernance et quality gates.

---

## Règle de cohérence

En cas de contradiction, la hiérarchie définie dans la
[fondation](fondation/README.md) s'applique.

Une évolution significative doit mettre à jour les documents affectés et faire
l'objet d'une décision formelle lorsqu'elle modifie durablement le produit ou son
modèle métier.
