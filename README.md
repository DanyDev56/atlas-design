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
9. [Acceptation de bout en bout du MVP](evolution/roadmap/mvp-acceptance.md)
10. [Product Blueprint](evolution/blueprint/README.md)
11. [Fixtures de référence du MVP](evolution/reference-fixtures/README.md)
12. [Product Language](fondation/language/README.md)
13. [Domain Map](fondation/domain-map/README.md)
14. [Architecture Decision Records](fondation/decisions/README.md)
15. [Security Foundation](fondation/security/README.md)

---

## Organisation

### Fondation

[`fondation/`](fondation/README.md) contient les règles structurantes et durables :

- vision et principes ;
- stratégie produit et utilisateurs cibles ;
- langage officiel ;
- domaines et invariants ;
- décisions structurantes ;
- sécurité transverse et modèles de menace.

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

---

## Valider le référentiel

Les contrôleurs documentaires nécessitent `ripgrep` et `jq`. Leur point d'entrée
unique est le même en local et dans GitHub Actions :

```bash
scripts/check-all.sh
```

---

## Implémentation

Le code exécutable du MVP vit dans [`implementation/`](implementation/README.md).
Les incréments 0 à 8 et le Palier 3 technique sont clôturés. Le chantier courant
est l'interface React de démonstration et d'early access décrite dans
[`implementation/UI-DEMO-SCOPE.md`](implementation/UI-DEMO-SCOPE.md).

```bash
make up           # Docker + PostgreSQL
make bootstrap    # Dépendances, configuration et migrations
make test         # Tests backend Pest dans une base atlas_test isolée
make web-check    # TypeScript strict + build Vite
```

État technique : [`implementation/MVP-CLOSURE.md`](implementation/MVP-CLOSURE.md) ·
UI courante : [`implementation/UI-DEMO-SCOPE.md`](implementation/UI-DEMO-SCOPE.md) ·
clôture du spike : [`implementation/SPIKE-CLOSURE.md`](implementation/SPIKE-CLOSURE.md).
