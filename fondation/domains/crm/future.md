---
id: CRM-FUTURE
title: CRM Future
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

## Import, déduplication et fusion

- import CSV guidé ;
- détection explicable de doublons ;
- fusion réversible avec journal de correspondance ;
- conservation des identifiants référencés par Billing.

Aucune fusion automatique silencieuse ne sera admise.

---

## Pipeline étendu

- étapes supplémentaires justifiées par les usages ;
- ordonnancement visuel ;
- raisons de stagnation ;
- vues par type de service.

Les étapes personnalisées libres sont exclues tant qu'elles menacent la
comparabilité des événements et la simplicité du produit.

---

## Acquisition

- sources entrantes ;
- formulaires publics ;
- campagnes ;
- attribution ;
- qualification assistée.

Ces capacités pourraient justifier de nouveaux concepts, mais pas la
réintroduction implicite de Lead sans décision terminologique.

---

## Communication et calendrier

- synchronisation d'e-mails ;
- journalisation consentie des réunions ;
- tâches et rappels ;
- modèles de relance ;
- désinscription et préférences de contact.

Les secrets, contenus privés et obligations de consentement devront posséder des
contrats dédiés.

---

## Relations avancées

- un Contact relié à plusieurs Clients ;
- groupes et filiales de Clients ;
- partenaires et prescripteurs ;
- transfert inter-workspaces ;
- propriétaires commerciaux multiples.

Ces modèles sont reportés pour éviter une structure de CRM d'entreprise dans le
MVP.

---

## Intelligence commerciale

- scoring explicable ;
- probabilité de signature ;
- détection d'inactivité ;
- suggestion de prochaine action ;
- estimation de date de décision.

Ces résultats appartiendront à Analytics ou Advisor et resteront distincts des
faits CRM saisis.

---

## Réouverture d'Opportunity

`Won` et `Lost` restent terminaux en 1.0. Une éventuelle réouverture devra
préserver les taux de conversion, la relation avec les Quotes et la causalité
historique. En attendant, une nouvelle tentative crée une nouvelle Opportunity.
