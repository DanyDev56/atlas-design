# Protocole d'exécution des expériences synthétiques

> Statut : `Draft`

## Objet

Ce protocole rend les expériences synthétiques Atlas reproductibles, comparables et auditables.

Il complète le cadre de `README.md` sans transformer les résultats synthétiques en preuves de marché.

## Pipeline obligatoire

```text
Experiment
    ↓
Frozen Scenario
    ↓
Independent Pre-Atlas Runs
    ↓
Frozen Observable States
    ↓
Independent Atlas Runs by Observable State
    ↓
Frozen Recommendations
    ↓
Independent Post-Atlas Runs
    ↓
Frozen Raw Results
    ↓
Devil's Advocate
    ↓
Review Committee
    ↓
Immutable Run Result
    ↓
Keep / Change / Kill / Field Test
```

Aucune étape aval ne peut modifier rétroactivement une réponse produite à une étape amont.

## 1. Figer l'expérience

Avant le premier persona, le run fige :
- l'identifiant et la version de l'expérience ;
- le scénario ;
- les données visibles par Atlas ;
- les Hidden States ;
- les personas et leur version ;
- les prompts/règles utilisés ;
- le modèle et, lorsque disponible, sa configuration ;
- les critères d'évaluation.

Toute modification impose un nouveau run ou une nouvelle version de l'expérience.

## 2. Délégation et isolation des contextes

Le prompt d'orchestration doit autoriser explicitement le lancement de sous-agents ou contextes indépendants lorsque l'environnement l'exige.

L'orchestrateur :
- coordonne le run ;
- prépare les entrées minimales de chaque sous-agent ;
- fige les artefacts produits ;
- ne joue jamais lui-même un persona ;
- ne joue jamais lui-même Atlas s'il a lu un Hidden State ;
- ne transmet jamais un Hidden State à un agent Atlas.

Chaque combinaison `persona × hidden state` est une exécution indépendante côté persona.

Une exécution persona :
- ne lit aucune réponse d'un autre persona ;
- ne lit aucun résultat consolidé ;
- ne lit pas les conclusions du Devil's Advocate ou du Review Committee ;
- ne connaît que son Profile, le Business State autorisé et son Hidden State ;
- formule et fige sa décision initiale avant toute recommandation Atlas.

Si l'environnement ne permet pas une isolation réelle des contextes malgré l'autorisation explicite de délégation, le run doit être arrêté avant les simulations et documenté comme bloqué.

## 3. Frontière d'information Atlas

La recommandation Atlas est produite dans une étape séparée et dans un contexte qui n'a jamais reçu de Hidden State.

Elle peut utiliser uniquement les données déclarées `Visible to Atlas` dans l'expérience.

Elle ne peut jamais exploiter :
- le Hidden State ;
- la décision initiale privée du persona, sauf si le scénario indique explicitement qu'Atlas la connaît ;
- une réaction produite dans un autre run ;
- une information inventée pour compléter le dossier.

Lorsque l'information disponible ne permet pas un conseil fiable, `No Recommendation` est une sortie valide et potentiellement préférable.

## 4. Déduplication des états observables

Une recommandation Atlas n'est pas générée une fois par persona si plusieurs simulations exposent exactement le même état observable.

Avant la phase Atlas, l'orchestrateur regroupe les simulations par **Observable State** : ensemble strictement identique des données `Visible to Atlas` et du contexte explicitement accessible au système.

Pour chaque Observable State unique :
1. créer un identifiant stable, par exemple `O01` ;
2. produire une seule recommandation Atlas dans un contexte indépendant ;
3. figer cette recommandation ;
4. distribuer exactement cette même recommandation à toutes les simulations rattachées à `O01`.

Cette règle évite de confondre variabilité du modèle et réaction du persona.

Deux états ne peuvent être fusionnés que si toutes les informations accessibles à Atlas sont identiques. En cas de doute, les garder séparés.

## 5. Séquence d'une simulation

Pour chaque combinaison persona × Hidden State :

1. `Pre-Atlas` — le persona décrit sa compréhension, sa décision et son calendrier d'action.
2. La réponse est figée.
3. La simulation est rattachée à son Observable State.
4. `Atlas` — la recommandation figée correspondant à cet Observable State est utilisée.
5. `Post-Atlas` — le persona reçoit exactement cette recommandation et réagit.
6. Le persona indique explicitement si sa décision change.
7. Une évaluation 0–4 est proposée avec justification factuelle.
8. Le résultat brut est figé.

## 6. Résultats bruts immuables

Les fichiers de simulation bruts ne sont pas réécrits par le comité.

Une erreur de protocole est corrigée par :
- l'invalidation explicite de l'exécution ;
- puis une nouvelle exécution identifiée.

Ne jamais corriger silencieusement une réponse afin de la rendre plus cohérente.

## 7. Devil's Advocate

Le contradicteur intervient uniquement après gel de tous les résultats bruts.

Il cherche notamment :
- conseil déjà connu ;
- information manquante qui inverse la décision ;
- fausse urgence ;
- substitut moins coûteux ;
- précision injustifiée ;
- causalité supposée ;
- optimisation d'un indicateur au détriment de l'utilisateur ;
- biais commun aux simulations ;
- variabilité artificielle introduite par le protocole.

Il ne rescrore pas silencieusement les runs. Toute contestation d'un score est consignée comme telle.

## 8. Review Committee

Le comité reçoit :
- le manifest ;
- les Observable States et recommandations Atlas figées ;
- les résultats bruts ;
- le rapport du Devil's Advocate.

Il produit :
- patterns observés ;
- contre-exemples ;
- conditions de validité ;
- informations critiques manquantes ;
- comparaison avec les substituts ;
- limites de la simulation ;
- décision `Keep`, `Change`, `Kill` ou `Field Test` ;
- prochaine hypothèse à tester.

Le comité ne transforme jamais une fréquence synthétique en probabilité de marché.

## 9. Structure d'un run

```text
results/<EXPERIMENT_ID>/run-XXX/
├── manifest.md
├── atlas/
│   ├── O01.md
│   └── ...
├── raw/
│   ├── P01-H01.md
│   ├── P01-H02.md
│   └── ...
├── devil-advocate.md
└── review.md
```

`run-XXX` est séquentiel pour une expérience donnée.

Un run bloqué reste immuable. Sa reprise crée toujours le numéro suivant.

## 10. Manifest obligatoire

`manifest.md` contient au minimum :

```text
Experiment:
Experiment version:
Run:
Date:
Orchestrator model:
Persona model:
Atlas model:
Model configuration:
Protocol version:
Persona version:
Prompt version:
Scenario hash or commit:
Executions planned:
Executions completed:
Observable states planned:
Observable states completed:
Delegation explicitly authorized: YES
Experiment modified during run: NO
Synthetic Evidence: YES
Market Evidence: NO
```

Lorsque la plateforme ne fournit pas une information de modèle/configuration, inscrire `Unknown` plutôt que l'inventer.

## 11. Format d'un Observable State Atlas

Chaque fichier `atlas/OXX.md` contient :

```text
Observable State:
Linked simulations:
Execution status:

## Visible to Atlas
...

## Atlas
Données utilisées:
Recommandation:
Incertitudes déclarées:

Recommendation frozen: YES
```

Le contenu `Visible to Atlas` doit être suffisant pour auditer qu'aucun Hidden State n'a contaminé la recommandation.

## 12. Format d'un résultat brut

Chaque fichier `PXX-HXX.md` conserve :

```text
Persona:
Hidden State:
Observable State:
Execution status:

## Pre-Atlas
Compréhension:
Décision initiale:
Calendrier prévu:

## Atlas
Recommendation source: atlas/OXX.md

## Post-Atlas
Réaction:
Décision finale:
Décision modifiée: YES | NO
Raison:

## Evaluation
Value level: 0 | 1 | 2 | 3 | 4
Justification:
Information critique manquante:
Substitut évident:
```

## 13. Comparaison entre versions

Pour comparer Advisor actuel et une conception candidate :
- conserver la même expérience et les mêmes Hidden States ;
- conserver les mêmes Observable States lorsque les données accessibles restent identiques ;
- créer un nouveau `run-XXX` ;
- documenter précisément la version du comportement Atlas testée ;
- ne pas modifier les critères après lecture des résultats.

Une comparaison n'est valide comme expérience synthétique que si les différences de protocole sont explicites.

## 14. Interdictions

Ne jamais :
- présenter `18/24` simulations positives comme un taux de conversion ou une probabilité ;
- utiliser une simulation pour valider un prix ;
- faire voter les personas sur la stratégie Atlas ;
- permettre au comité de réécrire les résultats ;
- modifier une expérience en cours parce qu'un résultat est décevant ;
- confondre répétabilité du modèle et répétabilité humaine ;
- générer plusieurs recommandations Atlas différentes pour un même Observable State dans un même run ;
- transmettre un Hidden State à un agent Atlas ;
- promouvoir automatiquement une hypothèse synthétique réussie en décision produit.

## 15. Usage des modèles coûteux

Les modèles à fort coût de raisonnement sont réservés de préférence à la synthèse finale ou à une question stratégique précise.

Les exécutions de personas peuvent utiliser un modèle moins coûteux, à condition que le modèle et sa configuration soient enregistrés dans le manifest.

Un modèle de synthèse ne doit recevoir que le dossier du run nécessaire à sa décision, pas l'ensemble du dépôt.
