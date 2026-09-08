# Atlas — Audit Pack

Ce dossier constitue le point d'entrée compact pour une revue stratégique du produit par un modèle de raisonnement à coût élevé comme Astra.

L'objectif n'est **pas** de remplacer le référentiel Atlas. Il permet de comprendre suffisamment le produit pour juger sa crédibilité, son positionnement, son branding, son pricing, son périmètre et la qualité de sa documentation **sans parcourir tout le dépôt**.

## Politique de lecture

Pour un audit général :

1. lire `PRODUCT_AUDIT_CONTEXT.md` ;
2. lire `AUDIT_PROMPTS.md` uniquement pour choisir le type d'audit ;
3. ne consulter les documents source du dépôt que pour vérifier une hypothèse précise ou approfondir un point identifié comme important ;
4. ne jamais parcourir récursivement le dépôt par défaut ;
5. ne pas relire un document déjà exploité sauf contradiction explicite ;
6. distinguer systématiquement :
   - les règles `Stable` ;
   - les éléments `In Review` ;
   - les hypothèses `Draft` ;
   - les preuves réellement observées.

## Budget de contexte recommandé

Le fichier `PRODUCT_AUDIT_CONTEXT.md` doit suffire à la première passe.

Lorsqu'un approfondissement est nécessaire, ouvrir **au maximum 3 à 5 documents source supplémentaires par passe**, choisis avant lecture.

Éviter les analyses du type « lis tout le dépôt puis donne ton avis ». La profondeur doit venir du raisonnement sur un contexte sélectionné, pas du volume de fichiers lus.

## Sources prioritaires

Ces documents sont les principales sources de vérité utilisées pour construire le contexte compact :

- `README.md`
- `fondation/vision/mission.md`
- `fondation/vision/vision.md`
- `fondation/vision/principles.md`
- `fondation/vision/anti-goals.md`
- `fondation/product/product-strategy.md`
- `fondation/product/pricing-strategy.md`
- `fondation/product/personnas/persona-primary.md`
- `fondation/language/README.md`
- `evolution/roadmap/mvp-scope.md`
- `evolution/governance/pricing-validation.md`
- `implementation/UI-DEMO-SCOPE.md`

## Règle d'autorité

Ce dossier est une synthèse d'audit et non une source normative.

En cas de contradiction, le référentiel Atlas original prévaut selon sa propre hiérarchie documentaire.

## Utilisation avec Astra

Pour une première évaluation, utiliser le prompt `Audit stratégique initial` de `AUDIT_PROMPTS.md`.

Ensuite, approfondir uniquement les dimensions qui présentent :

- un risque important ;
- une hypothèse fragile ;
- une incohérence ;
- ou un fort potentiel d'amélioration.

Cette séquence permet de réserver le raisonnement coûteux aux questions à forte valeur.