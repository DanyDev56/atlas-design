---
id: ROADMAP-001
title: Atlas MVP Scope
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-23

references:
  - mvp-acceptance.md
  - ../blueprint/README.md
  - ../../fondation/product/product-strategy.md
  - ../../fondation/product/pricing-strategy.md
  - ../../fondation/domain-map/dependencies.md
---

# Atlas MVP

## Objectif

Le MVP doit permettre à un indépendant de suivre son cycle commercial, d’être payé et de recevoir des recommandations réellement utiles à partir de ses données.

Il valide la boucle produit fondamentale :

```text
Gérer -> Comprendre -> Décider -> Agir -> Mesurer
```

---

## Résultat utilisateur attendu

Un utilisateur doit pouvoir :

1. créer son Workspace ;
2. importer son historique existant ou enregistrer un client ;
3. créer et envoyer un devis ;
4. faire accepter ou refuser ce devis ;
5. transformer le devis accepté en facture ;
6. enregistrer le paiement ;
7. comprendre la santé récente de son activité ;
8. recevoir une priorité d’action claire.

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
- import initial guidé des clients.

### Billing

- devis ;
- acceptation et refus publics ;
- factures ;
- acomptes simples ;
- paiements manuels ;
- documents PDF ;
- échéances ;
- relances manuelles.
- import initial guidé des devis, factures et paiements, sans rejeu d'effet
  opérationnel.

### Analytics — moteur interne

- ingestion idempotente des faits CRM et Billing ;
- métriques déterministes nécessaires à Business Health ;
- fraîcheur, complétude et états `NoData` explicites ;
- publication de snapshots cohérents et versionnés.

Analytics est indispensable au résultat du MVP, mais n'a pas à être exposé
comme un module de navigation autonome.

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

### Dashboard — surface de composition

- priorité Advisor courante ;
- synthèse Business Health ;
- aperçu du pipeline CRM et de Billing ;
- compteur de notifications non lues ;
- états de fraîcheur, d'absence de données et d'indisponibilité partielle.

Le Dashboard est une surface applicative. Il ne constitue pas un bounded
context, ne possède aucune vérité métier et ne recalcule aucun indicateur.

---

## Exclu du MVP

- comptabilité complète ;
- synchronisation bancaire ;
- rapprochement bancaire ;
- facturation électronique complète ;
- connecteurs synchronisés vers des produits tiers ;
- gestion de stock ;
- paie ;
- projets complexes ;
- automatisations libres ;
- application mobile native ;
- multi-devises ;
- multi-workspaces ;
- marketplace ;
- IA générative autonome ;
- prévisions financières avancées ;
- catalogue de plans, essai commercial, abonnement et paiement récurrent
  d'Atlas.

`Automation`, `Projects`, les intégrations produit tierces et l'API publique
externe restent hors MVP. Les adaptateurs techniques nécessaires à la remise
d'e-mails et au rendu des documents ne sont pas considérés comme des
intégrations produit.

La boucle fonctionnelle doit permettre de tester la disposition à payer, mais
sa présence ne vaut pas commercialisation. Le packaging proposé et le gate
commercial distinct sont définis dans la
[stratégie tarifaire](../../fondation/product/pricing-strategy.md).

---

## Preuve d'achèvement

Le périmètre n'est considéré implémenté que lorsque les trois parcours
`MVP-J1`, `MVP-J2` et `MVP-J3` définis dans
[`mvp-acceptance.md`](mvp-acceptance.md) passent de bout en bout avec leurs cas
d'échec, de retry et d'absence de données.
