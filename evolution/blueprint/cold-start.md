---
id: BLUEPRINT-009
title: Démarrage à froid et première valeur
status: In Review
owner: Product
version: 1.0
last_updated: 2026-08-06
---

# Démarrage à froid et première valeur

## Résultat mesurable

Un nouvel utilisateur atteint une **première valeur** lorsqu'Atlas lui montre un
fait exploitable à partir d'au moins un client et un fait commercial ou de
facturation. La cible est moins de dix minutes, sans imposer un import.

## Parcours canonique

1. Identity crée l'utilisateur et vérifie son adresse.
2. La saga de bootstrap crée le Workspace `Provisioning`, l'Owner Membership et
   active le Workspace. Elle reprend sur incident avec le même `RequestId`.
3. L'utilisateur choisit **Importer un CSV** ou **Commencer manuellement**. Le
   choix reste réversible et aucune donnée de démonstration n'entre dans les
   projections métier.
4. Atlas demande uniquement les informations nécessaires à la prochaine action.
5. CRM reçoit le premier Client ; Billing ou CRM reçoit ensuite le premier fait.
6. Analytics publie un snapshot partiel, avec sa couverture et ses données
   manquantes ; Business Health n'affiche jamais une certitude artificielle.
7. Le Dashboard présente le fait, sa fraîcheur et l'action suivante. Advisor ne
   publie une recommandation que si ses préconditions déterministes sont réunies.

## Import CSV guidé

L'import est un adaptateur applicatif, pas un nouveau propriétaire métier. Il
accepte au MVP les clients, contacts, opportunités, devis, factures et paiements
manuels, puis traduit chaque ligne valide en commande publique du domaine cible.

### Pipeline

`Upload → Inspect → Map → Validate → Preview → Confirm → Execute → Report`

- le fichier brut est chiffré, à durée de vie limitée et supprimable ;
- le mapping et le résultat sont visibles avant toute écriture ;
- la confirmation est explicite ;
- `ImportJobId + RowNumber + MappingVersion` forme la clé d'idempotence ;
- une ligne n'écrit que dans un domaine, via son API publique ;
- les références externes servent à détecter les doublons sans fusion silencieuse ;
- les erreurs sont isolées par ligne et le rapport est téléchargeable ;
- une reprise n'exécute pas deux fois les lignes déjà réussies.

La déduplication ambiguë, la fusion automatique et les écritures inter-domaines
sont exclues. L'utilisateur corrige ou confirme explicitement chaque ambiguïté.

## États d'expérience

| État | Affichage | Action principale |
|---|---|---|
| Workspace vide | choix import ou saisie | Ajouter des données |
| Import en contrôle | progression et erreurs | Corriger le mapping |
| Données insuffisantes | couverture factuelle | Ajouter le fait manquant |
| Première valeur | fait daté et expliqué | Effectuer l'action suivante |
| Calcul différé | dernier état fiable | Réessayer ou continuer à gérer |

## Télémétrie sans donnée métier

`WorkspaceActivated`, `ColdStartPathSelected`, `ImportPreviewed`,
`ImportCompleted` et `FirstValueReached` sont des événements produit séparés des
événements métier. `FirstValueReached` porte seulement le type de valeur, la
durée et le chemin choisi ; il ne contient ni client, ni montant, ni document.

## Critères d'acceptation

- le parcours manuel demeure complet si l'import est indisponible ;
- annuler avant confirmation ne crée aucune donnée métier ;
- une reprise produit le même résultat métier ;
- toute valeur affichée indique source, fraîcheur et couverture ;
- les erreurs n'empêchent pas l'export du rapport ni la correction ;
- aucune recommandation n'est inventée pour remplir un état vide.
