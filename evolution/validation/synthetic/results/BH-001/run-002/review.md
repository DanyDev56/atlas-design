# Review Committee — BH-001 / run-002

Conclusion : **Field Test**. Le run justifie un test terrain limité de la détection au moment de négocier un devis. Il ne démontre ni une amélioration des décisions commerciales, ni un avantage établi sur les outils existants.

Le comité a examiné les 24 résultats bruts figés, la recommandation unique O01, le manifeste et le rapport contradicteur. Les prérequis de revue sont satisfaits : 24 unités `COMPLETED`, un Observable State et une recommandation figés, Devil’s Advocate terminé. Les réactions, déclarations de changement et scores restent inchangés.

## Distribution descriptive du panel synthétique

| Variante | Score 0 | Score 1 | Score 2 | Score 3 | Score 4 | Total | « Décision modifiée: YES » |
|---|---:|---:|---:|---:|---:|---:|---:|
| H01 — arbitrage non prévu | 0 | 0 | 1 | 5 | 0 | 6 | 6 |
| H02 — souplesse volontaire | 0 | 1 | 4 | 1 | 0 | 6 | 1 |
| H03 — trésorerie contrainte | 0 | 0 | 6 | 0 | 0 | 6 | 0 |
| H04 — acompte déjà prévu | 1 | 3 | 1 | 1 | 0 | 6 | 0 |
| **Total** | **1** | **4** | **12** | **7** | **0** | **24** | **7** |

Ces effectifs décrivent exclusivement les sorties du panel synthétique construit. Ils ne représentent aucune fréquence attendue chez des indépendants réels, aucune probabilité de marché et aucun taux de conversion.

Un score 3 ne signifie pas nécessairement une décision modifiée : P03-H04 conserve sa décision initiale. Inversement, P06-H01 déclare un changement avec un score 2. La distribution ne mesure donc pas à elle seule la valeur décisionnelle incrémentale.

## Trois enseignements

1. **Le rapprochement avec le devis peut modifier l’action envisagée lorsque l’arbitrage n’était pas prévu.** Les six H01 passent de l’acceptation des conditions à une discussion préalable sur les paiements ; cinq attribuent un score 3. Le changement porte principalement sur l’ouverture ou l’ordre de la négociation. Il ne prouve pas qu’un acompte sera obtenu, encaissé ou préférable.

2. **La contrainte financière ne suffit pas à créer une valeur nouvelle pour Atlas.** Les six H03 avaient déjà prévu de revoir les conditions ; tous déclarent une décision inchangée et un score 2. H04 confirme également la faible nouveauté lorsque l’acompte était déjà décidé. La cible hypothétique est donc un arbitrage encore ouvert et insuffisamment explicité, pas simplement un client lent ou une trésorerie contrainte.

3. **L’avantage potentiel porte sur le déclenchement dans le travail courant.** P04-H01 identifie un lien utile malgré un suivi rigoureux, mais décrit aussi une adaptation simple de son tableur. P05-H02 et P01-H04 considèrent leurs outils largement suffisants. La continuité entre historique et devis reste une hypothèse à éprouver ; le run présente directement les données et la recommandation, sans démontrer cette continuité.

## Contre-exemples et trois objections

1. **Confirmation et persuasion peuvent être prises pour une amélioration.** P03-H04 attribue 3 sans changement ni modalité nouvelle, tandis que P02-H04 attribue 0 dans une situation comparable. P02-H02 abandonne partiellement une souplesse volontaire sans fait nouveau ; cette exception peut traduire un arbitrage utile ou une sensibilité au cadrage. Les scores contestés par le Devil’s Advocate sont conservés, mais ne constituent pas une preuve autonome d’amélioration.

2. **L’exposition est parfois surinterprétée.** Les anciennes factures sont intégralement réglées : leurs montants ne constituent pas un encours à cumuler avec les 8 000 €. Plusieurs réactions évoquent pourtant une « exposition cumulée ». Le montant du devis n’est pas non plus une mesure des dépenses réellement avancées. O01 reconnaît correctement que les retards ne prédisent pas le prochain paiement ; certaines réactions affirment néanmoins une réduction concrète de l’exposition ou la préservation de la relation sans résultat observé. Un acompte n’apporte l’effet attendu que s’il est accepté et effectivement encaissé.

3. **La supériorité sur les substituts et son coût restent inconnus.** Aucun outil concurrent n’a été exécuté dans des conditions comparables. Plusieurs rubriques « Substitut évident » décrivent une autre modalité commerciale plutôt qu’un outil ; P02-H01 répond « Aucun », sans comparaison démontrée. Le run ne mesure ni saisie, ni maintenance, ni interruption, ni friction de négociation.

Les cinq H02 qui maintiennent leurs conditions constituent des contre-exemples utiles à une règle générale imposant un acompte. Les douze H03/H04 sans changement réfutent également l’idée que le même conseil produit systématiquement un nouvel arbitrage.

## Évaluation des sept dimensions

| Dimension | Évaluation |
|---|---|
| **Decision Value** | Signal de changement déclaré dans les H01 et P02-H02. Aucun effet commercial réalisé ni supériorité de la décision finale démontrés. |
| **Novelty** | Nouveauté surtout dans le rapprochement entre retards connus et décision future. Faible lorsque l’utilisateur avait déjà intégré ce lien. |
| **Timing** | Moment pertinent dans le scénario : devis négociable avant démarrage. La détection réelle à cet instant et l’acceptabilité d’une interruption ne sont pas établies. Le souhait de démarrer rapidement ne démontre pas une urgence critique. |
| **Evidence** | Les quatre paiements soutiennent une description des retards historiques et une invitation prudente à revoir les conditions. Ils ne suffisent pas pour prédire un défaut, chiffrer le besoin de trésorerie ou fixer un acompte optimal. |
| **Substitution** | Le contenu analytique paraît reproductible ; l’avantage éventuel de continuité et de déclenchement n’est pas mesuré. |
| **Data Cost** | Non mesuré. Le coût dépend notamment de la disponibilité des dates de paiement et du lien avec le devis, puis de la collecte des informations privées nécessaires à l’arbitrage. |
| **Robustness** | O01 reconnaît ses inconnues et autorise le maintien des conditions. Les H02/H04 montrent que l’absence de changement est possible. La robustesse reste limitée à une exposition unique : aucune interaction après refus ni gestion des alertes répétées n’a été testée. |

## Comparaison explicite avec les substituts

| Substitut | Valeur comparable envisageable | Question non résolue |
|---|---|---|
| **Logiciel de facturation** | Si les échéances et règlements sont enregistrés, l’historique permet de retrouver les retards. Une consultation avant devis pourrait suffire. | Le logiciel effectivement utilisé rend-il spontanément le rapprochement avec la nouvelle mission assez visible ? Aucun produit n’a été testé. |
| **Tableur simple** | Dates prévues et réelles, devis à venir et seuil personnel d’exposition permettent de reconstruire l’essentiel du raisonnement. P04-H01 propose explicitement cette adaptation. | Quel effort de mise à jour et quelle discipline de consultation exige-t-il, comparés à Atlas ? |
| **IA généraliste** | Avec exactement les mêmes données, une synthèse des retards et des options d’acompte ou de jalons semble reproductible. Il s’agit d’une hypothèse, pas d’un résultat comparatif. | L’utilisateur penserait-il spontanément à poser la question ? À données égales, Atlas doit prouver un avantage de continuité et de déclenchement compensant son coût de maintenance. |

## Informations critiques manquantes et conditions de validité

Les informations récurrentes sont la trésorerie disponible, le montant et la date des dépenses imminentes, les autres engagements, le calendrier de production et de facturation, la rentabilité et l’importance stratégique du client, la tolérance au décalage d’encaissement et les possibilités réelles de négociation. La réponse du client et l’encaissement effectif d’un premier paiement restent à observer.

Certaines réponses signalent à tort l’historique de paiement comme manquant, notamment P02-H02, alors qu’il est fourni. P06-H02 déclare qu’aucune information ne manque. Ces réponses sont conservées comme limites des données brutes.

La valeur proposée suppose un devis encore modifiable, des données fiables, un arbitrage non déjà résolu et une intervention proportionnée. Une préférence commerciale consciente doit pouvoir conduire à conserver les conditions. Il faut distinguer un paiement par étapes simplement facturé d’un encaissement réellement anticipé.

## Limites de la simulation

Les 24 unités combinent six personas avec quatre états privés imposés autour d’un seul cas observable et d’une seule recommandation. Leur structure favorise des réponses déterminées par ces états ; elle ne reproduit pas la diversité ni l’indépendance d’observations humaines.

Le manifeste documente 29 tentatives invalidées, une contamination de lot et des conditions de reprise hétérogènes. Cette traçabilité rend les incidents visibles, mais n’élimine pas le risque de sélection lié aux corrections et reprises. Les versions sont encore `Draft`. Le comité n’a pas réexécuté les unités ni audité les tentatives écartées.

Aucun utilisateur réel, comportement spontané, devis accepté, paiement, coût d’usage, prix ou résultat relationnel n’est observé.

## Conclusion et prochaine expérience

**Field Test** respecte les critères figés de passage :

- Plusieurs profils H01 produisent un niveau 3 avec un arbitrage initialement non prévu.
- O01 reste négociable et autorise explicitement le statu quo ; les réactions H02/H04 ne montrent pas une obligation universelle de suivre le conseil. P02-H02 demeure un cas à examiner, et l’insistance après refus n’est pas testée.
- Les informations manquantes sont explicitement reconnues dans O01.
- L’avantage proposé face aux substituts repose sur la continuité et la détection pendant la négociation ; cet avantage demeure à démontrer.

La prochaine expérience minimale est un **pilote manuel sur six situations réelles de devis encore négociables**, dont certaines avec souplesse volontaire ou acompte déjà prévu. Avant toute intervention, figer la décision envisagée et observer le parcours habituel avec les outils existants. Répartir ensuite les situations entre une intervention Atlas manuelle au moment du devis et un rappel équivalent invitant à consulter un tableau simple contenant les mêmes faits. Faire également produire à une IA généraliste une analyse des mêmes données, hors exposition utilisateur, pour comparer le contenu sans multiplier les interventions.

Préenregistrer les observations : différence par rapport à la décision initiale, raison du changement, conditions effectivement proposées, réponse du client, effort de préparation et de mise à jour, acceptabilité de l’interruption. Conserver les refus et absences de changement. Ce petit pilote sert à identifier le mécanisme et les frictions, sans estimer un effet statistique.

Le run permet de retenir une hypothèse testable : relier un historique de paiement à un devis encore ouvert peut déclencher une discussion auparavant non prévue. Il ne permet pas de conclure qu’Atlas améliore les paiements, les décisions ou la relation client, surpasse les substituts, justifie son coût ou dispose d’un marché.

Ces résultats sont synthétiques et ne constituent pas une validation de marché.
