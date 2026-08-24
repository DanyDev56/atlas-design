---
title: Checklist — Release beta fermée
status: In Review
owner: Engineering + Product
last_updated: 2026-08-24
references:
  - ../PALIER-3-CLOSURE.md
  - ../SEC-TEST-MATRIX.md
  - runtime-roles.md
  - quick-tunnel-demo.md
  - email-delivery.md
  - stripe-billing.md
  - backup-restore.md
  - observability.md
  - outbox-incident.md
  - data-retention-beta.md
  - beta-program.md
  - beta-support-offboarding.md
  - beta-research-plan.md
  - beta-cohort-operations.md
  - backoffice-operations.md
  - beta-terms.fr.md
  - privacy-notice.fr.md
  - ../../fondation/product/pricing-strategy.md
  - ../../evolution/governance/pricing-validation.md
  - ../../evolution/blueprint/backoffice.md
  - ../../evolution/blueprint/backoffice-implementation-plan.md
---

# Checklist release beta fermée

Cette checklist est le gate avant l'accueil d'utilisateurs externes et de
données réelles dans une beta fermée. Une case n'est cochée que si sa preuve a
été observée dans l'environnement candidat ; une capacité validée localement ne
vaut pas preuve de déploiement.

Le Quick Tunnel permet une démonstration supervisée avec des données fictives.
Il ne constitue ni le staging durable, ni l'environnement de beta externe.

## Décision actuelle

| Surface | État | Décision |
|---|---|---|
| Beta interne | Acceptée le 7 août 2026 | équipe Atlas et proches, environnement local |
| Démonstrations supervisées | Prêtes | Quick Tunnel éphémère, données fictives, Mailpit local |
| Beta fermée externe | **No-Go** | environnement OCI, SMTP réel, sauvegarde hors site et support à prouver |
| Encaissement réel | **No-Go** | pricing encore `Draft`, registre sans observation et enforcement désactivé |

Le passage de la beta externe à `Go` n'autorise pas l'encaissement. Stripe doit
rester désactivé ou strictement en mode test tant que le gate commercial de la
[stratégie tarifaire](../../fondation/product/pricing-strategy.md) n'est pas
satisfait.

## Périmètre candidat

| Élément | Décision à figer avant ouverture |
|---|---|
| Cohorte initiale | 5 utilisateurs du persona principal, nommément autorisés |
| Accès | invitation ou inscription contrôlée, pas d'ouverture publique libre |
| Données | données professionnelles réelles uniquement sur l'environnement durable |
| Offre | essai Atlas Solo complet, 30 jours, sans carte bancaire |
| Paiement | désactivé ; Stripe test réservé à une recette explicitement annoncée |
| Support | adresse et délai de réponse à confirmer avant invitation |
| Release | tag, commit et digest OCI immuable à renseigner |
| Durée | fenêtre de beta et date de revue à renseigner |

## 1. Release Candidate et qualité

- [x] Parcours MVP, import historique, emails transactionnels, abonnement
  candidat et landing Early Access implémentés.
- [x] Suite backend, typecheck, build Vite et recettes Playwright disponibles.
- [x] Quality gates documentaires et fixtures de référence exécutables.
- [x] Image runtime multi-stage disponible pour les rôles API, worker et
  scheduler, sans dépendances de développement.
- [x] Recette Stripe sandbox complète documentée au 23 août 2026.
- [ ] Geler le périmètre fonctionnel de la Release Candidate.
- [ ] Exécuter `make test`, `make check-docs`, `make runtime-smoke` et la matrice
  de sécurité sur le commit candidat, puis conserver les résultats.
- [ ] Renseigner le tag de release, le commit, le digest OCI, le SBOM et la
  provenance dans le compte-rendu de release.
- [ ] Vérifier que le bundle runtime ne contient ni identifiants de démo, ni
  secret, ni route ou jeton de développement.

## 2. Environnement OCI durable

- [ ] Publier l'image runtime dans le registre retenu et l'adresser par digest.
- [ ] Déployer exactement ce digest pour l'API, le worker et le scheduler.
- [ ] Fournir PostgreSQL persistant avec chiffrement, accès réseau borné et
  politique de maintenance définie.
- [ ] Injecter `APP_KEY`, les accès PostgreSQL, SMTP et observabilité depuis un
  gestionnaire de secrets ; vérifier leur rotation sans les journaliser.
- [ ] Configurer un domaine beta durable avec HTTPS valide et redirection HTTP
  vers HTTPS.
- [ ] Configurer correctement les proxies de confiance ; n'activer
  `RUNTIME_FORCE_HTTPS=true` que derrière l'origine HTTPS attendue.
- [ ] Exécuter les migrations par un job ponctuel avant la bascule applicative.
- [ ] Vérifier `/up`, l'API, le worker continu et le scheduler horaire/quotidien
  après déploiement.
- [ ] Répéter un rollback vers le digest précédent et documenter la stratégie
  de compatibilité de base de données ou de forward-fix.
- [ ] Conserver les preuves de migration, smoke test et rollback.

## 3. Sécurité de la beta externe

### Preuves engineering acquises

- [x] Révocation de session (`SEC-TEST-004`).
- [x] Rate limits sur l'authentification et les preuves publiques
  (`SEC-TEST-006`).
- [x] Révocation de membership prise en compte en cours de session
  (`SEC-TEST-007`).
- [x] Idempotence documentée et testée (`SEC-TEST-013`).
- [x] Isolation SQL/module et gestion d'échec de l'outbox couvertes par les
  tests du sous-ensemble beta.
- [x] Routes dev/spike et jetons debug fermés par défaut hors développement.

### Preuves à produire sur la cible

- [ ] Confirmer `APP_ENV=staging`, `APP_DEBUG=false`, routes dev désactivées et
  jetons de vérification/récupération non exposés.
- [ ] Vérifier l'isolation Workspace sur clients, opportunités, devis,
  factures, membres, Analytics et abonnement avec deux comptes beta dédiés.
- [ ] Exécuter le scan de dépendances PHP/JavaScript, le scan de l'image OCI et
  le secret scan sur le commit candidat ; aucune vulnérabilité critique ouverte.
- [ ] Revoir les risques résiduels avec Product et Security ; documenter tout
  risque accepté, son owner et sa date de réexamen.
- [ ] Vérifier que logs, traces, alertes et pages d'erreur ne contiennent ni
  jeton, ni mot de passe, ni adresse destinataire en clair.
- [ ] Tester expiration, rejeu et mauvais Workspace des preuves publiques
  d'invitation, de devis, de vérification et de récupération.
- [ ] Tester la révocation d'un membre pendant une session réelle sur la cible.

## 4. Emails transactionnels réels

- [x] Adaptateur SMTP partagé et worker outbox implémentés pour Identity,
  Billing et Notifications.
- [x] Vérification d'adresse, récupération, invitation, devis, facture, relance
  et priorité Advisor couvertes en développement avec Mailpit.
- [x] Preuve technique de livraison, backoff, dead-letter et effacement des
  données de routage implémentés.
- [ ] Choisir et contractualiser le fournisseur SMTP de beta.
- [ ] Configurer le domaine d'envoi, SPF, DKIM et DMARC, ainsi que l'adresse
  `From` réellement surveillée.
- [ ] Injecter les secrets SMTP et définir `RUNTIME_MAIL_LINKS_URL` sur le
  domaine HTTPS de beta ; Mailpit et `MAIL_MAILER=log` sont interdits.
- [ ] Tester depuis une boîte externe la vérification, la récupération,
  l'invitation, le devis, la facture et la relance avec leurs liens/PDF.
- [ ] Vérifier qu'une révocation avant dispatch empêche bien l'email Advisor.
- [ ] Vérifier les statuts `Accepted`, `Retrying` et `Failed`, puis répéter la
  procédure de reprise d'une dead-letter depuis le back-office. Le parcours
  local, ses permissions, son idempotence et son rollback d'audit sont couverts
  automatiquement ; la vérification sur la cible reste à réaliser.
- [ ] Définir le traitement support des rejets, plaintes et adresses invalides.

## 5. Sauvegarde, restauration et rétention

- [x] Sauvegarde, restauration et canary disponibles localement
  (`SEC-TEST-023`).
- [x] Purge des sessions, outbox et clés d'idempotence planifiée et documentée.
- [ ] Valider formellement la politique de rétention pour des utilisateurs
  externes et indiquer comment exercer export, correction et suppression.
- [ ] Stocker les sauvegardes de beta hors du serveur applicatif, chiffrées et
  avec une rotation définie.
- [ ] Restaurer une sauvegarde de la cible dans un environnement isolé et
  mesurer le RPO/RTO observé.
- [ ] Vérifier que la restauration inclut les tables Identity, Workspace, CRM,
  Billing, Analytics, Notifications, email et Subscriptions.
- [ ] Documenter l'owner et la procédure d'autorisation d'une restauration.

## 6. Observabilité et exploitation

- [x] Logs JSON corrélés, traces HTTP/outbox et runbooks d'incident disponibles.
- [x] Monitoring du backlog, retries et dead-letters de l'outbox implémenté.
- [x] Back-office local raccordé aux heartbeats API/worker/scheduler, à la
  sonde PostgreSQL, aux abonnements/webhooks séparés par environnement et aux
  résultats de sauvegarde/restauration.
- [x] Back-office opérateur, catalogue de métriques et ordre d'implémentation
  documentés avant tout code ; `ADR-004` est accepté.
- [x] Livrer le socle local de l'incrément 1 : audience et session séparées,
  provisioning CLI, révocation immédiate, audit et shell en lecture seule.
- [x] Raccorder le dashboard opérateur aux registres Outbox et Emails avec
  provenance, permissions, pagination et états `NotCollected`/`Unavailable`.
- [x] Raccorder la cohorte beta pseudonymisée : registre limité à cinq,
  dérivation E0–E6, entonnoir avec dénominateurs, jalons et décisions pricing
  append-only, sans identité ni contenu métier dans les vues Product.
- [ ] Fermer `SEC-GAP-005` : le socle TOTP/step-up est livré et testé ; livrer
  l'authentification résistante au phishing, le break-glass, la rétention et
  valider les incréments 0 à 5 en lecture seule sur la cible externe.
- [ ] Collecter durablement logs et traces des trois rôles sans donnée sensible.
- [ ] Configurer une alerte réellement reçue pour indisponibilité HTTP, erreurs
  applicatives, backlog/dead-letter, échec scheduler et saturation PostgreSQL.
- [ ] Vérifier la corrélation d'une requête depuis l'API jusqu'à l'outbox et au
  registre de livraison email.
- [ ] Définir l'astreinte légère : owner, canal, horaires, délai de prise en
  compte et message utilisateur en cas d'incident.
- [ ] Tester les runbooks sauvegarde, outbox, email et rollback avec la personne
  qui assurera le support.
- [ ] Définir une fenêtre de maintenance et une procédure de communication à la
  cohorte.

## 7. Produit, support et conformité

- [x] Landing Early Access, inscription, onboarding, invitation et parcours
  métier démontrables sur desktop et mobile.
- [x] Essai de 30 jours sans carte et catalogue Atlas Solo versionné dans le
  produit ; enforcement désactivé par défaut.
- [x] Protocole de validation pricing et registre pseudonymisé disponibles.
- [x] Projection de cohorte et écran opérateur en lecture seule disponibles ;
  affectation pricing immutable, commandes administratives motivées et audit.
- [x] Programme participant, limites de la beta, parcours d'information et
  matrice de preuves formalisés dans le [programme beta](beta-program.md).
- [x] Niveaux de support, objectifs de réponse, traitement des demandes de
  droits et parcours de sortie documentés dans le
  [runbook support](beta-support-offboarding.md).
- [x] Registres minimisés Support et Conformité, échéances, preuves append-only,
  consentements séparés et écran opérateur pseudonymisé disponibles ; ils ne
  déclenchent encore aucune action destructive.
- [x] Première action Support non destructive disponible sous deux flags sûrs
  par défaut, permission dédiée, step-up, prévisualisation, révision,
  idempotence et audit ; aucune action Conformité ou Workspace n'est ouverte.
- [x] Registre pseudonymisé des sessions Operator et révocation ciblée livrés
  avec permission séparée, interdiction d'auto-révocation, step-up,
  prévisualisation, idempotence, audit et purge bornée ; les sessions Workspace
  restent inchangées.
- [x] Entonnoir d'activation basé sur les états métier, revues de cohorte,
  minimisation et script d'entretien définis sans tracking tiers dans le
  [plan de recherche](beta-research-plan.md).
- [x] Projets de [conditions beta](beta-terms.fr.md) et de
  [notice de confidentialité](privacy-notice.fr.md) préparés avec leurs champs
  bloquants explicitement identifiés.
- [x] La communication publique ne promet plus d'export en libre-service : la
  récupération assistée et sa limite technique sont annoncées honnêtement.
- [ ] Confirmer l'adresse de support, le responsable et le délai de réponse
  annoncé ; remplacer `beta@atlas-design.fr` si elle n'est pas opérationnelle.
- [ ] Confirmer l'identité de l'opérateur, le territoire, les sous-traitants,
  les transferts, les durées support/recherche et le contact données.
- [ ] Finaliser le registre des traitements, la mise en balance des intérêts
  légitimes, les contrats fournisseurs et le registre interne des violations.
- [ ] Faire valider juridiquement, versionner et publier les conditions et la
  notice sous des URL stables ; conserver la preuve de la version acceptée.
- [ ] Établir hors du dépôt la liste des cinq participants, leur canal de
  recrutement et leur accord explicite aux versions publiées.
- [x] Production et remise d'un export assisté testées sur un Workspace fictif :
  double contrôle, worker Outbox, chiffrement, empreinte, TTL, téléchargement
  `no-store`, idempotence et audit.
- [ ] Livrer une suppression Workspace atomique et auditable avant données réelles.
- [ ] Exécuter les revues d'activation J2/J7/J14/J21/J30 et conserver les
  résultats pseudonymisés avec leurs dénominateurs.
- [ ] Mener les entretiens avec une seule cellule `P19`, `P24` ou `P29` par
  participant et enregistrer chaque décision dans le registre pricing ; les
  notes nominatives restent hors du dépôt.

## 8. Stripe et gate commercial

- [x] Checkout, Customer Portal, webhooks signés, renouvellement, échec,
  Smart Retries, grâce de 14 jours, restauration, résiliation et rejeu validés
  en sandbox.
- [x] Un retour navigateur ne modifie jamais les droits ; seul un webhook signé
  constitue une preuve fournisseur.
- [x] `SUBSCRIPTIONS_ENFORCEMENT_ENABLED=false` reste le défaut.
- [ ] Confirmer pour la beta externe que Checkout est masqué/désactivé, ou
  explicitement identifié comme test sans valeur contractuelle.
- [ ] Ne charger aucune clé `sk_live_`, Price live ou moyen de paiement réel dans
  l'environnement de beta tant que le gate commercial est fermé.
- [ ] Avant tout paiement réel : valider prix, TVA, facture d'abonnement,
  mentions légales, résiliation, réconciliation et support conformément au
  [runbook Stripe](stripe-billing.md).
- [ ] Avant enforcement : obtenir la décision Product datée et les preuves
  exigées par le protocole pricing.

## 9. Recette de bout en bout sur la cible

- [ ] Créer un compte externe, vérifier son email puis créer son Workspace.
- [ ] Inviter un membre externe avec la bonne adresse, accepter puis révoquer le
  membership et vérifier la perte immédiate d'accès.
- [ ] Importer un petit historique contrôlé et vérifier la reconstruction
  Analytics, Business Health et Advisor.
- [ ] Créer un client et une opportunité, qualifier, envoyer puis accepter un
  devis depuis une seconde session navigateur.
- [ ] Créer et émettre la facture, recevoir le PDF, enregistrer un paiement
  partiel et envoyer une relance.
- [ ] Vérifier notifications, préférences, compteur non lu et email Advisor
  éligible/non éligible.
- [ ] Tester les parcours sur un navigateur desktop et mobile sans accès au
  réseau local de développement.
- [ ] Redémarrer API, worker et scheduler sans perte de message ni double envoi
  observé.
- [ ] Exécuter sauvegarde, restauration canary, migration et rollback avec les
  artefacts de la Release Candidate.
- [ ] Archiver les résultats, incidents et écarts ; tout défaut critique ou
  risque d'isolation non résolu impose `No-Go`.

## Go / No-Go

Le passage à `Go` exige :

1. toutes les cases des sections 1 à 7 et 9 cochées ou un écart explicitement
   accepté, daté et borné ;
2. aucun défaut critique de sécurité, d'isolation, de sauvegarde ou d'email ;
3. Stripe live et l'enforcement toujours désactivés ;
4. une cohorte, un support, un owner d'incident et une date de revue connus ;
5. le même digest OCI validé, déployé et réversible.

La décision est inscrite sans réécrire les preuves historiques :

| Champ | Valeur |
|---|---|
| Décision | `No-Go` |
| Motif actuel | environnement OCI externe et opérations non encore prouvés |
| Release candidate | à renseigner |
| Environnement | à renseigner |
| Cohorte | à renseigner |
| Date de revue | à renseigner |

## Sign-off

### Beta interne — historique accepté

| Rôle | Nom | Date |
|---|---|---|
| Engineering | Daniel | 2026-08-07 |
| Product | Daniel | 2026-08-07 |
| Security | Daniel | 2026-08-07 |

### Beta externe — à signer

| Rôle | Nom | Date | Décision |
|---|---|---|---|
| Engineering | — | — | — |
| Product | — | — | — |
| Security | — | — | — |
| Support / Operations | — | — | — |
