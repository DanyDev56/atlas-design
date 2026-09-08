# Prompt d'orchestration d'un run synthétique

> Statut : `Draft`

Ce prompt sert à lancer ou reprendre proprement une expérience depuis Codex ou un environnement agentique capable d'isoler les exécutions.

```text
Exécute ou reprends l'expérience synthétique <EXPERIMENT_ID> du projet Atlas.

Lis uniquement les fichiers nécessaires sous :
evolution/validation/synthetic/

Commence obligatoirement par RUN_PROTOCOL.md.

AUTORISATION EXPLICITE DE DÉLÉGATION

Je t'autorise explicitement à lancer et coordonner les sous-agents ou contextes indépendants nécessaires à cette expérience.
Utilise cette délégation pour garantir les frontières d'information du protocole.
Le contexte orchestrateur ne doit jamais jouer lui-même un persona.
Le contexte orchestrateur ne doit jamais jouer Atlas s'il a lu un Hidden State.

RÈGLE MULTI-TOURS

Le run n'a pas besoin d'être terminé dans ce tour.
Si un run existe déjà avec Run status: IN_PROGRESS, reprends exactement ce run.
Ne crée pas de nouveau numéro uniquement parce qu'une session précédente s'est arrêtée.
Ne recommence aucune unité déjà COMPLETED.
Travaille par lots bornés, crée un checkpoint propre, puis arrête-toi si nécessaire.

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

DÉMARRAGE OU REPRISE

1. cherche d'abord un run <EXPERIMENT_ID>/run-XXX avec Run status: IN_PROGRESS ;
2. s'il existe, lis son manifest et reprends-le ;
3. sinon, crée le prochain run après les runs BLOCKED ou COMPLETED existants ;
4. mets le manifest au format défini dans RUN_PROTOCOL.md ;
5. inscris Delegation explicitly authorized: YES ;
6. détermine Current phase et Next work item ;
7. ne lis ensuite que les artefacts nécessaires au prochain lot.

TAILLE DU LOT

Par défaut, traite au maximum 4 exécutions persona dans un même tour.
Tu peux traiter moins si le contexte, le temps ou les limites de sous-agents l'exigent.
La qualité de l'isolation prime toujours sur la taille du lot.

PHASE A — PRE-ATLAS

Tant qu'il reste des Pre-Atlas PENDING :
1. sélectionne le prochain lot de 1 à 4 combinaisons ;
2. lance un contexte persona indépendant pour chacune ;
3. ne lui fournis que son Profile, le Business State applicable, son Hidden State et les instructions nécessaires ;
4. produis compréhension, décision initiale et calendrier ;
5. écris ces éléments dans raw/PXX-HXX.md ;
6. marque Pre-Atlas frozen: YES et Execution status: PRE_ATLAS_COMPLETED ;
7. mets à jour le registre Progress du manifest.

À la fin du lot :
- mets à jour Executions completed si applicable ;
- définis Next work item ;
- conserve Run status: IN_PROGRESS ;
- committe le checkpoint ;
- si la session doit s'arrêter, arrête-toi proprement ici.

PHASE B — OBSERVABLE STATES ET ATLAS

N'entre dans cette phase que lorsque tous les Pre-Atlas requis sont figés.

1. identifie les ensembles uniques de données réellement Visible to Atlas ;
2. regroupe uniquement les simulations dont l'état observable est strictement identique ;
3. attribue O01, O02, etc. ;
4. pour chaque Observable State manquant, lance un contexte Atlas indépendant qui n'a jamais reçu de Hidden State ;
5. fournis-lui uniquement Visible to Atlas et les règles nécessaires ;
6. autorise explicitement No Recommendation ;
7. écris une seule recommandation dans atlas/OXX.md ;
8. marque Recommendation frozen: YES ;
9. mets à jour le manifest et committe un checkpoint.

PHASE C — POST-ATLAS

Tant qu'il reste des Post-Atlas PENDING :
1. sélectionne le prochain lot de 1 à 4 simulations ;
2. lance un contexte persona indépendant ;
3. fournis son Profile, son Hidden State, son propre Pre-Atlas figé et exactement la recommandation atlas/OXX.md correspondante ;
4. ne fournis aucune réponse des autres simulations ;
5. recueille réaction, décision finale, changement de décision et score 0–4 ;
6. complète raw/PXX-HXX.md sans modifier la section Pre-Atlas ;
7. marque Execution status: COMPLETED ;
8. mets à jour le registre Progress.

À la fin du lot :
- mets à jour le manifest ;
- définis Next work item ;
- conserve Run status: IN_PROGRESS tant qu'il reste des unités ;
- committe le checkpoint ;
- arrête-toi proprement si nécessaire.

PHASE D — REVUE

N'exécute cette phase qu'une fois toutes les simulations requises terminées et tous les Observable States figés.

1. lance le Devil's Advocate ;
2. écris devil-advocate.md ;
3. committe si une séparation de tour est utile ;
4. lance ensuite seulement le Review Committee ;
5. écris review.md ;
6. conclus Keep / Change / Kill / Field Test ;
7. indique explicitement ce que le run permet et ne permet pas de conclure ;
8. passe Run status à COMPLETED et Current phase à COMPLETE ;
9. crée le commit final du run.

N'utilise jamais les fréquences synthétiques comme statistiques de marché.

SI UNE SESSION S'ARRÊTE

Une limite de temps, de contexte ou de sous-agents n'est pas un BLOCKED expérimental.
Dans ce cas :
- termine proprement le lot en cours si possible ;
- fige les artefacts terminés ;
- mets à jour le manifest ;
- committe un checkpoint ;
- laisse Run status: IN_PROGRESS.

Utilise BLOCKED uniquement lorsqu'une capacité technique indispensable au protocole est réellement indisponible.

Si une unité était IN_PROGRESS lors d'une interruption sans artefact final fiable, marque uniquement cette unité INVALID avant de la rejouer dans un contexte neuf.
```

## Utilisation recommandée

Remplacer `<EXPERIMENT_ID>` par l'identifiant voulu, par exemple `BH-001`.

Pour un run déjà commencé comme `BH-001/run-002`, il suffit ensuite de demander :

```text
Reprends BH-001/run-002 en appliquant strictement RUN_PROMPT.md.
Traite le prochain lot manquant et crée un checkpoint.
```

Il est possible de répéter cette consigne jusqu'au passage du manifest à `Run status: COMPLETED`.

Pour limiter la consommation de tokens, utiliser un modèle économique pour les exécutions de personas et réserver un modèle de raisonnement plus coûteux à l'analyse finale lorsque la décision le justifie.

La déduplication des Observable States réduit également le coût : si plusieurs simulations exposent les mêmes données à Atlas, une seule recommandation Atlas est générée et figée pour toutes les réactions concernées.
