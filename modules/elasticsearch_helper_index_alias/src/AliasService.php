<?php

namespace Drupal\elasticsearch_helper_index_alias;

use Elasticsearch\Client;
use Drupal\Core\Config\ConfigManagerInterface;

/**
 * Class AliasService.
 */
class AliasService implements AliasServiceInterface {

  /**
   * Version prefix.
   *
   * @var string
   */
  protected const VERSION_PREFIX = '_version_';

  /**
   * Version config key.
   *
   * @var string
   */
  protected const CONFIG_KEY = 'current_version';

  /**
   * Elasticsearch\Client definition.
   *
   * @var \Elasticsearch\Client
   */
  protected $client;

  /**
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Constructs a new AliasService object.
   */
  public function __construct(Client $elasticsearch_helper_elasticsearch_client, ConfigManagerInterface $config_manager) {
    $this->client = $elasticsearch_helper_elasticsearch_client;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAll() {
    $versioned_indexes = $this->getVersionedIndexes();
    $version = $this->getCurrentVersion();

    // Process each index and update alias for each one.
    if (!empty($version)) {
      foreach ($versioned_indexes as $index_name) {
        if ($this->updateIndexAlias($this->client, $index_name, $version)) {
          \Drupal::messenger()->addMessage(t('Destination index updated for alias: @index', ['@index' => $index_name]), 'status');
        }
        else {
          \Drupal::messenger()->addMessage(t('Updating alias failed for index: @index', ['@index' => $index_name]), 'error');
        }

        // @TODO, Dispatch event.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentVersion(): string {
    // Get current version.
    $version = $this->configManager
      ->getConfigFactory()->get('elasticsearch_helper_index_alias.versionconfig')
      ->get(self::CONFIG_KEY);

    if (!empty($version)) {
      return self::VERSION_PREFIX . (int) $version;
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function incrementVersion() {
    $config = $this->configManager
      ->getConfigFactory()->getEditable('elasticsearch_helper_index_alias.versionconfig');

    $version = (int) $config->get(self::CONFIG_KEY);

    $config->set(self::CONFIG_KEY, $version + 1);
  }

  /**
   * {@inheritdoc}
   */
  public function getVersionedIndexDefinitions() {
    $definitions = $this->pluginManagerElasticsearchIndexProcessor->getDefinitions();

    // Remove non-versioned indices from definitions.
    foreach ($definitions as $key => $definition) {
      if (!isset($definition['versioned'])) {
        unset($definitions[$key]);
        continue;
      }
      if ($definition['versioned'] == FALSE) {
        unset($definitions[$key]);
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersionedIndexes() {
    $items = [];

    $indexes = $this->getVersionedIndexDefinitions();

    foreach ($indexes as $key => $index) {
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        // @TODO, unify detection of token replacements.
        // Language code needs to be replace in case used in index.
        $index_name = str_replace('{langcode}', $langcode, $index['indexName']);
        // Remove the version so we could append it later in other methods.
        $index_name = str_replace('{version}', '', $index_name);

        $items[] = $index_name;
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndexAlias(Client $client = NULL, $index_name, $version) {
    try {
      // Verify that destination exists before deleting current alias.
      if (!$client->indices()->exists(['index' => $index_name . $version])) {
        throw new \Exception('Destination index does not exist: ' . $index_name . $version);
      }

      if ($client->indices()->existsAlias(['name' => $index_name, 'ignore_unavailable' => TRUE])) {
        $index_point = $client->indices()->getAlias(['name' => $index_name]);
        $index_point = key($index_point);

        $client->indices()->deleteAlias(['name' => $index_name, 'index' => $index_point]);
      }

      // Delete old index first if it could conflict with the alias.
      if ($client->indices()->exists(['index' => $index_name])) {
        $client->indices()->delete(['index' => $index_name]);
      }

      // Create the new alias pointing to the new index version.
      $client->indices()->updateAliases(
        [
          'body' => [
            'actions' => [
              [
                'add' => [
                  'index' => $index_name . $version,
                  'alias' => $index_name,
                ],
              ],
            ],
          ],
        ]
      );

      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::logger('elasticsearch_helper_index_alias.versionconfig')->debug($e->getMessage());
    }

    return FALSE;
  }

}
