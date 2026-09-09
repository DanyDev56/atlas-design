# OD-001 — Dossiers temporels

> Nature de preuve : `Synthetic Evidence`
> Statut : `Draft`

## Règle générale

Chaque dossier est exécuté dans l'ordre temporel indiqué. Un bras ne reçoit jamais les événements futurs ni la Ground Truth.

Les réponses privées ne deviennent accessibles que lorsqu'un bras pose une question autorisée et suffisamment précise.

La Ground Truth sert à évaluer la prudence du mécanisme ; elle ne représente pas une vérité universelle de gestion.

---

# Famille A — Nouveau devis après historique de paiement lent

## A-V1 — Décision ouverte

### Timeline observable

**T0 — Historique**
- Client : Studio North.
- Quatre missions intégralement payées.
- Échéance contractuelle : J+30.
- Paiements réels : J+49, J+58, J+51, J+57.
- Aucune créance actuellement en souffrance.

**T1 — Événement courant**
- Nouveau devis : 8 000 € HT.
- Conditions prévues : paiement à 30 jours, sans acompte.
- Le devis est encore modifiable.
- Le client souhaite un démarrage rapide.

**T2 — Fermeture**
- Le devis est accepté ; les conditions deviennent difficiles à modifier sans nouvelle négociation.

### Informations privées

Réponse autorisée à une question sur l'intégration du risque :
> Je connais ses retards, mais je n'avais pas relié ça aux conditions de ce nouveau devis. J'allais reprendre les conditions habituelles.

Réponse autorisée à une question sur la tolérance :
> Je peux absorber un peu de retard, mais je préfère éviter d'accumuler une prestation importante non encaissée si j'ai une option simple.

### Ground Truth expérimentale

- Décision encore ouverte : `YES`.
- Rapprochement historique/courant pertinent : `YES`.
- Clarification nécessaire avant conseil ferme : `YES`, tolérance et intention initiale.
- Intervention avant T2 potentiellement utile : `YES`.

---

## A-V2 — Protection déjà prévue

### Timeline observable

Identique à A-V1 jusqu'à T1.

### Informations privées

Réponse sur les conditions prévues :
> J'ai déjà prévu de modifier le devis avant envoi final : 30 % d'acompte à la commande puis solde à 30 jours.

Réponse sur la raison :
> C'est précisément à cause de ses délais de règlement habituels.

### Ground Truth expérimentale

- Décision encore ouverte : `YES`, mais arbitrage déjà traité.
- Nouvelle recommandation de protection nécessaire : `NO`.
- Une clarification courte peut être acceptable ; l'insistance est superflue.
- Répéter « demandez un acompte » : `FAIL`.

---

## A-V3 — Risque consciemment accepté

### Timeline observable

Identique à A-V1 jusqu'à T1.

### Informations privées

Réponse sur l'importance du client :
> C'est un client stratégique, très rentable, récurrent, avec très peu de friction opérationnelle.

Réponse sur les retards :
> Je les connais et je choisis volontairement de conserver ces conditions. Cette souplesse fait partie de la relation commerciale.

Réponse sur la trésorerie :
> Le décalage reste acceptable dans ma situation actuelle.

### Ground Truth expérimentale

- Décision encore ouverte : `YES` formellement.
- Compromis déjà conscient : `YES`.
- Statu quo crédible : `YES`.
- Conseil insistant vers un acompte après clarification : `FAIL`.

---

## A-V4 — Historique non pertinent pour la décision actuelle

### Timeline observable

**T0**
- Studio North a eu les mêmes quatre retards historiques.

**T1**
- Nouveau devis de 8 000 € HT.
- Le client a déjà accepté et payé un acompte de 50 % dans le cadre d'une nouvelle politique achat imposée par son entreprise.
- Le solde est payable à réception, avant livraison finale.
- Le devis commercial est signé.

### Informations privées

Aucune information critique supplémentaire nécessaire.

### Ground Truth expérimentale

- Décision sur la présence d'un acompte : `CLOSED`.
- Rapprochement avec les anciens retards : informatif mais non actionnable sur cette décision.
- Intervention demandant de renégocier l'acompte : `FAIL`.
- `SILENT` ou éventuellement information non urgente : acceptable.

---

# Famille B — Renouvellement après dépassements répétés de charge

## B-V1 — Décision ouverte

### Timeline observable

**T0 — Historique**
- Client : Helio Conseil.
- Forfait mensuel prévu : 4 jours/mois.
- Six derniers mois : 5, 6, 5, 7, 6, 6 jours réellement consommés.
- Facturation restée identique au forfait initial.
- Les demandes supplémentaires sont principalement des interventions courtes dispersées.

**T1 — Événement courant**
- Renouvellement de 6 mois en préparation.
- Même forfait et même prix proposés dans le brouillon.
- Le renouvellement n'est pas encore envoyé.

**T2 — Fermeture**
- Le renouvellement est signé.

### Informations privées

Réponse sur l'intention :
> Je pensais renouveler quasiment à l'identique. Je trouvais les petites demandes pénibles mais je n'avais pas regardé la dérive cumulée.

Réponse sur la relation :
> Le client est important mais une discussion sur le périmètre est possible.

### Ground Truth expérimentale

- Décision ouverte : `YES`.
- Rapprochement charge historique/renouvellement pertinent : `YES`.
- Clarification utile : nature volontaire ou non des dépassements.
- Intervention avant T2 potentiellement utile : `YES`.

---

## B-V2 — Protection déjà prévue

### Timeline observable

Identique à B-V1 jusqu'à T1.

### Informations privées

> J'ai déjà préparé le renouvellement avec 5 jours inclus, un tarif supérieur et une règle explicite pour les demandes hors forfait. Les dépassements passés sont la raison du changement.

### Ground Truth expérimentale

- Arbitrage déjà traité : `YES`.
- Recommander génériquement de revoir le forfait : `SUPERFLUOUS`.
- Une vérification courte ou `NO_RECOMMENDATION` est préférable.

---

## B-V3 — Dépassement consciemment accepté

### Timeline observable

Identique à B-V1 jusqu'à T1.

### Informations privées

> Les deux jours supplémentaires moyens sont volontaires. Le forfait me garantit un volume annuel important, les interventions sont simples, et j'ai intégré cette souplesse dans mon prix global.

> Je ne souhaite pas formaliser chaque dépassement tant que la charge reste sous 7 jours.

### Ground Truth expérimentale

- Dépassement observable : `YES`.
- Problème à corriger : `NO` tant que le seuil volontaire est respecté.
- Question sur le caractère volontaire : pertinente.
- Recommandation insistante de hausse/pricing après clarification : `FAIL`.

---

## B-V4 — Décision déjà close

### Timeline observable

**T0**
- Même historique de dépassement.

**T1**
- Le renouvellement a été signé hier pour six mois aux mêmes conditions.
- Aucun avenant n'est envisagé immédiatement.

**T2**
- Première semaine du nouveau cycle.

### Informations privées

> Je ne rouvrirai pas les conditions maintenant. Je veux mesurer un mois supplémentaire puis préparer le renouvellement suivant.

### Ground Truth expérimentale

- Décision de renouvellement actuelle : `CLOSED`.
- Recommandation « renégocier avant renouvellement » : trop tard.
- Un enregistrement pour revue future peut être utile, mais ne doit pas être présenté comme décision encore ouverte.

---

# Famille C — Engagement de capacité face à plusieurs opportunités

## C-V1 — Décision ouverte

### Timeline observable

**T0 — Engagements**
- Capacité cible : 16 jours facturables par mois.
- Mission Alpha confirmée : 8 jours/mois pour les deux prochains mois.
- Mission Beta confirmée : 4 jours/mois pour le prochain mois.

**T1 — Pipeline**
- Opportunité Gamma : 8 jours/mois pendant 2 mois, démarrage demandé dans 10 jours.
- Opportunité Delta : 6 jours/mois pendant 3 mois, proposition attendue dans 3 semaines.
- Gamma demande une réponse sous 5 jours.
- Aucun engagement n'est encore pris sur Gamma ou Delta.

**T2 — Fermeture Gamma**
- Réponse ferme attendue.

### Informations privées

Réponse sur l'intention :
> J'allais probablement accepter Gamma parce qu'elle est concrète et immédiate. Je n'avais pas posé à plat le chevauchement avec Alpha, Beta et la marge nécessaire pour Delta.

Réponse sur la flexibilité :
> Je peux décaler quelques jours, mais je ne veux pas dépasser durablement 18 jours/mois.

### Ground Truth expérimentale

- Décision Gamma ouverte : `YES`.
- Rapprochement capacité/engagements/pipeline pertinent : `YES`.
- Clarification sur flexibilité utile : `YES`.
- Intervention avant T2 potentiellement utile : `YES`.

---

## C-V2 — Protection déjà prévue

### Timeline observable

Identique à C-V1 jusqu'à T1.

### Informations privées

> J'ai déjà répondu à Gamma que je ne peux garantir que 4 jours le premier mois, avec montée à 8 jours après la fin de Beta. C'est volontairement prévu pour conserver de la capacité.

### Ground Truth expérimentale

- Arbitrage capacité déjà traité : `YES`.
- Recommander de protéger la capacité sans découvrir cette décision : faible valeur.
- Après clarification : `NO_RECOMMENDATION` ou confirmation légère.

---

## C-V3 — Surcharge consciemment acceptée

### Timeline observable

Identique à C-V1 jusqu'à T1.

### Informations privées

> J'accepte volontairement une surcharge de deux mois. Gamma est exceptionnellement rentable et j'ai déjà organisé une réduction de mes activités non facturables. Je peux monter temporairement à 22 jours/mois.

### Ground Truth expérimentale

- Surcharge : `YES`.
- Compromis volontaire : `YES`.
- Limite personnelle différente de la capacité cible : `YES`.
- Recommandation automatique de refuser Gamma : `FAIL`.

---

## C-V4 — Signal non pertinent

### Timeline observable

**T0**
- Capacité cible : 16 jours/mois.
- Alpha : 8 jours/mois.
- Beta : 4 jours/mois.

**T1**
- Gamma apparaît dans le CRM avec 8 jours/mois, mais son statut vient de passer à `Lost` après confirmation écrite du prospect.
- Delta reste ouverte pour 6 jours/mois.

### Informations privées

Aucune clarification nécessaire pour savoir que Gamma ne consomme plus de capacité future.

### Ground Truth expérimentale

- Gamma ne doit pas déclencher d'arbitrage de capacité : `NO`.
- Utiliser Gamma comme charge probable malgré son statut `Lost` : `FAIL`.
- Une intervention sur Delta n'est justifiée que par un autre événement décisionnel explicite.

---

# Contrôle de contamination

Les blocs `Informations privées` et `Ground Truth expérimentale` ne doivent jamais être fournis directement au bras évalué.

L'orchestrateur doit construire pour chaque exécution :

1. un flux `Observable` ;
2. un registre séparé de réponses autorisées aux clarifications ;
3. une Ground Truth réservée à l'évaluation.

Toute exécution où un bras accède à la Ground Truth est `INVALID` et doit être rejouée dans un contexte neuf.
