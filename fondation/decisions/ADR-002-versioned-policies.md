---
id: ADR-002
title: Résultats immuables et politiques versionnées
status: Accepted
date: 2026-08-06
owner: Product and Architecture
---

# Contexte

Les scores, recommandations et remises doivent rester explicables après une
évolution de règle.

# Options étudiées

1. recalculer l'historique avec les règles courantes ;
2. modifier les seuils en place ;
3. publier des politiques immuables et persister la version appliquée.

# Décision

L'option 3 est retenue. Toute modification sémantique crée une version. Chaque
résultat persiste cette version. Une projection courante est séparée de
l'historique et son pointeur actif bascule selon le protocole de gouvernance.

# Raisons

La décision garantit reproductibilité, audit, rollback sans réécriture et
explication fidèle.

# Conséquences

Les consommateurs déclarent les versions supportées. Les corpus de référence,
pré-calculs et migrations de compatibilité font partie de l'activation.

# Alternatives futures

Une politique par cohorte pourra être introduite par ADR si l'unicité globale ne
suffit plus ; elle ne changera pas l'immutabilité des résultats.
