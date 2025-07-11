<?php

namespace Drupal\Core\Layout;

use Drupal\Component\Plugin\Discovery\AttributeBridgeDecorator;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\AttributeDiscoveryWithAnnotations;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscoveryDecorator;
use Drupal\Core\Layout\Attribute\Layout;
use Drupal\Core\Plugin\FilteredPluginManagerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a plugin manager for layouts.
 */
class LayoutPluginManager extends DefaultPluginManager implements LayoutPluginManagerInterface {

  use FilteredPluginManagerTrait;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * LayoutPluginManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler) {
    parent::__construct('Plugin/Layout', $namespaces, $module_handler, LayoutInterface::class, Layout::class, 'Drupal\Core\Layout\Annotation\Layout');
    $this->themeHandler = $theme_handler;

    $type = $this->getType();
    $this->setCacheBackend($cache_backend, $type);
    $this->alterInfo($type);
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'layout';
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists($provider) {
    return $this->moduleHandler->moduleExists($provider) || $this->themeHandler->themeExists($provider);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $discovery = new AttributeDiscoveryWithAnnotations($this->subdir, $this->namespaces, $this->pluginDefinitionAttributeName, $this->pluginDefinitionAnnotationName, $this->additionalAnnotationNamespaces);
      $discovery = new YamlDiscoveryDecorator($discovery, 'layouts', $this->moduleHandler->getModuleDirectories() + $this->themeHandler->getThemeDirectories());
      $discovery
        ->addTranslatableProperty('label')
        ->addTranslatableProperty('description')
        ->addTranslatableProperty('category');
      $discovery = new AttributeBridgeDecorator($discovery, $this->pluginDefinitionAttributeName);
      $discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
      $this->discovery = $discovery;
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    if (!$definition instanceof LayoutDefinition) {
      throw new InvalidPluginDefinitionException($plugin_id, sprintf('The "%s" layout definition must extend %s', $plugin_id, LayoutDefinition::class));
    }

    // Add the module or theme path to the 'path'.
    $provider = $definition->getProvider();
    if ($this->moduleHandler->moduleExists($provider)) {
      $base_path = $this->moduleHandler->getModule($provider)->getPath();
    }
    elseif ($this->themeHandler->themeExists($provider)) {
      $base_path = $this->themeHandler->getTheme($provider)->getPath();
    }
    else {
      $base_path = '';
    }

    $path = $definition->getPath();
    $path = !empty($path) ? $base_path . '/' . $path : $base_path;
    $definition->setPath($path);

    // Add the base path to the icon path.
    if ($icon_path = $definition->getIconPath()) {
      $definition->setIconPath($path . '/' . $icon_path);
    }

    // Add a dependency on the provider of the library.
    if ($library = $definition->getLibrary()) {
      $config_dependencies = $definition->getConfigDependencies();
      [$library_provider] = explode('/', $library, 2);
      if ($this->moduleHandler->moduleExists($library_provider)) {
        $config_dependencies['module'][] = $library_provider;
      }
      elseif ($this->themeHandler->themeExists($library_provider)) {
        $config_dependencies['theme'][] = $library_provider;
      }
      $definition->setConfigDependencies($config_dependencies);
    }

    // If 'template' is set, then we'll derive 'template_path' and 'theme_hook'.
    $template = $definition->getTemplate();
    if (!empty($template)) {
      $template_parts = explode('/', $template);

      $template = array_pop($template_parts);
      $template_path = $path;
      if (count($template_parts) > 0) {
        $template_path .= '/' . implode('/', $template_parts);
      }
      $definition->setTemplate($template);
      $definition->setThemeHook(strtr($template, '-', '_'));
      $definition->setTemplatePath($template_path);
    }

    if (!$definition->getDefaultRegion()) {
      $definition->setDefaultRegion(key($definition->getRegions()));
    }
    // Makes sure region names are translatable.
    $regions = array_map(function ($region) {
      if (!$region['label'] instanceof TranslatableMarkup) {
        // Region labels from YAML discovery needs translation.
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $region['label'] = new TranslatableMarkup($region['label'], [], ['context' => 'layout_region']);
      }
      return $region;
    }, $definition->getRegions());
    $definition->setRegions($regions);
  }

  /**
   * {@inheritdoc}
   */
  public function getThemeImplementations() {
    $hooks = [];
    $hooks['layout'] = [
      'render element' => 'content',
    ];
    /** @var \Drupal\Core\Layout\LayoutDefinition[] $definitions */
    $definitions = $this->getDefinitions();
    foreach ($definitions as $definition) {
      if ($template = $definition->getTemplate()) {
        $hooks[$definition->getThemeHook()] = [
          'render element' => 'content',
          'base hook' => 'layout',
          'template' => $template,
          'path' => $definition->getTemplatePath(),
        ];
      }
    }
    return $hooks;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategories() {
    // Fetch all categories from definitions and remove duplicates.
    $categories = array_unique(array_values(array_map(function (LayoutDefinition $definition) {
      return $definition->getCategory();
    }, $this->getDefinitions())));
    natcasesort($categories);
    return $categories;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Layout\LayoutDefinition[]
   *   An array of plugin definitions, sorted by category and label.
   */
  public function getSortedDefinitions(?array $definitions = NULL, $label_key = 'label') {
    // Sort the plugins first by category, then by label.
    $definitions = $definitions ?? $this->getDefinitions();
    uasort($definitions, function (LayoutDefinition $a, LayoutDefinition $b) {
      if ($a->getCategory() != $b->getCategory()) {
        return strnatcasecmp($a->getCategory(), $b->getCategory());
      }
      return strnatcasecmp($a->getLabel(), $b->getLabel());
    });
    return $definitions;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Layout\LayoutDefinition[][]
   *   Keys are category names, and values are arrays of which the keys are
   *   plugin IDs and the values are plugin definitions.
   */
  public function getGroupedDefinitions(?array $definitions = NULL, $label_key = 'label') {
    $definitions = $this->getSortedDefinitions($definitions ?? $this->getDefinitions(), $label_key);
    $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      $grouped_definitions[(string) $definition->getCategory()][$id] = $definition;
    }
    return $grouped_definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutOptions() {
    $layout_options = [];
    $filtered_definitions = $this->getFilteredDefinitions($this->getType());
    foreach ($this->getGroupedDefinitions($filtered_definitions) as $category => $layout_definitions) {
      foreach ($layout_definitions as $name => $layout_definition) {
        $layout_options[$category][$name] = $layout_definition->getLabel();
      }
    }
    return $layout_options;
  }

}
