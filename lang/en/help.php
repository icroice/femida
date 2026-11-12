<?php

return [
    'sections' => [
        'overview' => 'Overview',
        'quick-start' => 'Quick start',
        'case-library' => 'Case library',
        'sessions' => 'Sessions & review',
        'agent' => 'Connecting an agent',
        'roles' => 'Roles & access',
    ],

    'next_section' => 'Next',

    'overview' => [
        'title' => 'How Femida works',
        'lead' => 'Femida keeps the evidence of AI-assisted QA runs: the agent runs your test cases on the base and head commits of a task and uploads a before/after screenshot pair for every step. A human reviews the pairs and gives the verdict.',
        'flow_title' => 'The flow of a session',
        'steps' => [
            [
                'title' => 'Write cases once',
                'text' => 'A reusable library per project: every step is an action plus the expected result.',
                'chips' => [
                    ['text' => 'folders', 'tone' => 'neutral'],
                    ['text' => 'priorities', 'tone' => 'neutral'],
                    ['text' => 'active / archived', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'The agent runs both commits',
                'text' => 'It boots your app from femida.yaml in two git worktrees — base and head — and walks the selected cases in a real browser.',
                'chips' => [
                    ['text' => 'before run', 'tone' => 'neutral'],
                    ['text' => 'after run', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Evidence lands in Femida',
                'text' => 'Each step gets a before/after pair, a status and a note; the agent attaches its suggested verdict.',
                'chips' => [
                    ['text' => 'passed', 'tone' => 'ok'],
                    ['text' => 'skipped', 'tone' => 'warn'],
                    ['text' => 'failed', 'tone' => 'err'],
                ],
            ],
            [
                'title' => 'A human decides',
                'text' => 'Open the session, compare the pairs, press pass / fail / blocked — and paste the markdown summary into the tracker.',
                'chips' => [
                    ['text' => 'pass', 'tone' => 'ok'],
                    ['text' => 'fail', 'tone' => 'err'],
                    ['text' => 'blocked', 'tone' => 'warn'],
                ],
            ],
        ],
        'note_lead' => 'The server is a hub only.',
        'note_text' => 'It never needs access to your repositories or CI: the agent runs on your machine, and the screenshots stay on your disk.',
    ],

    'quick_start' => [
        'title' => 'Quick start',
        'lead' => 'From a fresh instance to the first reviewed session.',
        'steps' => [
            [
                'title' => 'Create a project',
                'text' => 'The instance admin creates the project and adds members with roles in the “Users” section.',
                'chips' => [
                    ['text' => 'admin', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Fill the case library',
                'text' => 'Write cases by hand — or point your agent at the project and let it draft them from your documentation.',
                'chips' => [
                    ['text' => 'action + expected', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Issue an API key',
                'text' => 'Project settings → API keys. The key is shown once and starts with fem_; personal keys live in your profile.',
                'chips' => [
                    ['text' => 'fem_…', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Run the agent',
                'text' => 'Give it FEMIDA_URL and FEMIDA_KEY and ask it to test and record: it reads femida.yaml from the commit under test, runs both sides and uploads the evidence.',
                'chips' => [
                    ['text' => 'REST', 'tone' => 'neutral'],
                    ['text' => 'MCP', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Review the session',
                'text' => 'Compare the before/after pairs, set the verdicts and copy the markdown summary into the tracker task.',
                'chips' => [
                    ['text' => 'pass', 'tone' => 'ok'],
                    ['text' => 'fail', 'tone' => 'err'],
                    ['text' => 'blocked', 'tone' => 'warn'],
                ],
            ],
        ],
        'note_lead' => 'Onboarding in one request.',
        'note_text' => 'The femida://onboarding resource (readable with the project key) contains ready skill skeletons, driver scripts and a femida.yaml reference.',
    ],

    'case_library' => [
        'title' => 'Case library',
        'lead' => 'Cases are the reusable unit of QA in Femida: written once, run on every task that touches the area.',
        'fields_title' => 'What a case consists of',
        'fields' => [
            ['name' => 'Title', 'text' => 'One line: what exactly is being checked.'],
            ['name' => 'Folder', 'text' => 'Grouping for navigation, up to two levels — for example “cart” or “checkout / promo”.'],
            ['name' => 'Priority', 'text' => 'P0 (critical) to P3 (cosmetic): what to look at first when the run fails.'],
            ['name' => 'Preconditions', 'text' => 'The state the agent may assume before starting — “the cart is empty”.'],
            ['name' => 'Steps', 'text' => 'An ordered list of actions, each with the expected result — this is what the agent walks.'],
        ],
        'note_lead' => 'Anyone on the project writes cases.',
        'note_text' => 'Every member can create and edit cases; moving a case to “archived” (it stops getting into new sessions) is a developer-level action.',
    ],

    'sessions' => [
        'title' => 'Sessions & review',
        'lead' => 'A session is the evidence of one task: the selected cases run on the base and head commits, reviewed by a human.',
        'flow_title' => 'The life of a session',
        'steps' => [
            [
                'title' => 'The agent creates the session',
                'text' => 'Task reference, title, base and head commits. The app under test boots from femida.yaml in two git worktrees.',
                'chips' => [
                    ['text' => 'before', 'tone' => 'neutral'],
                    ['text' => 'after', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Runs fill in the steps',
                'text' => 'Every step gets a status — passed, failed, skipped or error — with a screenshot and a note; a failed step can be re-taken while the run is open.',
                'chips' => [
                    ['text' => 'passed', 'tone' => 'ok'],
                    ['text' => 'skipped', 'tone' => 'warn'],
                    ['text' => 'failed', 'tone' => 'err'],
                    ['text' => 'error', 'tone' => 'err'],
                ],
            ],
            [
                'title' => 'A human reviews the pairs',
                'text' => 'Before and after side by side, the agent’s suggestion next to them. The verdict describes the head commit; the base run is the baseline.',
                'chips' => [
                    ['text' => 'pass', 'tone' => 'ok'],
                    ['text' => 'fail', 'tone' => 'err'],
                    ['text' => 'blocked', 'tone' => 'warn'],
                ],
            ],
            [
                'title' => 'The result is computed',
                'text' => 'The session status follows the verdicts. A markdown summary is ready to paste into the tracker task.',
                'chips' => [
                    ['text' => 'passed / not passed', 'tone' => 'neutral'],
                ],
            ],
        ],
        'note_lead' => 'Review is iterative.',
        'note_text' => 'Any member can add a case to a session — a finished session returns to review — or send a case for re-verification by resetting its results.',
    ],

    'agent' => [
        'title' => 'Connecting an agent',
        'lead' => 'The agent works through the public contract: a project API key, the REST API and the MCP server.',
        'rows_title' => 'The pieces',
        'rows' => [
            [
                'title' => 'API key',
                'text' => 'One Bearer key for REST /api/v1 and MCP /mcp. Project keys are issued in project settings, personal ones in your profile; the value is shown once.',
            ],
            [
                'title' => 'MCP tools',
                'text' => 'Fourteen tools with the femida- prefix: cases, sessions, runs, steps — the same rules as the UI, the same key.',
            ],
            [
                'title' => 'femida://onboarding',
                'text' => 'The MCP resource for a first-time agent: skill skeletons femida-write-cases and femida-run-session, the femida.mjs client and drive.mjs Playwright driver, a femida.yaml reference.',
            ],
            [
                'title' => 'femida.yaml',
                'text' => 'Lives in the tested repository and describes how to boot the app: commands, port, readiness check. The agent reads it from the commit under test.',
            ],
        ],
        'note_lead' => 'The agent runs locally.',
        'note_text' => 'It checks out git worktrees and drives a browser on your machine; the Femida server never sees your repositories or CI.',
    ],

    'roles' => [
        'title' => 'Roles & access',
        'lead' => 'Two levels: the instance (admin) and the project (owner, developer, tester).',
        'rows_title' => 'Who can do what',
        'rows' => [
            [
                'title' => 'Admin',
                'text' => 'Creates projects and users, manages the keys of every project, sees all projects.',
                'chips' => [
                    ['text' => 'projects', 'tone' => 'neutral'],
                    ['text' => 'users', 'tone' => 'neutral'],
                    ['text' => 'all keys', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Owner',
                'text' => 'A project developer who also manages it: project settings and the team. Adds developers and testers; appointing owners and resetting passwords is admin-only.',
                'chips' => [
                    ['text' => 'project settings', 'tone' => 'neutral'],
                    ['text' => 'team', 'tone' => 'neutral'],
                    ['text' => "a developer's rights", 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Developer',
                'text' => 'Full case library including archiving, drives sessions — creates them, resets them — and gives verdicts.',
                'chips' => [
                    ['text' => 'cases', 'tone' => 'neutral'],
                    ['text' => 'sessions', 'tone' => 'neutral'],
                    ['text' => 'verdicts', 'tone' => 'neutral'],
                ],
            ],
            [
                'title' => 'Tester',
                'text' => 'Edits test cases, adds cases to sessions, sends them for re-verification and gives verdicts.',
                'chips' => [
                    ['text' => 'edit cases', 'tone' => 'neutral'],
                    ['text' => 'attach cases', 'tone' => 'neutral'],
                    ['text' => 'verdicts', 'tone' => 'neutral'],
                ],
            ],
        ],
        'note_lead' => 'Verdicts belong to humans.',
        'note_text' => 'Any project member can set a verdict — the agent only suggests.',
    ],
];
