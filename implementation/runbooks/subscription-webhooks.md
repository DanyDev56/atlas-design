---
title: Webhooks d'abonnement en développement
status: Draft
owner: Engineering
last_updated: 2026-08-23
references:
  - ../../fondation/domains/subscriptions/scope.md
  - ../../fondation/product/pricing-strategy.md
  - ../UI-DEMO-SCOPE.md
---

# Webhooks d'abonnement en développement

Ce runbook exerce le cycle `Subscription` avec le prestataire `fake`. Il ne
réalise aucun paiement, ne stocke aucune carte et ne valide pas le prix candidat.

## Activation locale

Conserver les flags désactivés dans `.env.example`. Dans le `.env` local :

```dotenv
SUBSCRIPTIONS_GATEWAY=fake
SUBSCRIPTIONS_WEBHOOKS_ENABLED=true
SUBSCRIPTIONS_FAKE_WEBHOOK_SECRET=une-valeur-locale-aleatoire-de-32-caracteres
SUBSCRIPTIONS_ENFORCEMENT_ENABLED=false
```

Le secret doit contenir au moins 16 caractères. Après modification :

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate
```

## Contrat factice

Endpoint : `POST /api/subscriptions/webhooks/fake`

La signature `X-Atlas-Signature` est le HMAC SHA-256 hexadécimal du corps JSON
brut, calculé avec `SUBSCRIPTIONS_FAKE_WEBHOOK_SECRET`. Les types acceptés sont :

- `subscription.activated` ;
- `subscription.renewed` ;
- `subscription.payment_failed` ;
- `subscription.canceled`.

Exemple de corps pour le prix annuel candidat :

```json
{
  "id": "evt-local-activation-1",
  "type": "subscription.activated",
  "occurred_at": "2026-08-23T12:00:00+00:00",
  "data": {
    "workspace_id": "WORKSPACE_UUID",
    "subscription_reference": "fake-subscription-WORKSPACE_UUID",
    "plan_price_id": "b7f839e4-f01b-5fd8-866d-6e55b2bb32e6",
    "current_period_start": "2026-08-23T00:00:00+00:00",
    "current_period_end": "2027-08-23T00:00:00+00:00",
    "cancel_at_period_end": false
  }
}
```

La signature doit être calculée sur les octets exacts envoyés. Une première
livraison valide répond `202`, une livraison déjà traitée répond `200` avec
`duplicate: true`. Une signature invalide répond `401` et n'écrit rien dans
l'inbox.

## Ordre, échec et rejeu

L'inbox est unique par `(provider, provider_event_id)`. Une collision du même id
avec un autre corps répond `409`. La prise en charge `Processing` est atomique,
ce qui empêche deux livraisons concurrentes de produire deux transitions.

Un renouvellement reçu avant l'activation reste `Deferred`. Après réception de
l'activation, le rejouer avec :

```bash
docker compose exec app php artisan atlas:subscriptions:replay-webhook fake EVENT_ID
```

Les événements antérieurs ou de même date que le dernier événement appliqué
passent à `Ignored` et ne régressent ni la Subscription ni l'Entitlement. Les
exceptions passent à `Failed` et peuvent être rejouées après correction.

## Vérifications

- la page `/app/settings/subscription` affiche « Abonnement simulé » ;
- `access.source` vaut `Subscription` dans l'API d'overview ;
- `SUBSCRIPTIONS_ENFORCEMENT_ENABLED` reste à `false` ;
- aucune donnée de paiement ni promesse commerciale n'est créée.

Avant toute intégration réelle, ajouter l'adaptateur officiel du prestataire,
la rotation des secrets, les alertes sur `Deferred`/`Failed`, la réconciliation,
le portail client, le dunning et les validations Legal/Finance.
