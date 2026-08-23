---
title: Runbook — Stripe Billing
status: implemented
owner: Engineering
last_updated: 2026-08-23
references:
  - ../../fondation/domains/subscriptions/scope.md
  - ../../fondation/product/pricing-strategy.md
  - subscription-webhooks.md
  - https://docs.stripe.com/payments/checkout
  - https://docs.stripe.com/customer-management
  - https://docs.stripe.com/billing/revenue-recovery/smart-retries
---

# Stripe Billing

## Portée

L'adaptateur utilise le SDK officiel `stripe/stripe-php` pour :

- créer une session Stripe Checkout en mode `subscription` ;
- vérifier les signatures `Stripe-Signature` ;
- normaliser activation, renouvellement, échec et résiliation dans le cycle
  `Subscription` Atlas ;
- ouvrir un Customer Portal après vérification du Workspace ;
- préserver les identifiants de prix Atlas dans les metadata Stripe.

Le retour navigateur de Checkout ne modifie jamais l'accès. Seul un webhook
signé peut projeter une Subscription et ses Entitlements.

## Paramétrage sandbox

Dans Stripe, créer un produit `Atlas Solo` avec deux Prices récurrents en EUR :

- mensuel : 24,00 EUR, intervalle `month` ;
- annuel : 240,00 EUR, intervalle `year`.

Conserver la décision HT/TTC et l'automatisation fiscale désactivées tant que
Finance n'a pas validé le traitement TVA. Activer le Customer Portal pour la
mise à jour du moyen de paiement, l'historique des factures et la résiliation à
fin de période.

Configurer uniquement dans un `.env` non versionné :

```dotenv
SUBSCRIPTIONS_GATEWAY=stripe
SUBSCRIPTIONS_CHECKOUT_ENABLED=true
SUBSCRIPTIONS_WEBHOOKS_ENABLED=true
SUBSCRIPTIONS_STRIPE_SECRET_KEY=sk_test_...
SUBSCRIPTIONS_STRIPE_WEBHOOK_SECRET=whsec_...
SUBSCRIPTIONS_STRIPE_MONTHLY_PRICE_ID=price_...
SUBSCRIPTIONS_STRIPE_ANNUAL_PRICE_ID=price_...
SUBSCRIPTIONS_PAST_DUE_GRACE_DAYS=14
SUBSCRIPTIONS_ENFORCEMENT_ENABLED=false
```

Puis :

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate
```

## Webhooks

Endpoint : `POST /api/subscriptions/webhooks/stripe`.

Souscrire exclusivement aux événements suivants :

- `customer.subscription.created` ;
- `customer.subscription.updated` ;
- `customer.subscription.deleted` ;
- `invoice.paid` ;
- `invoice.payment_failed`.

La projection distingue strictement un changement d'abonnement d'un paiement :

- `invoice.paid` est la seule preuve de renouvellement payé et peut avancer la
  période acquittée ;
- `customer.subscription.updated` avec un statut `active` met à jour les
  attributs de gestion, notamment `cancel_at_period_end`, mais ne prolonge pas
  les droits ;
- `invoice.payment_failed` et un statut Stripe `past_due` conservent la dernière
  période payée et fixent le premier échec sans le repousser lors des relances.

Cette distinction est indispensable : au renouvellement, Stripe peut publier
un `customer.subscription.updated` avant la tentative de paiement de la
nouvelle facture.

En local, le profil Docker Compose `stripe` exécute l'image officielle Stripe
CLI épinglée et transfère les événements vers le service `app`. La clé test est
injectée depuis `app/.env` par la commande Make sans être placée dans les
arguments du processus :

```bash
make up-stripe
make logs-stripe
```

Le service `stripe-listener` redémarre automatiquement tant que la stack Docker
reste active. `make stop-stripe` l'arrête sans toucher à Atlas, PostgreSQL ou
Mailpit. Un `docker compose down` arrête toute la stack ; relancer ensuite
`make up-stripe` pour réactiver les webhooks locaux.

Au premier branchement, reporter le secret `whsec_...` fourni par Stripe CLI
dans le `.env`, puis vider le cache de configuration. Ce secret reste stable
entre les redémarrages du listener pour un même compte Stripe. Ne jamais le
réutiliser pour l'endpoint créé dans le Dashboard : chaque endpoint possède son
propre secret.

La commande historique au premier plan reste utilisable pour diagnostiquer le
réseau hors Docker :

```bash
stripe listen \
  --events customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.paid,invoice.payment_failed \
  --forward-to http://localhost:8000/api/subscriptions/webhooks/stripe
```

## Recette Checkout et portail

1. conserver `SUBSCRIPTIONS_ENFORCEMENT_ENABLED=false` ;
2. ouvrir `/app/settings/subscription` avec un Owner sans abonnement actif ;
3. choisir la période et vérifier la redirection vers le domaine Stripe ;
4. réaliser un paiement sandbox ;
5. vérifier que le retour affiche une confirmation en attente, sans activer
   lui-même l'accès ;
6. attendre le webhook puis vérifier `subscription.provider=stripe` et
   `access.source=Subscription` dans l'overview ;
7. ouvrir « Gérer mon abonnement » et vérifier le Customer Portal ;
8. modifier un moyen de paiement puis vérifier que les webhooks restent
   idempotents en cas de rejeu.

Une tentative de second checkout est refusée tant qu'une Subscription non
résiliée existe. Une resouscription après résiliation crée une nouvelle
référence Stripe et retire l'ancienne sans perdre son historique.

## Dunning et grâce

Configurer Stripe Smart Retries sur huit tentatives pendant deux semaines et
activer les emails Stripe après échec de paiement. Atlas applique une grâce de
14 jours à partir du **premier** échec : les tentatives suivantes ne repoussent
pas la date de restriction.

Recette attendue :

1. `invoice.payment_failed` place la Subscription en `PastDue` ;
2. l'accès reste complet pendant la grâce ;
3. un `invoice.paid` restaure immédiatement `Active` ;
4. sans paiement, l'accès devient restreint à l'expiration ;
5. lecture, export et gestion d'abonnement restent disponibles.

Les Test Clocks pilotent le calendrier de facturation Stripe, mais le champ
`event.created` reçu par Atlas reste l'heure d'émission effective du webhook.
La recette sandbox valide donc les renouvellements, échecs, Smart Retries et
restaurations avec Test Clocks ; les bornes exactes de la grâce de 14 jours et
la non-prolongation au second échec sont en complément des oracles automatisés
`SubscriptionEntitlementPolicyTest` et `SubscriptionEnforcementTest`.

## Recette sandbox complète

Dernière exécution : **23 août 2026**, avec des Workspaces et des Customers
Stripe test dédiés, sans modifier l'abonnement du compte de démonstration.

| Scénario | Attendu | Résultat observé |
|---|---|---|
| Checkout mensuel | session `cs_test_`, Price mensuel | conforme |
| Checkout annuel | session `cs_test_`, Price annuel | conforme |
| Activation | `Active`, entitlement `Subscription` | conforme |
| Customer Portal | session `bps_` sur `billing.stripe.com` | conforme |
| Renouvellement payé | période payée avancée par `invoice.paid` | conforme |
| Premier échec | facture `open`, Subscription `PastDue` | conforme |
| Smart Retry | second débit, `past_due_since` inchangé | conforme |
| Rétablissement | facture payée, retour immédiat à `Active` | conforme |
| Annulation programmée | `Active` et `cancel_at_period_end=true` | conforme |
| Annulation immédiate | `Canceled` projeté par webhook | conforme |
| Réabonnement | nouvelle référence active, ancienne retirée | conforme |
| Rejeu webhook | versions et nombre de tentatives inchangés | conforme |

Procédure reproductible :

1. démarrer Atlas, Mailpit et le listener avec `make up-stripe` ;
2. créer un Workspace vierge et lancer successivement un Checkout mensuel puis
   annuel sur deux Workspaces distincts ;
3. créer un Customer sous Test Clock avec `pm_card_visa`, puis une Subscription
   portant les metadata `workspace_id` et `plan_price_id` ;
4. vérifier dans Atlas `Active`, la période, la source d'accès et le portail ;
5. rattacher `pm_card_chargeCustomerFail`, conserver l'identifiant `pm_...`
   réellement retourné et le définir comme moyen de paiement par défaut ;
6. avancer l'horloge jusqu'à la finalisation de facture puis à la tentative de
   paiement, et vérifier `PastDue` ;
7. avancer jusqu'à une Smart Retry et vérifier que `past_due_since` ne change
   pas ;
8. rattacher une nouvelle `pm_card_visa`, payer la facture ouverte et vérifier
   le retour à `Active` ;
9. programmer puis exécuter une annulation, créer une nouvelle Subscription et
   vérifier l'historique des références fournisseur ;
10. rejouer un événement traité avec
    `php artisan atlas:subscriptions:replay-webhook stripe evt_...` et vérifier
    que les versions de Subscription et d'Entitlement restent identiques.

Ne jamais réutiliser ces moyens de paiement de test ni les Test Clocks en mode
live. Les identifiants `sk_test_` et `whsec_` restent exclusivement dans le
`.env` local non versionné.

## Gate de production

Ne passer ni Checkout ni enforcement en production avant :

- validation du prix et du packaging ;
- validation TVA, factures d'abonnement et mentions légales ;
- secrets live stockés dans le gestionnaire de secrets OCI et rotation testée ;
- endpoint HTTPS Stripe enregistré et signature vérifiée ;
- alertes sur webhooks `Failed` et `Deferred` ;
- réconciliation Stripe–Atlas et procédure support testées ;
- recette complète avec Stripe Test Clocks ;
- sauvegarde et restauration validées avec les nouvelles tables.
