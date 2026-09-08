Experiment: BH-001
Experiment version: Draft; scenario SHA-256 ci-dessous
Run: run-002
Run status: IN_PROGRESS
Current phase: PRE_ATLAS
Next work item: P04-H02, P04-H03, P04-H04, puis P05-H01 (prochain lot, maximum 4)
Last checkpoint commit: 16f66ad (checkpoint précédent); ce checkpoint se résout avec git log pour ce chemin
Date: 2026-09-09 (Europe/Paris); démarré 2026-09-08 22:16:38 UTC
Orchestrator model: GPT-5 (identifiant exact déclaré par la session; configuration exacte Unknown)
Persona model: gpt-5.6-luna
Atlas model: gpt-6-astra
Model configuration: Persona reasoning effort medium pour ce lot; autres paramètres Unknown
Protocol version: Draft; SHA-256 6BF0B2789A7FBB1426A001D65627D3EA299571E82C4369A14D8B5AA8C7E44A0E
Persona version: SHA-256 8CD566A6140C6B5AD2FD7FC373BD7D5B1D2C6D5531C454C84A2902086A2F65A4
Prompt version: Draft; SHA-256 875E415221D37542B93766F9FFAF36C65A2DD7C782FEAB2BCEE9E2AA78DF7A2D
Scenario hash or commit: SHA-256 518CA4A70C2F534E4EB2B0E88DF5D45B5AB4DCD94A8D2AB2540B3885227C6936; initial commit 29391983d83ca0a8d2b71ec0d693f6fd8456316e
Executions planned: 24 (P01–P06 × H01–H04)
Executions completed: 0
Executions invalid: 0 canonical units (10 attempts invalidated; audit ci-dessous)
Observable states planned: 1
Observable states completed: 0
Delegation explicitly authorized: YES
Experiment modified during run: NO
Synthetic Evidence: YES
Market Evidence: NO

## Progress

| Unit | Phase | Status | Artifact |
|---|---|---|---|
| P01-H01 | PRE_ATLAS | COMPLETED | raw/P01-H01.md |
| P01-H02 | PRE_ATLAS | COMPLETED | raw/P01-H02.md |
| P01-H03 | PRE_ATLAS | COMPLETED | raw/P01-H03.md |
| P01-H04 | PRE_ATLAS | COMPLETED | raw/P01-H04.md |
| P02-H01 | PRE_ATLAS | COMPLETED | raw/P02-H01.md |
| P02-H02 | PRE_ATLAS | COMPLETED | raw/P02-H02.md |
| P02-H03 | PRE_ATLAS | COMPLETED | raw/P02-H03.md |
| P02-H04 | PRE_ATLAS | COMPLETED | raw/P02-H04.md |
| P03-H01 | PRE_ATLAS | COMPLETED | raw/P03-H01.md |
| P03-H02 | PRE_ATLAS | COMPLETED | raw/P03-H02.md |
| P03-H03 | PRE_ATLAS | COMPLETED | raw/P03-H03.md |
| P03-H04 | PRE_ATLAS | COMPLETED | raw/P03-H04.md |
| P04-H01 | PRE_ATLAS | COMPLETED | raw/P04-H01.md |
| P04-H02 | PRE_ATLAS | PENDING | - |
| P04-H03 | PRE_ATLAS | PENDING | - |
| P04-H04 | PRE_ATLAS | PENDING | - |
| P05-H01 | PRE_ATLAS | PENDING | - |
| P05-H02 | PRE_ATLAS | PENDING | - |
| P05-H03 | PRE_ATLAS | PENDING | - |
| P05-H04 | PRE_ATLAS | PENDING | - |
| P06-H01 | PRE_ATLAS | PENDING | - |
| P06-H02 | PRE_ATLAS | PENDING | - |
| P06-H03 | PRE_ATLAS | PENDING | - |
| P06-H04 | PRE_ATLAS | PENDING | - |
| O01 | ATLAS | PENDING | - |

`Executions completed` compte uniquement les simulations ayant aussi terminé Post-Atlas; les treize unités marquées ci-dessus ont seulement leur Pre-Atlas figé.

## Audit de reprise

- Le run a commencé avant l'ajout du protocole multi-tours. La demande explicite de reprise applique le schéma de checkpoint courant sans modifier scénario, personas, Hidden States ou critères.
- L'interruption précédente n'avait produit aucun checkpoint. Neuf sorties sans artefact final fiable ont été invalidées avant reprise: P01-H01 (1), P01-H02 (2), P01-H03 (1), P01-H04 (1), P02-H01 (2), P02-H02 (1), P02-H03 (1).
- P02-H04 avait un artefact Pre-Atlas fiable. Seules ses métadonnées et sa marque de gel ont été ajoutées; son contenu Pre-Atlas est inchangé.
- Ce lot a rejoué P01-H01 à P01-H04 dans quatre contextes neufs et indépendants.
- Le lot suivant a produit P02-H01, P02-H02, P02-H03 et P03-H01 dans quatre contextes neufs et indépendants.
- Ce lot a produit P03-H02, P03-H03, P03-H04 et P04-H01 dans quatre contextes indépendants. La première tentative P03-H03 a été invalidée pour omission de la contrainte privée, puis rejouée dans un contexte neuf.

## Sources

- RUN_PROTOCOL.md: 6BF0B2789A7FBB1426A001D65627D3EA299571E82C4369A14D8B5AA8C7E44A0E
- RUN_PROMPT.md: 875E415221D37542B93766F9FFAF36C65A2DD7C782FEAB2BCEE9E2AA78DF7A2D
- AGENT_PROMPTS.md: AD683A274798F43458E3DB7F43FF9793ECB3BB5F074B5398ADA9318C38E6336D
- personas.md: 8CD566A6140C6B5AD2FD7FC373BD7D5B1D2C6D5531C454C84A2902086A2F65A4
- experiments/BH-001-slow-payer-new-mission.md: 518CA4A70C2F534E4EB2B0E88DF5D45B5AB4DCD94A8D2AB2540B3885227C6936
