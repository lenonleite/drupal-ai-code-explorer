# Drupal AI Code Explorer

A Drupal 11 learning project for exploring code architecture with AI agents,
grounded source retrieval, tool calling, and evidence validation.

The project contains the custom **Drupal Developer Assistant** module. It can
inventory enabled Drupal modules and their services, plugins, entities, routes,
controllers, forms, hooks, configuration, PHP structure, and relationships. A
read-only AI agent can then retrieve bounded local evidence and explain the
codebase without sending the entire project to the model.

## AI concepts explored

- AI agents and function calling
- Context engineering
- Structured and lexical RAG
- Grounding and source citations
- Evidence validation and bounded repair
- Context limits and cost control
- Drupal cache and provider abstractions
- Agent evaluations

## Project documentation

The complete installation, configuration, UI, testing, and troubleshooting
guide is in the
[Drupal Developer Assistant README](web/modules/custom/drupal_developer_assistant/README.md).

Additional documentation:

- [Agent execution flow](web/modules/custom/drupal_developer_assistant/docs/agent-execution-flow.md)
- [AI concepts](web/modules/custom/drupal_developer_assistant/docs/ai-concepts.md)
- [Agent acceptance checks](web/modules/custom/drupal_developer_assistant/docs/agent-acceptance.md)

## Development environment

The repository uses DDEV:

```bash
ddev start
ddev composer install
```

API credentials are managed through Drupal's Key module and must never be
committed to this repository.

