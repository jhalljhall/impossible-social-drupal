<?php

namespace Drupal\simpleads\Entity\ViewsData;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Advertisement entities.
 */
class AdvertisementViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    if (!empty($data['simpleads']['group']['filter']['id'])) {
      $data['simpleads']['group']['filter']['id'] = 'simpleads_group';
    }
    if (!empty($data['simpleads']['campaign']['filter']['id'])) {
      $data['simpleads']['campaign']['filter']['id'] = 'simpleads_campaign';
    }

    $data['simpleads_clicks']['table']['group'] = $this->t('SimpleAds');
    $data['simpleads_clicks']['table']['provider'] = 'simpleads';
    $data['simpleads_clicks']['table']['base'] = [
      'field'  => 'id',
      'title'  => $this->t('Daily advertisement clicks'),
      'help'   => $this->t('Advertisement click record ID.'),
      'weight' => -10,
    ];
    $data['simpleads_clicks']['id'] = [
      'title' => t('Click record ID'),
      'help'  => t('Serial ID of the click record.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_clicks']['table']['join'] = [
      'simpleads' => [
        'left_field' => 'id',
        'field'      => 'entity_id',
      ],
    ];
    $data['simpleads_clicks']['entity_id'] = [
      'title' => $this->t('Daily advertisement click records'),
      'help'  => $this->t('This is SimpleAds advertisement entity ID.'),
      'relationship' => [
        'base'       => 'simpleads',
        'base field' => 'id',
        'id'         => 'standard',
        'label'      => t('Daily advertisement click records'),
      ],
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_clicks']['ip_address'] = [
      'title' => $this->t('IP Address'),
      'help'  => $this->t('IP Address of the device that created this record.'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['simpleads_clicks']['timestamp'] = [
      'title' => $this->t('Timestamp'),
      'help'  => $this->t('Timestamp when this record was created at.'),
      'field' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
    ];

    $data['simpleads_impressions']['table']['group'] = $this->t('SimpleAds');
    $data['simpleads_impressions']['table']['provider'] = 'simpleads';
    $data['simpleads_impressions']['table']['base'] = [
      'field'  => 'id',
      'title'  => $this->t('Daily advertisement impressions'),
      'help'   => $this->t('Advertisement impression record ID.'),
      'weight' => -10,
    ];
    $data['simpleads_impressions']['id'] = [
      'title' => t('Impression record ID'),
      'help'  => t('Serial ID of the impression record.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_impressions']['table']['join'] = [
      'simpleads' => [
        'left_field' => 'id',
        'field'      => 'entity_id',
      ],
    ];
    $data['simpleads_impressions']['entity_id'] = [
      'title' => $this->t('Daily advertisement impression records'),
      'help'  => $this->t('This is SimpleAds advertisement entity ID.'),
      'relationship' => [
        'base'       => 'simpleads',
        'base field' => 'id',
        'id'         => 'standard',
        'label'      => t('Daily advertisement impression records'),
      ],
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_impressions']['ip_address'] = [
      'title' => $this->t('IP Address'),
      'help'  => $this->t('IP Address of the device that created this record.'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['simpleads_impressions']['timestamp'] = [
      'title' => $this->t('Timestamp'),
      'help'  => $this->t('Timestamp when this record was created at.'),
      'field' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
    ];

    $data['simpleads_stats']['table']['group'] = $this->t('SimpleAds');
    $data['simpleads_stats']['table']['provider'] = 'simpleads';
    $data['simpleads_stats']['table']['base'] = [
      'field'  => 'entity_id',
      'title'  => $this->t('Aggregated advertisement statistics'),
      'help'   => $this->t('This is SimpleAds advertisement entity ID.'),
      'weight' => -10,
    ];
    $data['simpleads_stats']['table']['join'] = [
      'simpleads' => [
        'left_field' => 'id',
        'field'      => 'entity_id',
      ],
    ];
    $data['simpleads_stats']['entity_id'] = [
      'title' => $this->t('Aggregated advertisement statistics'),
      'help'  => $this->t('This is SimpleAds advertisement entity ID.'),
      'relationship' => [
        'base'       => 'simpleads',
        'base field' => 'id',
        'id'         => 'standard',
        'label'      => t('Aggregated advertisement statistics'),
      ],
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_stats']['date'] = [
      'title' => $this->t('Date'),
      'help'  => $this->t('Aggregated date (YYYYMMDD).'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['simpleads_stats']['timestamp'] = [
      'title' => $this->t('Timestamp'),
      'help'  => $this->t('Timestamp when this record was created at.'),
      'field' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
    ];
    $data['simpleads_stats']['clicks'] = [
      'title' => $this->t('Clicks'),
      'help'  => $this->t('Number of advertisement clicks.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_stats']['clicks_unique'] = [
      'title' => $this->t('Unique clicks'),
      'help'  => $this->t('Number of unique advertisement clicks.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_stats']['impressions'] = [
      'title' => $this->t('Impressions'),
      'help'  => $this->t('Number of advertisement impressions.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    $data['simpleads_stats']['impressions_unique'] = [
      'title' => $this->t('Unique impressions'),
      'help'  => $this->t('Number of unique advertisement impressions.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];

    return $data;
  }

}
