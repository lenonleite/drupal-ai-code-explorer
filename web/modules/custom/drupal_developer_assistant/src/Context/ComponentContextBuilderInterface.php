<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Context;

use Drupal\drupal_developer_assistant\Model\ModuleComponentDetails;

/**
 * Builds bounded AI context for one selected Drupal component.
 */
interface ComponentContextBuilderInterface {

  /**
   * Builds component context from locally discovered architecture evidence.
   */
  public function build(ModuleComponentDetails $details): ComponentContext;

}
