---
id: CRM-PERMISSIONS
title: CRM Permissions
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - scope.md
  - invariants.md
  - commands/README.md
  - ../identity/permissions.md
---

# Permissions

CRM définit ses capacités. Identity enregistre leurs identités, les associe aux
rôles et résout leur efficacité dans le Workspace.

## Client

| Clé | Sensibilité | Intention |
|---|---|---|
| `crm.clients.read` | Standard | Consulter les Clients. |
| `crm.clients.create` | Standard | Créer un Client. |
| `crm.clients.update-profile` | Elevated | Modifier son profil commercial. |
| `crm.clients.update-billing-profile` | Elevated | Modifier ses données administratives. |
| `crm.clients.archive` | Elevated | Archiver un Client. |
| `crm.clients.reactivate` | Elevated | Réactiver un Client. |

## Contact

| Clé | Sensibilité | Intention |
|---|---|---|
| `crm.contacts.read` | Standard | Consulter les Contacts. |
| `crm.contacts.create` | Standard | Ajouter un Contact. |
| `crm.contacts.update` | Standard | Modifier un Contact. |
| `crm.contacts.change-primary` | Elevated | Changer le Contact principal. |
| `crm.contacts.archive` | Elevated | Archiver un Contact. |
| `crm.contacts.reactivate` | Elevated | Réactiver un Contact. |

## Opportunity

| Clé | Sensibilité | Intention |
|---|---|---|
| `crm.opportunities.read` | Standard | Consulter le pipeline et les opportunités. |
| `crm.opportunities.create` | Standard | Créer une Opportunity. |
| `crm.opportunities.update` | Standard | Modifier une Opportunity non terminale. |
| `crm.opportunities.qualify` | Elevated | Confirmer une Opportunity qualifiée. |
| `crm.opportunities.win` | Elevated | Marquer manuellement une Opportunity gagnée. |
| `crm.opportunities.lose` | Elevated | Marquer une Opportunity perdue. |

## Activity

| Clé | Sensibilité | Intention |
|---|---|---|
| `crm.activities.read` | Standard | Consulter l'historique CRM. |
| `crm.activities.record` | Standard | Enregistrer une Activity. |
| `crm.activities.correct` | Elevated | Corriger une Activity en préservant l'audit. |
| `crm.activities.remove` | Elevated | Retirer logiquement une Activity. |

---

## Capacités SystemActorOnly

| Clé | Usage |
|---|---|
| `crm.opportunities.win-from-quote` | demander un gain après `QuoteAccepted` authentique |
| `crm.analytics-facts.read` | lire une révision Opportunity minimale pour Analytics |

Ces clés ne peuvent pas être accordées à un rôle humain.

---

## Implications

Toute mutation implique la lecture de sa ressource. Les capacités Contact
impliquent aussi `crm.clients.read`. Les capacités Opportunity et Activity
impliquent `crm.clients.read`.

Le rôle système owner conserve toutes les permissions CRM attribuables actives.
Les rôles par défaut sont une politique produit opérée dans Identity.

---

## Évaluation

Une permission CRM exige :

1. un principal actif ;
2. un Workspace actif ;
3. un Membership actif ;
4. un Role actif dans le même Workspace ;
5. la clé exacte ;
6. les préconditions métier CRM.

Les références à un autre Workspace et les décisions obsolètes sont refusées par
défaut. Une capacité système exige une causalité signée, une portée bornée et un
audit.
