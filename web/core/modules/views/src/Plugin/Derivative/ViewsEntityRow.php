<?php

namespace Drupal\views\Plugin\Derivative;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides views row plugin definitions for all non-special entity types.
 *
 * @ingroup views_row_plugins
 *
 * @see \Drupal\views\Plugin\views\row\EntityRow
 */
class ViewsEntityRow implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Stores all entity row plugin information.
   *
   * @var array
   */
  protected $derivatives = [];

  /**
   * The base plugin ID that the derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The views data service.
   *
   * @var \Drupal\views\ViewsData
   */
  protected $viewsData;

  /**
   * Constructs a ViewsEntityRow object.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\views\ViewsData $views_data
   *   The views data service.
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager, ViewsData $views_data) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
    $this->viewsData = $views_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager'),
      $container->get('views.views_data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    if (!empty($this->derivatives) && !empty($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
    $this->getDerivativeDefinitions($base_plugin_definition);
    return $this->derivatives[$derivative_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Just add support for entity types which have a views integration.
      if (($base_table = $entity_type->getBaseTable()) && $this->viewsData->get($base_table) && $this->entityTypeManager->hasHandler($entity_type_id, 'view_builder')) {
        $this->derivatives[$entity_type_id] = [
          'id' => 'entity:' . $entity_type_id,
          'provider' => 'views',
          'title' => $this->t('@label view mode', ['@label' => $entity_type->getLabel()]),
          'help' => $this->t('Display the @label', ['@label' => $entity_type->getLabel()]),
          'base' => [$entity_type->getDataTable() ?: $entity_type->getBaseTable()],
          'entity_type' => $entity_type_id,
          'display_types' => ['normal'],
          'class' => $base_plugin_definition['class'],
        ];
      }
    }

    return $this->derivatives;
  }

}
