# Validation synthétique Atlas

> Statut : `Draft`
>
> Ce dossier définit un laboratoire de Product Discovery synthétique. Il sert à réfuter, préciser et prioriser des hypothèses avant développement. Il ne constitue jamais une preuve de marché.

## Objectif

Le panel synthétique permet de tester rapidement si une proposition Atlas paraît :

- évidente ou réellement informative ;
- contextualisée ou générique ;
- susceptible de modifier une décision ;
- dépendante de données qu'Atlas ne possède pas ;
- plus coûteuse à alimenter que la valeur qu'elle produit.

Il est particulièrement adapté à Business Health, Advisor, au cold start, aux parcours d'onboarding et aux arbitrages de produit.

## Ce que le panel ne valide jamais

Aucun résultat synthétique ne doit être interprété comme une preuve de :

- volonté réelle de payer ;
- conversion ;
- rétention ;
- fréquence d'usage réelle ;
- coût d'acquisition ;
- comportement humain observé ;
- taille ou existence d'un marché.

Une hypothèse qui échoue synthétiquement peut être simplifiée ou abandonnée avant développement. Une hypothèse qui réussit synthétiquement devient seulement **candidate à une validation terrain**.

## Panel V1

Le panel contient six clients synthétiques et un contradicteur :

| Agent | Rôle de test |
|---|---|
| Julien | Cœur de cible : freelance tech établi, pilotage encore intuitif |
| Thomas | Missions longues et faible fréquence commerciale |
| Léa | Nombreux petits clients et activité fragmentée |
| Sophie | Indépendante mature avec processus déjà solides |
| Nicolas | Sceptique, tableur + IA généraliste comme substituts |
| Camille | Solo en croissance vers une petite agence |
| Contradicteur | Cherche pourquoi le conseil Atlas est évident, faux, incomplet ou inutile |

Les définitions détaillées sont dans `personas.md`.

## Règles anti-complaisance

1. Un persona ne sait pas qu'il doit aimer Atlas.
2. Il répond d'abord à partir de son état et de ses pratiques, pas de la vision produit.
3. Sa décision **avant Atlas** est figée avant présentation de la recommandation.
4. Le modèle jouant Atlas n'accède qu'aux données déclarées disponibles pour Atlas.
5. Le `Hidden State` du persona est inaccessible à Atlas et sert à tester les erreurs de contexte.
6. Une recommandation peut être rejetée même si ses calculs sont exacts.
7. « Intéressant », « utile » ou « j'aime bien » ne comptent pas comme changement de décision.
8. Le contradicteur cherche activement une explication alternative et un substitut moins coûteux.
9. Les résultats négatifs ne sont jamais régénérés dans le but d'obtenir une meilleure note.
10. Les exécutions et variantes doivent être conservées, y compris les échecs.

## Protocole d'une expérience

Chaque expérience suit la séquence :

```text
Hypothèse
    ↓
État métier figé
    ↓
Décision du persona avant Atlas
    ↓
Recommandation Atlas
    ↓
Réaction du persona
    ↓
Décision après Atlas
    ↓
Contre-analyse
    ↓
Classification
```

### Échelle de valeur

- `0 — Inutile` : déjà connu, faux, hors contexte ou sans action utile.
- `1 — Informatif` : apporte une information nouvelle sans influencer la décision.
- `2 — Pertinent` : mérite réflexion ou vérification.
- `3 — Décision modifiée` : change une action, un calendrier ou un arbitrage prévu.
- `4 — Forte valeur` : le persona estime qu'il n'aurait probablement pas pris cette décision sans l'analyse et peut expliquer pourquoi.

Les niveaux 3 et 4 sont les seuls signaux synthétiques forts. Ils restent des signaux synthétiques.

## Critères supplémentaires

Chaque recommandation est également évaluée sur :

- `nouveauté` : Atlas révèle-t-il quelque chose que le persona n'avait pas déjà identifié ?
- `pertinence temporelle` : pourquoi agir maintenant ?
- `preuve` : les faits utilisés sont-ils vérifiables ?
- `contexte manquant` : une information absente pourrait-elle inverser la recommandation ?
- `actionnabilité` : existe-t-il une prochaine décision ou action concrète ?
- `substitution` : tableur, logiciel de gestion, calendrier ou IA généraliste font-ils aussi bien avec un effort acceptable ?
- `friction` : coût de collecte et de maintien des données nécessaires.

## Séparation des preuves

Les résultats de ce dossier utilisent obligatoirement le préfixe `SYN-`.

Les observations humaines futures utilisent un registre distinct sous :

```text
evolution/validation/field/
```

Une synthèse de stratégie ou une décision de gouvernance doit toujours distinguer :

- `Synthetic Evidence` : simulation utile pour éliminer ou préciser une hypothèse ;
- `Market Evidence` : comportement ou déclaration d'une personne réelle ;
- `Commercial Evidence` : paiement, renouvellement, conversion ou autre comportement économique réel.

Aucun nombre issu de `synthetic/` ne doit être agrégé avec les registres terrain ou pricing.

## Utilisation des modèles

Les exécutions répétitives doivent privilégier un modèle moins coûteux. Un modèle de raisonnement haut de gamme intervient principalement pour :

- analyser une synthèse compacte de plusieurs exécutions ;
- identifier les hypothèses qui survivent ;
- décider quelle expérience suivante maximise l'information ;
- challenger une décision de simplification ou de pivot.

Il ne doit pas être utilisé par défaut pour jouer chaque persona à chaque exécution.

## Première expérience

La première expérience de référence est `experiments/SYN-BH-001.md` : nouvelle mission proposée par un client récurrent dont les paiements passés sont lents.
