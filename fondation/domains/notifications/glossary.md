---
id: NTF-GLOSSARY
title: Notifications Glossary
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - notification-policy.md
  - model.md
  - value-objects.md
  - ../../language/glossary.md
---

# Glossaire Notifications

| Terme | Définition |
|---|---|
| `Notification` | message durable, personnel et pertinent dérivé d'un fait métier déjà décidé |
| `NotificationPolicy` | règles versionnées de source, audience, déduplication, canal, fréquence et contenu |
| `NotificationPlan` | process manager garantissant la décision complète d'un événement pour son audience |
| `NotificationTopicCursor` | racine sérialisant AdvisorOverviewVersion et le plan en cours d'un topic |
| `RecipientDecision` | résultat durable d'un plan pour un User et ses canaux |
| `NotificationPreference` | choix et consentement de canaux d'un User dans un Workspace |
| `NotificationTopic` | famille sémantique utilisée pour thread et fréquence |
| `NotificationThreadKey` | clé assurant au plus un message actif par destinataire et topic |
| `ContentFingerprint` | empreinte matérielle empêchant la recréation du même message |
| `NotificationStatus` | Active, Resolved, Superseded ou Expired ; pertinence du message |
| `NotificationReadState` | Unread, Read ou NotApplicable ; décision de lecture personnelle |
| `NotificationTerminalReason` | raison structurée expliquant un statut terminal sans texte libre |
| `NotificationChannel` | InApp ou Email en 1.0 |
| `ChannelDecision` | sélection ou suppression auditée d'un canal selon la politique |
| `NotificationDelivery` | remise externe Email attachée à une Notification |
| `DeliveryStatus` | état Pending, Dispatching, Accepted, Delivered, Failed, Suppressed ou Cancelled |
| `DeliveryAttempt` | tentative immuable utilisant la même ProviderIdempotencyKey |
| `DeliveryEndpointReference` | handle Identity opaque vers un endpoint vérifié, jamais une adresse brute |
| `ProviderIdempotencyKey` | clé stable empêchant un double envoi chez le fournisseur |
| `EmailFrequencyKey` | clé opaque sérialisant la fréquence Email par Workspace, User et topic |
| `FrequencyLease` | lease durable empêchant deux dispatch Email concurrents de franchir la même fenêtre |
| `Accepted` | fournisseur ayant pris en charge la soumission sans preuve de remise |
| `Delivered` | remise confirmée par une preuve fournisseur authentique |

## Termes à éviter

- Alert pour toute Notification sans niveau ni politique d'alerte explicite ;
- Sent lorsque seul Requested ou Accepted est prouvé ;
- Delivered pour une réponse synchrone d'acceptation ;
- Read pour Resolved, Superseded ou Expired ;
- Subscriber, Contact ou email address pour RecipientReference ;
- Preference pour une permission Identity ;
- Notification pour un e-mail Identity sensible ou un document Billing ;
- Clicked, Opened ou Converted comme état métier Notifications ;
- Action exécutée lorsqu'un lien ouvre seulement un parcours ;
- Real-time lorsqu'aucune garantie de délai contractuelle n'existe.
