---
id: CRM-DECISIONS
title: CRM Decision Record
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - model.md
  - invariants.md
  - integrations.md
  - future.md
---

# Registre de décisions

## CRM-ADR-001 — CRM est propriétaire de la mémoire commerciale

**Statut :** Accepted

Client, Contact, Opportunity et Activity appartiennent au même bounded context.
Billing, Advisor et Analytics les consomment par contrats publics.

---

## CRM-ADR-002 — Aucun agrégat Prospect ou Lead

**Statut :** Accepted

`Client` est la contrepartie stable, potentielle ou effective. `Opportunity`
exprime la vente potentielle. Un concept supplémentaire créerait une conversion,
des doublons et une terminologie déjà rejetée.

---

## CRM-ADR-003 — Client contient ses Contacts

**Statut :** Accepted

La cible initiale possède peu de Contacts par Client. Les contenir garantit
simplement l'appartenance et le Contact principal. Une extraction future reste
possible si les volumes ou collaborations l'exigent.

---

## CRM-ADR-004 — Opportunity et Activity sont des agrégats séparés

**Statut :** Accepted

Leur historique et leur concurrence ne doivent pas verrouiller le Client. Ils
référencent Client et Contact par identifiants stables.

---

## CRM-ADR-005 — Pipeline est une projection

**Statut :** Accepted

Le pipeline 1.0 reflète `Open` et `Qualified`. Il ne possède ni étapes
personnalisées, ni transitions distinctes de l'Opportunity.

---

## CRM-ADR-006 — Cycle Opportunity minimal

**Statut :** Accepted

Le cycle est `Open -> Qualified -> Won` avec sortie vers `Lost` depuis les deux
états non terminaux. Les étapes de devis restent dans Billing.

---

## CRM-ADR-007 — Won et Lost sont terminaux

**Statut :** Accepted

Une nouvelle tentative commerciale produit une nouvelle Opportunity. Cette
décision protège les analyses de conversion et la causalité avec Billing.

---

## CRM-ADR-008 — QuoteAccepted orchestre WinOpportunity

**Statut :** Accepted

Billing publie un fait passé. Un orchestrateur idempotent demande ensuite
`WinOpportunity`. Billing ne modifie jamais l'agrégat CRM et CRM ne confond pas
l'événement externe avec son propre fait `OpportunityWon`.

---

## CRM-ADR-009 — Activity n'est pas la timeline universelle

**Statut :** Accepted

Activity enregistre une interaction commerciale CRM. La timeline utilisateur
est une projection transverse qui conserve les événements dans leurs domaines
sources.

---

## CRM-ADR-010 — Archivage, jamais suppression métier

**Statut :** Accepted

Clients et Contacts sont archivés et réactivables. Activity est retirée
logiquement. Les identifiants, événements et références financières restent
interprétables.

---

## CRM-ADR-011 — Aucune unicité dure sur nom ou e-mail

**Statut :** Accepted

Deux contreparties peuvent partager un nom ou une boîte générique. CRM peut
signaler des doublons mais ne refuse pas une vérité métier sur une heuristique.

---

## CRM-ADR-012 — Billing fige le ClientSnapshot

**Statut :** Accepted

CRM expose le profil courant et sa version. Billing décide de sa complétude,
copie les données nécessaires et préserve le document contre toute mise à jour
rétroactive.

---

## CRM-ADR-013 — La devise estimée est un snapshot

**Statut :** Accepted

L'Opportunity utilise la devise Workspace comme défaut initial. Elle conserve
ensuite le code effectif ; CRM ne réalise aucune conversion lors d'un changement
de préférence.

---

## CRM-ADR-014 — Les recommandations restent hors CRM

**Statut :** Accepted

CRM fournit des faits et read models. Advisor détermine la priorité, la confiance
et l'action recommandée. Une indisponibilité d'Advisor n'empêche jamais la
gestion commerciale de base.
