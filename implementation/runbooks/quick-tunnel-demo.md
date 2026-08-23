---
title: Runbook — Démonstration par Quick Tunnel
owner: Engineering + Product
last_updated: 2026-08-23
references:
  - ../docker-compose.yml
  - ../scripts/share-quick-tunnel.sh
  - email-delivery.md
  - stripe-billing.md
---

# Démonstration par Quick Tunnel

Ce parcours publie temporairement Atlas sur une URL HTTPS aléatoire
`trycloudflare.com`, sans ouvrir de port sur la box et sans installer
`cloudflared` sur l'hôte. Il est réservé à une démonstration supervisée avec des
données fictives. Ce n'est ni un staging durable, ni une beta publique.

Cloudflare réserve les Quick Tunnels au test et au développement : URL
éphémère, aucune garantie de disponibilité, 200 requêtes simultanées au maximum
et absence de Server-Sent Events. Voir la
[documentation officielle](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/trycloudflare/).

## Préparation

Le bootstrap et le build web doivent avoir été exécutés au moins une fois :

```bash
make bootstrap
make web-check
make demo-seed
```

Ne placer aucune donnée client réelle dans la base utilisée pour la démo.
Stripe doit rester en environnement de test.

## Démarrage

Depuis la racine du dépôt :

```bash
make share
```

La commande :

1. démarre l'application et le worker d'outbox ;
2. démarre l'image `cloudflare/cloudflared` épinglée, sans port entrant ;
3. récupère l'URL publique générée ;
4. redémarre l'application et le worker avec cette origine publique ;
5. force `APP_ENV=staging`, `APP_DEBUG=false`, désactive les routes et jetons de
   développement, active la confiance du proxy et force les URL générées en
   HTTPS ;
6. désactive le rechargement à chaud Vite afin que le navigateur extérieur
   utilise les assets compilés plutôt que `localhost:5173` ;
7. vérifie `/up`, la landing et ses fichiers JavaScript/CSS depuis l'extérieur,
   puis affiche les liens à présenter.

La commande reste active pendant toute la démonstration. L'application locale
reste accessible sur <http://localhost:8000> et Mailpit sur
<http://localhost:8025>.

## Emails et Stripe sandbox

Pendant le partage, `APP_URL` et `MAIL_LINKS_URL` utilisent automatiquement
l'URL du tunnel. Les nouveaux liens de vérification, d'invitation, de
récupération et d'acceptation de devis sont donc publics.

Le transport de développement reste Mailpit : un destinataire extérieur ne
reçoit pas directement ces emails. L'opérateur ouvre Mailpit localement puis
copie le lien public pour la démonstration. Une remise réelle nécessite un SMTP
extérieur conformément au [runbook email](email-delivery.md).

Le listener Stripe n'est pas démarré automatiquement. Si la démonstration inclut
Checkout, le lancer dans un second terminal avec `make up-stripe` et conserver
exclusivement les clés et moyens de paiement de test décrits dans le
[runbook Stripe](stripe-billing.md).

## Arrêt et restauration

Dans le terminal de `make share`, utiliser `Ctrl+C`. Depuis un autre terminal :

```bash
make stop-share
```

Le script arrête le tunnel, remet `APP_URL` et `MAIL_LINKS_URL` sur localhost,
restaure le mode de développement et redémarre le serveur Laravel. L'ancienne
URL publique devient immédiatement inutilisable.

Si `make web-dev` était actif avant le partage, le relancer après la démo pour
retrouver le rechargement à chaud local.

Pour diagnostiquer le tunnel depuis un autre terminal :

```bash
make logs-share
```

Une page vide indique généralement que le navigateur n'a pas pu charger le
bundle web. Relancer `make share` : la commande supprime désormais le marqueur
Vite local, force le schéma HTTPS pour éviter le contenu mixte et contrôle
chaque asset public avant d'afficher l'URL. Si elle s'arrête, conserver son
message d'erreur et consulter `make logs-share`.

Le serveur Laravel est lancé avec `--no-reload`. Cette option est nécessaire
ici : sans elle, `artisan serve` recharge le fichier `.env` dans son processus
enfant et peut perdre les variables temporaires injectées par Compose, dont
l'URL publique et les protections du profil de partage.

## Frontière de sécurité

- seul le service HTTP Atlas est transmis au tunnel ;
- les ports de l'application, de Vite, de PostgreSQL et de Mailpit sont liés à
  `127.0.0.1` sur l'hôte ;
- aucun secret Cloudflare ni compte Cloudflare n'est requis ;
- le tunnel doit être arrêté dès la fin de la démonstration ;
- ne pas utiliser ce parcours pour une disponibilité permanente, des données
  réelles ou un paiement Stripe live.
