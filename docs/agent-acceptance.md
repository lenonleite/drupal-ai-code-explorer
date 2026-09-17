# Agent acceptance checks

The YAML files in `evaluations/agent` describe representative developer
questions and their expected bounded tool paths.

| Case | Purpose | Expected tool path |
| --- | --- | --- |
| `cron_module_discovery` | Discover an enabled module from functionality words | Find modules, inspect module, validate answer |
| `automated_cron_overview` | Explain a known module at architecture level | Inspect module, validate answer |
| `automated_cron_request_execution` | Explain implementation behavior | Inspect module, search source, validate answer |
| `automated_cron_subscriber_component` | Explain a specific known component | Inspect module, inspect component, search source, validate answer |

These paths intentionally demonstrate that source search is a fallback, not a
mandatory step. Architecture questions should stop after `inspect_module` when
its evidence is sufficient. Implementation questions can add one focused
`search_module_source` call.

## Automated checks

`AgentAcceptanceDatasetTest` verifies that:

- every required tool is enabled in the agent configuration;
- discovery runs only when the module ID is unknown;
- module inspection precedes narrower evidence tools;
- final validation is always last;
- evidence tools are not repeated; and
- each path fits within the configured loop limit.

Function-call kernel tests verify tool behavior and permission checks without
contacting an external provider. Unit and kernel tests therefore do not spend
API credit.

## Live smoke check

Open AI Agent Explorer, choose **Drupal Developer Assistant**, and run one of
the evaluation questions. Confirm that:

1. The visible progress labels match the expected path.
2. The agent uses only enabled module IDs returned by discovery.
3. Source search runs only when the question needs more detail than the
   structured architecture contains.
4. The final progress stage is **Agent validates the final answer and repairs
   it if needed**.
5. The final answer cites one of the required paths from the case.

A live check uses the configured AI provider and can make multiple model
requests. Run a single case first with a small tool-capable model.

The discovery case was manually verified with this path:

```text
drupal_developer_assistant_find_modules(query: cron)
  -> drupal_developer_assistant_inspect_module(module_id: automated_cron)
  -> drupal_developer_assistant_validate_grounded_answer(...)
```
