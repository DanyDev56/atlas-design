# Template — Synthetic Experiment

> Nature de preuve : `Synthetic Evidence`
> Statut : `Draft | Running | Completed | Abandoned`

## Identité

- **ID** : `SYN-XXX`
- **Titre** :
- **Date** :
- **Hypothèse testée** :
- **Décision produit concernée** :

## Critère de falsification

Décrire avant l'expérience ce qui conduirait à considérer l'hypothèse comme faible ou invalide.

Une expérience sans critère d'échec explicite ne doit pas être lancée.

## Panel

- Personas utilisés :
- Nombre de runs par persona :
- Variations de scénario :
- Justification du panel :

## Données accessibles à Atlas

Lister uniquement les faits qu'Atlas peut connaître dans le scénario.

## Hidden State

Définir séparément, pour chaque persona si nécessaire :
- ce qu'il sait déjà ;
- sa décision prévue ;
- ses contraintes privées ;
- ses préférences ;
- les faits inconnus d'Atlas susceptibles de modifier la décision.

Le moteur Atlas ne reçoit pas cette section.

## Scénario

Décrire une situation précise et figée, sans orienter le persona vers la conclusion recherchée.

## Phase A — Baseline

Questions obligatoires avant exposition à Atlas :

1. Que comprends-tu de la situation ?
2. Quelle décision comptes-tu prendre ?
3. Quand comptes-tu agir ?
4. Pourquoi ?
5. Quelle information pourrait te faire changer d'avis ?

Conserver la réponse telle quelle.

## Phase B — Recommandation Atlas

La recommandation doit contenir :
- décision ou arbitrage proposé ;
- pourquoi maintenant ;
- preuves accessibles ;
- hypothèses importantes ;
- information manquante susceptible d'inverser le conseil ;
- prochaine action concrète ;
- condition d'expiration ou de réévaluation lorsque pertinente.

Une réponse `No Recommendation` est valide.

## Phase C — Réaction

Demander :

1. Qu'est-ce qui est nouveau pour toi ?
2. Qu'est-ce que tu savais déjà ?
3. Es-tu d'accord avec le raisonnement ? Pourquoi ?
4. Quelle information manque ?
5. Ta décision change-t-elle ?
6. Quelle action vas-tu maintenant prendre ?
7. Aurais-tu obtenu la même conclusion avec tes outils actuels ? Comment ?

## Scoring

- `0 — Inutile`
- `1 — Informatif`
- `2 — Pertinent`
- `3 — Décision modifiée`
- `4 — Forte valeur`

Documenter la justification du score. Ne pas attribuer 3 ou 4 sur la seule base d'une appréciation positive.

## Devil's Advocate

Évaluer au minimum :
- évidence de la recommandation ;
- alternative la moins coûteuse ;
- information cachée qui invalide le conseil ;
- fausse urgence ;
- précision injustifiée ;
- effort de collecte / maintien des données ;
- confusion entre résultat observé et causalité Atlas.

## Résultats

| Persona | Baseline | Réaction | Nouvelle décision | Score | Objection principale |
|---|---|---|---|---:|---|

## Synthèse

- distribution des scores ;
- recommandations déjà connues ;
- décisions effectivement modifiées dans la simulation ;
- informations manquantes récurrentes ;
- substituts crédibles ;
- biais possibles de la simulation.

## Décision

Choisir exactement une conclusion :

- `Keep` — hypothèse suffisamment robuste pour poursuivre les simulations ciblées ;
- `Change` — modifier l'hypothèse ou la recommandation avant nouveau test ;
- `Kill` — ne pas investir davantage sur cette direction sur la seule base actuelle ;
- `Field Test` — hypothèse synthétiquement prometteuse qui mérite désormais une validation réelle.

## Limite obligatoire

Terminer toute synthèse par :

> Ces résultats sont issus d'agents synthétiques. Ils servent à falsifier, comparer et préparer des hypothèses. Ils ne démontrent ni demande réelle, ni comportement réel, ni volonté de payer, ni rétention.
