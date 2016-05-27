<?php

namespace Drupal\elasticsearch_helper_example\Plugin\ElasticsearchIndex;

use Drupal\elasticsearch_helper\Annotation\ElasticsearchIndex;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexBase;

/**
 * @ElasticsearchIndex(
 *   id = "simple_node_index",
 *   label = @Translation("Simple Node Index"),
 *   indexName = "simple",
 *   typeName = "node",
 *   entityType = "node"
 * )
 */
class SimpleNodeIndex extends ElasticsearchIndexBase{
  
}
