<?php

namespace Drupal\culturefeed_content;

use CultuurNet\SearchV3\Parameter\AudienceType;
use CultuurNet\SearchV3\Parameter\Query;
use CultuurNet\SearchV3\SearchQuery;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\culturefeed_search_api\DrupalCulturefeedSearchClientInterface;

/**
 * Provides a lazy builder for Culturfeed content fields.
 */
class CulturefeedContentFieldLazyBuilder {

  use StringTranslationTrait;

  /**
   * The Culturefeed search client.
   *
   * @var \Drupal\culturefeed_search_api\DrupalCulturefeedSearchClientInterface
   */
  protected $searchClient;

  /**
   * CulturefeedContentFieldLazyBuilder constructor.
   *
   * @param \Drupal\culturefeed_search_api\DrupalCulturefeedSearchClientInterface $searchClient
   *   The Culturefeed search client.
   */
  public function __construct(DrupalCulturefeedSearchClientInterface $searchClient) {
    $this->searchClient = $searchClient;
  }

  /**
   * Build a Culturefeed content field.
   *
   * @param string $title
   *   The title to display.
   * @param string $query
   *   The search query to execute.
   * @param string $viewMode
   *   The view mode of the items to display.
   * @param int $limit
   *   Limit the number of items displayed.
   * @param string $sort
   *   The field to sort on.
   * @param string $sortDirection
   *   The sorting direction.
   * @param bool $defaultMoreLink
   *   Use default link or custom.
   * @param string $moreLink
   *   Custom show more link url.
   *
   * @return array
   *   Render array.
   */
  public function buildCulturefeedContent(string $title = '', string $query = '', string $viewMode = '', int $limit = 10, string $sort = NULL, string $sortDirection = 'desc', bool $defaultMoreLink = TRUE, string $moreLink = '') {
    if (!empty($query)) {
      $query = str_replace(',', ' AND ', '(' . rtrim($query . ')', ','));
    }

    if ($defaultMoreLink) {
      $moreLink = Link::createFromRoute($this->t('Show all events'), 'culturefeed_agenda.agenda', [], ['query' => array_filter(['q' => $query])]);
    }
    else {
      try {
        $moreLink = Link::fromTextAndUrl($this->t('Show all events'), Url::fromUserInput($moreLink ?? '/'));
      }
      catch (\InvalidArgumentException $e) {
        $moreLink = NULL;
      }
    }

    $build = [
      '#theme' => 'culturefeed_content_formatter',
      '#items' => [],
      '#view_mode' => $viewMode ?? 'teaser',
      '#title' => $title ?? '',
      '#more_link' => $moreLink,
      '#cache' => [
        'tags' => [
          'culturefeed_search',
        ],
        'max-age' => strtotime('+2 hours'),
      ],
    ];

    // Query the search API.
    try {
      $searchQuery = new SearchQuery(TRUE);
      $searchQuery->addParameter(new Query($query));
      $searchQuery->addParameter(new AudienceType('*'));
      $searchQuery->setLimit($limit);

      if ($sort) {
        $searchQuery->addSort($sort, $sortDirection);
      }

      $results = $this->searchClient->searchEvents($searchQuery);
      if (!empty($results->getMember()->getItems())) {
        $build['#items'] = $results->getMember()->getItems();
      }
    }
    catch (\Exception $e) {
      $build['cache']['max-age'] = 0;
    }

    return $build;
  }

}
