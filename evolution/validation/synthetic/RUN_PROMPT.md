# Prompt d'orchestration d'un run synthétique

> Statut : `Draft`

Ce prompt sert à lancer proprement une expérience depuis Codex ou un environnement agentique capable d'isoler les exécutions.

```text
Exécute l'expérience synthétique <EXPERIMENT_ID> du projet Atlas.

Lis uniquement les fichiers nécessaires sous :
evolution/validation/synthetic/

Commence obligatoirement par RUN_PROTOCOL.md.

AUTORISATION EXPLICITE DE DÉLÉGATION

Je t'autorise explicitement à lancer et coordonner les sous-agents ou contextes indépendants nécessaires à cette expérience.
Utilise cette délégation pour garantir les frontières d'information du protocole.
Le contexte orchestrateur ne doit jamais jouer lui-même un persona.
Le contexte orchestrateur ne doit jamais jouer Atlas s'il a lu un Hidden State.

Respecte strictement :
- la séparation entre Profile, Business State et Hidden State ;
- les règles anti-complaisance ;
- l'isolation de chaque combinaison persona × Hidden State ;
- la décision Pre-Atlas figée avant exposition à Atlas ;
- la frontière des données Visible to Atlas ;
- l'interdiction absolue de transmettre un Hidden State à Atlas ;
- la déduplication par Observable State ;
- une seule recommandation Atlas figée par Observable State unique ;
- la possibilité explicite de No Recommendation ;
- le scoring 0–4 ;
- la distinction Synthetic Evidence / Market Evidence.

Ne modifie aucun fichier produit, Business Health, Advisor ou architecture.
Ne modifie pas l'expérience pendant le run.
Ne réutilise jamais un run bloqué ou terminé : crée le numéro suivant.

Avant exécution :
1. détermine le prochain dossier results/<EXPERIMENT_ID>/run-XXX/ ;
2. crée manifest.md ;
3. enregistre les versions, modèles/configurations disponibles, commit/scénario et nombre d'exécutions prévues ;
4. inscris Delegation explicitly authorized: YES ;
5. vérifie que Experiment modified during run = NO.

PHASE A — PRE-ATLAS

Pour chaque combinaison persona × Hidden State :
1. lance un contexte persona indépendant ;
2. ne lui fournis que son Profile, le Business State applicable, son Hidden State et les instructions nécessaires ;
3. produis sa compréhension, sa décision initiale et son calendrier ;
4. fige immédiatement Pre-Atlas ;
5. ne lui révèle aucune recommandation Atlas ni réponse d'un autre persona.

PHASE B — OBSERVABLE STATES ET ATLAS

1. identifie les ensembles uniques de données réellement Visible to Atlas ;
2. regroupe uniquement les simulations dont l'état observable est strictement identique ;
3. attribue O01, O02, etc. ;
4. pour chaque Observable State, lance un contexte Atlas indépendant qui n'a jamais reçu de Hidden State ;
5. fournis-lui uniquement Visible to Atlas et les règles de recommandation nécessaires ;
6. autorise explicitement No Recommendation ;
7. produis une seule recommandation pour cet Observable State ;
8. écris-la dans atlas/OXX.md et fige-la ;
9. ne génère jamais une variante de cette recommandation pour l'adapter à un persona caché.

PHASE C — POST-ATLAS

Pour chaque simulation :
1. lance un contexte persona indépendant ;
2. fournis son Profile, son Hidden State, son propre Pre-Atlas figé et exactement la recommandation atlas/OXX.md correspondante ;
3. ne fournis aucune réponse des autres simulations ;
4. recueille sa réaction et sa décision finale ;
5. indique explicitement si sa décision a changé ;
6. attribue le score 0–4 avec justification ;
7. écris le résultat brut dans raw/PXX-HXX.md ;
8. fige le résultat.

PHASE D — REVUE

Une fois tous les résultats bruts figés :
1. lance le Devil's Advocate sur le manifest, les Observable States et les résultats bruts ;
2. écris devil-advocate.md ;
3. lance ensuite seulement le Review Committee ;
4. écris review.md ;
5. conclus Keep / Change / Kill / Field Test ;
6. indique explicitement ce que le run permet et ne permet pas de conclure.

N'utilise jamais les fréquences synthétiques comme statistiques de marché.

Si, malgré cette autorisation explicite, l'environnement ne permet réellement pas de créer les contextes indépendants nécessaires :
- arrête avant toute simulation contaminée ;
- documente précisément la capacité technique manquante dans le manifest ;
- conserve le run comme BLOCKED ;
- ne simule jamais artificiellement l'isolation dans le contexte partagé.

À la fin, committe uniquement les résultats du run dans un commit séparé.
```

## Utilisation recommandée

Remplacer `<EXPERIMENT_ID>` par l'identifiant voulu, par exemple `BH-001`.

Après un run bloqué, utiliser obligatoirement le numéro suivant. Ainsi, après `BH-001/run-001` bloqué, la prochaine exécution est `BH-001/run-002`.

Pour limiter la consommation de tokens, utiliser un modèle économique pour les exécutions de personas et réserver un modèle de raisonnement plus coûteux à l'analyse finale lorsque la décision le justifie.

La déduplication des Observable States réduit également le coût : si 24 simulations exposent les mêmes données à Atlas, une seule recommandation Atlas est générée et figée pour les 24 réactions.
