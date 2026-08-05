---
id: CRM-ENTITIES
title: CRM Entities
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - value-objects.md
  - invariants.md
---

# Entités

## Client

Contrepartie stable d'une relation commerciale potentielle ou établie.

| Attribut | Rôle |
|---|---|
| `ClientId` | identité durable |
| `WorkspaceId` | frontière d'isolation immuable |
| `Kind` | `Individual` ou `Organization` |
| `Status` | `Active` ou `Archived` |
| `Profile` | profil commercial courant |
| `BillingProfile` | données administratives courantes |
| `Contacts` | entités Contact contenues |
| `PrimaryContactId?` | Contact actif du même agrégat |
| versions et instants | concurrence et audit |

Un Client n'est jamais identifié par son nom, son e-mail ou un identifiant
fiscal. Ces valeurs peuvent changer ou être partagées.

---

## Contact

Personne avec laquelle l'utilisateur peut interagir pour un Client.

| Attribut | Rôle |
|---|---|
| `ContactId` | identité stable dans le Workspace |
| `Status` | `Active` ou `Archived` |
| `Profile` | nom, rôle et coordonnées professionnelles |
| `CreatedAt` | première inscription dans CRM |
| `ArchivedAt?` | retrait de l'usage courant |

Le Contact appartient à exactement un Client en 1.0. Il n'est ni un `User`
Atlas, ni une identité d'authentification.

---

## Opportunity

Possibilité réelle de conclure une vente avec un Client identifié.

| Attribut | Rôle |
|---|---|
| `OpportunityId` | identité stable |
| `WorkspaceId` | frontière d'isolation |
| `ClientId` | contrepartie immuable |
| `ContactId?` | interlocuteur courant du même Client |
| `Details` | titre, description et contexte |
| `EstimatedAmount?` | estimation monétaire non contractuelle |
| `ExpectedDecisionDate?` | date anticipée, jamais promesse |
| `NextAction?` | intention utilisateur, non automatisée |
| `Status` | `Open`, `Qualified`, `Won` ou `Lost` |
| résultat terminal | instant, acteur et raison ou source |

Une Opportunity ne contient ni Quote, ni probabilité calculée, ni
Recommendation.

---

## Activity

Fait commercial manuel déjà survenu.

| Attribut | Rôle |
|---|---|
| `ActivityId` | identité stable |
| `WorkspaceId` | frontière d'isolation |
| `ClientId` | Client concerné |
| `ContactId?` | Contact concerné |
| `OpportunityId?` | Opportunity concernée |
| `Kind` | `Note`, `Call`, `Meeting` ou `Email` |
| `Content` | résumé contrôlé et borné |
| `OccurredAt` | instant du fait |
| `Status` | `Recorded` ou `Removed` |
| `RevisionHistory` | corrections auditables |

Une Activity ne sert pas à recopier un événement Billing. Une timeline produit
peut agréger les événements de plusieurs domaines dans une projection dédiée.
