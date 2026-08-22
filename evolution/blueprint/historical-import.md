---
id: BPT-013
title: MVP Historical Import
status: In Review
owner: Product and Engineering
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - user-journeys.md
  - dashboard.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
  - ../../fondation/domains/crm/commands/ImportHistoricalClients.md
  - ../../fondation/domains/billing/commands/ImportHistoricalBillingHistory.md
  - ../../fondation/domains/analytics/workflows.md
---

# Import historique du MVP

## Décision produit

Un indépendant établi ne doit pas attendre la constitution d'un nouvel
historique pour évaluer Atlas. Le MVP accepte donc un package canonique contenant
des Clients, Quotes, Invoices et Payments historiques.

L'import est un transfert initial contrôlé, pas une synchronisation continue ni
un connecteur spécifique à Freebe, Indy ou Tiime. Des modèles CSV documentés et
un mapping guidé constituent le format d'entrée 1.0.

## Frontière entre préparation et domaine

L'adaptateur d'import :

1. charge le fichier dans une zone isolée à rétention courte ;
2. contrôle type, taille, malware, encodage et structure ;
3. transforme les colonnes vers le schéma canonique sans inventer de valeur ;
4. présente un aperçu, les erreurs et les doublons probables ;
5. calcule un hash immuable du package confirmé ;
6. soumet `ImportHistoricalClients`, puis
   `ImportHistoricalBillingHistory` après résolution des Clients.

Les fichiers invalides ne touchent aucun modèle métier. Le package brut est
supprimé au plus tard vingt-quatre heures après succès, échec définitif ou
abandon ; les manifestes minimaux et les preuves d'audit suivent la rétention du
domaine propriétaire.

## Intentions et ordre

```text
Canonical package confirmed
  -> ImportHistoricalClients
  -> ClientHistoryImportCompleted
  -> ImportHistoricalBillingHistory
  -> BillingHistoryImportCompleted
  -> controlled Analytics rebuild
  -> next eligible snapshot
```

Les deux intentions sont distinctes des commandes opérationnelles. L'import ne
simule jamais `CreateClient`, `SendQuote`, `IssueInvoice`, `SendInvoice` ou
`RecordPayment` et ne republie pas leurs événements.

Chaque run possède un checkpoint et une clé stable par
`(WorkspaceId, SourceSystem, ExternalId, RecordKind)`. Un retry reprend le même
run ; un même identifiant externe avec un contenu différent bloque la ligne et
demande une décision explicite. Un run n'est `Completed` que lorsque son
manifest, ses compteurs et son hash concordent.

## Conservation de l'histoire

Atlas conserve pour chaque enregistrement importé :

- le système source et son identifiant externe opaque ;
- le numéro de devis ou de facture tel qu'exporté ;
- les dates de création, émission, échéance, réponse et paiement disponibles ;
- l'état historique supporté et les montants dans leur devise d'origine ;
- le hash de la ligne canonique et l'`ImportRunId`.

Ces valeurs sont des faits historiques. L'import n'alloue aucun numéro Atlas,
n'avance aucune séquence, ne génère aucun PDF, aucune preuve publique et aucune
communication. Une future relance d'une facture importée exige une intention
humaine distincte, une adresse revalidée et la permission Billing courante.

## Publication Analytics

Les agrégats importés restent hors de la génération Analytics active pendant le
run. Après les deux événements de completion, un rebuild borné relit les
manifestes et les contrats de faits exacts, puis bascule seulement si tous les
checkpoints et totaux sont validés. Aucun snapshot partiellement importé n'est
publié comme complet.

## Première valeur et états limités

Le Dashboard reste utile avant un score disponible :

| Situation | Expérience attendue |
|---|---|
| aucune donnée | proposer l'import ou la première saisie, sans score inventé |
| aperçu invalide | afficher les lignes à corriger sans mutation métier |
| import en cours | afficher progression, compteurs et reprise sûre |
| historique partiel | montrer Clients, pipeline, soldes et périodes couvertes issus des domaines propriétaires |
| Business Health `Limited` | montrer le score avec couverture, facteurs absents et limites explicites |
| `InsufficientData` | montrer les données manquantes et les prochaines saisies utiles, sans Recommendation factice |

La checklist de préparation est une composition jetable de lectures publiques.
Elle n'est ni un score, ni une Recommendation, ni une nouvelle vérité métier.

## Critères d'acceptation

- réimporter le même package ne crée aucun doublon ni effet externe ;
- un package altéré sous la même identité produit `Conflict` ;
- numéros, dates, états et montants historiques sont restitués sans
  renumérotation ;
- aucun événement de livraison, d'ouverture ou de paiement opérationnel n'est
  fabriqué ;
- un échec après checkpoint reprend sans recommencer les lignes validées ;
- Analytics ne publie pas de génération partielle ;
- la suppression du fichier brut respecte la fenêtre de vingt-quatre heures ;
- l'utilisateur obtient une surface utile même si Business Health conclut à
  `Limited` ou `InsufficientData`.

## État d'implémentation (août 2026)

La tranche livrée sous `implementation/` couvre `ImportHistoricalClients`,
`ImportHistoricalBillingHistory` et le rebuild Analytics borné après corrélation
des deux completions. Les connecteurs externes et le step-up d'authentification
restent hors de cette tranche.
