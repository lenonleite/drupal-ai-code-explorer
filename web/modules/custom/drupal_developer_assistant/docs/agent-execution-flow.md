# Agent execution flow

This document records what happens after a developer enters a question in AI
Agent Explorer and selects **Run Agent**.

## Browser and Drupal flow

1. `AiAgentExplorerForm` renders the agent, prompt, provider/model, optional
   files, and submit controls.
2. `agent_explorer.js` creates a runner UUID, posts the form to
   `/admin/config/ai/agents/explore/start`, and polls the progress endpoint.
3. `AiAgentExplorerController::runAgent()` loads the selected provider and the
   Drupal Developer Assistant plugin, wraps the prompt in a `Task`, and starts
   its decision loop.
4. The agent configuration supplies the system prompt, five enabled tools,
   per-tool limits, and the eight-loop ceiling.
5. Drupal records each model decision under the runner UUID. Agent Explorer
   renders those records as progress and finally displays the direct result of
   the validation tool.

Opening Agent Explorer from a Drupal Developer Assistant page starts a clean
form and selects this agent. The navigation click does not inspect a module and
does not call the provider; the workflow begins only after **Run Agent**.

## Retrieval and answer flow

```text
Question
  |
  |-- exact module ID supplied -----------------------------|
  |                                                         v
  |-- module ID unknown -> FindModules -> enabled module ID -> InspectModule
                                                               |
                                                               |-- enough architecture evidence
                                                               |       |
                                                               |       v
                                                               |     draft answer
                                                               |
                                                               |-- specific known component
                                                               |       |
                                                               |       v
                                                               |  InspectComponent
                                                               |
                                                               |-- implementation detail is missing
                                                                       |
                                                                       v
                                                              SearchModuleSource
                                                                       |
                                                                       v
                                                                  draft answer
                                                                       |
                                                                       v
                                                         ValidateGroundedAnswer
                                                                       |
                                                                       v
                                                               final direct result
```

The important retrieval order is:

1. `FindModules` runs once only when the question does not include an exact
   enabled module machine name.
2. `InspectModule` is the required first evidence step after identification. It
   builds or reads cached architecture and returns bounded structured context.
3. `InspectComponent` is optional and accepts only a component type and ID
   discovered during module inspection.
4. `SearchModuleSource` is optional. It is the local fallback when a question
   needs method behavior or evidence that the architectural context does not
   contain. It is not an internet search.
5. `ValidateGroundedAnswer` is always the final tool. Its output is returned
   directly and the model must not rewrite it.

The agent does not need to call every tool. For example, the live question
“What enabled module works with cron, and what does it do?” used:

```text
FindModules("cron")
  -> InspectModule("automated_cron")
  -> ValidateGroundedAnswer(...)
```

Source search was correctly skipped because the architecture context already
contained sufficient evidence.

## What each model loop means

A model decision may request one tool. Drupal executes the requested PHP tool
locally and appends its JSON result to the same agent conversation. The next
model decision receives that evidence and chooses whether to retrieve more or
prepare the answer.

A tool call is not another agent. The implementation is one AI agent with
reusable, deterministic Drupal tools. Local PHP execution does not itself spend
provider tokens, but each new model decision normally requires another provider
request.

## Final validation and repair

The agent submits the original module ID, original question, and complete draft
to `ValidateGroundedAnswer`.

The validator checks that:

- cited source paths belong to the inspected module;
- cited symbols and line ranges exist in resolved evidence;
- the required Markdown evidence sections are consistent;
- redacted values are disclosed and are not reconstructed; and
- the answer does not claim unsupported source details.

A valid draft is returned unchanged. An invalid draft can receive one bounded
repair request. The repaired draft is validated again; if it still fails, the
tool returns a safe failure instead of presenting it as validated.

## Main files

- `config/optional/ai_agents.ai_agent.drupal_developer_assistant.yml`
- `src/Plugin/AiFunctionCall/FindModules.php`
- `src/Plugin/AiFunctionCall/InspectModule.php`
- `src/Plugin/AiFunctionCall/InspectComponent.php`
- `src/Plugin/AiFunctionCall/SearchModuleSource.php`
- `src/Plugin/AiFunctionCall/ValidateGroundedAnswer.php`
- `src/Architecture/ModuleArchitectureBuilder.php`
- `src/Context/ModuleContextBuilder.php`
- `src/Retrieval/SourceSnippetRetriever.php`
- `src/Agent/GroundedAnswerValidator.php`
- `src/Agent/GroundedAnswerRepairer.php`
- `src/EventSubscriber/AgentExplorerProgressSubscriber.php`

The Agent Explorer form, JavaScript, controller, and decision entity belong to
the contributed `ai_agents_explorer` module.

