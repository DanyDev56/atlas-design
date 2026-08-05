---
id: WSP-FUTURE
title: Workspace Future
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - decision-record.md
  - ../../vision/anti-goals.md
---

# Évolutions futures

Les sujets suivants ne font pas partie de Workspace 1.0.

## Expérience multi-workspaces

- sélecteur de Workspace ;
- Workspace par défaut d'un User ;
- limites d'offre ;
- transfert de données explicitement supporté.

Le modèle actuel n'interdit pas cette évolution, mais ne l'expose pas dans le
MVP.

---

## Structures avancées

- branches ou établissements ;
- groupes de Workspaces ;
- équipes ou départements ;
- délégation inter-workspaces ;
- consolidation multi-entités.

Ces capacités exigeraient de nouveaux concepts. Elles ne doivent pas détourner
`Workspace` de son sens ni rapprocher Atlas d'un ERP généraliste.

---

## Profil réglementaire enrichi

- vérification externe d'identifiants ;
- historique réglementaire consultable ;
- signatures ou preuves documentaires ;
- profils propres à certaines juridictions.

Toute extension devra maintenir la frontière entre donnée déclarée Workspace et
validation métier Billing.

---

## Préférences étendues

- jours ouvrés ;
- calendriers ;
- formats de document ;
- langues secondaires ;
- politiques de défaut par domaine.

Une préférence ne sera ajoutée qu'avec un consommateur et une valeur utilisateur
identifiés.

---

## Réouverture ou archivage

`Closed` reste terminal en 1.0. Une future capacité de réouverture devrait
définir :

- les garanties de sécurité ;
- la validité des anciennes autorisations ;
- la reprise des intégrations ;
- les contraintes légales et commerciales ;
- la différence entre archivage, fermeture et suspension.

Aucune réouverture implicite n'est admise avant cette décision.

---

## Gouvernance configurable

La valeur `RequiresActiveOwner` est toujours vraie en 1.0. Une évolution vers
d'autres modèles de gouvernance nécessiterait un contrat versionné avec Identity
et des invariants équivalents d'administrabilité.
