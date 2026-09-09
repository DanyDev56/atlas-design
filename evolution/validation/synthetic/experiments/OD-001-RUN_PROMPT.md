# OD-001 — Prompt d'exécution

> Statut : `Draft`

```text
Exécute ou reprends OD-001 — Open Decision Detection Benchmark.

Lis uniquement :
- evolution/validation/synthetic/RUN_PROTOCOL.md ;
- evolution/validation/synthetic/experiments/OD-001-open-decision-detection-benchmark.md ;
- evolution/validation/synthetic/experiments/OD-001-cases.md ;
- evolution/validation/synthetic/experiments/OD-001-arms-and-ablations.md ;
- evolution/validation/synthetic/experiments/OD-001-evaluation.md ;
- le manifest et les artefacts déjà figés du run actif, s'il existe.

AUTORISATION DE DÉLÉGATION

Je t'autorise explicitement à utiliser des sous-agents ou contextes indépendants.
Utilise-les pour empêcher toute contamination entre :
- données observables ;
- réponses privées aux clarifications ;
- Ground Truth ;
- sorties des autres bras ;
- évaluation.

Le contexte qui connaît la Ground Truth ne doit jamais produire une sortie d'un bras évalué.

OBJECTIF

Comparer ATLAS, TABLE, GENERAL_AI, ABLATION_LINK et ABLATION_TIMING sur les 12 dossiers préenregistrés.

Ne cherche pas à faire gagner Atlas.
Le résultat le plus utile peut être Kill.

RÈGLE MULTI-TOURS

Le benchmark n'a pas besoin d'être terminé dans un seul tour.
Reprends toujours un run IN_PROGRESS avant d'en créer un nouveau.
Travaille par lots bornés et committe des checkpoints.
Ne recommence aucune unité COMPLETED.

PRÉPARATION

1. crée ou reprends results/OD-001/run-XXX/ ;
2. crée/actualise manifest.md ;
3. fige les versions et commit du scénario, des cas, des bras et des gates ;
4. construis pour chaque cas trois artefacts séparés :
   - Observable timeline ;
   - Authorized clarifications ;
   - Ground Truth ;
5. vérifie que les bras n'ont accès qu'à Observable timeline et aux réponses obtenues par leurs propres questions ;
6. enregistre modèles/configurations réellement disponibles, sinon Unknown.

PHASE A — BRAS PRINCIPAUX

Exécute d'abord ATLAS, TABLE et GENERAL_AI.

Pour chaque Case × Arm :
1. démarre dans un contexte indépendant ;
2. avance la timeline événement par événement ;
3. à chaque événement, autorise SILENT, ASK, RECOMMEND ou NO_RECOMMENDATION ;
4. si ASK, ne révèle que la réponse autorisée correspondant à la question ;
5. journalise chaque interaction ;
6. arrête l'accès aux événements futurs ;
7. fige la sortie finale du bras pour ce cas ;
8. ne révèle jamais les autres bras.

Travaille par défaut sur un maximum de 4 unités Case × Arm par tour.

PHASE B — ABLATIONS

Après gel de la sortie ATLAS correspondante :

- exécute ABLATION_LINK selon sa règle, sans rapprochement explicite ;
- exécute ABLATION_TIMING avec le contenu informationnel Atlas exposé uniquement après fermeture.

Ne modifie jamais la sortie Atlas pour améliorer une ablation.

PHASE C — ÉVALUATION AVEUGLE

1. normalise les sorties sans modifier leur substance ;
2. attribue des identifiants anonymes ;
3. lance un évaluateur indépendant connaissant Ground Truth mais pas l'identité des bras ;
4. complète exactement les métriques de OD-001-evaluation.md ;
5. fige l'évaluation primaire ;
6. révèle ensuite seulement l'identité des bras ;
7. construis la comparaison.

PHASE D — EFFORT

Enregistre uniquement l'effort réellement observable.
N'invente jamais de temps humain.
Si une mesure n'est pas disponible : Unknown.

Le temps Wizard-of-Oz compte comme coût et ne prouve aucune automatisation future.

PHASE E — REVUE

Lorsque toutes les unités et évaluations sont figées :
1. lance le Devil's Advocate ;
2. exige qu'il recherche notamment avantage informationnel artificiel, contrôle trop faible, faux positifs V2/V3/V4 et métriques incohérentes ;
3. fige devil-advocate.md ;
4. lance ensuite le Review Committee ;
5. applique les gates préenregistrées sans les modifier ;
6. conclus Keep, Change, Kill ou Field Test ;
7. sépare explicitement Synthetic Evidence, inférences et hypothèses ;
8. passe le run à COMPLETED.

INTERDICTIONS

- aucun scan récursif du dépôt ;
- aucune modification de Business Health ou Advisor ;
- aucune modification des 12 cas pendant le run ;
- aucune modification des gates après observation des résultats ;
- aucun taux de marché dérivé des 12 dossiers ;
- aucune préférence donnée à Atlas dans l'évaluation ;
- aucune information supplémentaire donnée à Atlas sans la rendre accessible au substitut dans les mêmes conditions ;
- aucun résultat réel inventé.

À chaque limite de session :
- fige les unités terminées ;
- mets à jour manifest.md ;
- committe un checkpoint ;
- laisse le run IN_PROGRESS ;
- indique précisément Next work item.
```

## Reprise courte

Après le premier tour :

```text
Reprends OD-001/run-XXX en appliquant strictement OD-001-RUN_PROMPT.md.
Traite le prochain lot manquant, mets à jour le manifest et crée un checkpoint.
```
