# Devil's Advocate — BH-001 run-002

## Verdict contradicteur

O01 formule une précaution raisonnable, mais le run ne démontre pas une valeur incrémentale robuste d'Atlas. Les faits visibles étaient déjà connus, l'action proposée reste générique et aucun résultat de paiement futur n'est observé. Le run fournit uniquement une Synthetic Evidence : aucune fréquence observée ne doit être extrapolée au marché.

## Objections

1. **Critique — Confusion entre retard, exposition et défaut.**
   Les quatre factures ont été payées intégralement ; les retards établissent un délai d'encaissement historique, pas une probabilité d'impayé. O01 affirme qu'un acompte ou des jalons « réduiraient le montant restant à encaisser », mais ne démontre ni que le client les acceptera, ni qu'ils amélioreront le paiement final. P01-H04 rappelle explicitement que les retards ne prédisent pas un impayé ; P06-H02 accepte volontairement cette exposition.

2. **Importante — Information déjà connue dans de nombreuses unités.**
   Plusieurs Pre-Atlas identifiaient déjà les retards et demandaient déjà une protection : P01-H03/H04, P02-H03/H04, P03-H03/H04, P04-H03/H04, P05-H03/H04 et P06-H03/H04. Pour ces unités, Atlas confirme surtout une décision préexistante ; il ne révèle pas une information nouvelle.

3. **Importante — Alternative moins coûteuse et presque équivalente.**
   Un historique de factures, un tableur de trésorerie, un suivi des relances et un seuil d'exposition reproduisent l'essentiel de l'analyse : P01-H04, P04-H01/H02, P05-H02/H04 et P02-H02. Les substituts sont explicitement décrits comme suffisants ou déjà disponibles.

4. **Critique — Information inconnue susceptible d'inverser la décision.**
   Trésorerie, dépenses imminentes, rentabilité, importance stratégique, tolérance au risque, marge de négociation et réaction réelle du client restent inconnues. Ces variables inversent effectivement la réponse : P01-H02, P03-H02, P05-H02 et P06-H02 maintiennent les conditions sans acompte malgré les mêmes retards. La recommandation ne peut donc pas être présentée comme universelle.

5. **Importante — Fausse urgence et cadrage orienté.**
   « Démarrer rapidement » est une préférence du client, pas une échéance critique démontrée. La formulation « avant d'accepter et de démarrer », l'accent mis sur l'« exposition » et l'invitation à demander un acompte orientent vers une protection supplémentaire, alors que les données montrent seulement quatre paiements tardifs mais complets. P01-H02, P03-H02 et P06-H02 ne constatent aucune dégradation nouvelle.

6. **Importante — Action trop vague pour être opérationnelle.**
   « Acompte ou facturation par étapes » ne précise ni montant, ni pourcentage, ni jalon, ni condition d'arrêt. O01 reconnaît lui-même qu'aucun montant n'est préconisé. P01-H01, P02-H02, P03-H03 et P05-H01 évaluent positivement une action qui reste pourtant à négocier et à définir.

7. **Mineure — Coûts et frictions sous-estimés.**
   La collecte des données critiques nécessite un travail réel ; la négociation d'un acompte ou de jalons peut coûter du temps, de l'administration ou du capital relationnel. P05-H02/H04 et P01-H04 indiquent que les outils existants couvrent déjà le suivi. La réduction d'exposition n'est donc pas gratuite ni nécessairement supérieure au coût du dispositif.

8. **Critique — Variabilité artificielle du protocole.**
   La structure des résultats est presque entièrement déterminée par le Hidden State : H01 produit 6/6 changements de décision ; H02, 1/6 ; H03, 0/6 ; H04, 0/6. Le total de 7/24 changements est donc un artefact synthétique de quatre états privés répétés, non une estimation de comportement. Le manifeste signale aussi 29 tentatives invalidées, une contamination de lot, des reprises et des contextes parfois « sans accès fichier ». Cela limite la comparabilité entre unités.

9. **Importante — Scores de valeur hétérogènes et parfois incohérents.**
   P03-H04 attribue une valeur 3 alors que la décision était déjà prise, les données étaient connues et aucune modalité nouvelle n'est fournie. À l'inverse, P02-H04 attribue 0 dans une situation structurellement similaire ; P04-H04 et P06-H04 attribuent 1. Les scores semblent donc refléter une appréciation subjective de la confirmation plutôt qu'une mesure stable de valeur incrémentale.

## Scores contestés

- Moyenne brute observée : **49/24 = 2,04**, mais elle n'a pas de portée statistique marché.
- Je conteste au minimum le **3 de P03-H04** : les preuves disponibles soutiennent plutôt une valeur de confirmation faible, de niveau 1–2.
- Les **3 de P02-H02, P03-H01, P04-H01 et P05-H01** sont également discutables : le changement porte surtout sur l'ordre des actions ou le cadrage, sans seuil, montant, résultat ou information nouvelle.
- Les scores ne doivent pas être agrégés sans distinguer : changement décisionnel, changement de calendrier, simple confirmation et action effectivement réalisable.

## Substituts et coût des données

Le substitut le moins coûteux est un suivi des factures et encaissements, complété par un seuil personnel d'exposition et une vérification de trésorerie : P01-H04, P04-H01, P05-H02.

Les données potentiellement décisives — trésorerie, dépenses, encours, rentabilité et tolérance — sont privées et absentes d'Atlas. La réponse du client sur un acompte ou des jalons ne peut être obtenue que par une négociation réelle. Le run ne mesure ni le coût de cette négociation ni son effet sur la relation.

## Limites protocolaires

Le manifeste classe explicitement le run en **Synthetic Evidence: YES / Market Evidence: NO**. Une seule recommandation O01 a été produite dans un contexte Atlas vierge, sans Hidden State, à partir de données visibles identiques pour les 24 simulations.

Les versions du scénario, du protocole et du prompt sont encore « Draft ». Les invalidations et reprises sont documentées, mais les contextes d'exécution ne sont pas entièrement homogènes. Le protocole ne teste ni l'acceptation réelle d'un acompte, ni le paiement futur, ni la conservation de la relation commerciale.

## Ce que le run permet / ne permet pas de conclure

Le run permet de conclure qu'une synthèse explicite des retards et de l'encours peut pousser certains profils synthétiques à négocier avant de démarrer, surtout lorsqu'ils n'avaient pas déjà formalisé cette exposition.

Il ne permet pas de conclure qu'Atlas :

- détecte un risque de défaut ;
- améliore les délais ou la probabilité de paiement ;
- produit une décision supérieure à un tableur ou à un suivi existant ;
- justifie un acompte dans tous les cas ;
- préserve la relation commerciale ;
- possède une valeur ou une fréquence généralisable au marché.

## Recommandation au Review Committee

Traiter ce run comme une hypothèse de cadrage et non comme une validation de résultat. Avant toute décision finale, exiger une comparaison préenregistrée avec le substitut tableur/seuil d'exposition, des critères chiffrés d'acompte ou de jalons, et une observation séparée de la faisabilité commerciale et des paiements réels.
