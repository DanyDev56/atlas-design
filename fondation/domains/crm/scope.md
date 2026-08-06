---
id: CRM-SCOPE
title: CRM Scope
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - README.md
  - mission.md
  - model.md
  - integrations.md
  - future.md
---

# Périmètre

## Inclus dans CRM 1.0

### Client

- personne ou structure identifiée comme contrepartie commerciale potentielle
  ou effective ;
- profil commercial ;
- coordonnées administratives utilisées comme source par Billing ;
- état actif ou archivé ;
- Contact principal optionnel.

`Client` couvre la contrepartie avant comme après une vente. CRM n'introduit ni
`Lead` ni `Prospect` comme entité séparée.

### Contact

- personne rattachée à exactement un Client ;
- nom, rôle et coordonnées professionnelles ;
- état actif ou archivé ;
- désignation éventuelle comme Contact principal.

### Opportunity

- intention commerciale nommée ;
- Client obligatoire et Contact optionnel ;
- montant estimé optionnel ;
- date de décision estimée et prochaine action optionnelles ;
- cycle `Open`, `Qualified`, `Won`, `Lost`.

### Activity

- note, appel, réunion ou e-mail commercial déjà survenu ;
- rattachement obligatoire à un Client ;
- références optionnelles vers Contact et Opportunity ;
- correction traçable et retrait logique.

### Lectures

- listes et recherche simple ;
- fiche Client ;
- pipeline standard par statut ;
- historique des activités CRM ;
- contextes versionnés fournis à Billing.

### Démarrage à froid

- import initial guidé de Clients depuis le schéma canonique Atlas ;
- prévisualisation et refus des lignes invalides avant confirmation ;
- conservation des états, dates et références historiques minimales ;
- déduplication exacte par identité externe, sans fusion heuristique.

---

## Hors périmètre

| Responsabilité | Propriétaire |
|---|---|
| Workspace, préférences et cycle d'accès | `Workspace` |
| utilisateurs, memberships et autorisations | `Identity` |
| devis, factures, paiements et snapshots financiers | `Billing` |
| projets, missions et charge | domaine Projects futur |
| scores, priorités calculées et recommandations | `Analytics` / `Advisor` |
| envoi d'e-mails et synchronisation calendrier | intégrations futures |
| notifications et rappels | `Notifications` / `Automation` |

---

## Frontière avec Billing

```text
CRM                                  Billing
----------------------------------   ----------------------------------
Client courant                       ClientSnapshot du document
ClientBillingProfile courant         validation pour l'émission
Opportunity et estimation            Quote et contenu financier
fait OpportunityWon                  QuoteAccepted / QuoteRejected
```

Billing copie les données nécessaires dans ses propres agrégats. Une mise à jour
CRM ne réécrit jamais un document financier existant.

---

## Limites MVP

- pipeline fixe, sans étapes personnalisées ;
- aucun scoring prédictif dans CRM ;
- aucun connecteur synchronisé, fusion ou déduplication heuristique ;
- aucun Client parent/enfant ;
- un Contact appartient à un seul Client ;
- une seule devise de référence par Opportunity, figée à la saisie ;
- aucune suppression physique ;
- aucune réouverture d'une Opportunity gagnée ou perdue.

Les extensions sont classées dans [`future.md`](future.md).
