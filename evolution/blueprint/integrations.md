---
id: BPT-011
title: MVP Integration Boundaries
status: In Review
owner: Product and Engineering
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - public-api.md
  - ../roadmap/mvp-scope.md
  - ../../fondation/domains/identity/integrations.md
  - ../../fondation/domains/workspace/integrations.md
  - ../../fondation/domains/crm/integrations.md
  - ../../fondation/domains/billing/integrations.md
  - ../../fondation/domains/analytics/integrations.md
  - ../../fondation/domains/business-health/integrations.md
  - ../../fondation/domains/advisor/integrations.md
  - ../../fondation/domains/notifications/integrations.md
---

# Frontières d'intégration du MVP

## Distinction

Le MVP utilise des adaptateurs techniques pour exécuter ses propres contrats.
Il n'inclut pas encore de connecteurs produit qui synchronisent le compte Gmail,
la banque, la comptabilité ou d'autres systèmes de l'utilisateur.

```text
Domain-owned port -> replaceable infrastructure adapter

not

Third-party product synchronization
```

---

## Adaptateurs nécessaires

| Port | Propriétaire | Effet attendu |
|---|---|---|
| vérification et récupération d'identité | Identity | remet une preuve opaque sans exposer le secret au reste du produit. |
| rendu de document | Billing | produit un artefact versionné pour Quote, Invoice ou CreditNote. |
| remise de document financier | Billing | remet devis, factures et relances et retourne une preuve de prise en charge. |
| remise d'email Advisor | Notifications | remet uniquement les notifications éligibles après consentement et revalidation. |
| stockage d'artefacts | domaine propriétaire | conserve un objet immuable adressé par référence opaque et politique de rétention. |
| horloge, bus, observabilité | plateforme | fournit des capacités techniques sans devenir propriétaire des décisions métier. |

Identity, Billing et Notifications ne partagent pas un service métier de
« communication ». Ils peuvent partager une librairie ou une infrastructure,
mais gardent leurs templates, consentements, preuves, retries et événements.

---

## Contrat minimal d'un fournisseur

Tout effet externe comporte :

- une clé d'idempotence stable dans le scope du fournisseur ;
- une référence d'endpoint opaque ;
- un payload minimisé et une version de template ou d'artefact ;
- un timeout borné et une classification retryable/non-retryable ;
- une preuve `Accepted`, `Delivered`, `Failed` ou équivalente sans ambiguïté ;
- une corrélation avec l'intention métier ;
- une politique de rétention et de redaction ;
- un mode sandbox ou fake déterministe pour les tests.

Un succès HTTP ne prouve pas nécessairement une livraison. Chaque domaine
matérialise uniquement le niveau de preuve réellement fourni.

---

## Sélection de fournisseur

Aucun fournisseur n'est imposé dans ce Blueprint. La sélection compare au
minimum :

- résidence, sous-traitants et traitement des données ;
- authentification, rotation et séparation des environnements ;
- garanties d'idempotence, webhooks et signatures ;
- délivrabilité, rendu, accessibilité et localisation ;
- quotas, limites, coût et stratégie de sortie ;
- export des preuves et support des incidents.

Le domaine dépend d'un port stable. Remplacer le fournisseur ne doit pas changer
une commande, un invariant ou un événement métier.

---

## Connecteurs produit différés

Gmail, Outlook, calendriers, stockage utilisateur, Stripe, GoCardless,
Pennylane, Indy, banques, signature, GitHub et outils similaires sont des pistes
post-MVP, pas des engagements.

Chaque connecteur futur nécessite un besoin priorisé, un propriétaire de vérité,
un modèle de consentement, une stratégie de conflit, des limites de données et
un workflow de déconnexion/reconstruction avant d'entrer dans la roadmap.
