---
id: PRODUCT-PRICING-001
title: Stratégie tarifaire Atlas
status: Draft
owner: Product
version: 0.2.0
last_updated: 2026-08-23

references:
  - product-strategy.md
  - personnas/persona-primary.md
  - personnas/anti-personas.md
  - ../../evolution/roadmap/mvp-scope.md
  - ../../evolution/roadmap/mvp-acceptance.md
  - ../../evolution/governance/experimentation.md
  - ../../evolution/governance/metrics.md
  - ../../evolution/governance/pricing-validation.md
  - ../../evolution/blueprint/roadmap.md
  - ../domains/workspace/scope.md
---

# Stratégie tarifaire Atlas

## Statut de la proposition

Ce document définit l'alignement tarifaire à tester pour l'early access et la
V1. Il ne constitue ni un tarif public déjà commercialisé, ni une autorisation
d'implémenter immédiatement un système d'abonnement.

Les montants marqués **hypothèse** doivent être validés auprès d'utilisateurs du
persona principal. Le passage de `Draft` à `In Review` exige les preuves
définies dans la section « Validation » et enregistrées dans le
[protocole de validation](../../evolution/governance/pricing-validation.md).

Ce document est la source de vérité sur l'intention de packaging et les règles
de décision. Le catalogue commercial versionné deviendra la source de vérité
exécutable uniquement après la gate de commercialisation.

---

## Résumé de la recommandation

| Décision | Proposition V1 |
|---|---|
| Offre publique | Une seule offre payante `Atlas Solo` |
| Prix mensuel | **24 € HT/mois**, sans engagement — hypothèse |
| Prix annuel | **240 € HT/an**, payé d'avance — hypothèse |
| Équivalent annuel | 20 € HT/mois, soit deux mois offerts |
| Plage à tester | 19 à 29 € HT/mois |
| Essai | 30 jours, produit complet, sans carte bancaire |
| Offre gratuite permanente | Aucune |
| Unité de prix | Un Workspace actif par mois |
| Limites métier | Pas de quota commercial sur Clients, devis ou factures |
| Valeur différenciante | Business Health, Advisor, priorités et preuves inclus |
| Offre équipe | Exploration ultérieure, non commercialisée en V1 |

Le prix recommandé place Atlas au-dessus d'un simple outil de facturation, tout
en restant dans la zone des logiciels de gestion payants utilisés par les
indépendants. Pour le persona réalisant 70 000 à 120 000 € de chiffre d'affaires
annuel, l'offre mensuelle représente environ 0,24 % à 0,41 % de son chiffre
d'affaires annuel. Ce ratio sert uniquement à vérifier l'ordre de grandeur ; il
ne prouve pas la disposition à payer.

### Niveau de certitude des décisions

| Élément | Niveau actuel | Condition de consolidation |
|---|---|---|
| Positionnement payant centré sur la décision | Direction produit | cohérence continue avec la stratégie produit |
| Offre unique sans plan gratuit permanent | Candidat V1 | compréhension et conversion observées |
| Workspace comme unité de prix | Candidat V1 | absence d'objection récurrente et marge soutenable |
| 24 € HT/mois et 240 € HT/an | Hypothèse | scorecard pricing et décisions réelles |
| 30 jours sans carte bancaire | Hypothèse | activation et décision d'achat avant J30 |
| 1 Owner et 2 Members inclus | Hypothèse de packaging | usages collaboratifs et coûts observés |
| Atlas Équipe | Exploration future | découverte spécifique du persona secondaire |

Une documentation complète ne transforme pas une hypothèse en fait. La
maturité commerciale dépend des preuves et des gates, pas du nombre de sections
écrites.

### Principes tarifaires invariants

- le prix est présenté avant consentement avec sa devise, sa période, les taxes
  applicables et les conditions de renouvellement ;
- Atlas monétise la valeur de pilotage, pas la rétention artificielle des
  données ni une limitation punitive des objets métier ;
- Business Health et Advisor restent inclus dans l'offre principale ;
- aucune facturation ni conversion automatique d'essai sans consentement
  explicite ;
- les règles sont identiques pour deux clients placés dans la même situation
  commerciale, hors remises documentées et bornées ;
- l'utilisateur peut résilier, exporter et fermer son compte par un parcours
  compréhensible ;
- toute promesse tarifaire publique doit correspondre à une capacité réellement
  livrée et supportée.

---

## Alignement avec la stratégie produit

### Ce que le client achète

Le client n'achète pas seulement la possibilité de créer un devis ou une
facture. Il achète une boucle de pilotage complète :

```text
Gérer
  -> Comprendre
  -> Décider
  -> Agir
  -> Mesurer
```

Le packaging doit donc inclure ensemble :

- CRM et cycle commercial ;
- devis, factures, paiements, relances et documents ;
- import de l'historique utile au démarrage ;
- Analytics et preuves explicables ;
- Business Health ;
- Advisor et priorités d'action ;
- notifications in-app et e-mails importants ;
- accès aux données et à leur provenance.

Séparer la gestion et la décision dans deux plans différents affaiblirait la
promesse du produit : l'utilisateur pourrait payer Atlas sans accéder à sa
différenciation principale.

### Ce que le prix ne cherche pas à financer

Atlas ne se positionne pas comme :

- une Plateforme Agréée de facturation électronique ;
- un compte bancaire professionnel ;
- une solution de tenue comptable complète ;
- un ERP configurable ;
- un logiciel de facturation gratuit financé par des services adjacents ;
- un produit publicitaire ou fondé sur la revente de données.

Le prix ne doit donc pas être justifié par une promesse de conformité ou de
comptabilité qu'Atlas ne tient pas encore.

---

## Benchmark de marché

Benchmark observé le 23 août 2026 sur les pages officielles. Les offres ne sont
pas strictement comparables : elles couvrent davantage la facturation, la
comptabilité ou le compte professionnel, alors qu'Atlas se différencie par le
pilotage et la décision. Les tarifs peuvent évoluer ; les pages officielles font
foi.

| Produit | Repère public observé | Lecture pour Atlas |
|---|---|---|
| [Freebe](https://www.freebe.me/tarifs) | Offre unique, toutes fonctionnalités, 30 jours d'essai ; exemple affiché à 12,50 €/mois facturé annuellement pour une micro-entreprise | La simplicité d'une offre unique est crédible, mais Atlas ne doit pas s'aligner sur la seule valeur administrative |
| [Tiime](https://www.tiime.fr/tarifs) | Free ; Smart 17,99 € HT/mois ; Business 24,99 € HT/mois, avec 60 jours d'essai | 24 € HT/mois reste dans une zone déjà acceptée pour un produit de pilotage complet |
| [Indy](https://www.indy.fr/prix/) | Essentiel gratuit ; Plus dès 9 € HT/mois ; Premium variable selon la forme d'entreprise | La facturation gratuite rend une entrée gratuite Atlas peu différenciante et coûteuse à défendre |
| [Abby](https://aide.abby.fr/fr/articles/14107871-comment-utiliser-un-code-promotionnel-sur-abby) | Hors promotion : Start 11 €, Pro 19 € et Business 39 € HT/mois | La plage 19–29 € place Atlas entre usage professionnel individuel et offre avancée |

### Conclusion du benchmark

- la création de devis et factures est fréquemment gratuite ou peu chère ;
- les prix augmentent avec l'automatisation, la collaboration, la comptabilité
  et les services financiers ;
- Atlas doit défendre son prix par des décisions mieux préparées, des retards
  évités et du temps administratif économisé ;
- une guerre de prix sur la facturation contredirait les anti-personas et les
  anti-objectifs du produit.

---

## Architecture de l'offre V1

### Atlas Solo

| Dimension | Inclus dans la cible commerciale |
|---|---|
| Workspace | 1 Workspace actif |
| Accès | 1 Owner et jusqu'à 2 Members inclus |
| CRM | Clients, contacts, opportunités et historique |
| Billing métier | Devis, acceptation publique, factures, acomptes, avoirs, paiements manuels et relances |
| Documents et e-mails | PDF et e-mails transactionnels dans le cadre d'un usage professionnel normal |
| Démarrage | Import historique guidé inclus |
| Pilotage | Dashboard, Analytics, Business Health et preuves |
| Décision | Advisor, priorités, explications et suivi des décisions |
| Notifications | Inbox, préférences et e-mails importants éligibles |
| Support | Support asynchrone standard, sans SLA contractuel |
| Volumétrie métier | Clients, opportunités, devis et factures non facturés à l'unité |

Les capacités indiquées comme incluses décrivent la cible de packaging. Elles ne
doivent être affichées sur une page tarifaire publique que lorsqu'elles sont
effectivement livrées, testées et supportées.

### Pourquoi une seule offre au lancement

- le persona principal est homogène et travaille majoritairement seul ;
- la V1 doit valider la valeur de la boucle complète, pas l'optimisation d'une
  grille tarifaire ;
- plusieurs plans imposeraient des entitlements et des comparaisons complexes
  avant d'avoir observé les usages ;
- aucun composant central de la décision ne doit devenir une option artificielle ;
- une offre unique réduit les erreurs d'achat et facilite la transparence.

### Pourquoi aucun plan gratuit permanent

- les alternatives gratuites possèdent déjà un avantage structurel sur la
  facturation réglementaire ;
- le persona recherchant uniquement la gratuité est un anti-persona explicite ;
- une offre gratuite attirerait des comptes peu activés et augmenterait les
  coûts de support, d'e-mail et de stockage ;
- la valeur d'Atlas nécessite de l'historique, de l'analyse et des
  recommandations, qui doivent être évalués dans un essai complet.

L'absence de plan gratuit n'interdit ni une démo, ni un environnement exemple,
ni un essai sans carte bancaire.

---

## Essai et activation

### Proposition d'essai

- durée : 30 jours à partir de l'activation du Workspace ;
- accès : toutes les capacités d'`Atlas Solo` disponibles ;
- carte bancaire : non requise au démarrage ;
- e-mail : adresse utilisateur vérifiée obligatoire ;
- protection : contrôles anti-abus sur les e-mails et preuves publiques ;
- rappel : notifications avant la fin de l'essai, sans dark pattern ;
- conversion : consentement explicite avant tout prélèvement.

Trente jours couvrent mieux un cycle devis–facture–paiement que quatorze jours.
L'import historique doit néanmoins permettre d'atteindre une première valeur
pendant la première session.

### Définition d'un Workspace activé

Un Workspace est activé pour l'analyse pricing lorsqu'il a :

1. un compte et une adresse e-mail vérifiés ;
2. un profil Workspace suffisamment complété ;
3. au moins un historique importé ou un premier cycle commercial commencé ;
4. une première évaluation Business Health consultable ;
5. une priorité Advisor comprise ou une absence de priorité expliquée.

L'inscription seule n'est pas une activation.

### Fin d'essai

Le comportement cible doit être défini et livré avant commercialisation :

- aucune facturation automatique sans consentement ;
- conservation d'un accès sûr aux données selon la politique de rétention ;
- export et fermeture du compte clairement accessibles ;
- suspension des mutations et effets externes si aucun abonnement n'est actif ;
- restauration idempotente après souscription ;
- messages distincts pour essai terminé, paiement échoué et accès révoqué.

Les durées de grâce et de conservation restent à valider avec Legal, Security et
Support. Elles ne sont pas fixées par ce document.

---

## Métrique de prix et limites

### Métrique principale

La métrique principale est le **Workspace actif par période**, car :

- la valeur est produite à l'échelle de l'activité ;
- les données CRM, Billing et Analytics appartiennent au Workspace ;
- elle reste compréhensible avant l'apparition d'équipes plus grandes ;
- elle n'incite pas l'utilisateur à limiter son usage du produit.

### Métriques à ne pas utiliser au MVP

Atlas ne facture pas au nombre :

- de clients ;
- d'opportunités ;
- de devis ;
- de factures ;
- de paiements ;
- de recommandations ;
- d'e-mails transactionnels normaux.

Ces limites pénaliseraient précisément les comportements que le produit cherche
à améliorer. Des protections techniques anti-abus peuvent exister, mais elles
ne doivent pas devenir silencieusement une tarification à l'usage.

### Usage professionnel normal

La mention « sans quota métier » ne signifie pas qu'Atlas devient un service
d'envoi en masse ou de stockage généraliste. L'usage normal couvre les actions
transactionnelles rattachées à l'activité du Workspace : devis, factures,
relances, notifications et documents produits par les parcours Atlas.

- les campagnes marketing et fichiers de prospection en masse sont hors scope ;
- les limites de débit protègent la sécurité et la délivrabilité, pas la marge
  par une facturation cachée ;
- un blocage anti-abus est explicable, journalisé et contestable ;
- si un coût variable devient structurel, Product revoit publiquement la
  métrique de prix au lieu d'introduire une limite silencieuse.

---

## Politique commerciale cible

Ces règles décrivent le comportement attendu de la V1 payante. Elles restent
soumises à validation Legal et Finance pour le territoire effectivement servi.

### Affichage et taxes

- devise catalogue initiale : euro ;
- prix professionnel de référence : hors taxes ;
- montant HT, taxes applicables, total TTC, période et date du prochain
  prélèvement affichés avant consentement ;
- facture d'abonnement distincte des factures que l'utilisateur émet à ses
  propres clients ;
- aucune ouverture de pays sans règle documentée de taxation, facturation et
  support.

### Cycle de souscription

- le mensuel est payé au début de chaque période mensuelle ;
- l'annuel est payé d'avance pour douze mois ;
- la date d'ancrage et le prochain renouvellement sont visibles dans le portail ;
- un essai ne bascule vers le payant qu'après choix d'une période et consentement
  explicite au prix affiché ;
- le renouvellement conserve la version de prix contractualisée tant qu'aucune
  évolution conforme aux règles ci-dessous n'a été notifiée.

### Résiliation, réactivation et remboursement

- la résiliation en libre-service prend effet à la fin de la période déjà payée,
  sauf obligation légale ou geste commercial plus favorable ;
- l'accès payant reste disponible jusqu'à cette date ;
- une réactivation après la fin d'accès démarre une nouvelle période au
  catalogue applicable, sans mutation rétroactive des données ;
- aucune politique générale de remboursement n'est promise avant validation
  Legal ; les erreurs de prélèvement et indisponibilités imputables à Atlas
  suivent un traitement explicite et traçable ;
- les droits d'export, de fermeture et de conservation ne dépendent pas d'un
  parcours volontairement difficile.

### Évolution des prix

- chaque évolution crée une nouvelle version de catalogue datée ;
- aucune hausse rétroactive sur une période déjà payée ;
- les abonnés concernés sont informés avant le renouvellement selon un délai
  validé juridiquement ;
- la communication précise l'ancien prix, le nouveau prix, la date d'effet et
  le moyen de résilier ;
- un maintien d'ancien tarif est borné par une population, une durée et une
  règle de sortie ; il ne devient pas une promesse implicite à vie ;
- les cohortes historiques restent identifiables pour analyser rétention et
  marge sans mélanger les prix.

---

## Offre équipe future

`Atlas Équipe` est une hypothèse pour le persona secondaire. Elle n'est pas
commercialisée avec la V1 et ne doit pas apparaître comme « bientôt disponible »
sans découverte dédiée.

| Élément exploratoire | Hypothèse à tester |
|---|---|
| Prix Workspace | 49 à 69 € HT/mois |
| Point de départ expérimental | 59 € HT/mois ou 590 € HT/an |
| Membres inclus | 3 à 5 |
| Membre supplémentaire | 8 à 12 € HT/mois |
| Valeur attendue | rôles plus fins, responsabilité des actions, collaboration et vues d'équipe |

La simple présence des invitations et Memberships ne suffit pas à justifier une
offre équipe. Le prix par siège ne devient pertinent qu'après livraison d'une
valeur collaborative mesurable.

Atlas ne définit aucune offre Enterprise ou « sur devis » avant d'avoir validé
un besoin compatible avec ses anti-personas.

---

## Économie unitaire

Le prix ne peut être validé sans une lecture des coûts directs par Workspace
actif.

```text
Revenu net mensuel
  - frais du prestataire de paiement
  - infrastructure variable
  - e-mails transactionnels
  - stockage et rendu des documents
  - support directement attribuable
  = marge contributive
```

### Cibles internes initiales

| Indicateur | Cible de pilotage |
|---|---:|
| Marge brute sur abonnement | >= 80 % |
| Infrastructure variable hors support | <= 10 % du revenu net |
| Coût e-mail et documents | <= 5 % du revenu net |
| Temps de support récurrent | <= 15 minutes par Workspace actif et par mois |
| Délai de récupération du CAC payant | <= 6 mois avant accélération marketing |

Ces valeurs sont des seuils de décision internes, pas des promesses clients.
Elles doivent être recalculées à partir des factures fournisseurs et du temps de
support réel.

---

## Promotions et remises

### Early access

Une cohorte `Founding` peut tester **19 € HT/mois pendant douze mois maximum** si
elle est :

- limitée en nombre et en durée ;
- associée à un accord explicite de feedback ;
- mesurée séparément du prix catalogue ;
- accompagnée d'une date de fin annoncée dès la souscription.

Ce tarif n'est ni un plan permanent ni une promesse de prix à vie.

### Règles de remise

- une remise annuelle cible deux mois offerts et ne se cumule pas ;
- aucune fausse urgence ni promotion permanente ;
- aucun prix différent fondé sur des données sensibles ou une estimation opaque
  de la capacité à payer ;
- les gestes commerciaux sont tracés avec un motif et une date de fin ;
- toute évolution de prix pour les abonnés existants exige une communication
  explicite et une validation juridique préalable.

---

## Validation de la disposition à payer

Le protocole, la taxonomie des décisions, les seuils et le registre de preuves
sont maintenus dans
[`pricing-validation.md`](../../evolution/governance/pricing-validation.md).
Les pourcentages exploratoires ne sont jamais interprétés sans leurs effectifs,
leur cellule de prix et les biais de recrutement.

### Hypothèses

| ID | Hypothèse | Preuve attendue |
|---|---|---|
| `PRICE-H1` | Une offre unique est plus claire qu'une grille à trois plans | compréhension correcte lors d'au moins 80 % des entretiens |
| `PRICE-H2` | 24 € HT/mois est acceptable après démonstration de valeur | engagements ou précommandes dans la plage 19–29 € |
| `PRICE-H3` | Business Health et Advisor justifient le différentiel face à la facturation gratuite | valeur citée spontanément par les utilisateurs activés |
| `PRICE-H4` | 30 jours suffisent pour atteindre la première valeur | majorité des activations avant J7 et décision d'achat avant J30 |
| `PRICE-H5` | L'absence de quotas métier améliore la confiance | aucun besoin récurrent de rassurance sur devis/factures illimités |

### Séquence de validation

1. mener au moins 15 entretiens avec le persona principal ;
2. montrer le produit avant de demander une réaction au prix ;
3. tester au minimum 19 €, 24 € et 29 € sans promotions artificielles ;
4. obtenir au moins 10 décisions réelles : précommande, paiement ou refus
   motivé, pas seulement une intention déclarée ;
5. suivre une cohorte d'essai jusqu'à l'activation et la conversion ;
6. documenter les objections par valeur, confiance, périmètre et prix ;
7. réviser le packaging avant de réduire le prix.

Le candidat retenu est le prix le plus élevé qui franchit la scorecard de
compréhension et de décisions réelles, tout en respectant la marge cible. Cette
règle évite de sélectionner automatiquement le prix le plus bas ou de retenir
le prix le plus haut sur la base d'intentions déclarées.

### Signaux suivis

- taux inscription → Workspace activé ;
- temps jusqu'à la première valeur ;
- taux essai activé → payant ;
- revenu moyen par Workspace actif ;
- choix mensuel contre annuel ;
- churn volontaire et involontaire ;
- raison de non-conversion ;
- usage de Business Health, Advisor et actions recommandées ;
- marge contributive par cohorte ;
- demandes de remboursement et charge de support.

Un taux de conversion obtenu uniquement grâce à une forte remise ne valide pas
le prix catalogue.

---

## Frontières de domaine et état d'implémentation

### Propriété métier

L'abonnement commercial à Atlas n'appartient :

- ni à `Billing`, qui gère les documents financiers des clients de l'utilisateur ;
- ni à `Workspace`, qui porte l'identité et le cycle de vie de l'activité ;
- ni à `Identity`, qui gère utilisateurs, memberships et autorisations.

Un futur contexte commercial de plateforme devra posséder au minimum :

- `Plan` et version de catalogue ;
- `Subscription` et son cycle de vie ;
- `Trial` ;
- `BillingAccount` ;
- `Entitlement` calculé ;
- références du prestataire de paiement ;
- consommation uniquement lorsqu'elle devient une métrique de prix explicite ;
- événements de souscription, renouvellement, échec et résiliation.

Le nom définitif et les frontières de ce contexte exigent une décision
architecturale avant implémentation.

### État actuel

| Capacité | État au 23 août 2026 |
|---|---|
| Boucle fonctionnelle Atlas | Implémentée dans le MVP |
| E-mails transactionnels | Environnement et outbox disponibles |
| Catalogue de plans | Non implémenté |
| Essai et entitlements | Non implémentés |
| Checkout et paiement récurrent | Non implémentés |
| Portail de facturation Atlas | Non implémenté |
| Dunning et période de grâce | Non implémentés |
| Page tarifaire publique contractuelle | Non publiée |

La présence de cette stratégie tarifaire n'autorise donc pas encore une mise en
vente.

---

## Gate avant commercialisation payante

La vente d'`Atlas Solo` exige :

1. validation Product du prix et du packaging à partir de décisions réelles ;
2. décision sur le contexte propriétaire de Subscription et Entitlement ;
3. catalogue de plans versionné et entitlements appliqués côté serveur ;
4. checkout et webhooks idempotents d'un prestataire de paiement ;
5. aucun stockage local de données de carte ;
6. cycle essai, souscription, renouvellement, résiliation et restauration testé ;
7. traitement explicite des paiements échoués et de la période de grâce ;
8. factures d'abonnement et affichage HT/TTC validés avec Legal et Finance ;
9. portail client pour moyen de paiement, factures et résiliation ;
10. export, fermeture et rétention cohérents avec les politiques de données ;
11. observabilité, alertes et runbook de support ;
12. page tarifaire sans promesse dépassant les capacités livrées ;
13. tests de sécurité, d'isolation Workspace et de rejeu des webhooks ;
14. mesure de conversion, churn et marge disponible dès le lancement.

La maturité documentaire suit deux étapes distinctes :

- `Draft` → `In Review` après exécution complète du protocole exploratoire et
  recommandation Product documentée ;
- `In Review` → `Stable` après observation d'une cohorte payante pendant au
  moins 90 jours et franchissement des seuils opérationnels.

Les effectifs, seuils et preuves attendues sont définis dans le
[registre de validation](../../evolution/governance/pricing-validation.md).

---

## Décisions encore ouvertes

- prix catalogue final dans la plage 19–29 € HT/mois ;
- durée et conditions exactes de l'early access Founding ;
- nombre final de Members inclus dans `Atlas Solo` ;
- durée de grâce après échec de paiement ;
- politique d'accès et d'export après fin d'essai ;
- prestataire de paiement ;
- traitement TVA et facturation selon les pays servis ;
- date d'ouverture d'une découverte `Atlas Équipe`.

Ces décisions doivent être fermées avant le passage du document à `Stable`.
