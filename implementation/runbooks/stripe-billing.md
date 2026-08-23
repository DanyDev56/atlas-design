---
title: Runbook — Stripe Billing
status: Draft
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

En local, Stripe CLI peut transférer les événements :

```bash
stripe listen \
  --events customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.paid,invoice.payment_failed \
  --forward-to http://localhost:8000/api/subscriptions/webhooks/stripe
```

Reporter le secret `whsec_...` affiché par la CLI dans le `.env`, puis vider le
cache de configuration. Ne jamais réutiliser ce secret pour l'endpoint créé
dans le Dashboard : chaque endpoint possède son propre secret.

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
