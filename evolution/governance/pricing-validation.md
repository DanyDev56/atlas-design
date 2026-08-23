---
id: GOV-PRICING-001
title: Pricing Validation Protocol and Evidence Register
status: Draft
owner: Product
version: 0.1.0
last_updated: 2026-08-23

references:
  - ../../fondation/product/pricing-strategy.md
  - ../../fondation/product/personnas/persona-primary.md
  - experimentation.md
  - metrics.md
  - consolidation-matrix.md
---

# Validation du pricing

Ce document transforme la
[stratégie tarifaire](../../fondation/product/pricing-strategy.md) en protocole
de décision reproductible. Il contient les preuves, résultats et décisions ;
il ne remplace ni la stratégie produit ni les conditions contractuelles.

Tant que les registres ci-dessous sont vides, `24 € HT/mois` et
`240 € HT/an` restent des prix candidats.

---

## Responsabilités

| Responsabilité | Owner | Contribution attendue |
|---|---|---|
| Hypothèses, recrutement et synthèse | Product | protocole, entretiens, analyse et recommandation |
| Faisabilité du packaging | Engineering | état réel des capacités et coût technique |
| Coûts directs et marge | Product avec Finance | factures fournisseurs, paiement, support et marge contributive |
| Taxes et conditions commerciales | Legal avec Finance | territoire, TVA, facturation, rétractation et résiliation |
| Go/no-go commercial | Product | décision documentée après revue des preuves |

Une même personne peut tenir plusieurs rôles au démarrage, mais chaque revue
doit indiquer explicitement quelles perspectives n'ont pas été couvertes.

---

## Population admissible

Un participant compte dans l'échantillon principal s'il :

- correspond au persona principal Atlas ;
- exploite réellement une activité de services ;
- décide ou influence directement l'achat de ses logiciels de gestion ;
- peut évaluer le produit à partir de ses propres pratiques ;
- n'est ni un membre de l'équipe Atlas, ni un proche recruté uniquement pour
  confirmer l'hypothèse.

Les retours des anti-personas et du persona secondaire sont conservés dans une
annexe, mais ne déterminent pas le prix V1.

Un participant n'est compté qu'une fois dans le test comparatif de prix. Il ne
voit pas successivement les trois montants, afin d'éviter l'ancrage artificiel.

---

## Protocole d'entretien

### Avant de montrer le prix

1. qualifier l'activité, les outils actuels et le rôle dans l'achat ;
2. faire décrire un problème récent de pilotage, sans présenter Atlas ;
3. observer le parcours Atlas sur des données crédibles ou sur les données du
   participant avec son accord ;
4. vérifier que la boucle `Gérer → Comprendre → Décider → Agir → Mesurer` est
   comprise ;
5. faire reformuler la valeur perçue et les capacités indispensables.

### Présentation du prix

Le participant voit une seule carte d'offre complète, sans remise fictive :

| Cellule | Mensuel présenté | Annuel présenté |
|---|---:|---:|
| `P19` | 19 € HT | 190 € HT |
| `P24` | 24 € HT | 240 € HT |
| `P29` | 29 € HT | 290 € HT |

Les cellules sont réparties aussi équitablement que possible. Le canal de
recrutement, le niveau d'activation et le packaging montré doivent rester
comparables. Toute différence est notée dans le registre.

### Après la présentation

Les questions doivent distinguer :

- ce qui est compris de l'offre ;
- ce qui manque pour acheter ;
- le coût des alternatives actuelles ;
- la préférence mensuelle ou annuelle ;
- la décision prise maintenant, et non l'intention abstraite ;
- la raison principale d'un refus.

La formulation « achèteriez-vous ? » seule ne constitue pas une preuve.

---

## Taxonomie des décisions

| Code | Décision observable | Signal pricing |
|---|---|---|
| `PAID` | paiement effectivement autorisé | positif fort |
| `PREORDERED` | précommande explicite, traçable et révocable | positif |
| `TRIAL_COMMITTED` | essai accepté avec date de décision convenue | apprentissage, pas une conversion |
| `DECLINED_PRICE` | valeur comprise et périmètre suffisant, mais prix refusé | objection prix |
| `DECLINED_VALUE` | bénéfice différenciant insuffisant ou non compris | objection valeur |
| `DECLINED_SCOPE` | capacité indispensable absente | objection packaging |
| `DECLINED_TRUST` | confiance, sécurité ou pérennité insuffisante | objection confiance |
| `INELIGIBLE` | participant hors cible ou sans pouvoir de décision | exclu du score principal |

Une précommande ne doit pas être enregistrée si aucun mécanisme concret ne
permet au participant d'exprimer et de retirer son engagement.

---

## Données minimales par observation

Le registre conserve uniquement des identifiants pseudonymes. Les notes brutes
contenant des données personnelles restent dans un espace d'étude à accès
restreint et selon une durée de conservation définie.

| Champ | Format attendu |
|---|---|
| `ObservationId` | identifiant pseudonyme unique |
| Date | `YYYY-MM-DD` |
| Segment | persona principal, secondaire ou anti-persona |
| Canal | recommandation, organique, communauté, outbound ou autre |
| Niveau d'activation | démonstration, activé ou usage réel |
| Cellule | `P19`, `P24` ou `P29` |
| Packaging présenté | version ou empreinte de l'offre |
| Décision | code de la taxonomie |
| Motif principal | prix, valeur, scope, confiance ou contexte |
| Préférence | mensuel, annuel ou indifférent |
| Preuve | référence interne, jamais une donnée bancaire |
| Notes de biais | relation, remise, incident ou différence de protocole |

---

## Scorecard exploratoire

Les seuils suivants servent à choisir le candidat à tester en conditions
réelles. Ils ne constituent pas une prévision statistique du marché.

| Signal | Calcul | Seuil exploratoire |
|---|---|---:|
| Compréhension de l'offre | offre correctement reformulée / entretiens admissibles | >= 80 % |
| Décision positive réelle | (`PAID` + `PREORDERED`) / décisions finales admissibles | >= 40 % |
| Objection prix isolée | `DECLINED_PRICE` / refus admissibles | < 50 % |
| Valeur différenciante reconnue | Business Health ou Advisor cité sans suggestion / entretiens admissibles | >= 60 % |
| Préférence annuelle | décisions positives annuelles / décisions positives | observation, sans seuil V1 |

Le prix candidat retenu est le montant le plus élevé qui respecte simultanément
les seuils de compréhension et de décision, reste compatible avec les cibles
de marge et ne dégrade pas manifestement la confiance. En cas de résultats
inconclusifs, le packaging est retravaillé avant d'ajouter une remise.

---

## Gates de maturité

### `Draft` → `In Review`

- au moins 15 entretiens admissibles réalisés ;
- au moins 10 décisions finales `PAID`, `PREORDERED` ou refus motivé ;
- les cellules `P19`, `P24` et `P29` effectivement testées ;
- compréhension, objections et biais synthétisés ;
- coûts directs renseignés à partir de données observables ;
- territoire initial et hypothèses fiscales explicités ;
- recommandation Product datée avec options écartées.

### `In Review` → `Stable`

- au moins 20 Workspaces payants au prix catalogue retenu ou à une remise
  explicitement normalisée ;
- une cohorte observée pendant au moins 90 jours ;
- rétention à 90 jours >= 80 %, hors fermetures d'activité documentées ;
- marge brute >= 80 % sur la cohorte ;
- support récurrent médian <= 15 minutes par Workspace et par mois ;
- raisons de churn, remboursements et paiements échoués revues ;
- règles fiscales, contractuelles, de résiliation et de rétention validées ;
- aucun défaut critique de sécurité ou d'isolation des Workspaces ouvert.

Ces seuils sont des garde-fous internes pour une petite cohorte. Ils doivent
être réévalués avant toute accélération d'acquisition.

---

## Registre des observations

| Observation | Date | Segment | Canal | Activation | Cellule | Décision | Motif | Biais | Preuve |
|---|---|---|---|---|---|---|---|---|---|
| _Aucune observation enregistrée_ | — | — | — | — | — | — | — | — | — |

---

## Synthèse par cellule

| Cellule | Admissibles | Décisions finales | Positives | Refus prix | Refus valeur | Refus scope | Refus confiance |
|---|---:|---:|---:|---:|---:|---:|---:|
| `P19` | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| `P24` | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| `P29` | 0 | 0 | 0 | 0 | 0 | 0 | 0 |

---

## Journal de décision

| Date | Décision | Statut | Preuves | Owner | Prochaine revue |
|---|---|---|---|---|---|
| 2026-08-23 | Ouvrir la validation sur une offre unique et les cellules 19/24/29 € HT | Candidat | stratégie tarifaire `0.3.0` et catalogue interne `Atlas Solo@1` | Product | après 15 entretiens |

Une décision remplacée reste dans ce journal avec son statut `Superseded`. Les
preuves ne sont jamais réécrites pour faire correspondre l'historique à la
décision finale.
