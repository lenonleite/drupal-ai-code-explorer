# AI concepts used by Drupal Developer Assistant

Drupal Developer Assistant combines deterministic Drupal code analysis with
probabilistic language-model generation. Drupal discovers, bounds, and validates
the evidence; the model chooses retrieval tools and explains the evidence.

## Current workflow

```text
Developer question
  -> agent instructions and tool definitions
  -> module discovery when the ID is unknown
  -> structured module inspection
  -> optional component inspection
  -> optional focused source retrieval
  -> grounded draft
  -> deterministic validation
  -> zero or one repair request
  -> final answer
```

## Prompt engineering

Prompt engineering is the design of the model's instructions. The exported
agent configuration tells the model when to use each tool, forbids unsupported
claims, defines the citation format, limits tool repetition, and requires final
validation.

See
[`ai_agents.ai_agent.drupal_developer_assistant.yml`](../config/optional/ai_agents.ai_agent.drupal_developer_assistant.yml).

## Context engineering

Context engineering decides what information the model receives, how it is
represented, and how much is included. Sending the entire Drupal installation
would be expensive and would hide relevant facts inside noise.

`ModuleContextBuilder` instead produces a bounded JSON document containing:

- module metadata and dependencies;
- architecture components;
- source-file metadata;
- parsed PHP classes, interfaces, traits, methods, functions, and parameters;
- explicit relationships; and
- available, included, and omitted counts.

See [`ModuleContextBuilder.php`](../src/Context/ModuleContextBuilder.php) and
[`ModuleContextLimits.php`](../src/Context/ModuleContextLimits.php).

## AI agent

An AI agent combines a model, instructions, tools, and a decision loop. The
model reads the question, selects a registered tool, reads the returned result,
and decides whether it has enough evidence.

This project uses one agent with five tools. Tool calls are local PHP operations,
not other agents. The eight-loop ceiling limits cost and prevents an accidental
infinite cycle.

## Function calling

Function calling lets the model request a named operation with structured
arguments. The model cannot execute arbitrary PHP. Drupal validates the
arguments, checks access, invokes the registered plugin, and returns JSON.

For an unknown module, the first call can be:

```json
{
  "name": "drupal_developer_assistant_find_modules",
  "arguments": {
    "query": "cron"
  }
}
```

After receiving an enabled ID, the agent inspects it:

```json
{
  "name": "drupal_developer_assistant_inspect_module",
  "arguments": {
    "module_id": "automated_cron"
  }
}
```

See [`FindModules.php`](../src/Plugin/AiFunctionCall/FindModules.php) and
[`InspectModule.php`](../src/Plugin/AiFunctionCall/InspectModule.php).

## Retrieval-Augmented Generation (RAG)

RAG means retrieving external evidence and supplying it to a model before it
generates an answer. “External” here means outside the model's trained weights;
the evidence still comes from the local Drupal installation.

The module uses structured, on-demand RAG:

```text
question
  -> enabled module discovery
  -> cached or newly built architecture
  -> optional targeted source snippets
  -> bounded evidence supplied to the model
```

It does not currently use embeddings or a vector database.

## Structured retrieval

Structured retrieval selects known Drupal facts using deterministic identifiers
and relationships. Examples include:

- finding an enabled module by ID, label, description, dependencies, or path;
- selecting all services or routes owned by a module;
- selecting a component by its exact type and ID; and
- following relationships such as `depends_on` or `implemented_by`.

`inspect_module` is the primary structured retrieval tool. It provides the
architectural map before the model asks for lower-level source.

See
[`ModuleArchitectureBuilder.php`](../src/Architecture/ModuleArchitectureBuilder.php).

## Lexical source retrieval

`search_module_source` is a focused local fallback. It tokenizes a developer
question and ranks parsed PHP declarations. Declaration-name matches have more
weight than matches in containing symbols, paths, types, or parameters.

Retrieval is limited to a small number of snippets safely resolved inside the
selected module. Comments, inline HTML, and PHP string literal values are
redacted before the snippets reach the model. If no declaration matches, the
tool reports its bounded production-code fallback.

Lexical retrieval is not semantic similarity and does not require embeddings.

See [`SourceSnippetRetriever.php`](../src/Retrieval/SourceSnippetRetriever.php).

## Grounding

Grounding restricts an answer to supplied, verifiable evidence. The agent must
inspect the module, cite tool-returned paths, line ranges, and symbols, and
disclose missing, truncated, fallback, or redacted evidence.

Grounding reduces hallucinations, but a prompt alone cannot guarantee a correct
answer. That is why the workflow also uses deterministic evidence validation
and repeatable acceptance cases.

## Evidence validation

The model's complete draft must pass through `validate_grounded_answer`. PHP
resolves the cited evidence and checks paths, symbols, line ranges, required
headings, and redaction disclosure.

This is different from asking the model “Are you sure?” The validator checks
claims against local evidence rather than trusting the model's self-assessment.

See
[`ValidateGroundedAnswer.php`](../src/Plugin/AiFunctionCall/ValidateGroundedAnswer.php),
[`GroundedAnswerValidator.php`](../src/Agent/GroundedAnswerValidator.php), and
[`ModuleEvidenceResolver.php`](../src/Evidence/ModuleEvidenceResolver.php).

## Repair requests

If a draft fails validation, the repairer sends the draft, validation problems,
and evidence back through the configured Drupal AI provider. The repair response
is parsed and validated again.

Repair is bounded to one request. The application does not repeatedly ask until
it happens to receive a valid response.

See [`GroundedAnswerRepairer.php`](../src/Agent/GroundedAnswerRepairer.php),
[`GroundedAnswerRepairInputBuilder.php`](../src/Agent/GroundedAnswerRepairInputBuilder.php),
and
[`GroundedAnswerRepairResponseParser.php`](../src/Agent/GroundedAnswerRepairResponseParser.php).

## Bounded context and truncation

Models have finite context windows and token-based cost. The module limits the
number of components, files, symbols, properties, methods, functions,
parameters, relationships, snippets, and string bytes supplied to the model.

The context reports `available`, `included`, and `omitted` counts plus a
`truncated` flag. The model and developer can therefore see when the evidence is
incomplete instead of silently assuming it is exhaustive.

## Caching

Architecture analysis is deterministic but can be expensive. Drupal stores one
completed architecture per module in
`cache_drupal_developer_assistant_architecture`.

This is a normal Drupal database cache:

```text
exact module ID -> exact architecture report
```

It is not semantic search, an answer cache, or a vector database. The UI's
**Refresh architecture** action invalidates one module after source changes.

See [`ModuleArchitectureCache.php`](../src/Architecture/ModuleArchitectureCache.php).

## Access control and read-only tools

Tools check Drupal permissions before returning evidence. General architecture
requires `access drupal developer assistant`; source retrieval additionally
requires `view drupal developer assistant source`.

All custom tools are read-only. They do not change content, configuration,
users, or files.

## Provider abstraction and model routing

The agent uses Drupal AI's provider API. The site administrator chooses a
provider and a default model for `chat_with_tools`; the custom tools do not
depend directly on one provider SDK.

Different model decisions may create multiple API requests during one agent
run. Local collector and tool execution does not itself consume provider tokens.
Provider-specific features such as moderation can add separate requests.

## Evaluations

Evaluations, or evals, are repeatable cases for checking agent behavior. The
YAML files under `evaluations/agent` record representative questions, expected
tool order, required answer headings, and required evidence paths.

The automated suite validates configuration and tool paths without calling an
external model. Live smoke checks verify the model's actual choices.

See [Agent acceptance checks](agent-acceptance.md).

## Database cache versus vector retrieval

Current retrieval uses exact structure and lexical matches:

```text
module ID -> architecture cache -> lexical source search
```

A future vector design would add:

```text
source chunks -> embeddings -> vector store
question -> embedding -> similarity search -> relevant chunks
```

Vector retrieval could find conceptually similar code with different wording,
but it would add indexing, freshness, storage, embedding cost, and relevance
evaluation. It should be introduced only if structured and lexical retrieval
prove insufficient on larger codebases.

## Concepts not currently implemented

The project does not currently use:

- source-code embeddings;
- a vector database;
- semantic similarity search;
- fine-tuning;
- long-term conversational memory;
- multi-agent orchestration;
- agent-to-agent handoffs;
- background queues for interactive agent runs; or
- durable caching of generated answers.

