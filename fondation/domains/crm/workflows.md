---
id: CRM-WORKFLOWS
title: CRM Workflows
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - commands/README.md
  - invariants.md
  - events.md
  - integrations.md
---

# Workflows

## Premier Client

```text
Authorize crm.clients.create
  -> CreateClient
  -> optional AddContact
  -> optional ChangeClientPrimaryContact
  -> ClientCreated / ContactAdded
```

Le parcours demande uniquement les données utiles. Le profil de facturation peut
être complété avant la première Quote ou Invoice.

---

## Importer les Clients existants

1. l'adaptateur prépare et prévisualise un package canonique ;
2. l'utilisateur corrige les erreurs et confirme son hash ;
3. `ImportHistoricalClients` crée le run et matérialise les lignes par
   checkpoints idempotents ;
4. les doublons exacts convergent, les collisions divergentes bloquent ;
5. la validation finale rend les Clients visibles et publie
   `ClientHistoryImportCompleted` ;
6. Billing peut alors résoudre le manifest Client pour son propre import.

Aucune ligne n'émet `ClientCreated`. Un run interrompu reprend depuis son dernier
checkpoint et le package brut est supprimé selon sa rétention courte.

---

## Capture d'une Opportunity

```text
Select or Create Client
  -> optional select Contact
  -> CreateOpportunity (Open)
  -> record next action if known
```

La saisie rapide ne crée aucun Lead intermédiaire. Un possible doublon est un
signal non bloquant.

---

## Qualification et devis

```mermaid
sequenceDiagram
    participant U as User
    participant C as CRM
    participant B as Billing

    U->>C: QualifyOpportunity
    C-->>U: OpportunityQualified
    U->>B: CreateQuote from OpportunityId
    B->>C: getOpportunityCommercialContext
    B->>C: getClientBillingContext
    B-->>U: QuoteCreated
```

Billing peut refuser une identité Client incomplète sans modifier l'Opportunity.
L'utilisateur complète alors le profil et reprend la création de Quote.

---

## Quote acceptée

```text
QuoteAccepted
  -> orchestration validates Billing event and correlation
  -> WinOpportunity(Source = AcceptedQuote, QuoteId)
  -> OpportunityWon
```

Le workflow est idempotent par couple `QuoteId` et `OpportunityId`. Si
l'Opportunity est déjà gagnée par la même Quote, il retourne le succès initial.
Un résultat terminal incompatible déclenche une alerte de cohérence sans
réécriture automatique.

---

## Perte d'une Opportunity

L'utilisateur fournit une raison structurée. `LoseOpportunity` est distinct du
rejet d'une Quote : prix, délai ou absence de réponse peuvent conduire à une
nouvelle proposition avant la perte réelle.

---

## Archivage Client

```text
Request archive
  -> verify permission and expected revision
  -> check no Open or Qualified Opportunity
  -> ArchiveClient
  -> ClientArchived
```

Contacts et historiques restent rattachés. Les documents Billing conservent
leurs snapshots. Une réactivation rend le Client et ses Contacts encore actifs
individuellement disponibles selon leur propre statut.

---

## Historique d'activité

Les interactions manuelles utilisent `RecordActivity`. Les événements Billing
et Opportunity sont affichés dans une timeline transverse sans être copiés comme
Activity.

Une correction produit `ActivityCorrected`. Une erreur de Client exige
`RemoveActivity` puis un nouvel enregistrement afin de préserver la causalité.

---

## Fermeture ou restriction du Workspace

- `Restricted` et `Closed` bloquent les commandes CRM ordinaires ;
- aucune donnée CRM n'est automatiquement archivée ;
- les projections restent interprétables pour les traitements autorisés ;
- la rétention et l'effacement suivent des workflows de conformité séparés.
