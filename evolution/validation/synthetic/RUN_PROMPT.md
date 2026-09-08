# Prompt d'orchestration d'un run synthétique

> Statut : `Draft`

Ce prompt sert à lancer proprement une expérience depuis Codex ou un environnement agentique capable d'isoler les exécutions.

```text
Exécute l'expérience synthétique <EXPERIMENT_ID> du projet Atlas.

Lis uniquement les fichiers nécessaires sous :
evolution/validation/synthetic/

Commence obligatoirement par RUN_PROTOCOL.md.

Respecte strictement :
- la séparation entre Profile, Business State et Hidden State ;
- les règles anti-complaisance ;
- l'isolation de chaque combinaison persona × Hidden State ;
- la décision Pre-Atlas figée avant exposition à Atlas ;
- la frontière des données Visible to Atlas ;
- l'interdiction pour Atlas d'utiliser le Hidden State ;
- la possibilité explicite de No Recommendation ;
- le scoring 0–4 ;
- la distinction Synthetic Evidence / Market Evidence.

Ne modifie aucun fichier produit, Business Health, Advisor ou architecture.
Ne modifie pas l'expérience pendant le run.

Avant exécution :
1. crée le prochain dossier results/<EXPERIMENT_ID>/run-XXX/ ;
2. crée manifest.md ;
3. enregistre les versions, modèle/configuration disponibles, commit/scénario et nombre d'exécutions prévues ;
4. vérifie que Experiment modified during run = NO.

Pour chaque combinaison persona × Hidden State :
1. exécute le persona dans un contexte indépendant ;
2. produis et fige Pre-Atlas ;
3. produis Atlas séparément avec uniquement les données autorisées ;
4. fige la recommandation ;
5. expose exactement cette recommandation au persona ;
6. produis Post-Atlas ;
7. attribue le score 0–4 avec justification ;
8. écris le résultat brut dans raw/PXX-HXX.md ;
9. ne réécris plus ce fichier sauf invalidation explicite du run.

Un persona ne doit jamais connaître les réponses des autres personas.

Une fois tous les résultats bruts figés :
1. exécute le Devil's Advocate ;
2. écris devil-advocate.md ;
3. exécute ensuite seulement le Review Committee ;
4. écris review.md ;
5. conclus Keep / Change / Kill / Field Test ;
6. indique explicitement ce que le run permet et ne permet pas de conclure.

N'utilise jamais les fréquences synthétiques comme statistiques de marché.

Si l'environnement ne permet pas l'isolation réelle des contextes, arrête avant les simulations et indique qu'il faut exécuter les combinaisons dans des sessions séparées. Ne simule pas artificiellement l'isolation dans un contexte partagé.

À la fin, committe uniquement les résultats du run dans un commit séparé.
```

## Utilisation recommandée

Remplacer `<EXPERIMENT_ID>` par l'identifiant voulu, par exemple `BH-001`.

Pour limiter la consommation de tokens, utiliser un modèle économique pour les exécutions et réserver un modèle de raisonnement plus coûteux à l'analyse de `review.md` lorsque la décision le justifie.
