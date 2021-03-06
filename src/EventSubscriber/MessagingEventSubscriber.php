<?php

namespace Drupal\elasticsearch_helper\EventSubscriber;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\elasticsearch_helper\Event\ElasticsearchEvents;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperationErrorEvent;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperations;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Displays a message when throwable is thrown during Elasticsearch operation.
 */
class MessagingEventSubscriber implements EventSubscriberInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ElasticsearchEvents::OPERATION_ERROR][] = ['onOperationError'];

    return $events;
  }

  /**
   * Displays a message if throwable is thrown during Elasticsearch operation.
   *
   * @param \Drupal\elasticsearch_helper\Event\ElasticsearchOperationErrorEvent $event
   */
  public function onOperationError(ElasticsearchOperationErrorEvent $event) {
    // Do not display messages when executed in the command line.
    if (PHP_SAPI === 'cli') {
      return;
    }

    // Get request wrapper.
    $request_wrapper = $event->getRequestWrapper();

    $operation = $event->getOperation();

    // Customise the message for certain expected exceptions.
    if ($operation == ElasticsearchOperations::DOCUMENT_INDEX) {
      $t_args = $event->getMessageContextArguments();

      $error_message = $request_wrapper && $request_wrapper->getDocumentId()
        ? 'Could not index document "@id" into "@index" Elasticsearch index.'
        : 'Could not index the document.';

      $error_message .= ' Please see the log messages for details.';

      $this->messenger()->addError($this->t($error_message, $t_args));
    }
  }

}
