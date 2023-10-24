<?php

namespace Drupal\commerce_funds\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactory;
use Drupal\commerce_funds\FeesManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for site balance.
 *
 * @Block(
 *   id = "funds_operations",
 *   admin_label = @Translation("Funds operations")
 * )
 */
class FundsUserOperations extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * The fees manager.
   *
   * @var \Drupal\commerce_funds\FeesManagerInterface
   */
  protected $feesManager;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactory $config, FeesManagerInterface $fees_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config;
    $this->feesManager = $fees_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('commerce_funds.fees_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'deposit funds');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->config->get('commerce_funds.settings');
    $disabled_forms = $config->get('global')['disable_funds_forms'] ?? [];
    $withdrawal_methods = $config->get('withdrawal_methods');
    $exchange_rates = $this->feesManager->getExchangeRates();

    return [
      '#theme' => 'user_operations',
      '#disabled_forms' => $disabled_forms,
      '#withdrawal_methods' => $withdrawal_methods,
      '#exchange_rates' => $exchange_rates,
    ];
  }

}
