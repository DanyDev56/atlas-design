# Product Metrics

## Valeur

Temps nécessaire pour envoyer un premier devis.

Temps nécessaire pour être payé.

Temps gagné grâce aux automatisations.

---

## Qualité

Taux d'erreur.

Temps moyen de réponse.

Disponibilité.

---

## Advisor

Recommendations Generated par RuleKey et Priority.

Taux de Recommendation Completed.

Taux de Recommendation Dismissed par DismissalReason.

Taux de Recommendation Expired.

Temps entre Generated et Completed.

Affichages, ouvertures et clics sont des métriques Product Analytics, pas des
états Advisor. L'impact métier n'est publié qu'après définition d'une méthode
d'outcome distincte de la simple completion.

---

## Satisfaction

NPS.

CSAT.

Taux de rétention.

Temps jusqu'à la première valeur.

---

## Activation et pricing

Les métriques tarifaires sont interprétées par cohorte et par prix réellement
présenté. Une intention déclarée ne vaut pas une décision d'achat.

- taux inscription → Workspace activé ;
- temps jusqu'à la première valeur ;
- taux essai démarré → essai activé ;
- taux essai activé → abonnement payant ;
- revenu moyen par Workspace actif ;
- répartition mensuel / annuel ;
- taux de remise effectif ;
- churn volontaire et involontaire ;
- raisons structurées de non-conversion et de résiliation ;
- marge contributive par cohorte ;
- délai de récupération du coût d'acquisition ;
- charge de support par Workspace actif.

Les métriques d'inscription, d'activation et de conversion restent distinctes.
Une conversion obtenue par une promotion exceptionnelle ne valide ni le prix
catalogue ni la rétention.

### Définitions de calcul

| Indicateur | Numérateur | Dénominateur |
|---|---|---|
| Activation d'essai | Workspaces ayant franchi la définition d'activation | Workspaces dont l'essai a démarré dans la cohorte |
| Conversion activé → payant | Workspaces ayant autorisé un premier paiement | Workspaces activés arrivés à une décision d'achat |
| Rétention à 90 jours | Workspaces encore payants à J90 | Workspaces payants observables jusqu'à J90 |
| Churn logo volontaire | Workspaces résiliés volontairement dans la période | Workspaces payants au début de période |
| ARPA mensuel | revenu d'abonnement net des remises, hors taxes | Workspaces payants moyens de la période |
| Marge contributive | revenu net moins coûts directs attribuables | revenu net hors taxes |

Chaque publication indique la fenêtre, la taille de cohorte, le prix catalogue,
la remise effective et les exclusions. Les comptes encore non observables à
J90 ne sont ni comptés comme retenus ni comme perdus.

Pour la beta fermée, l'activation est mesurée à partir des états métier déjà
nécessaires au service, et non à partir de pages vues ou de clics : adresse
vérifiée, Workspace prêt, données métier disponibles, première valeur puis
réutilisation lors d'un jour distinct. Les définitions, dates de revue et règles
de minimisation sont fixées dans le
[`beta-research-plan.md`](../../implementation/runbooks/beta-research-plan.md).
Cette phase n'autorise ni SDK de tracking tiers, ni session replay, ni profilage
publicitaire.

Le protocole et le registre qui alimentent ces mesures sont décrits dans
[`pricing-validation.md`](pricing-validation.md).
