Experiment: BH-001
Experiment version: Draft; scenario SHA-256 ci-dessous
Run: run-002
Run status: IN_PROGRESS
Current phase: COMMITTEE
Next work item: Review Committee — analyser le manifest, O01, les 24 résultats bruts et devil-advocate.md
Last checkpoint commit: d466aa3 (checkpoint précédent); ce checkpoint se résout avec git log pour ce chemin
Date: 2026-09-09 (Europe/Paris); démarré 2026-09-08 22:16:38 UTC
Orchestrator model: GPT-5 (identifiant exact déclaré par la session; configuration exacte Unknown)
Persona model: gpt-5.6-luna
Atlas model: gpt-6-astra
Model configuration: Persona reasoning effort medium; Atlas reasoning effort high; Devil's Advocate gpt-5.6-luna reasoning effort high; autres paramètres Unknown
Protocol version: Draft; SHA-256 6BF0B2789A7FBB1426A001D65627D3EA299571E82C4369A14D8B5AA8C7E44A0E
Persona version: SHA-256 8CD566A6140C6B5AD2FD7FC373BD7D5B1D2C6D5531C454C84A2902086A2F65A4
Prompt version: Draft; SHA-256 875E415221D37542B93766F9FFAF36C65A2DD7C782FEAB2BCEE9E2AA78DF7A2D
Scenario hash or commit: SHA-256 518CA4A70C2F534E4EB2B0E88DF5D45B5AB4DCD94A8D2AB2540B3885227C6936; initial commit 29391983d83ca0a8d2b71ec0d693f6fd8456316e
Executions planned: 24 (P01–P06 × H01–H04)
Executions completed: 24
Executions invalid: 0 canonical units (29 attempts invalidated; audit ci-dessous)
Observable states planned: 1
Observable states completed: 1
Delegation explicitly authorized: YES
Experiment modified during run: NO
Synthetic Evidence: YES
Market Evidence: NO

## Progress

| Unit | Phase | Status | Artifact |
|---|---|---|---|
| P01-H01 | POST_ATLAS | COMPLETED | raw/P01-H01.md |
| P01-H02 | POST_ATLAS | COMPLETED | raw/P01-H02.md |
| P01-H03 | POST_ATLAS | COMPLETED | raw/P01-H03.md |
| P01-H04 | POST_ATLAS | COMPLETED | raw/P01-H04.md |
| P02-H01 | POST_ATLAS | COMPLETED | raw/P02-H01.md |
| P02-H02 | POST_ATLAS | COMPLETED | raw/P02-H02.md |
| P02-H03 | POST_ATLAS | COMPLETED | raw/P02-H03.md |
| P02-H04 | POST_ATLAS | COMPLETED | raw/P02-H04.md |
| P03-H01 | POST_ATLAS | COMPLETED | raw/P03-H01.md |
| P03-H02 | POST_ATLAS | COMPLETED | raw/P03-H02.md |
| P03-H03 | POST_ATLAS | COMPLETED | raw/P03-H03.md |
| P03-H04 | POST_ATLAS | COMPLETED | raw/P03-H04.md |
| P04-H01 | POST_ATLAS | COMPLETED | raw/P04-H01.md |
| P04-H02 | POST_ATLAS | COMPLETED | raw/P04-H02.md |
| P04-H03 | POST_ATLAS | COMPLETED | raw/P04-H03.md |
| P04-H04 | POST_ATLAS | COMPLETED | raw/P04-H04.md |
| P05-H01 | POST_ATLAS | COMPLETED | raw/P05-H01.md |
| P05-H02 | POST_ATLAS | COMPLETED | raw/P05-H02.md |
| P05-H03 | POST_ATLAS | COMPLETED | raw/P05-H03.md |
| P05-H04 | POST_ATLAS | COMPLETED | raw/P05-H04.md |
| P06-H01 | POST_ATLAS | COMPLETED | raw/P06-H01.md |
| P06-H02 | POST_ATLAS | COMPLETED | raw/P06-H02.md |
| P06-H03 | POST_ATLAS | COMPLETED | raw/P06-H03.md |
| P06-H04 | POST_ATLAS | COMPLETED | raw/P06-H04.md |
| O01 | ATLAS | COMPLETED | atlas/O01.md |
| Devil's Advocate | ADVERSARIAL_REVIEW | COMPLETED | devil-advocate.md |
| Review Committee | COMMITTEE | PENDING | - |

Les vingt-quatre unités ont leur Pre-Atlas figé et ont terminé Post-Atlas. Tous les résultats bruts sont maintenant figés.

## Audit de reprise

- Le run a commencé avant l'ajout du protocole multi-tours. La demande explicite de reprise applique le schéma de checkpoint courant sans modifier scénario, personas, Hidden States ou critères.
- L'interruption précédente n'avait produit aucun checkpoint. Neuf sorties sans artefact final fiable ont été invalidées avant reprise: P01-H01 (1), P01-H02 (2), P01-H03 (1), P01-H04 (1), P02-H01 (2), P02-H02 (1), P02-H03 (1).
- P02-H04 avait un artefact Pre-Atlas fiable. Seules ses métadonnées et sa marque de gel ont été ajoutées; son contenu Pre-Atlas est inchangé.
- Ce lot a rejoué P01-H01 à P01-H04 dans quatre contextes neufs et indépendants.
- Le lot suivant a produit P02-H01, P02-H02, P02-H03 et P03-H01 dans quatre contextes neufs et indépendants.
- Ce lot a produit P03-H02, P03-H03, P03-H04 et P04-H01 dans quatre contextes indépendants. La première tentative P03-H03 a été invalidée pour omission de la contrainte privée, puis rejouée dans un contexte neuf.
- Ce lot a produit P04-H02, P04-H03, P04-H04 et P05-H01 dans quatre contextes neufs et indépendants.
- Ce lot a produit P05-H02, P05-H03, P05-H04 et P06-H01 dans quatre contextes neufs et indépendants.
- Le dernier lot Pre-Atlas a produit P06-H02, P06-H03 et P06-H04. La première tentative P06-H02 a été invalidée pour confusion d'identité et hypothèses non fournies, puis rejouée dans un contexte neuf.
- O01 regroupe les 24 simulations, dont les données Visible to Atlas sont strictement identiques. Une seule recommandation a été produite dans un contexte Atlas vierge, sans Hidden State, puis figée dans atlas/O01.md.
- Le premier lot Post-Atlas a complété P01-H01 à P01-H04 dans quatre contextes indépendants. Deux tentatives P01-H02 ont été invalidées (complaisance puis langage méta) et une tentative P01-H03 a été interrompue et invalidée pour prompt malformé; chaque unité concernée a été rejouée dans un contexte neuf.
- Le deuxième lot Post-Atlas a complété P02-H01 à P02-H04 dans quatre contextes indépendants. Une première tentative P02-H02 a été invalidée pour contradiction interne, puis rejouée dans un contexte neuf.
- Le troisième lot Post-Atlas a complété P03-H01 à P03-H04 dans quatre contextes indépendants. Deux tentatives P03-H02 ont été invalidées (langage méta puis donnée fournie déclarée manquante), puis rejouées dans des contextes neufs.
- Le quatrième lot Post-Atlas a complété P04-H01 à P04-H04 dans quatre contextes indépendants. Les premières tentatives P04-H02 et P04-H03 ont été invalidées pour données faussement manquantes ou incohérence avec leur baseline, puis rejouées dans des contextes neufs.
- Le cinquième lot Post-Atlas a d'abord été entièrement invalidé : un contexte P05-H01 a ouvert et complété les quatre variantes, contaminant l'isolation du lot. Les quatre unités ont été restaurées depuis le checkpoint Pre-Atlas, puis rejouées dans quatre contextes sans accès fichier. Trois tentatives supplémentaires P05-H02 ont été invalidées pour donnée fournie déclarée manquante, réponse à la troisième personne puis incohérence décision/score.
- Le dernier lot Post-Atlas a complété P06-H01 à P06-H04 dans quatre contextes sans accès fichier. Deux tentatives P06-H02 ont été invalidées pour troisième personne puis incohérence YES/NO; une tentative P06-H04 a été invalidée pour rubrique absente et donnée fournie déclarée manquante. Chaque unité a été rejouée dans un contexte neuf.
- Le Devil's Advocate a analysé le manifest, O01 et les 24 résultats bruts figés dans un contexte distinct. Son rapport a été figé sans rescoring des résultats bruts.

## Sources

- RUN_PROTOCOL.md: 6BF0B2789A7FBB1426A001D65627D3EA299571E82C4369A14D8B5AA8C7E44A0E
- RUN_PROMPT.md: 875E415221D37542B93766F9FFAF36C65A2DD7C782FEAB2BCEE9E2AA78DF7A2D
- AGENT_PROMPTS.md: AD683A274798F43458E3DB7F43FF9793ECB3BB5F074B5398ADA9318C38E6336D
- personas.md: 8CD566A6140C6B5AD2FD7FC373BD7D5B1D2C6D5531C454C84A2902086A2F65A4
- experiments/BH-001-slow-payer-new-mission.md: 518CA4A70C2F534E4EB2B0E88DF5D45B5AB4DCD94A8D2AB2540B3885227C6936
