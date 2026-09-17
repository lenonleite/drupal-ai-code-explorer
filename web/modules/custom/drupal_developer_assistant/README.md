# Drupal Developer Assistant

Drupal Developer Assistant is a read-only development module for exploring an
installed Drupal codebase and asking grounded questions about enabled modules.

It combines deterministic PHP analysis with Drupal AI Agents. Drupal collects
module metadata, components, PHP structure, relationships, and bounded source
evidence locally. The selected language model explains only that retrieved
evidence, and a final validation tool checks the answer before it is displayed.

## What the module provides

- A report UI for enabled modules, services, plugin managers and plugins,
  entities, routes, controllers, forms, hooks, configuration, and relationships.
- Per-module pages for components, source-file metadata, parsed PHP structure,
  relationships, and the bounded JSON context supplied to AI.
- A Drupal AI Agent named **Drupal Developer Assistant**.
- Five read-only agent tools:

  | Tool | Purpose |
  | --- | --- |
  | `find_modules` | Finds enabled module IDs from short functionality terms. |
  | `inspect_module` | Returns a bounded architectural overview of one module. |
  | `inspect_component` | Narrows the context to one known component. |
  | `search_module_source` | Retrieves focused, redacted PHP source evidence when architectural data is insufficient. |
  | `validate_grounded_answer` | Checks citations and redaction rules and performs at most one repair request. |

The module does not edit content, configuration, users, or source code.

## Requirements

- Drupal 11
- [Drupal AI](https://www.drupal.org/project/ai)
- [AI Agents](https://www.drupal.org/project/ai_agents)
- AI Agents Explorer
- An AI provider and model that support the `chat_with_tools` operation
- `nikic/php-parser` for local PHP structure analysis

This development project uses the OpenAI provider, but the custom module is
written against Drupal AI abstractions rather than the OpenAI SDK directly.

## Installation

For this DDEV project, install the Composer dependencies and enable the modules:

```bash
ddev composer require drupal/ai drupal/ai_agents drupal/ai_provider_openai nikic/php-parser
ddev drush en ai_provider_openai drupal_developer_assistant -y
ddev drush cr
```

Enabling `drupal_developer_assistant` also enables its declared dependencies,
including AI Agents Explorer. On a new installation, Drupal imports the optional
Drupal Developer Assistant agent configuration automatically.

If the module was already enabled before its optional agent configuration was
added, import that configuration once:

```bash
ddev drush config:import --partial \
  --source=/var/www/html/web/modules/custom/drupal_developer_assistant/config/optional \
  -y
ddev drush cr
```

## Provider configuration

The following example uses OpenAI. Another Drupal AI provider can be used if it
supports tool calling.

1. Create a secret in **Configuration > System > Keys**:
   `/admin/config/system/keys`.
2. Select that key in **Configuration > AI > Providers > OpenAI**:
   `/admin/config/ai/providers/openai`.
3. In **Configuration > AI > Settings**, select a default provider and model
   for **Chat with tools**: `/admin/config/ai/settings`.
4. During inexpensive development tests, use a small tool-capable model. This
   project currently uses `gpt-4o-mini`; the model is a site configuration
   choice, not a module requirement.

Never commit an API key to this module or its exported configuration.

## Permissions

Grant permissions at `/admin/people/permissions`:

- **Access Drupal Developer Assistant** allows a role to browse reports and use
  the structured inspection tools.
- **View Drupal Developer Assistant source evidence** additionally allows the
  role and its agent runs to retrieve bounded source excerpts.
- **Use the agent explorer** comes from AI Agents Explorer and is required to
  run the agent in its development UI.

Both module permissions are marked `restrict access: true`, so they should be
granted only to trusted developer or administrator roles.

## UI workflow

Open **Reports > Drupal Developer Assistant** or visit:

```text
/admin/reports/drupal-developer-assistant
```

The normal developer flow is:

1. Open **Modules** and select **Inspect and analyze** for an enabled module.
2. Review its overview and, when useful, browse components, source files, PHP
   structure, and relationships.
3. Open **Preview AI context** to see the bounded JSON document returned by the
   `inspect_module` tool.
4. Select **Ask the AI Assistant**. The link starts a clean Agent Explorer form
   with the Drupal Developer Assistant selected; it does not send an AI request.
5. Enter a question and select **Run Agent**.

You can also start a clean conversation directly at:

```text
/admin/config/ai/agents/explore?agent_id=drupal_developer_assistant&drupal_developer_assistant_new=1
```

The top-level **Catalog** pages are read-only indexes. The **Modules** section is
the primary inspection and AI workflow.

## Agent retrieval flow

```text
Developer question
  -> find enabled module ID when it is not explicit
  -> inspect bounded module architecture
  -> optionally inspect one known component
  -> search source only when implementation evidence is still needed
  -> draft an evidence-based answer
  -> validate and optionally repair once
  -> return the validated answer directly
```

`inspect_module` is the default retrieval step. It returns structured module
metadata, components, source-file metadata, parsed PHP declarations, and
relationships. `search_module_source` is a focused fallback for questions that
need method behavior or details missing from that architectural context. Both
tools read the local Drupal codebase; neither searches the internet.

When the developer supplies an exact module machine name, the agent skips
`find_modules`. Each evidence tool is bounded to prevent repeated or excessively
large requests, and the agent has a maximum of eight decision loops.

See [Agent execution flow](docs/agent-execution-flow.md) for the code-level
sequence and [AI concepts](docs/ai-concepts.md) for the terminology.

## Grounding and source safety

- Context size is limited by components, files, PHP declarations,
  relationships, parameters, and string length.
- Context summaries disclose available, included, and omitted records and
  whether truncation occurred.
- Source retrieval is restricted to files inside the selected enabled module.
- Comments, inline HTML, and PHP string literal values are redacted from source
  snippets before they reach the model.
- Retrieved source is treated as untrusted evidence, never as agent
  instructions.
- Final answers must cite paths, line ranges, and symbols returned by the tools.
- Invalid answers receive at most one repair attempt; an answer that still
  fails validation is not returned as a validated result.

## Cache behavior

Deterministic module architecture is cached in Drupal's
`cache_drupal_developer_assistant_architecture` cache bin. The cache stores the
result of local code analysis, not generated AI answers and not embeddings.

Use **Refresh architecture** on a module overview after changing that module's
source code. A normal Drupal cache rebuild also clears the architecture cache:

```bash
ddev drush cr
```

## Tests and quality checks

The automated test suite does not call an external AI provider.

GitHub Actions runs Composer validation, Drupal coding standards, and the unit,
kernel, and functional suites on pull requests targeting `main` and on every
push to `main`. The same workflow can be started manually from the repository's
**Actions** page. See
[`tests.yml`](../../../../.github/workflows/tests.yml).

```bash
# Unit tests.
ddev exec vendor/bin/phpunit -c web/core \
  web/modules/custom/drupal_developer_assistant/tests/src/Unit

# Kernel tests.
ddev exec env SIMPLETEST_DB=mysql://db:db@db/db \
  vendor/bin/phpunit -c web/core \
  web/modules/custom/drupal_developer_assistant/tests/src/Kernel

# Drupal coding standards.
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice \
  web/modules/custom/drupal_developer_assistant
```

Functional browser tests are in `tests/src/Functional` and require Drupal's
`SIMPLETEST_BASE_URL` and `SIMPLETEST_DB` environment settings.

The YAML cases under `evaluations/agent` describe expected tool paths. The unit
suite validates those paths without spending API credit. A live acceptance
check can then be run manually in Agent Explorer. See
[Agent acceptance checks](docs/agent-acceptance.md).

## Troubleshooting

### The agent is not listed

Import the optional agent configuration, rebuild caches, and confirm that
`ai_agents_explorer` is enabled.

### The agent cannot use source search

Grant **View Drupal Developer Assistant source evidence** to the user running
Agent Explorer. Module inspection alone requires only **Access Drupal Developer
Assistant**.

### The provider times out or cannot connect

This is a provider/network failure, not a collector failure. Retry after
confirming outbound HTTPS access and the provider status. Queue processing is
not part of the current interactive agent workflow.

### The provider reports a rate limit or quota error

Wait before retrying, confirm the provider account has available credit, and
use a smaller tool-capable model for development. Do not create an unbounded
automatic retry loop.

### An answer says evidence was truncated or redacted

That is an intentional safety signal. Ask a narrower question. Source string
values are deliberately unavailable after redaction and must not be guessed.

## Current limitations

- Retrieval is structured and lexical; there are no embeddings or vector
  database.
- Only enabled modules are inspected.
- Source search is limited to parsed PHP declarations and a small number of
  snippets.
- The Agent Explorer flow is interactive and synchronous.
- Generated answers are not cached as durable analysis reports.
- There is one agent with multiple tools, not a multi-agent system.

## Additional documentation

- [Agent execution flow](docs/agent-execution-flow.md)
- [AI concepts](docs/ai-concepts.md)
- [Agent acceptance checks](docs/agent-acceptance.md)
