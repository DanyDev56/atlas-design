---
title: Programme beta fermée — Produit, support et conformité
status: In Review
owner: Product + Support + Legal
last_updated: 2026-08-23
references:
  - beta-release-checklist.md
  - beta-support-offboarding.md
  - beta-research-plan.md
  - beta-terms.fr.md
  - privacy-notice.fr.md
  - data-retention-beta.md
  - ../../evolution/governance/pricing-validation.md
---

# Programme beta fermée

Ce document est la source de vérité opérationnelle du point 7 de la checklist
de release. Il prépare l'accueil de cinq participants externes sans présenter
comme validées des informations juridiques ou des capacités qui ne le sont pas.

Les textes marqués `Draft` ne doivent pas être publiés ni acceptés par un
participant avant validation des champs bloquants ci-dessous.

## Paramètres à ratifier

| Paramètre | Candidat | État | Owner |
|---|---|---|---|
| Population | 5 professionnels correspondant au persona principal | prêt à recruter | Product |
| Accès | invitation nominative, un participant par adresse vérifiée | candidat | Product |
| Offre | Atlas Solo complet, 30 jours, sans carte bancaire | décidé | Product |
| Paiement | aucun paiement réel ; Stripe live et enforcement désactivés | décidé | Product + Engineering |
| Territoire | France, participants professionnels uniquement | à confirmer | Legal + Product |
| Durée du programme | date de début, date de fin et revue à renseigner | bloquant | Product |
| Responsable du traitement | dénomination, forme, adresse et contact à renseigner | bloquant | Legal |
| Support | `beta@atlas-design.fr` | à rendre opérationnel | Support |
| Sous-traitants | hébergeur OCI, SMTP et observabilité à nommer | bloquant | Operations + Legal |
| Conditions | version `beta-2026-08-draft` | à valider et publier | Legal + Product |
| Confidentialité | version `privacy-2026-08-draft` | à valider et publier | Legal + Product |

## Promesse faite au participant

La communication d'invitation et l'onboarding doivent présenter ensemble les
éléments suivants :

- Atlas est un produit en beta, susceptible de contenir des défauts et
  d'évoluer pendant l'expérimentation ;
- l'accès dure 30 jours, ne demande aucune carte et ne déclenche aucun paiement
  ni renouvellement automatique ;
- le participant peut utiliser des données professionnelles réelles uniquement
  dans l'environnement durable annoncé, jamais via un Quick Tunnel de démo ;
- aucun SLA contractuel n'est fourni ; les objectifs de réponse du support sont
  des objectifs opérationnels ;
- Atlas ne remplace ni un comptable, ni une Plateforme Agréée, ni un conseil
  juridique ou financier ;
- les indicateurs et recommandations dépendent des données disponibles et
  affichent leurs limites de couverture ;
- la récupération des données est assistée par le support pendant la beta ; il
  n'existe pas encore de libre-service d'export ou de fermeture de compte ;
- le participant peut quitter le programme à tout moment et demander la
  fermeture de son compte selon le runbook de sortie ;
- les entretiens, enregistrements et usages de citations sont facultatifs et
  font l'objet d'un accord séparé.

## Parcours participant

### 1. Recrutement

Product vérifie le persona, le rôle dans l'achat, le canal de recrutement et
l'absence de conflit manifeste. Les coordonnées nominatives restent dans un
outil restreint hors du dépôt ; seul un identifiant `BETA-001` à `BETA-005` est
utilisé dans les registres versionnés.

### 2. Information et accord

Avant l'ouverture du compte, le participant reçoit :

1. la description de la beta et ses limites ;
2. les conditions beta dans leur version publiée ;
3. la notice de confidentialité dans sa version publiée ;
4. l'adresse du support et la procédure de sortie ;
5. les dates de début et de fin de sa participation.

Tant que le produit ne conserve pas lui-même la version des textes acceptés,
le support archive dans l'espace restreint une réponse explicite du participant
contenant la date, les deux versions et son adresse vérifiée. Une ouverture de
compte ou un silence ne valent pas accord documenté.

### 3. Activation

Le support confirme l'adresse vérifiée, l'accès au Workspace et le premier
objectif métier. Aucun participant ne reçoit simultanément une cellule de prix
différente de celle qui lui a été affectée dans le plan de recherche.

### 4. Suivi

Le point de contact suit l'activation à J2, propose un échange à J7 et recueille
la décision à J21 ou avant la fin de l'essai. Les incidents produit sont séparés
des observations pricing afin qu'un défaut ne soit pas interprété comme une
objection au prix.

### 5. Sortie

À la demande du participant ou à la fin de la fenêtre, le support applique le
[runbook de sortie](beta-support-offboarding.md), confirme ce qui a été remis,
ce qui a été supprimé et la durée résiduelle des sauvegardes.

## Registre de cohorte pseudonymisé

Le registre nominatif et les preuves d'accord ne sont jamais committés. Ce
tableau ne contient que les informations nécessaires au pilotage agrégé.

| Participant | Persona admissible | Canal | Début | Fin | Conditions | Confidentialité | Cellule | État |
|---|---|---|---|---|---|---|---|---|
| `BETA-001` | à qualifier | à renseigner | — | — | — | — | `P19` | à recruter |
| `BETA-002` | à qualifier | à renseigner | — | — | — | — | `P24` | à recruter |
| `BETA-003` | à qualifier | à renseigner | — | — | — | — | `P29` | à recruter |
| `BETA-004` | à qualifier | à renseigner | — | — | — | — | `P19` | à recruter |
| `BETA-005` | à qualifier | à renseigner | — | — | — | — | `P24` | à recruter |

L'ordre des cellules est un plan initial équilibré `2/2/1`, pas une règle de
recrutement. Un remplacement conserve la cellule de la place remplacée. Toute
déviation est consignée comme biais dans le registre pricing.

## Preuves exigées avant invitation

- [ ] Identité légale et territoire confirmés.
- [ ] Boîte support testée en émission et réception, avec un owner secondaire.
- [ ] Conditions et notice validées, publiées sous des URL stables et versionnées.
- [ ] Liste des sous-traitants et éventuels transferts complétée.
- [ ] Contrats de sous-traitance et garanties de transfert revus et archivés.
- [ ] Registre des traitements, analyse des intérêts légitimes et registre des
  violations prêts dans l'espace conformité restreint.
- [ ] Dates de la beta et date de revue annoncées.
- [ ] Participant qualifié et preuve d'accord archivée hors dépôt.
- [ ] Environnement durable, SMTP réel, sauvegarde et alertes validés.
- [ ] Procédure d'export assisté testée sur des données fictives.

Une invitation externe n'est envoyée que lorsque toutes ces preuves sont
réunies.

## Références officielles de conformité

La revue finale s'appuie au minimum sur :

- [l'information des personnes et les mentions attendues par la CNIL](https://www.cnil.fr/fr/conformite-rgpd-information-des-personnes-et-transparence) ;
- [le choix et la justification d'un intérêt légitime](https://www.cnil.fr/fr/les-bases-legales/interet-legitime) ;
- [l'encadrement contractuel des sous-traitants](https://www.cnil.fr/fr/qualification-juridique-sous-traitance) ;
- [l'identification et l'encadrement des transferts hors UE](https://www.cnil.fr/fr/responsables-de-traitement-comment-identifier-et-traiter-des-transferts-de-donnees-hors-ue) ;
- [la documentation et la notification des violations](https://www.cnil.fr/fr/services-en-ligne/notifier-une-violation-de-donnees-personnelles).
