<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Evidence\ModuleEvidenceResolverInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Displays a bounded source preview for one validated evidence reference.
 */
final class ModuleEvidenceController extends ControllerBase {

  /**
   * Constructs a module evidence controller.
   */
  public function __construct(
    private readonly ModuleArchitectureBuilderInterface $architectureBuilder,
    private readonly ModuleEvidenceResolverInterface $evidenceResolver,
  ) {}

  /**
   * Displays source evidence resolved from an opaque identifier.
   */
  public function view(string $module_id, string $evidence_id): array {
    $architecture = $this->architectureBuilder->build($module_id);
    if ($architecture === NULL) {
      throw new NotFoundHttpException(sprintf(
        'Enabled module "%s" was not found.',
        $module_id,
      ));
    }
    $evidence = $this->evidenceResolver->resolve(
      $architecture,
      $evidence_id,
    );
    if ($evidence === NULL) {
      throw new NotFoundHttpException(
        'The requested evidence reference was not found in this module.',
      );
    }

    $rows = [
      [$this->t('Module'), $architecture->module->label],
      [$this->t('Source path'), $evidence->sourcePath],
      [
        $this->t('Symbol'),
        $evidence->symbol === '' ? $this->t('File-level evidence') : $evidence->symbol,
      ],
      [$this->t('Kind'), $evidence->kind],
      [$this->t('File type'), $evidence->fileType],
      [$this->t('Category'), $evidence->category],
      [
        $this->t('Displayed lines'),
        $evidence->startLine === NULL
          ? $this->t('Preview unavailable')
          : sprintf('%d–%d', $evidence->startLine, $evidence->endLine),
      ],
      [$this->t('Truncated'), $evidence->truncated ? $this->t('Yes') : $this->t('No')],
    ];

    $build = [
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to AI context'),
        '#url' => Url::fromRoute(
          'drupal_developer_assistant.module_context',
          ['module_id' => $module_id],
        ),
      ],
      'intro' => [
        '#markup' => $this->t('This read-only reference was resolved from the module files and symbols discovered by Drupal Developer Assistant.'),
      ],
      'metadata' => [
        '#type' => 'table',
        '#caption' => $this->t('Evidence details'),
        '#header' => [$this->t('Property'), $this->t('Value')],
        '#rows' => $rows,
      ],
    ];
    if ($evidence->content === NULL) {
      $build['preview_unavailable'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'message' => [
          '#markup' => $this->t('A source preview is unavailable for this file type or file size.'),
        ],
      ];
    }
    else {
      $build['source'] = [
        '#type' => 'details',
        '#title' => $this->t('Bounded source preview'),
        '#open' => TRUE,
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => Html::escape($evidence->content),
        ],
      ];
    }

    return $build;
  }

}
