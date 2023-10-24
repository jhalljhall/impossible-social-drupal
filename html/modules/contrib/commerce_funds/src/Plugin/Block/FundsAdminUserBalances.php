<?php

namespace Drupal\commerce_funds\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_funds\TransactionManagerInterface;

/**
 * Provides an admin block for user balances.
 *
 * @Block(
 *   id = "admin_user_balances",
 *   admin_label = @Translation("Admin user balances")
 * )
 */
class FundsAdminUserBalances extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The transaction manager.
   *
   * @var \Drupal\commerce_funds\TransactionManagerInterface
   */
  protected $transactionManager;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, TransactionManagerInterface $transaction_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->transactionManager = $transaction_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
    $configuration,
    $plugin_id,
    $plugin_definition,
    $container->get('current_route_match'),
    $container->get('commerce_funds.transaction_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'administer transactions');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($account = $this->routeMatch->getParameter('user')) {
      // The route doesn't implements parameters.
      if (!$account instanceof AccountInterface && is_numeric($account)) {
        $account = User::load($account);
      }
      $balance = $this->transactionManager->loadAccountBalance($account);
      foreach ($balance as $currency_code => $amount) {
        $symbol = Currency::load($currency_code)->getSymbol();
        $balance[$currency_code] = $symbol . $amount;
      }

      return [
        '#theme' => 'admin_user_balances',
        '#balance' => $balance ?: 0,
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
  }

}
