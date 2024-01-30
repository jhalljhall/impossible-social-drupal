<?php

namespace Drupal\commerce_webform_order\Plugin\WebformHandler;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\webform\Element\WebformMessage;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformDialogHelper;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a commerce order with a webform submission.
 *
 * @WebformHandler(
 *   id = "commerce_webform_order",
 *   label = @Translation("Commerce Webform Order Handler"),
 *   category = @Translation("Commerce"),
 *   description = @Translation("Creates a commerce order with a webform submission."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   conditions = TRUE,
 *   tokens = TRUE,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class CommerceWebformOrderHandler extends WebformHandlerBase {

  use CommerceWebformOrderDebugTrait;

  /**
   * New order option value.
   */
  const NEW_ORDER_OPTION = '_new_order_';

  /**
   * The created cart order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $cart;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity UUID mapper.
   *
   * @var \Drupal\commerce\EntityUuidMapperInterface
   */
  protected $entityUuidMapper;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The DrupalKernel instance used in the test.
   *
   * @var \Drupal\Core\DrupalKernel
   */
  protected $kernel;

  /**
   * The created order item.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $orderItem;

  /**
   * The order item repository.
   *
   * @var \Drupal\commerce_webform_order\OrderItemRepositoryInterface
   */
  protected $orderItemRepository;

  /**
   * The order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\OrderTypeResolverInterface
   */
  protected $orderTypeResolver;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The webform element manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $webformElementManager;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Webform elements selectors as options.
   *
   * @var array
   */
  protected $elementsOptions;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    // Webform's services.
    $instance->loggerFactory = $container->get('logger.factory');
    $instance->configFactory = $container->get('config.factory');
    $instance->renderer = $container->get('renderer');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->conditionsValidator = $container->get('webform_submission.conditions_validator');
    $instance->tokenManager = $container->get('webform.token_manager');

    // Commerce webform order's services.
    $instance->cartManager = $container->get('commerce_cart.cart_manager');
    $instance->cartProvider = $container->get('commerce_cart.cart_provider');
    $instance->requestStack = $container->get('request_stack');
    $instance->currentUser = $container->get('current_user');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityUuidMapper = $container->get('commerce.entity_uuid_mapper');
    $instance->kernel = $container->get('kernel');
    $instance->orderItemRepository = $container->get('commerce_webform_order.order_item_repository');
    $instance->orderTypeResolver = $container->get('commerce_order.chain_order_type_resolver');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->webformElementManager = $container->get('plugin.manager.webform.element');
    $instance->workflowManager = $container->get('plugin.manager.workflow');
    $instance->moduleHandler = $container->get('module_handler');

    $instance->setConfiguration($configuration);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default = [
      'store' => [
        'store_entity' => NULL,
        'bypass_access' => FALSE,
      ],
      'order_item' => [
        'order_item_id' => NULL,
        'purchasable_entity_type' => NULL,
        'purchasable_entity' => NULL,
        'title' => NULL,
        'overwrite_price' => FALSE,
        'amount' => NULL,
        'currency' => NULL,
        'quantity' => 1,
        'order_item_bundle' => NULL,
        'fields' => [],
      ],
      'checkout' => [
        'new_cart' => FALSE,
        'empty_cart' => FALSE,
        'combine_cart' => TRUE,
        'owner' => NULL,
        'owner_id' => NULL,
        'billing_profile_id' => NULL,
        'billing_profile_bypass_access' => FALSE,
        'payment_gateway_id' => NULL,
        'payment_method_id' => NULL,
        'cancel_url' => NULL,
        'hide_add_to_cart_message' => FALSE,
        'redirect' => TRUE,
        'order_state' => '',
        'order_data' => '',
      ],
      'sync' => FALSE,
      'webform_states' => [WebformSubmissionInterface::STATE_COMPLETED],
      'order_states' => [self::NEW_ORDER_OPTION],
      'prevent_update' => FALSE,
      'debug' => FALSE,
    ];

    // By default, all carts with draft status should be able to be updated.
    // Commerce by default uses "draft" for each of the defined workflows, so
    // let's find all those states and add them. This way we can always continue
    // adding products to the cart until it is completed.
    foreach ($this->getOrderStatesOptions() as $state_group) {
      if (is_array($state_group)) {
        $draft_states = array_filter(array_keys($state_group), function ($state) {
          return substr_compare($state, ':draft', -6) === 0;
        });

        if (!empty($draft_states)) {
          $default['order_states'] = array_merge($default['order_states'], $draft_states);
        }
      }
    };

    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);

    // Make sure that all the configuration keys are present, this is useful to
    // fix possible outdated configuration.
    // Webform and order states are not an associative array, so let's remove
    // them before to make sure the default values are not added again in case
    // they haven't been configured.
    $default = $this->defaultConfiguration();
    $default['webform_states'] = [];
    $default['order_states'] = [];

    $this->configuration = array_replace_recursive($default, $this->configuration);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOffCanvasWidth() {
    return WebformDialogHelper::DIALOG_WIDE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Apply submitted form state settings to configuration.
    $this->applyFormStateToConfiguration($form_state);

    // Get #options array of webform elements.
    $webform_elements = $this->getElements();

    $form['tabs_wrapper'] = [
      '#type' => 'fieldset',
    ];
    $form['tabs_wrapper']['message'] = [
      '#type' => 'webform_message',
      '#message_message' => $this->t('When using tokens, you can determine what tokens should be removed if no replacement value can be generated adding the suffix <code>:clear</code>. For example <code>[current-user:name:clear]</code>.'),
      '#message_type' => 'warning',
    ];
    $form['tabs_wrapper']['tabs'] = [
      '#type' => 'vertical_tabs',
      '#title_display' => FALSE,
      '#default_tab' => 'edit-store',
    ];

    // Settings: Store.
    $form['tabs_wrapper']['store'] = [
      '#type' => 'details',
      '#title' => $this->t('Store'),
      '#group' => 'settings][tabs_wrapper][tabs',
    ];
    $form['tabs_wrapper']['store']['store_entity'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Store'),
      '#description' => $this->t('The Store ID or Name. Empty for default store. Support token value.'),
      '#required' => TRUE,
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['store']['store_entity'],
      '#empty_option' => $this->t('- Select -'),
      '#parents' => ['settings', 'store', 'store_entity'],
      '#other__type' => 'commerce_webform_order_entity_autocomplete_token',
      '#other__option_label' => $this->t('Reference one…'),
      '#other__target_type' => 'commerce_store',
      '#other__maxlength' => 2500,
    ];
    if ($this->configuration['store']['store_entity'] === NULL) {
      $stores = $this->entityTypeManager
        ->getStorage('commerce_store')
        ->getQuery()
        ->accessCheck()
        ->range(0, 2)
        ->execute();

      // If there is only a store use it as default value.
      if (count($stores) == 1) {
        $form['tabs_wrapper']['store']['store_entity']['#default_value'] = reset($stores);
      }
      // Otherwise load the default store.
      elseif (($default_store = $this->entityTypeManager->getStorage('commerce_store')->loadDefault()) !== NULL) {
        $form['tabs_wrapper']['store']['store_entity']['#default_value'] = $default_store->id();
      }
    }
    elseif (!empty($this->configuration['store']['store_entity']) && Uuid::isValid($this->configuration['store']['store_entity'])) {
      $store_ids = $this->entityUuidMapper->mapToIds('commerce_store', [$this->configuration['store']['store_entity']]);
      if (count($store_ids) > 0) {
        $form['tabs_wrapper']['store']['store_entity']['#default_value'] = reset($store_ids);
      }
    }
    $form['tabs_wrapper']['store']['bypass_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass access checks'),
      '#description' => $this->t('By default, commerce does not allow to see stores to anonymous user, so you need to fix the permissions or enable this option.'),
      '#default_value' => $this->configuration['store']['bypass_access'],
      '#return_value' => TRUE,
      '#parents' => ['settings', 'store', 'bypass_access'],
    ];

    // Settings: Order item.
    $form['tabs_wrapper']['order_item'] = [
      '#type' => 'details',
      '#title' => $this->t('Order item'),
      '#group' => 'settings][tabs_wrapper][tabs',
    ];
    $form['tabs_wrapper']['order_item']['order_item_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Store the order item ID'),
      '#suffix' => '<hr />',
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['order_item']['order_item_id'],
      '#empty_option' => $this->t('- Select -'),
      '#parents' => ['settings', 'order_item', 'order_item_id'],
    ];
    $purchasable_entity_types = $this->getPurchasableEntityTypes();
    if (empty($purchasable_entity_types)) {
      $form['tabs_wrapper']['order_item']['message'] = [
        '#type' => 'webform_message',
        '#message_message' => $this->t('There is no purchasable entities, enable the module "Commerce Entity Purchasable" or "Commerce Product".'),
        '#message_type' => 'error',
      ];
    }
    $form['tabs_wrapper']['order_item']['purchasable_entity_type'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Purchasable entity type'),
      '#description' => $this->t('The purchasable entity type. Support token value.'),
      '#required' => TRUE,
      '#options' => $purchasable_entity_types,
      '#default_value' => $this->configuration['order_item']['purchasable_entity_type'],
      '#empty_option' => $this->t('- Select -'),
      '#parents' => ['settings', 'order_item', 'purchasable_entity_type'],
      '#other__type' => 'textfield',
      '#other__maxlength' => 2500,
      '#ajax' => [
        'callback' => [get_called_class(), 'updatePurchasableEntitySettings'],
        'wrapper' => 'commerce-webform-order--purchasable-entity',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Updating...'),
        ],
      ],
    ];
    // If we have an entity type, we use an entity reference field, otherwise
    // a textfield, because without entity type we cannot use an entity
    // reference field.
    $purchasable_entity_type = $this->configuration['order_item']['purchasable_entity_type'];
    if (array_key_exists($purchasable_entity_type, $purchasable_entity_types)) {
      $form['tabs_wrapper']['order_item']['purchasable_entity'] = [
        '#type' => 'webform_select_other',
        '#title' => $this->t('Purchasable entity'),
        '#description' => $this->t('The purchasable entity ID or SKU of the order item. Support token value.'),
        '#suffix' => '<hr />',
        '#required' => TRUE,
        '#options' => $webform_elements,
        '#default_value' => $this->configuration['order_item']['purchasable_entity'] ?? NULL,
        '#empty_option' => $this->t('- Select -'),
        '#parents' => ['settings', 'order_item', 'purchasable_entity'],
        '#other__type' => 'commerce_webform_order_entity_autocomplete_token',
        '#other__option_label' => $this->t('Reference one…'),
        '#other__target_type' => $this->configuration['order_item']['purchasable_entity_type'],
        '#other__maxlength' => 2500,
        '#other__ajax' => [
          'callback' => [get_called_class(), 'updatePurchasableEntityBundle'],
          'wrapper' => 'commerce-webform-order--order-item-bundle',
          'event' => 'autocompleteclose',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Updating...'),
          ],
        ],
        // Workaround for change and autocompleteclose events.
        // @see https://www.drupal.org/project/webform/issues/3044798
        '#attached' => [
          'library' => ['commerce_webform_order/commerce_webform_order.handler.commerce_webform_order'],
        ],
        '#attributes' => [
          'class' => ['commerce-webform-order--purchasable-entity'],
        ],
        '#wrapper_attributes' => [
          'id' => 'commerce-webform-order--purchasable-entity',
        ],
      ];
      if (!empty($this->configuration['order_item']['purchasable_entity']) && Uuid::isValid($this->configuration['order_item']['purchasable_entity'])) {
        $purchasable_entity_ids = $this->entityUuidMapper->mapToIds($this->configuration['order_item']['purchasable_entity_type'], [$this->configuration['order_item']['purchasable_entity']]);
        if (count($purchasable_entity_ids) > 0) {
          $form['tabs_wrapper']['order_item']['purchasable_entity']['#default_value'] = reset($purchasable_entity_ids);
        }
      }
    }
    else {
      $form['tabs_wrapper']['order_item']['purchasable_entity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Purchasable entity'),
        '#description' => $this->t('The purchasable entity ID or SKU of the order item. Support token value.'),
        '#suffix' => '<hr />',
        '#required' => TRUE,
        '#default_value' => $this->configuration['order_item']['purchasable_entity'],
        '#parents' => ['settings', 'order_item', 'purchasable_entity'],
        '#maxlength' => 2500,
        '#wrapper_attributes' => [
          'id' => 'commerce-webform-order--purchasable-entity',
        ],
        '#disabled' => empty($purchasable_entity_type),
      ];
    }
    $form['tabs_wrapper']['order_item']['title'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The title of the order item.'),
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['order_item']['title'],
      '#empty_value' => NULL,
      '#empty_option' => $this->t('Use the product variation selected'),
      '#parents' => ['settings', 'order_item', 'title'],
      '#other__type' => 'textfield',
      '#other__maxlength' => 2500,
    ];
    $form['tabs_wrapper']['order_item']['title_warning'] = [
      '#type' => 'webform_message',
      '#message_type' => 'warning',
      '#message_message' => $this->t('Currently is not possible to use a custom title. See <a href=":url">#3008173</a>.', [
        ':url' => 'https://www.drupal.org/project/commerce/issues/3008173',
      ]),
      '#suffix' => '<hr />',
    ];
    $form['tabs_wrapper']['order_item']['amount'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Amount'),
      '#description' => $this->t('The unit price of the order item. Support token value.'),
      '#suffix' => '<hr />',
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['order_item']['amount'],
      '#empty_value' => NULL,
      '#empty_option' => $this->t('Use the product variation selected'),
      '#parents' => ['settings', 'order_item', 'amount'],
      '#other__type' => 'textfield',
      '#other__maxlength' => 2500,
    ];
    $form['tabs_wrapper']['order_item']['currency'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Currency'),
      '#description' => $this->t('The currency code, name or numeric code.'),
      '#suffix' => '<hr />',
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['order_item']['currency'],
      '#empty_value' => NULL,
      '#empty_option' => $this->t('Use the product variation selected'),
      '#parents' => ['settings', 'order_item', 'currency'],
      '#other__type' => 'commerce_webform_order_entity_autocomplete_token',
      '#other__option_label' => $this->t('Reference one…'),
      '#other__target_type' => 'commerce_currency',
      '#other__maxlength' => 2500,
    ];
    $form['tabs_wrapper']['order_item']['quantity'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Quantity'),
      '#description' => $this->t('The units of the order item. Support token value.'),
      '#suffix' => '<hr />',
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['order_item']['quantity'] == 1 ? NULL : $this->configuration['order_item']['quantity'],
      '#empty_value' => 1,
      '#empty_option' => $this->t('One item'),
      '#parents' => ['settings', 'order_item', 'quantity'],
      '#other__type' => 'textfield',
      '#other__maxlength' => 2500,
    ];
    $form['tabs_wrapper']['order_item']['order_item_bundle'] = [
      '#type' => 'webform_entity_select',
      '#title' => $this->t('Order item type'),
      '#description' => $this->t('The order item fields.'),
      '#suffix' => '<hr />',
      '#required' => TRUE,
      '#target_type' => 'commerce_order_item_type',
      '#selection_handler' => 'default',
      '#default_value' => $this->configuration['order_item']['order_item_bundle'],
      '#empty_value' => NULL,
      '#empty_option' => $this->t('- Select -'),
      '#parents' => ['settings', 'order_item', 'order_item_bundle'],
      '#wrapper_attributes' => [
        'id' => 'commerce-webform-order--order-item-bundle',
      ],
    ];
    if (!empty($this->configuration['order_item']['purchasable_entity']) &&
      preg_match('/^:input\[name=\"(.*?)\"]$/', $this->configuration['order_item']['purchasable_entity'], $match) == FALSE) {
      try {
        /** @var \Drupal\commerce\PurchasableEntityInterface $purchasable_entity */
        $purchasable_entity = $this->entityTypeManager->getStorage($purchasable_entity_type)
          ->load($this->configuration['order_item']['purchasable_entity']);

        if ($purchasable_entity instanceof PurchasableEntityInterface) {
          /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
          $order_item = $this->entityTypeManager->getStorage('commerce_order_item')
            ->createFromPurchasableEntity($purchasable_entity);

          $form['tabs_wrapper']['order_item']['order_item_bundle']['#default_value'] = $order_item->bundle();
          $form['tabs_wrapper']['order_item']['order_item_bundle']['#value'] = $order_item->bundle();
        }
      }
      catch (\Exception $exception) {
        // Just prevent errors.
      }
    }
    $order_items = $this->getOrderItemBundles();
    foreach ($order_items as $order_item_id => $order_item) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
      foreach ($order_item['fields'] as $field_id => $field) {
        $form['tabs_wrapper']['order_item']['bundles'][$order_item_id][$field_id] = [
          '#type' => 'webform_select_other',
          '#title' => $field->getLabel(),
          '#description' => $field->getDescription(),
          '#suffix' => '<hr />',
          '#options' => $webform_elements,
          '#default_value' => $this->configuration['order_item']['fields'][$order_item_id][$field_id] ?? NULL,
          '#empty_option' => $this->t('- Select -'),
          '#parents' => [
            'settings', 'order_item', 'fields', $order_item_id, $field_id,
          ],
          // @todo Use the same type of this order item field.
          '#other__type' => 'textfield',
          '#other__maxlength' => 2500,
          '#states' => [
            'visible' => [
              ':input[name="settings[order_item][order_item_bundle]"]' => ['value' => $order_item_id],
            ],
          ],
        ];
        // Mark it as required if the order item field is required.
        if ($field->isRequired()) {
          $form['tabs_wrapper']['order_item']['bundles'][$order_item_id][$field_id]['#states']['required'] = [
            ':input[name="settings[order_item][order_item_bundle]"]' => ['value' => $order_item_id],
          ];
        }
      }
    }

    // Settings: Checkout.
    $form['tabs_wrapper']['checkout'] = [
      '#type' => 'details',
      '#title' => $this->t('Checkout'),
      '#group' => 'settings][tabs_wrapper][tabs',
    ];
    $form['tabs_wrapper']['checkout']['new_cart'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force a new cart to be created with each submission'),
      '#description' => $this->t('If checked, current users cart will dismissed and a new one will be created.'),
      '#suffix' => '<hr />',
      '#return_value' => TRUE,
      '#parents' => ['settings', 'checkout', 'new_cart'],
      '#default_value' => $this->configuration['checkout']['new_cart'],
    ];
    $form['tabs_wrapper']['checkout']['empty_cart'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Empty the current cart order'),
      '#description' => $this->t('If checked, current users cart will be emptied.'),
      '#suffix' => '<hr />',
      '#return_value' => TRUE,
      '#parents' => ['settings', 'checkout', 'empty_cart'],
      '#default_value' => $this->configuration['checkout']['empty_cart'],
    ];
    $form['tabs_wrapper']['checkout']['combine_cart'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Combine order items containing the same product variation.'),
      '#suffix' => '<hr />',
      '#return_value' => TRUE,
      '#parents' => ['settings', 'checkout', 'combine_cart'],
      '#default_value' => $this->configuration['checkout']['combine_cart'],
    ];
    $form['tabs_wrapper']['checkout']['owner'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t("Owner's e-mail"),
      '#description' => $this->t("The owner's e-mail of the order."),
      '#suffix' => '<hr />',
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['checkout']['owner'],
      '#empty_value' => NULL,
      '#empty_option' => $this->t('- Select -'),
      '#parents' => ['settings', 'checkout', 'owner'],
      '#other__type' => 'textfield',
      '#other__maxlength' => 2500,
    ];
    $form['tabs_wrapper']['checkout']['owner_id'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Owner ID'),
      '#description' => $this->t('The Owner ID. Empty for current user. Support token value.'),
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['checkout']['owner_id'],
      '#empty_option' => $this->t('- Select -'),
      '#parents' => ['settings', 'checkout', 'owner_id'],
      '#other__type' => 'commerce_webform_order_entity_autocomplete_token',
      '#other__option_label' => $this->t('Reference one…'),
      '#other__target_type' => 'user',
      '#other__maxlength' => 2500,
    ];
    if (!empty($this->configuration['checkout']['owner_id']) && Uuid::isValid($this->configuration['checkout']['owner_id'])) {
      $user_ids = $this->entityUuidMapper->mapToIds('user', [$this->configuration['checkout']['owner_id']]);
      if (count($user_ids) > 0) {
        $form['tabs_wrapper']['checkout']['owner_id']['#default_value'] = reset($user_ids);
      }
    }
    $form['tabs_wrapper']['checkout']['billing_profile_id'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t("Billing profile"),
      '#options' => $webform_elements,
      '#default_value' => $this->configuration['checkout']['billing_profile_id'],
      '#empty_option' => $this->t('- Select -'),
      '#parents' => ['settings', 'checkout', 'billing_profile_id'],
      '#other__type' => 'commerce_webform_order_entity_autocomplete_token',
      '#other__option_label' => $this->t('Reference one…'),
      '#other__target_type' => 'profile',
      '#other__selection_settings' => [
        'target_bundles' => ['customer'],
      ],
      '#other__maxlength' => 2500,
    ];
    if (!empty($this->configuration['checkout']['billing_profile_id']) && Uuid::isValid($this->configuration['checkout']['billing_profile_id'])) {
      $profile_ids = $this->entityUuidMapper->mapToIds('profile', [$this->configuration['checkout']['billing_profile_id']]);
      if (count($profile_ids) > 0) {
        $form['tabs_wrapper']['checkout']['billing_profile_id']['#default_value'] = reset($profile_ids);
      }
    }
    $form['tabs_wrapper']['checkout']['billing_profile_bypass_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass access checks for billing profile'),
      '#suffix' => '<hr />',
      '#default_value' => $this->configuration['checkout']['billing_profile_bypass_access'],
      '#return_value' => TRUE,
      '#parents' => ['settings', 'checkout', 'billing_profile_bypass_access'],
    ];
    if ($this->moduleHandler->moduleExists('commerce_payment')) {
      $form['tabs_wrapper']['checkout']['payment_gateway_id'] = [
        '#type' => 'webform_select_other',
        '#title' => $this->t('Payment gateway'),
        '#description' => $this->t('For now, payment gateways collecting billing information is not supported.'),
        '#options' => $webform_elements,
        '#default_value' => $this->configuration['checkout']['payment_gateway_id'],
        '#empty_option' => $this->t('- Select -'),
        '#parents' => ['settings', 'checkout', 'payment_gateway_id'],
        '#other__type' => 'commerce_webform_order_entity_autocomplete_token',
        '#other__option_label' => $this->t('Reference one…'),
        '#other__target_type' => 'commerce_payment_gateway',
        '#other__maxlength' => 2500,
      ];
      if (!empty($this->configuration['checkout']['payment_gateway_id']) && Uuid::isValid($this->configuration['checkout']['payment_gateway_id'])) {
        $payment_gateway_ids = $this->entityUuidMapper->mapToIds('commerce_payment_gateway', [$this->configuration['checkout']['payment_gateway_id']]);
        if (count($payment_gateway_ids) > 0) {
          $form['tabs_wrapper']['checkout']['payment_gateway_id']['#default_value'] = reset($payment_gateway_ids);
        }
      }
      $form['tabs_wrapper']['checkout']['payment_method_id'] = [
        '#type' => 'webform_select_other',
        '#title' => $this->t('Payment method'),
        '#options' => $webform_elements,
        '#default_value' => $this->configuration['checkout']['payment_method_id'],
        '#empty_option' => $this->t('- Select -'),
        '#parents' => ['settings', 'checkout', 'payment_method_id'],
        '#other__type' => 'commerce_webform_order_entity_autocomplete_token',
        '#other__option_label' => $this->t('Reference one…'),
        '#other__target_type' => 'commerce_payment_method',
        '#other__maxlength' => 2500,
      ];
      if (!empty($this->configuration['checkout']['payment_method_id']) && Uuid::isValid($this->configuration['checkout']['payment_method_id'])) {
        $payment_method_ids = $this->entityUuidMapper->mapToIds('commerce_payment_method', [$this->configuration['checkout']['payment_method_id']]);
        if (count($payment_method_ids) > 0) {
          $form['tabs_wrapper']['checkout']['payment_method_id']['#default_value'] = reset($payment_method_ids);
        }
      }
      $form['tabs_wrapper']['checkout']['cancel_url'] = [
        '#type' => 'webform_select_other',
        '#title' => $this->t('Cancel URL'),
        '#description' => $this->t('If your plan is to replace the checkout payment method selector by this webform hiding the checkout pane "Payment information". You need to replace the checkout pane "Payment process" by the pane "Payment process (Commerce Webform Order)", and provide a URL to return the user to in case the payment fails. For example, the edit url of this webform submission ([webform_submission:token-update-url:relative:clear]), so that the user can restart the flow.'),
        '#suffix' => '<hr />',
        '#options' => $webform_elements,
        '#default_value' => $this->configuration['checkout']['cancel_url'],
        '#empty_option' => $this->t('- Select -'),
        '#parents' => ['settings', 'checkout', 'cancel_url'],
        '#other__type' => 'textfield',
        '#other__maxlength' => 2500,
      ];
    }
    $form['tabs_wrapper']['checkout']['hide_add_to_cart_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide the add to cart message'),
      '#description' => $this->t('If checked, add to cart message will be removed.'),
      '#return_value' => TRUE,
      '#parents' => ['settings', 'checkout', 'hide_add_to_cart_message'],
      '#default_value' => $this->configuration['checkout']['hide_add_to_cart_message'],
    ];
    $form['tabs_wrapper']['checkout']['redirect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override confirmation type'),
      '#description' => $this->t('If checked, current user will be redirected to the checkout page after submit this webform.'),
      '#return_value' => TRUE,
      '#parents' => ['settings', 'checkout', 'redirect'],
      '#default_value' => $this->configuration['checkout']['redirect'],
    ];
    $form['tabs_wrapper']['checkout']['order_state'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Order state'),
      '#description' => $this->t('Create the order in a different status from cart.'),
      '#options' => $webform_elements + $this->getOrderStatesOptions(),
      '#parents' => ['settings', 'checkout', 'order_state'],
      '#default_value' => $this->configuration['checkout']['order_state'],
    ];
    // Enable a YAML input for order data.
    $form['tabs_wrapper']['checkout']['order_data'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Custom order data'),
      '#description' => $this->t('Enter custom data that will be included in the order data. In YAML format.'),
      '#default_value' => $this->configuration['checkout']['order_data'],
      '#parents' => ['settings', 'checkout', 'order_data'],
    ];

    // Synchronization.
    $form['synchronization'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Synchronization settings'),
      '#group' => 'tab_advanced',
    ];
    $form['synchronization']['sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable order item synchronization'),
      '#description' => $this->t('If checked, when created order items are removed the related submission will be removed also.'),
      '#return_value' => TRUE,
      '#parents' => ['settings', 'sync'],
      '#default_value' => $this->configuration['sync'],
    ];

    // Additional.
    $results_disabled = $this->getWebform()->getSetting('results_disabled');
    $form['additional'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional settings'),
    ];
    // Settings: States.
    $form['additional']['webform_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Execute this handler'),
      '#options' => [
        WebformSubmissionInterface::STATE_DRAFT_CREATED => $this->t('…when <b>draft is created</b>.'),
        WebformSubmissionInterface::STATE_DRAFT_UPDATED => $this->t('…when <b>draft is updated</b>.'),
        WebformSubmissionInterface::STATE_CONVERTED => $this->t('…when anonymous submission is <b>converted</b> to authenticated.'),
        WebformSubmissionInterface::STATE_COMPLETED => $this->t('…when submission is <b>completed</b>.'),
        WebformSubmissionInterface::STATE_UPDATED => $this->t('…when submission is <b>updated</b>.'),
        WebformSubmissionInterface::STATE_DELETED => $this->t('…when submission is <b>deleted</b>.'),
      ],
      '#parents' => ['settings', 'webform_states'],
      '#access' => $results_disabled ? FALSE : TRUE,
      '#default_value' => $results_disabled ? [WebformSubmissionInterface::STATE_COMPLETED] : $this->configuration['webform_states'],
    ];
    $form['additional']['states_message'] = [
      '#type' => 'webform_message',
      '#message_type' => 'warning',
      '#message_message' => $this->t('When the submission is deleted, the related order item will be deleted.'),
      '#message_close' => FALSE,
      '#message_id' => 'commerce_webform_order_states_message',
      '#message_storage' => WebformMessage::STORAGE_NONE,
      '#states' => [
        'visible' => [
          ':input[name="settings[webform_states][deleted]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['additional']['order_states'] = [
      '#type' => 'select',
      '#title' => $this->t('And order is'),
      '#options' => [self::NEW_ORDER_OPTION => $this->t('New')] + $this->getOrderStatesOptions(),
      '#parents' => ['settings', 'order_states'],
      '#default_value' => $this->configuration['order_states'],
      '#multiple' => TRUE,
      '#size' => 8,
    ];
    $form['additional']['prevent_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('If checked, users will not be able to update their submissions if the associated order is not a draft.'),
      '#parents' => ['settings', 'prevent_update'],
      '#default_value' => $this->configuration['prevent_update'],
      '#states' => [
        'visible' => [
          ':input[name="settings[webform_states][updated]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Settings: Debug.
    $form['development'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Development options'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, created orders will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#parents' => ['settings', 'debug'],
      '#default_value' => $this->configuration['debug'],
    ];

    // ISSUE: TranslatableMarkup is breaking the #ajax.
    // WORKAROUND: Convert all Render/Markup to strings.
    WebformElementHelper::convertRenderMarkupToStrings($form);

    $this->elementTokenValidate($form);

    return $form;
  }

  /**
   * Ajax callback for the 'Purchasable Entity' form element.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public static function updatePurchasableEntitySettings(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $array_parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $element = NestedArray::getValue($form, $array_parents);
    unset($element['purchasable_entity']['#suffix']);

    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand(
        '#commerce-webform-order--purchasable-entity',
        $element['purchasable_entity']
      )
    );

    return $response;
  }

  /**
   * Ajax callback for the 'Purchasable Entity' form element.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public static function updatePurchasableEntityBundle(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $array_parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $element = NestedArray::getValue($form, $array_parents);
    unset($element['order_item_bundle']['#suffix']);

    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand(
        '#commerce-webform-order--order-item-bundle',
        $element['order_item_bundle']
      )
    );

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValues();

    // Cleanup states.
    $values['webform_states'] = array_values(array_filter($values['webform_states']));
    $values['order_states'] = array_values(array_filter($values['order_states']));

    // Convert IDs into UUIDs.
    if (!empty($values['store']['store_entity']) && is_numeric($values['store']['store_entity'])) {
      $store_ids = $this->entityUuidMapper->mapFromIds('commerce_store', [$values['store']['store_entity']]);
      if (count($store_ids) > 0) {
        $values['store']['store_entity'] = reset($store_ids);
      }
    }
    if (!empty($values['order_item']['purchasable_entity']) && is_numeric($values['order_item']['purchasable_entity'])) {
      $purchasable_entity_ids = $this->entityUuidMapper->mapFromIds($values['order_item']['purchasable_entity_type'], [$values['order_item']['purchasable_entity']]);
      if (count($purchasable_entity_ids) > 0) {
        $values['order_item']['purchasable_entity'] = reset($purchasable_entity_ids);
      }
    }
    if (!empty($values['checkout']['owner_id']) && is_numeric($values['checkout']['owner_id'])) {
      $user_ids = $this->entityUuidMapper->mapFromIds('user', [$values['checkout']['owner_id']]);
      if (count($user_ids) > 0) {
        $values['checkout']['owner_id'] = reset($user_ids);
      }
    }
    if (!empty($values['checkout']['billing_profile_id']) && is_numeric($values['checkout']['billing_profile_id'])) {
      $profile_ids = $this->entityUuidMapper->mapFromIds('profile', [$values['checkout']['billing_profile_id']]);
      if (count($profile_ids) > 0) {
        $values['checkout']['billing_profile_id'] = reset($profile_ids);
      }
    }
    if (!empty($values['checkout']['payment_gateway_id']) && is_numeric($values['checkout']['payment_gateway_id'])) {
      $payment_gateway_ids = $this->entityUuidMapper->mapToIds('commerce_payment_gateway', [$values['checkout']['payment_gateway_id']]);
      if (count($payment_gateway_ids) > 0) {
        $values['checkout']['payment_gateway_id'] = reset($payment_gateway_ids);
      }
    }
    if (!empty($values['checkout']['payment_method_id']) && is_numeric($values['checkout']['payment_method_id'])) {
      $payment_method_ids = $this->entityUuidMapper->mapToIds('commerce_payment_method', [$values['checkout']['payment_method_id']]);
      if (count($payment_method_ids) > 0) {
        $values['checkout']['payment_method_id'] = reset($payment_method_ids);
      }
    }

    $form_state->setValues($values);

    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    try {
      if ($this->shouldBeExecuted($webform_submission)) {
        // Collect data from the handler and the webform submission.
        $data = $this->prepareData($webform_submission);

        // Create the order item.
        if ($update) {
          $this->orderItem = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, $this->getHandlerId());

          if ($this->orderItem instanceof OrderItemInterface) {
            // Just remove it from the order, so order total is recalculated.
            $this->orderItem
              ->getOrder()
              ->removeItem($this->orderItem);

            if ($this->orderItem->bundle() != $data['order_item_bundle']) {
              // And the outdated order item also, we need to create a new one.
              $this->orderItem->set('commerce_webform_order_submission', NULL);
              $this->orderItem->delete();
              $this->orderItem = NULL;
            }
          }
        }

        if (!$this->orderItem instanceof OrderItemInterface) {
          /** @var \Drupal\commerce_order\OrderItemStorage $order_item_storage */
          $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');

          $this->orderItem = $order_item_storage->create(['type' => $data['order_item_bundle']]);
        }

        $this->orderItem
          ->set('purchased_entity', $data['purchasable_entity'])
          ->set('commerce_webform_order_submission', $webform_submission->id())
          ->setTitle($data['title'])
          ->setUnitPrice($data['price'], TRUE)
          ->setQuantity($data['quantity']);

        $order_item_data = [];
        foreach (['sync', 'prevent_update'] as $setting_key) {
          if ($this->configuration[$setting_key]) {
            $order_item_data[$setting_key] = TRUE;
          }
        }
        $order_item_data['handler_id'] = $this->getHandlerId();
        $this->orderItem->setData('commerce_webform_order', $order_item_data);

        // Add non BaseFieldDefinition field values.
        foreach ($data['order_item_fields'] as $field => $value) {
          if ($this->orderItem->hasField($field)) {
            $this->orderItem->set($field, $value);
          }
        }

        // Create or update the cart.
        $order_type_id = $this->orderTypeResolver->resolve($this->orderItem);
        $this->cart = $this->cartProvider->getCart($order_type_id, $data['store'], $data['owner']);

        if ($this->configuration['checkout']['new_cart'] && $this->cart) {
          $this->cartProvider->finalizeCart($this->cart);
          $this->cart = NULL;
        }

        if (!$this->cart) {
          $this->cart = $this->cartProvider->createCart($order_type_id, $data['store'], $data['owner']);
        }
        elseif ($this->configuration['checkout']['empty_cart']) {
          $this->cartManager->emptyCart($this->cart);
        }

        // Set the owner and the email if the user is not an anonymous user.
        if (empty($data['owner_email']) && empty($data['owner']) && $this->currentUser->isAuthenticated()) {
          $this->cart->setCustomerId($this->currentUser->id());
          $this->cart->setEmail($this->currentUser->getEmail());
        }
        elseif (!empty($data['owner_email'])) {
          // Set the email.
          $this->cart->setEmail($data['owner_email']);
        }

        // Set the billing profile.
        if (!empty($data['billing_profile'])) {
          $this->cart->setBillingProfile($data['billing_profile']);
        }

        // Set the payment gateway and payment method.
        if ($this->moduleHandler->moduleExists('commerce_payment')) {
          if (isset($data['payment_gateway'])) {
            $this->cart->set('payment_gateway', $data['payment_gateway']);
          }
          if (isset($data['payment_method'])) {
            $this->cart->set('payment_method', $data['payment_method']);
          }
          if (isset($data['cancel_url'])) {
            $this->cart->setData('commerce_webform_order_cancel_url', $data['cancel_url']);
          }
        }

        // Set the order state.
        if (!empty($data['order_state'])) {
          $this->cart->set('state', $data['order_state']);
        }

        // Set the order data.
        if (!empty($data['order_data']) && is_array($data['order_data'])) {
          foreach ($data['order_data'] as $key => $value) {
            $this->cart->setData($key, $value);
          }
        }

        // Allow other modules to alter the order, the order item and the
        // webform submission before they're fully processed.
        $this->moduleHandler->alter('commerce_webform_order_handler_postsave', $this->cart, $this->orderItem, $webform_submission);

        // Add the order item to the order.
        $original_order_item_uuid = $this->orderItem->uuid();
        // Allow to no add any order item, for example for only initialize
        // an order.
        if (!empty($data['purchasable_entity']) || !empty($data['title'])) {
          $this->orderItem = $this->cartManager->addOrderItem($this->cart, $this->orderItem, $this->configuration['checkout']['combine_cart']);
        }
        else {
          // If there is no order item, we need to save the order manually.
          $this->cart->save();
        }

        // Update the webform submission ID if a new order item has been, for
        // example combining order items.
        if ($original_order_item_uuid != $this->orderItem->uuid()) {
          // Update the webform submission ID.
          $this->orderItem
            ->set('commerce_webform_order_submission', $webform_submission->id())
            ->save();
        }

        if (!empty($this->configuration['order_item']['order_item_id']) &&
          preg_match('/^:input\[name=\"(.*?)\"]$/', $this->configuration['order_item']['order_item_id'], $match) == TRUE) {
          $webform_submission->setElementData($match[1], $this->orderItem->id());
        }

        // Set the current cart as source entity for this webform submission.
        $webform_submission->set('entity_type', $this->cart->getEntityTypeId());
        $webform_submission->set('entity_id', $this->cart->id());

        // Make sure any possible change is stored without triggering any hooks
        // or handlers.
        $webform_submission->resave();

        // Remove the add to cart status message.
        if ($this->configuration['checkout']['hide_add_to_cart_message']) {
          $added_to_cart_msg = $this->t('@entity added to <a href=":url">your cart</a>.', [
            '@entity' => '',
            ':url' => Url::fromRoute('commerce_cart.page')->toString(),
          ])->__toString();
          $messages = $this->messenger()->messagesByType('status');
          $this->messenger()->deleteByType('status');

          /** @var \Drupal\Core\Render\Markup $original_message */
          foreach ($messages as $original_message) {
            if ($original_message instanceof MarkupInterface) {
              $message = $original_message->__toString();
            }
            else {
              $message = $original_message;
            }

            /* @see \Drupal\commerce_cart\EventSubscriber\CartEventSubscriber::displayAddToCartMessage */
            if (!str_ends_with($message, $added_to_cart_msg)) {
              $this->messenger()->addMessage($message, 'status');
            }
          }
        }

        // Log message in Drupal's log.
        $context = [
          '@form' => $this->getWebform()->label(),
          '@title' => $this->label(),
          'link' => $this->getWebform()->toLink($this->t('Edit'), 'handlers')->toString(),
        ];
        $this->getLogger('commerce_webform_order')->notice('@form webform created @title order.', $context);

        // Log message in Webform's submission log.
        $context = [
          '@order_id' => $this->cart->get('order_id')->getString(),
          'webform_submission' => $webform_submission,
          'handler_id' => $this->getHandlerId(),
          'data' => [],
          'link' => $this->cart->toLink($this->t('Edit'), 'edit-form')->toString(),
        ];
        if ($this->cart->getEmail() !== NULL) {
          $context['@owner_email'] = $this->cart->getEmail();
          $this->getLogger('webform_submission')->notice("Order #@order_id created to '@owner_email'.", $context);
        }
        else {
          $this->getLogger('webform_submission')->notice("Order #@order_id created.", $context);
        }

        // Debug by displaying create order onscreen.
        if ($this->configuration['debug']) {
          $this->debug();
        }
      }
    }
    catch (\Exception $exception) {
      $variables = Error::decodeException($exception);
      $this->getLogger('commerce_webform_order')
        ->error('%type: @message in %function (line %line of %file).', $variables);
      $this->messenger()->addWarning($this->t('There was a problem processing your request. Please, try again.'), TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(WebformSubmissionInterface $webform_submission) {
    try {
      // Remove only the order item created by this submission.
      if ($this->shouldBeExecuted($webform_submission, TRUE)) {
        /** @var \Drupal\commerce_order\Entity\OrderItemInterface[]|null $order_item */
        $order_items = $this->orderItemRepository->getAllByWebformSubmission($webform_submission, $this->getHandlerId());

        if (!empty($order_items)) {
          foreach ($order_items as $order_item) {
            if ($order_item instanceof OrderItemInterface) {
              $order_item->getOrder()
                ->removeItem($order_item);

              $order_item->delete();
            }
          }
        }
      }
    }
    catch (\Exception $exception) {
      $variables = Error::decodeException($exception);
      $this->getLogger('commerce_webform_order')
        ->error('%type: @message in %function (line %line of %file).', $variables);
      $this->messenger()->addWarning($this->t('There was a problem processing your request. Please, try again.'), TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    try {
      if ($this->configuration['checkout']['redirect'] && $this->cart) {
        $url = Url::fromRoute(
          'commerce_checkout.form',
          [
            'commerce_order' => $this->cart->get('order_id')->getString(),
            'step' => NULL,
          ],
          [
            'query' => $this->requestStack->getCurrentRequest()->query->all(),
          ]
        );

        $request = $this->requestStack->getCurrentRequest();

        // Build Ajax redirect or trusted redirect response.
        $wrapper_format = $request->get(MainContentViewSubscriber::WRAPPER_FORMAT);
        $is_ajax_request = ($wrapper_format === 'drupal_ajax');
        if ($is_ajax_request) {
          $response = new AjaxResponse();
          $response->addCommand(new RedirectCommand($url->toString()));
          $response->setData($response->getCommands());
        }
        else {
          $response = new TrustedRedirectResponse($url->toString());
        }

        // Save the session so things like messages get saved.
        $request->getSession()->save();
        $response->prepare($request);

        // Make sure to trigger kernel events.
        $this->kernel->terminate($request, $response);
        $response->send();
        exit;
      }
    }
    catch (\Exception $exception) {
      $variables = Error::decodeException($exception);
      $this->getLogger('commerce_webform_order')
        ->error('%type: @message in %function (line %line of %file).', $variables);
      $this->messenger()->addWarning($this->t('There was a problem processing your request. Please, try again.'), TRUE);
    }
  }

  /**
   * Determines if this handler should be executed.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission entity.
   * @param bool $deleted
   *   True to check deleted operation.
   *
   * @return bool
   *   TRUE if this handler should be executed, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function shouldBeExecuted(WebformSubmissionInterface $webform_submission, $deleted = FALSE) {
    if ($deleted) {
      $webform_state = WebformSubmissionInterface::STATE_DELETED;
    }
    else {
      $webform_state = $webform_submission->getWebform()->getSetting('results_disabled') ? WebformSubmissionInterface::STATE_COMPLETED : $webform_submission->getState();
    }

    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, $this->getHandlerId());
    if ($order_item instanceof OrderItemInterface) {
      $order_state = $this->getOrderState($order_item->getOrder());
    }
    else {
      /** @var \Drupal\commerce_order\OrderStorage $order_item_storage */
      $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');

      $data = $this->prepareData($webform_submission);

      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $order_item_storage->create([
        'type' => $data['order_item_bundle'],
        'commerce_webform_order_submission' => $webform_submission->id(),
      ]);
      $order_type_id = $this->orderTypeResolver->resolve($order_item);
      $cart = $this->cartProvider->getCart($order_type_id, $data['store'], $data['owner']);

      $order_state = $cart ? $this->getOrderState($cart) : self::NEW_ORDER_OPTION;
    }

    return $this->configuration['webform_states'] && in_array($webform_state, $this->configuration['webform_states']) &&
      $this->configuration['order_states'] && in_array($order_state, $this->configuration['order_states']);
  }

  /**
   * Prepare data from the handler and the webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission entity.
   *
   * @return array
   *   The prepared data from the handler and the submission.
   *
   * @throws \Exception
   */
  protected function prepareData(WebformSubmissionInterface $webform_submission) {
    // Get the handler configuration and replace the values of the mapped
    // elements.
    $data = $this->replaceConfiguration($webform_submission);

    // Load the entity values, and replace the tokens if they are supported.
    if (empty($data['store']['store_entity'])) {
      $prepared_data['store'] = $this->loadEntityValue(
        TRUE,
        'commerce_store',
        ['uuid', 'is_default'],
        $data['store']['bypass_access']
      );
    }
    else {
      $prepared_data['store'] = $this->loadEntityValue(
        $data['store']['store_entity'],
        'commerce_store',
        ['uuid', 'store_id', 'name'],
        $data['store']['bypass_access']
      );
    }

    $prepared_data['purchasable_entity'] = NULL;
    $entity_type_id = $data['order_item']['purchasable_entity_type'];
    if (!empty($entity_type_id)) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $entity_keys = [];
      foreach (['uuid', 'id', 'sku'] as $key) {
        $entity_keys[] = $entity_type->getKey($key);
      }
      if (!empty($data['order_item']['purchasable_entity'])) {
        $prepared_data['purchasable_entity'] = $this->loadEntityValue(
          $data['order_item']['purchasable_entity'],
          $entity_type_id,
          array_filter($entity_keys)
        );
      }
    }

    $prepared_data['title'] = $data['order_item']['title'];
    if (empty($prepared_data['title']) && !empty($prepared_data['purchasable_entity'])) {
      $prepared_data['title'] = $prepared_data['purchasable_entity']->getTitle();
    }

    $amount = $data['order_item']['amount'];
    if ((empty($amount) || !is_numeric($amount)) && !empty($prepared_data['purchasable_entity'])) {
      $data['order_item']['amount'] = $prepared_data['purchasable_entity']->getPrice()->getNumber();
    }

    if (empty($data['order_item']['currency']) && !empty($prepared_data['purchasable_entity'])) {
      $data['order_item']['currency'] = $prepared_data['purchasable_entity']->getPrice()->getCurrencyCode();
    }
    else {
      $currency = $this->loadEntityValue(
        $data['order_item']['currency'],
        'commerce_currency',
        ['currencyCode', 'name', 'numericCode']
      );

      $data['order_item']['currency'] = $currency->getCurrencyCode();
    }

    $prepared_data['price'] = new Price((string) $data['order_item']['amount'], $data['order_item']['currency']);

    $prepared_data['quantity'] = $data['order_item']['quantity'];

    $prepared_data['order_item_bundle'] = $data['order_item']['order_item_bundle'];

    $prepared_data['order_item_fields'] = [];
    if (!empty($data['order_item']['fields'][$prepared_data['order_item_bundle']])) {
      foreach ($data['order_item']['fields'][$prepared_data['order_item_bundle']] as $field_key => $field) {
        $prepared_data['order_item_fields'][$field_key] = $field;
      }
    }

    $prepared_data['owner_email'] = $data['checkout']['owner'];

    if (!empty($data['checkout']['owner_id']) || $data['checkout']['owner_id'] === '0') {
      $prepared_data['owner'] = $this->loadEntityValue(
        $data['checkout']['owner_id'],
        'user',
        ['uuid', 'uid']
      );
    }
    else {
      $prepared_data['owner'] = NULL;
    }

    if (!empty($data['checkout']['billing_profile_id'])) {
      $prepared_data['billing_profile'] = $this->loadEntityValue(
        $data['checkout']['billing_profile_id'],
        'profile',
        ['uuid', 'profile_id'],
        $data['checkout']['billing_profile_bypass_access']
      );
    }
    else {
      $prepared_data['billing_profile'] = NULL;
    }

    if ($this->moduleHandler->moduleExists('commerce_payment')) {
      if (!empty($data['checkout']['payment_gateway_id'])) {
        $prepared_data['payment_gateway'] = $this->loadEntityValue(
          $data['checkout']['payment_gateway_id'],
          'commerce_payment_gateway',
          ['uuid', 'id']
        );
      }
      else {
        $prepared_data['payment_gateway'] = NULL;
      }

      if (!empty($data['checkout']['payment_method_id'])) {
        $prepared_data['payment_method'] = $this->loadEntityValue(
          $data['checkout']['payment_method_id'],
          'commerce_payment_method',
          ['uuid', 'method_id']
        );
      }
      else {
        $prepared_data['payment_method'] = NULL;
      }

      if (!empty($data['checkout']['cancel_url'])) {
        $prepared_data['cancel_url'] = Html::decodeEntities($data['checkout']['cancel_url']);
      }
      else {
        $prepared_data['cancel_url'] = NULL;
      }
    }

    if (!empty($data['checkout']['order_state'])) {
      [, $state] = explode(':', $data['checkout']['order_state']);
      $prepared_data['order_state'] = $state;
    }

    $prepared_data['order_data'] = $data['checkout']['order_data'];

    return $prepared_data;
  }

  /**
   * Replaces the mapped configuration files.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission entity.
   *
   * @return array
   *   The replaced configuration data.
   */
  protected function replaceConfiguration(WebformSubmissionInterface $webform_submission) {
    $data = $this->configuration;
    $submission_data = $webform_submission->getData();
    array_walk_recursive($data, function (&$value) use ($webform_submission, $submission_data) {
      // Skip empty values.
      if (empty($value)) {
        return FALSE;
      }

      if (preg_match('/^:input\[name=\"(.*?)\"]$/', $value, $match) == TRUE) {
        $element_key = array_map(
          function ($item) {
            return rtrim($item, ']');
          },
          explode('[', $match[1])
        );
        $value = NestedArray::getValue($submission_data, $element_key);
      }
      $value = $this->tokenManager->replace($value, $webform_submission);
    });

    // De-structure order data.
    $data['checkout']['order_data'] = !empty($data['checkout']['order_data']) ? Yaml::decode($data['checkout']['order_data']) : [];

    return $data;
  }

  /**
   * Get webform element's selectors as options.
   *
   * @return array
   *   Webform elements selectors as options.$this->elementOptions
   *
   * @throws \Exception
   */
  protected function getElements() {
    if (!isset($this->elementsOptions)) {
      $this->elementsOptions = [];
      foreach ($this->getWebform()->getElementsInitializedAndFlattened() as $element) {
        try {
          $element_plugin = $this->webformElementManager->getElementInstance($element);
          if (!$element_plugin instanceof WebformCompositeBase) {
            $t_args = [
              '@title' => $element['#title'],
              '@type' => $element_plugin->getPluginLabel(),
            ];
            $this->elementsOptions[":input[name=\"{$element['#webform_key']}\"]"] = $this->t('@title [@type]', $t_args);
          }
          else {
            $this->elementsOptions += $element_plugin->getElementSelectorOptions($element);
          }
        }
        catch (\Exception $exception) {
          // Nothing to do.
        }
      }
    }

    return $this->elementsOptions;
  }

  /**
   * Prepare array of order item types with its non BaseFieldDefinition fields.
   *
   * @return array
   *   Prepared array of order item types with its fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getOrderItemBundles() {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorage $order_item_type_storage */
    $order_item_type_storage = $this->entityTypeManager->getStorage('commerce_order_item_type');

    $order_items = [];
    /** @var \Drupal\commerce_order\Entity\OrderItemType $order_item_type */
    foreach ($order_item_type_storage->loadMultiple() as $order_item_type) {
      if ($order_item_type->getOrderTypeId() != 'recurring') {
        if ($order_item_type->getPurchasableEntityTypeId() !== NULL) {
          $fields = $this->entityFieldManager->getFieldDefinitions('commerce_order_item', $order_item_type->id());
          $base_fields = $this->entityFieldManager->getBaseFieldDefinitions('commerce_order_item');

          $order_items[$order_item_type->id()] = [
            'label' => $order_item_type->label(),
            'fields' => array_diff_key($fields, $base_fields),
          ];
        }
      }
    }

    return $order_items;
  }

  /**
   * Get webform element's selectors as options.
   *
   * @return array
   *   Webform elements selectors as options.
   *
   * @throws \Exception
   */
  protected function getPurchasableEntityTypes() {
    // Prepare the list of purchasable entity types.
    $entity_types = $this->entityTypeManager->getDefinitions();
    $entity_types_options = array_filter($entity_types, function (EntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(PurchasableEntityInterface::class);
    });
    $entity_types_options = array_map(function (EntityTypeInterface $entity_type) {
      return $entity_type->getLabel();
    }, $entity_types_options);

    return $entity_types_options;
  }

  /**
   * Returns the order state.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The order state.
   *
   * @throws \Exception
   */
  protected function getOrderState(OrderInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $this->loadEntityValue($order->bundle(), 'commerce_order_type', ['id'], TRUE);

    return $order_type->getWorkflowId() . ':' . $order->getState()->getId();
  }

  /**
   * Returns order states as options.
   *
   * @return array
   *   Order states as options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getOrderStatesOptions() {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface[] $order_types */
    $order_types = $this->entityTypeManager
      ->getStorage('commerce_order_type')
      ->loadMultiple();
    $used_workflows = [];
    foreach ($order_types as $order_type) {
      $used_workflows[] = $order_type->getWorkflowId();
    }

    /** @var \Drupal\state_machine\WorkflowManager $workflowManager */
    $groups = $this->workflowManager->getGroupedLabels('commerce_order');
    $group = reset($groups);

    $order_states = [];
    foreach ($group as $workflow_id => $workflow) {
      // Only add used workflows.
      if (in_array($workflow_id, $used_workflows)) {
        $workflow_label = $this->t('@label workflow', ['@label' => $workflow])->__toString();
        /** @var \Drupal\state_machine\Plugin\Workflow\Workflow $workflow_definition */
        $workflow_definition = $this->workflowManager->createInstance($workflow_id);
        foreach ($workflow_definition->getStates() as $state_id => $state) {
          $order_states[$workflow_label][$workflow_id . ':' . $state_id] = $state->getLabel();
        }
      }
    }

    return $order_states;
  }

  /**
   * Helper method to load entity values.
   *
   * @param mixed $value
   *   The value to load.
   * @param string $entity_type
   *   The entity type id.
   * @param array $properties
   *   A property array to try to load the entity by them.
   * @param bool $bypass_access
   *   True to skip access check.
   *
   * @return mixed
   *   The loaded entity or the input key.
   *
   * @throws \Exception
   */
  protected function loadEntityValue($value, $entity_type, array $properties = [], $bypass_access = FALSE) {
    // Try to load the entity for each property and return the first occurrence.
    if (!empty($properties)) {
      try {
        // Return the same value if it is an element value.
        if (strpos($value, ':input') !== FALSE) {
          return $value;
        }

        if (empty($value) && $value !== '0') {
          throw new \Exception(sprintf('Trying to load an empty value for the entity type: %s.', $entity_type));
        }

        /** @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
        $entity_storage = $this->entityTypeManager->getStorage($entity_type);

        $query = $entity_storage->getQuery();

        // Query all conditions.
        $or = $query->orConditionGroup();
        foreach ($properties as $property) {
          $or->condition($property, $value);
        }
        $query->condition($or);
        $query->range(0, 1);

        if ($bypass_access) {
          $query->accessCheck(FALSE);
        }
        else {
          $query->accessCheck();
        }

        $entity_ids = $query->execute();
        if (!empty($entity_ids)) {
          $entity_id = reset($entity_ids);

          if (($entity = $entity_storage->load($entity_id)) !== NULL) {
            return $entity;
          }
          else {
            throw new \Exception(sprintf('Unable to load the entity ID: %s of type %s.', $entity_id, $entity_type));
          }
        }
        else {
          throw new \Exception(sprintf('Unable to load the specified entity of type %s.', $entity_type));
        }
      }
      catch (\Exception $exception) {
        if ($entity_type == 'commerce_store') {
          $exception = new \Exception('Unable to load the specified Commerce Store, please, try to fix the user permissions to view stores or enable bypass access control under store setting of the handler.');
        }

        throw $exception;
      }
    }

    return $value;
  }

}
