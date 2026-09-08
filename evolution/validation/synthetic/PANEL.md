# Synthetic Customer Panel

Statut : `Draft`

Le panel couvre volontairement plusieurs variantes proches de la cible Atlas. Il ne cherche pas la représentativité statistique ; il cherche la **diversité des objections plausibles**.

| Persona | Profil | Tension principale testée |
|---|---|---|
| Julien | Développeur freelance établi | Cœur de la proposition Atlas |
| Thomas | Consultant en mission longue | Faible fréquence commerciale |
| Léa | Designer à activité fragmentée | Volume, administration, priorisation |
| Sophie | Consultante très organisée | Valeur incrémentale face à de bons processus |
| Nicolas | Freelance tech sceptique | Substitution par tableur, automatisations et IA généraliste |
| Camille | Indépendante en croissance | Limites du solo et complexification progressive |

## Contrat commun des personas

Chaque persona doit distinguer quatre couches.

### 1. Profil stable

- métier ;
- ancienneté ;
- niveau de revenu approximatif ;
- structure ;
- maturité numérique ;
- outils ;
- tolérance administrative ;
- propension au changement.

### 2. État métier

L'expérience fournit un état daté :
- clients ;
- missions ;
- opportunités ;
- devis ;
- factures ;
- paiements ;
- capacité future ;
- événements utiles.

Le persona ne doit pas inventer de nouvelles données métier pour rendre Atlas plus pertinent.

### 3. État caché

Informations connues de l'utilisateur mais potentiellement inconnues d'Atlas :
- intentions ;
- contraintes personnelles ;
- relation particulière avec un client ;
- décisions déjà prises ;
- tolérance au risque ;
- informations informelles ;
- raison d'une exception.

Cet état sert à tester les limites du conseil. Il doit être défini **avant** de voir la recommandation Atlas.

### 4. Décision initiale

Avant exposition à Atlas, le persona doit répondre :
1. que comprend-il de la situation ?
2. que compte-t-il faire ?
3. quand ?
4. pourquoi ?
5. avec quel niveau de certitude ?

Cette décision devient immuable pour la comparaison.

## Instructions de rôle

Lorsqu'un modèle joue un persona :

- rester strictement dans les informations accessibles au persona ;
- ne pas adopter le vocabulaire interne d'Atlas sauf s'il lui a été présenté ;
- ne pas chercher à satisfaire le concepteur ;
- signaler lorsqu'une recommandation était déjà évidente ;
- refuser une recommandation incompatible avec l'état caché ;
- accepter de ne pas savoir ;
- ne jamais prétendre avoir payé, utilisé plusieurs semaines ou obtenu un résultat réel ;
- expliquer les changements de décision par les éléments précis qui les provoquent.

## Contradicteur

Le contradicteur n'est pas un client synthétique. Il intervient après les simulations et cherche notamment :
- les recommandations triviales ;
- les informations qu'un outil existant suffisait à fournir ;
- les décisions que le persona avait déjà prises ;
- les données artificiellement favorables à Atlas ;
- les raisonnements circulaires ;
- les faux liens de causalité ;
- les scénarios où Atlas aurait dû s'abstenir ;
- les résultats dépendant du fait que le même modèle connaît implicitement la vision produit.

Une expérience n'est pas considérée comme prometteuse tant que ces objections n'ont pas été examinées.