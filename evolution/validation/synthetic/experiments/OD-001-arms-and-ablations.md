# OD-001 — Bras de comparaison et ablations

> Nature de preuve : `Synthetic Evidence`
> Statut : `Draft`

## Objectif

Comparer le mécanisme `open decision` à des substituts crédibles et isoler la contribution du rapprochement et du timing.

La comparaison porte sur le comportement du mécanisme, pas sur le style rédactionnel.

## Règles communes

Tous les bras :

- avancent dans la même timeline ;
- reçoivent les mêmes faits observables au même instant ;
- n'accèdent pas au futur ;
- n'accèdent pas à la Ground Truth ;
- peuvent demander uniquement les clarifications autorisées par le dossier ;
- paient le même coût comptable par question ;
- doivent choisir `SILENT`, `ASK`, `RECOMMEND` ou `NO_RECOMMENDATION` ;
- journalisent les faits utilisés ;
- ne peuvent inventer ni résultat commercial, ni paiement, ni préférence utilisateur.

Les sorties sont normalisées avant évaluation afin que la longueur ou le ton ne révèle pas le bras.

---

## ARM-01 — ATLAS

### Rôle

Simuler le mécanisme décisionnel candidat Atlas, sans supposer son implémentation technique future.

### Mission

À chaque événement :

1. déterminer si une décision potentiellement modifiable existe ;
2. rechercher un rapprochement factuel pertinent dans les données disponibles ;
3. identifier les inconnues capables d'inverser l'intervention ;
4. choisir entre silence, clarification, recommandation conditionnelle ou abstention ;
5. intervenir avant fermeture seulement si la valeur attendue justifie l'interruption.

### Contraintes

- ne pas utiliser de score Business Health comme prérequis ;
- ne pas supposer qu'un signal défavorable exige une action ;
- le statu quo doit être une alternative possible ;
- une décision déjà traitée doit réduire fortement la probabilité d'intervention ;
- après refus ou clarification indiquant un compromis volontaire, ne pas insister sans fait nouveau.

---

## ARM-02 — TABLE

### Rôle

Représenter un substitut volontairement simple : historique structuré + règles déterministes + rappel contextuel.

### Capacités autorisées

- lire les champs structurés fournis ;
- appliquer les règles préenregistrées ;
- produire un rappel lorsque la règle se déclenche ;
- afficher les faits liés à la règle.

### Règles préenregistrées

**Famille A**
> Lorsqu'un nouveau devis est ouvert pour un client ayant au moins deux paiements après échéance dans l'historique fourni, afficher l'historique de délai et rappeler de vérifier les modalités de paiement.

**Famille B**
> Lorsqu'un renouvellement est ouvert et que la consommation moyenne observée dépasse le forfait prévu, afficher l'écart et rappeler de vérifier périmètre, capacité et prix.

**Famille C**
> Lorsqu'une nouvelle opportunité exige une décision et que la somme des engagements confirmés + charge de l'opportunité dépasse la capacité cible fournie, afficher le chevauchement et rappeler de vérifier la capacité avant engagement.

### Limites

Le bras TABLE :

- ne raisonne pas librement sur une préférence privée ;
- ne transforme pas une règle après lecture du cas ;
- ne reçoit pas de règle spécifique à une variante V1–V4 ;
- peut néanmoins éviter un déclenchement si les champs structurés montrent explicitement que la décision est close ou le signal invalide.

L'effort de création et de maintenance des règles est comptabilisé séparément.

---

## ARM-03 — GENERAL_AI

### Rôle

Représenter une IA généraliste disposant exactement des mêmes informations et du même point d'appel qu'Atlas.

### Mission

> Analyse uniquement les faits disponibles à cet instant. Détermine s'il existe une décision encore modifiable qui mérite une intervention. Tu peux te taire, poser une question ciblée, proposer une option conditionnelle ou refuser de recommander. N'invente aucune information.

### Égalité stricte

GENERAL_AI reçoit :

- les mêmes événements ;
- les mêmes réponses aux clarifications qu'ATLAS lorsqu'il pose une question équivalente ;
- le même accès temporel ;
- aucun contexte de marque Atlas ;
- aucun avantage ou handicap artificiel sur la longueur du contexte.

Si l'IA généraliste est appelée automatiquement par l'orchestrateur aux mêmes événements qu'Atlas, l'expérience ne peut pas attribuer à Atlas un avantage de déclenchement simplement parce que l'utilisateur n'aurait pas pensé à ouvrir une IA.

Cette distinction doit apparaître dans la conclusion.

---

## ARM-04 — ABLATION_LINK

### Objectif

Tester si le rapprochement explicite entre historique et événement courant contribue réellement à la qualité de l'intervention.

### Règle

Le bras reçoit les mêmes faits et le même timing qu'ATLAS, mais sa sortie ne peut pas :

- relier causalement ou décisionnellement un fait historique à l'événement courant ;
- formuler explicitement « compte tenu de X passé, vérifiez Y maintenant ».

Il peut présenter les faits séparément et poser une question générique.

### Interprétation

Si ABLATION_LINK obtient une qualité équivalente à ATLAS, la valeur incrémentale du rapprochement est affaiblie dans le périmètre testé.

---

## ARM-05 — ABLATION_TIMING

### Objectif

Tester la contribution du moment d'intervention.

### Règle

Le contenu informationnel est identique à la meilleure sortie ATLAS figée pour le cas, mais il n'est exposé qu'après l'événement de fermeture documenté.

Le bras ne peut pas prétendre que la décision initiale est encore ouverte.

### Interprétation

Si l'intervention tardive conserve la même utilité opérationnelle, l'hypothèse selon laquelle le timing avant fermeture est différenciant est affaiblie.

---

# Journal d'exécution

Chaque sortie doit conserver :

```text
Case:
Arm:
Timestamp / event:
Facts visible:
Decision state inferred:
Action: SILENT | ASK | RECOMMEND | NO_RECOMMENDATION
Question asked:
Answer received:
Facts used:
Proposed option:
Alternative / status quo:
Invalidation condition:
Next observable result:
Human preparation time:
Execution time:
Interaction count:
```

## Coût des clarifications

Chaque question compte pour `1 interaction`.

Le temps nécessaire à :

- préparer les données ;
- maintenir une règle ;
- reformuler une entrée ;
- répondre à une clarification ;

est enregistré séparément lorsque l'environnement permet une mesure fiable.

Ne pas inventer une précision temporelle absente. Utiliser `Unknown` si nécessaire.

## Évaluation aveugle

Lorsque possible, l'évaluateur reçoit des sorties normalisées sous des identifiants neutres (`X1`, `X2`, etc.) sans connaître le bras.

Il évalue d'abord :

- décision détectée avant fermeture ;
- compatibilité avec les contraintes ;
- intervention superflue ;
- qualité de l'abstention ;
- nombre de clarifications ;
- action réellement faisable.

L'identité des bras n'est révélée qu'après gel de l'évaluation primaire.
