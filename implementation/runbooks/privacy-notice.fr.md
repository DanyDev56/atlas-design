---
title: Notice de confidentialité Atlas beta — Projet
status: Draft
owner: Product + Legal + Security
last_updated: 2026-08-23
references:
  - beta-program.md
  - beta-support-offboarding.md
  - data-retention-beta.md
  - ../../fondation/security/mvp-threat-model.md
---

# Notice de confidentialité Atlas beta

> Projet `privacy-2026-08-draft` — non publiable. Cette cartographie doit être
> rapprochée de l'environnement OCI réellement déployé, puis complétée et
> validée avant le traitement de données externes.

## Responsable et contact

Responsable du traitement : **[dénomination ou nom, forme, adresse,
immatriculation]**. Contact données personnelles : **[adresse surveillée]**.
Délégué à la protection des données : **[coordonnées ou « non désigné » après
validation]**.

## Données, finalités et bases à valider

| Données | Finalité | Base candidate | Durée candidate |
|---|---|---|---|
| nom, email, statut de vérification | créer et sécuriser le compte beta | exécution des conditions beta | durée de participation puis clôture |
| sessions, empreintes techniques, journaux de sécurité | authentifier, prévenir les abus, investiguer | intérêt légitime de sécurité | sessions : 30 j après fin ; logs : 14 j |
| Workspace, membres et préférences | fournir l'espace collaboratif | exécution des conditions beta | durée de participation puis clôture |
| clients, contacts, activités, devis, factures et paiements déclarés | fournir CRM, facturation et documents | exécution des conditions beta ; participant responsable de ses données client | durée de participation puis clôture |
| faits Analytics, scores, recommandations et décisions | fournir le pilotage explicable | exécution des conditions beta | durée de participation puis clôture |
| emails transactionnels et preuves de remise | fournir et diagnostiquer les communications demandées | exécution + intérêt légitime de preuve | routage minimisé après remise ; outbox 90 j |
| demandes support | répondre, corriger et sécuriser | exécution + intérêt légitime | **[durée à fixer]** |
| notes d'entretien | évaluer la beta | intérêt légitime après information | **[durée courte à fixer]** |
| audio, vidéo ou citation attribuée | recherche ou communication facultative | consentement distinct | durée annoncée dans l'accord |

Les bases sont des candidates de travail. Legal doit notamment confirmer la
répartition des rôles entre Atlas et le participant pour les données de ses
propres clients.

## Origine et caractère nécessaire

Les données proviennent du participant, des membres qu'il invite, des imports
qu'il déclenche et du fonctionnement sécurisé du service. Les champs marqués
obligatoires sont nécessaires au compte ou à la capacité demandée. Refuser un
entretien ou un enregistrement n'empêche pas d'utiliser la beta.

## Destinataires et sous-traitants

L'accès est limité aux personnes Atlas qui exploitent ou sécurisent la beta et
aux fournisseurs nécessaires. La liste publiée doit remplacer chaque ligne
avant ouverture :

| Catégorie | Fournisseur et région | Usage | Transfert hors EEE / garantie |
|---|---|---|---|
| hébergement OCI/PostgreSQL | **[à renseigner]** | application, base, sauvegardes | **[à renseigner]** |
| email transactionnel | **[à renseigner]** | remise des emails | **[à renseigner]** |
| logs et traces | **[à renseigner]** | disponibilité et diagnostic | **[à renseigner]** |
| paiement | Stripe test uniquement, sans paiement réel beta | recette explicitement annoncée | **[à confirmer]** |
| support/recherche | **[outil restreint à renseigner]** | tickets, accords et notes | **[à renseigner]** |

Les données ne sont ni vendues, ni utilisées pour de la publicité ciblée. Tout
nouveau destinataire exige une mise à jour de cette notice et de la revue de
sécurité.

## Conservation et suppression

Les durées techniques sont détaillées dans la
[politique de rétention beta](data-retention-beta.md). À la fermeture, les
copies actives et de travail sont supprimées selon la procédure validée ; les
sauvegardes chiffrées expirent au plus tard après leur rotation de 30 jours,
sauf obligation légale ou conservation bornée d'une preuve d'incident.

La durée des comptes fermés, tickets support et notes de recherche reste à
fixer avant publication. Les preuves d'accord doivent avoir leur propre durée,
liée à la défense des droits de l'opérateur et du participant.

## Sécurité

Atlas prévoit notamment chiffrement en transit, isolation des Workspaces,
contrôle d'accès, révocation de session, secrets hors du code, sauvegardes,
journaux minimisés et procédures d'incident. Aucun dispositif ne garantit un
risque nul. Le participant signale une suspicion au contact support sans
transmettre de secret.

## Droits et demandes

Selon le droit applicable, une personne peut demander accès, correction,
effacement, limitation, opposition ou portabilité, et retirer un consentement
pour l'avenir. La demande est adressée à **[contact données]**. Atlas peut
demander des éléments proportionnés pour vérifier l'identité et répond dans le
délai légal applicable.

Le participant peut également saisir **[autorité de contrôle compétente ; CNIL
si la France est confirmée]**. Le runbook de support décrit le traitement
opérationnel et l'export assisté disponible pendant la beta.

## Décisions automatisées et tracking

Atlas produit des indicateurs et recommandations explicables pour aider le
participant ; ils ne produisent pas seuls d'effet juridique et le participant
reste décisionnaire. La beta n'utilise pas de publicité comportementale, de
session replay ni de tracking tiers. Les mesures d'activation sont dérivées des
états métier nécessaires au service.

## Incidents et modifications

Un incident est qualifié, contenu et documenté. Les personnes et autorités sont
informées lorsque le droit applicable l'exige. Toute modification substantielle
de cette notice est communiquée avant son entrée en vigueur ; la version et sa
date restent accessibles.

Version publiée : **[à attribuer]**. Date d'effet : **[à renseigner]**.

## Dossier de conformité à constituer

Avant publication, l'opérateur conserve hors du dépôt :

- le registre des traitements et la justification de chaque base légale ;
- la mise en balance documentée pour chaque intérêt légitime retenu ;
- les contrats de sous-traitance, sous-traitants ultérieurs, régions et
  garanties de transfert ;
- le registre interne des violations et la chaîne de décision de notification ;
- les versions publiées de la notice et les preuves d'information ;
- les tests d'exercice des droits, d'export et de suppression.

Sources officielles utilisées pour ce projet :

- [article 13 du RGPD](https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=celex%3A32016R0679) ;
- [transparence et information des personnes — CNIL](https://www.cnil.fr/fr/conformite-rgpd-information-des-personnes-et-transparence) ;
- [bases légales et contrat — CNIL](https://www.cnil.fr/fr/les-bases-legales/contrat) ;
- [durées de conservation — CNIL](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees) ;
- [règles applicables aux violations — CNIL](https://www.cnil.fr/fr/violations-de-donnees-personnelles-les-regles-suivre).
