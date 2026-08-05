---
id: NTF-MISSION
title: Notifications Mission
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - scope.md
  - notification-policy.md
  - ../../vision/principles.md
  - ../../../evolution/roadmap/mvp-scope.md
---

# Mission

## Raison d'être

Une priorité utile perd sa valeur si elle est noyée, envoyée plusieurs fois ou
remise à une personne non autorisée. À l'inverse, une notification trop
agressive réduit la confiance et détourne l'utilisateur du produit.

> Notifications rend les faits importants visibles avec une fréquence, une
> confidentialité et un état de livraison maîtrisés.

## Promesse

Notifications permet :

- de retrouver les nouvelles priorités Advisor dans une inbox Atlas ;
- de recevoir par e-mail uniquement les priorités importantes consenties ;
- de distinguer unread, read, resolved, superseded et expired ;
- de comprendre pourquoi un canal a été utilisé ou supprimé ;
- de suivre accepted, delivered ou failed sans promesse trompeuse ;
- de modifier ses préférences sans renvoyer le passé.

## Contribution à Atlas

```text
décision et priorité    -> Advisor
audience et endpoint    -> Identity
locale et accès         -> Workspace
préférence et remise    -> Notifications
action après lecture    -> utilisateur dans le domaine cible
```

## Critère de réussite

Pour toute Notification, Atlas peut répondre :

- quel événement, quelle Recommendation et quelle politique l'ont causée ;
- pourquoi ce destinataire et ces canaux étaient éligibles ;
- quelle préférence et quelle version d'audience ont été appliquées ;
- si une livraison a été demandée, acceptée, confirmée ou a échoué ;
- si le contenu a été lu, remplacé, résolu ou expiré ;
- pourquoi aucun e-mail n'a été envoyé le cas échéant.
