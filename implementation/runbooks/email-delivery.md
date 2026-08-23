---
title: Runbook — Livraison email transactionnelle
owner: Engineering
status: implemented
last_updated: 2026-08-22
references:
  - ../../evolution/blueprint/integrations.md
  - ../../evolution/roadmap/mvp-acceptance.md
  - outbox-incident.md
---

# Livraison email transactionnelle

## Portée

Les emails réels sont remis par SMTP depuis le worker outbox pour :

- Identity : vérification d'adresse, récupération de compte, invitation ;
- Billing : devis, facture et relance par email, avec PDF lorsqu'il existe ;
- Notifications : priorité Advisor `High` ou `Critical`, uniquement après
  consentement `ImportantOnly` et revalidation de l'audience.

Identity, Billing et Notifications gardent leurs décisions et leurs contenus.
Ils partagent seulement l'adaptateur SMTP et le registre technique
`platform.email_deliveries`.

## Développement avec Mailpit

```bash
make up
./implementation/scripts/bootstrap.sh

# le worker outbox de développement démarre automatiquement ; traitement ponctuel :
docker compose -f implementation/docker-compose.yml exec app \
  php artisan atlas:outbox:process --batch=100
```

- application : <http://localhost:8000/app>
- boîte Mailpit : <http://localhost:8025>
- SMTP Mailpit : `mailpit:1025` depuis Docker, `localhost:1025` depuis l'hôte.

`composer dev` lance aussi le worker outbox. Après modification des variables
d'environnement, exécuter `php artisan config:clear`.
Le worker Docker de développement attend les dépendances, applique les
migrations réexécutables, puis commence à consommer l'outbox.

Les services immuables `api`, `worker` et `scheduler` appartiennent au profil
Compose `runtime`. Ils ne démarrent pas avec la stack de développement afin
qu'un ancien worker runtime ne puisse pas consommer l'outbox avant le worker
Mailpit. Pour les lancer explicitement : `docker compose --profile runtime up`.

Les tests d'intégration utilisent la base séparée `atlas_test`. Ne remplacez pas
sa valeur par `atlas` dans `phpunit.xml` : `RefreshDatabase` recrée les schémas.

Sur une installation créée avant cet incrément, mettre aussi
`ATLAS_DEBUG_VERIFICATION_TOKENS=false` et
`VITE_DEBUG_VERIFICATION_TOKENS=false` dans `implementation/app/.env` afin que
les parcours passent réellement par Mailpit au lieu de l'auto-vérification.

## Configuration hors développement

Le runtime utilise `MAIL_MAILER=log` tant qu'un transport n'est pas fourni.
Configurer au minimum :

```dotenv
RUNTIME_MAIL_MAILER=smtp
RUNTIME_MAIL_HOST=smtp.provider.example
RUNTIME_MAIL_PORT=587
RUNTIME_MAIL_USERNAME=...
RUNTIME_MAIL_PASSWORD=...
RUNTIME_MAIL_FROM_ADDRESS=no-reply@example.com
RUNTIME_MAIL_FROM_NAME=Atlas
RUNTIME_MAIL_LINKS_URL=https://app.example.com
RUNTIME_MAIL_MESSAGE_ID_DOMAIN=example.com
```

`RUNTIME_MAIL_LINKS_URL` doit pointer vers l'origine publique d'Atlas. Elle sert
aux actions sécurisées et au lien « Découvrir Atlas » du pied de page de chaque
email transactionnel.

Ne jamais utiliser Mailpit comme relais de production.

## Preuve et idempotence

- la clé stable fournisseur est l'`EventId`, également placée dans `Message-ID`
  et `X-Atlas-Delivery-Key` ;
- l'outbox et l'inbox empêchent le traitement normal d'un même événement deux fois ;
- `platform.email_deliveries.status = Accepted` signifie uniquement que le
  transport SMTP a accepté le message ; ce n'est pas une preuve de réception ;
- SMTP ne fournit pas d'idempotence forte après une coupure entre l'acceptation
  distante et le commit local. Le `Message-ID` stable permet le rapprochement,
  mais un doublon reste possible dans cette fenêtre rare.

Les erreurs SMTP lèvent une exception : l'outbox applique son backoff borné puis
la dead-letter existante. Suivre [`outbox-incident.md`](outbox-incident.md).

## Devis : validation et renvoi

- un premier envoi sans `billing_email` est refusé avant le verrouillage du devis ;
- l'interface affiche séparément le statut métier du devis et l'état de remise
  email (`Pending`, `Retrying`, `Accepted`, `Cancelled` ou `Failed`) ;
- un devis `Sent` peut être renvoyé après correction de l'adresse de facturation ;
- chaque renvoi crée une nouvelle preuve d'acceptation et un nouvel événement ;
- le contenu CRM/Billing du devis reste son snapshot historique. Seule l'adresse
  de routage courante est utilisée pour un renvoi et stockée chiffrée le temps
  du traitement.

## Secrets et données

Les événements contiennent des handles, jamais les jetons ni les destinataires
en clair. Les preuves nécessaires à la construction des liens et l'adresse de
routage sont chiffrées avec `APP_KEY`, puis effacées dès envoi, consommation,
révocation ou invalidation. Le registre de livraison conserve seulement
l'empreinte SHA-256 du destinataire.

Pour diagnostiquer sans exposer l'adresse :

```sql
SELECT event_id, event_type, template_key, status, provider,
       provider_message_id, accepted_at, updated_at
FROM platform.email_deliveries
ORDER BY updated_at DESC
LIMIT 50;
```

## Vérification rapide

1. Créer un compte sans auto-vérification debug.
2. Traiter l'outbox.
3. Ouvrir Mailpit et suivre le lien de vérification.
4. Envoyer un devis à un client ayant un `billing_email` et vérifier le PDF.
5. Activer `ImportantOnly` avec consentement, produire une priorité éligible et
   vérifier qu'un membre révoqué avant dispatch ne reçoit aucun email.
