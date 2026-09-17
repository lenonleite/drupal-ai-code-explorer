<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Context\ModuleContextBuilderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a local, read-only preview of module context prepared for AI.
 */
final class ModuleContextController extends ControllerBase {

  /**
   * Constructs a module context controller.
   */
  public function __construct(
    private readonly ModuleArchitectureBuilderInterface $architectureBuilder,
    private readonly ModuleContextBuilderInterface $contextBuilder,
  ) {}

  /**
   * Builds the context preview for one enabled module.
   *
   * @param string $module_id
   *   The enabled module machine name from the route.
   *
   * @return array
   *   A render array containing context selection details and JSON.
   */
  public function preview(string $module_id): array {
    $architecture = $this->architectureBuilder->build($module_id);
    if ($architecture === NULL) {
      throw new NotFoundHttpException(sprintf(
        'Enabled module "%s" was not found.',
        $module_id,
      ));
    }

    $context = $this->contextBuilder->build($architecture);
    $data = $context->jsonSerialize();
    $json = json_encode(
      $context,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    $selection_rows = [];
    foreach ($data['summary']['available'] as $name => $available) {
      $selection_rows[] = [
        str_replace('_', ' ', ucfirst($name)),
        $available,
        $data['summary']['included'][$name],
        $data['summary']['omitted'][$name],
      ];
    }
    $limit_rows = [];
    foreach ($data['summary']['limits'] as $name => $limit) {
      $limit_rows[] = [
        str_replace('_', ' ', ucfirst($name)),
        $limit,
      ];
    }

    return [
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to module overview'),
        '#url' => Url::fromRoute(
          'drupal_developer_assistant.module_overview',
          ['module_id' => $module_id],
        ),
      ],
      'intro' => [
        '#markup' => $this->t('This bounded context is prepared locally from Drupal architecture facts and is returned by the agent’s Inspect Drupal Module tool.'),
      ],
      'agent' => [
        '#type' => 'link',
        '#title' => $this->t('Ask the AI Assistant'),
        '#url' => Url::fromRoute(
          'ai_agents_explorer.explorer',
          [],
          [
            'query' => [
              'agent_id' => 'drupal_developer_assistant',
              'drupal_developer_assistant_new' => 1,
            ],
          ],
        ),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'document' => [
        '#type' => 'table',
        '#caption' => $this->t('Context document'),
        '#header' => [$this->t('Property'), $this->t('Value')],
        '#rows' => [
          [$this->t('Module'), $data['module']['name']],
          [$this->t('Schema version'), $data['schema_version']],
          [$this->t('JSON size (bytes)'), strlen($json)],
          [
            $this->t('Truncated'),
            $data['summary']['truncated']
              ? $this->t('Yes')
              : $this->t('No'),
          ],
          [
            $this->t('Strings shortened'),
            $data['summary']['strings_shortened'],
          ],
        ],
      ],
      'selection' => [
        '#type' => 'table',
        '#caption' => $this->t('Context selection'),
        '#header' => [
          $this->t('Record type'),
          $this->t('Available'),
          $this->t('Included'),
          $this->t('Omitted'),
        ],
        '#rows' => $selection_rows,
      ],
      'limits' => [
        '#type' => 'table',
        '#caption' => $this->t('Applied limits'),
        '#header' => [$this->t('Limit'), $this->t('Maximum')],
        '#rows' => $limit_rows,
      ],
      'json' => [
        '#type' => 'details',
        '#title' => $this->t('JSON document'),
        '#open' => TRUE,
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => Html::escape($json),
        ],
      ],
    ];
  }

}
