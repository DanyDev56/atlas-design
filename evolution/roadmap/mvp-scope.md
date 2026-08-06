---
id: ROADMAP-001
title: Atlas MVP Scope
status: In Review
owner: Product
version: 1.0
last_updated: 2026-08-06
---

# Atlas MVP

## Objectif

Le MVP doit permettre à un indépendant de suivre son cycle commercial, d’être payé et de recevoir des recommandations réellement utiles à partir de ses données.

---

## Résultat utilisateur attendu

Un utilisateur doit pouvoir :

1. créer son Workspace ;
2. enregistrer un client ;
3. créer et envoyer un devis ;
4. faire accepter ou refuser ce devis ;
5. transformer le devis accepté en facture ;
6. enregistrer le paiement ;
7. comprendre la santé récente de son activité ;
8. recevoir une priorité d'action claire.

Le démarrage doit aussi permettre un chemin manuel sans dépendance externe et un
import CSV guidé conforme au contrat de
[`cold-start.md`](../blueprint/cold-start.md).

---

## Inclus

### Identity

- inscription ;
- connexion ;
- récupération de mot de passe ;
- vérification d’adresse email.

### Workspace

- création du Workspace ;
- informations commerciales ;
- identité de facturation réutilisable ;
- préférences principales.

### CRM

- clients ;
- contacts ;
- opportunités simples ;
- historique d’activité.
- import CSV guidé des clients, contacts et opportunités sans fusion automatique.

### Billing

- devis ;
- acceptation et refus publics ;
- factures ;
- acomptes simples ;
- paiements manuels ;
- documents PDF ;
- échéances ;
- relances manuelles.
- import CSV guidé des devis, factures et paiements manuels.

### Business Health

- score global ;
- facteurs principaux ;
- évolution sur une période ;
- zone d'attention principale, sans préjuger de l'action Advisor.

### Advisor

- recommandations déterministes ;
- explication ;
- action principale ;
- confirmation d'accomplissement ou rejet d'une recommandation.

### Notifications

- notifications internes ;
- emails importants après consentement explicite ;
- état lu ou non lu.

---

## Exclu du MVP

- comptabilité complète ;
- synchronisation bancaire ;
- rapprochement bancaire ;
- facturation électronique complète ;
- gestion de stock ;
- paie ;
- projets complexes ;
- automatisations libres ;
- application mobile native ;
- multi-devises ;
- multi-workspaces ;
- marketplace ;
- IA générative autonome ;
- prévisions financières avancées.


## Définition de terminé du MVP

- le parcours froid, la boucle commerciale et la boucle de décision passent sur
  écran mobile de 320 px sans dépendre d'une intégration optionnelle ;
- chaque domaine livre sa slice verticale décrite dans le Blueprint ;
- les politiques et résultats portent leur version et sont réversibles ;
- accessibilité, sécurité, observabilité, contrats et documentation satisfont les
  quality gates ;
- aucune exclusion ci-dessus n'apparaît comme dépendance cachée.

## Plan d’implémentation

Les incréments verticaux et leurs gates sont définis dans
[`mvp-implementation-slices.md`](mvp-implementation-slices.md).
