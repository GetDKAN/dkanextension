<?php

namespace Drupal\DKANExtension\ServiceContainer;

use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DKANExtension implements ExtensionInterface {
  /**
   * Returns the extension config key.
   *
   * @return string
   */
  public function getConfigKey(){
    return 'dkan';
  }

  /**
   * Initializes other extensions.
   *
   * This method is called immediately after all extensions are activated but
   * before any extension `configure()` method is called. This allows extensions
   * to hook into the configuration of other extensions providing such an
   * extension point.
   *
   * @param ExtensionManager $extensionManager
   */
  public function initialize(ExtensionManager $extensionManager) {
    // Nothing is needed here.
    $i = 1;
  }

  /**
   * Setups configuration for the extension.
   *
   * @param ArrayNodeDefinition $builder
   */
  public function configure(ArrayNodeDefinition $builder) {
    $builder->
      children()->
        scalarNode('some_param')->
          defaultValue('\Drupal\DKANExtension\ServiceContainer\EntityStore')->
          info('The class to use to store entities between steps.')->
        end()->
      end()->
    end();
  }

  /**
   * Loads extension services into temporary container.
   *
   * @param ContainerBuilder $container
   * @param array            $config
   */
  public function load(ContainerBuilder $container, array $config) {
    $loader = new YamlFileLoader($container, new FileLocator(__DIR__));
    $loader->load('services.yml');
    //$container->setParameter('dkan.entity_store.class', $config['entity_store.class']);

    # Override the DrupalExtension's Hook loader so we can add our own hooks.
    $container->setParameter('drupal.context.annotation.reader.class',
      'Drupal\DKANExtension\Context\Annotation\Reader');
  }

  public function process(ContainerBuilder $container) {
   $i = 'test';
  }
}
