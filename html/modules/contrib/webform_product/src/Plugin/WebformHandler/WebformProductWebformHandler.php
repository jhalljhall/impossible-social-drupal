<?php

namespace Drupal\webform_product\Plugin\WebformHandler;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\profile\Entity\Profile;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformException;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_product\Controller\WebformProductController;
use Drupal\webform_product\Event\OrderItemEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Webform submission Commerce Product handler.
 *
 * @WebformHandler(
 *   id = "webform_product",
 *   label = @Translation("Webform product"),
 *   category = @Translation("Commerce"),
 *   description = @Translation("Save submission as a product."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class WebformProductWebformHandler extends WebformHandlerBase {

  use MessengerTrait;

  // Commerce values:
  const COMMERCE_STORE = 'store';
  const COMMERCE_ORDER_TYPE = 'order_type';
  const COMMERCE_ORDER_ITEM_TYPE = 'order_item_type';
  const COMMERCE_ORDER_ITEM_TITLE = 'order_item_title';
  const COMMERCE_CHECKOUT_STEP = 'checkout_step';
  const COMMERCE_GATEWAY = 'payment_gateway';
  const COMMERCE_METHOD = 'payment_method';

  // Order total:
  const ORDER_TOTAL = 'order_total';

  // Order mapping field names:
  const PAYMENT_STATUS = 'payment_status';
  const ORDER_ID = 'order_id';
  const ORDER_URL = 'order_url';
  const TOTAL_PRICE = 'total_price';
  const FIELD_LINK_ORDER_ORIGIN = 'field_link_order_origin';

  // Contact mapping field names:
  const CONTACT_EMAIL = 'contact_email';

  // Billing mapping field names:
  const BILLING_FIRST_NAME = 'billing_first_name';
  const BILLING_LAST_NAME = 'billing_last_name';

  // Default values:
  const DEFAULT_ORDER_TYPE = 'webform';
  const DEFAULT_ORDER_ITEM_TYPE = 'webform';
  const DEFAULT_CHECKOUT_STEP = 'payment';
  const DEFAULT_ORDER_ITEM_TITLE = '[webform_submission:source-title]';

  // Debug:
  const DEBUG = FALSE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The token service.
   *
   * @var \Drupal\token\Token
   */
  protected $token;

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The commerce cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  private $cartProvider;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManager
   */
  protected $cartManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $requestStack;

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  private $kernel;

  /**
   * The redirect middleware.
   *
   * @var \Drupal\webform_product\RedirectMiddleware
   */
  protected $redirect;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->token = $container->get('token');
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->cartManager = $container->get('commerce_cart.cart_manager');
    $instance->cartProvider = $container->get('commerce_cart.cart_provider');
    $instance->requestStack = $container->get('request_stack')->getCurrentRequest();
    $instance->kernel = $container->get('kernel');
    $instance->redirect = $container->get('http_middleware.redirect_after_webform_submit');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      self::COMMERCE_STORE => NULL,
      self::COMMERCE_ORDER_TYPE => self::DEFAULT_ORDER_TYPE,
      self::COMMERCE_ORDER_ITEM_TITLE => self::DEFAULT_ORDER_ITEM_TITLE,
      self::COMMERCE_ORDER_ITEM_TYPE => self::DEFAULT_ORDER_ITEM_TYPE,
      'route' => 'commerce_checkout.form',
      self::COMMERCE_CHECKOUT_STEP => self::DEFAULT_CHECKOUT_STEP,
      self::COMMERCE_GATEWAY => NULL,
      self::COMMERCE_METHOD => NULL,
      self::ORDER_TOTAL => NULL,
      self::PAYMENT_STATUS => NULL,
      self::ORDER_ID => NULL,
      self::ORDER_URL => NULL,
      self::TOTAL_PRICE => NULL,
      self::CONTACT_EMAIL => NULL,
      self::BILLING_FIRST_NAME => NULL,
      self::BILLING_LAST_NAME => NULL,
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @todo Create debug mode setting.
   * @todo Create field mapping for Billing information (name, address & mail).
   * @todo Create more route choices.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['commerce'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Commerce'),
    ];
    $form['commerce'][self::COMMERCE_STORE] = [
      '#type' => 'select',
      '#title' => $this->t('Store'),
      '#options' => $this->getEntityOptions('commerce_store'),
      '#default_value' => $this->configuration[self::COMMERCE_STORE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_ORDER_TYPE] = [
      '#type' => 'select',
      '#title' => $this->t('Order type'),
      '#options' => $this->getEntityOptions('commerce_order_type'),
      '#default_value' => $this->configuration[self::COMMERCE_ORDER_TYPE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_ORDER_ITEM_TYPE] = [
      '#type' => 'select',
      '#title' => $this->t('Order item type'),
      '#options' => $this->getEntityOptions('commerce_order_item_type'),
      '#default_value' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_ORDER_ITEM_TITLE] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order item title'),
      '#description' => $this->t('Default %default.', ['%default' => self::DEFAULT_ORDER_ITEM_TITLE]),
      '#default_value' => $this->configuration[self::COMMERCE_ORDER_ITEM_TITLE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_CHECKOUT_STEP] = [
      '#type' => 'select',
      '#title' => $this->t('Checkout step'),
      '#options' => $this->getCheckOutFlowPanesOptions(),
      '#default_value' => $this->configuration[self::COMMERCE_CHECKOUT_STEP],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_GATEWAY] = [
      '#type' => 'select',
      '#title' => $this->t('Payment provider'),
      '#options' => $this->getEntityOptions('commerce_payment_gateway', [
        'status' => TRUE,
      ]),
      '#default_value' => $this->configuration[self::COMMERCE_GATEWAY],
      '#required' => TRUE,
    ];

    $token_types = ['webform', 'webform_submission'];
    $form['commerce']['token_tree_link'] = $this->tokenManager->buildTreeLink(
      $token_types,
      $this->t('Use [webform_submission:values:ELEMENT_KEY:raw] to get plain text values.')
    );

    $form['order_data'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Order data'),
    ];
    $form['order_data']['info'] = [
      '#markup' => '<p>' . $this->t('Select the total price field for a single order item. Select None to use individual webform elements with a Price field for each order item.') . '</p>',
    ];

    $field_types = [
      'checkbox',
      'hidden',
      'radios',
      'number',
      'numeric',
      'textfield',
      'webform_computed_twig',
    ];
    $form['order_data'][self::ORDER_TOTAL] = [
      '#type' => 'select',
      '#title' => $this->t('Total price'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $this->configuration[self::ORDER_TOTAL],
      '#empty_value' => '',
      '#required' => FALSE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $form['order_mapping'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Order field mapping'),
    ];

    $field_types = ['textfield'];
    $form['order_mapping'][self::PAYMENT_STATUS] = [
      '#type' => 'select',
      '#title' => $this->t('Payment status'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $this->configuration[self::PAYMENT_STATUS],
      '#empty_value' => '',
      '#required' => TRUE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $field_types = ['number', 'numeric', 'textfield'];
    $form['order_mapping'][self::ORDER_ID] = [
      '#type' => 'select',
      '#title' => $this->t('Order ID'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $this->configuration[self::ORDER_ID],
      '#empty_value' => '',
      '#required' => TRUE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $field_types = ['url'];
    $form['order_mapping'][self::ORDER_URL] = [
      '#type' => 'select',
      '#title' => $this->t('Order URL'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $this->configuration[self::ORDER_URL],
      '#empty_value' => '',
      '#required' => TRUE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $field_types = ['hidden', 'number', 'numeric', 'textfield'];
    $form['order_mapping'][self::TOTAL_PRICE] = [
      '#type' => 'select',
      '#title' => $this->t('Total price'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $this->configuration[self::TOTAL_PRICE],
      '#empty_value' => '',
      '#required' => FALSE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]) . '<br />' . $this->t('Use this if you want to safe the total order amount to a specific field.'),
    ];

    // Contact Mapping:
    $form['contact_mapping'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact Field Mapping'),
    ];
    $field_types = ['email', 'textfield'];
    $form['contact_mapping'][self::CONTACT_EMAIL] = [
      '#type' => 'select',
      '#title' => $this->t('Email'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $this->configuration[self::CONTACT_EMAIL],
      '#empty_value' => '',
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    // Development:
    $form['development'] = [
      '#type' => 'details',
      '#title' => $this->t('Development Settings'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, posted submissions will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Set mapped webform order and payment field permissions to 'view-only'.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    if ($update == TRUE) {
      return;
    }

    try {
      $order_items = $this->getOrderItems($webform_submission);

      if (empty($order_items)) {
        return;
      }

      /** @var \Drupal\commerce_order\Entity\OrderInterface $cartOrder */
      $cartOrder = $this->getCart($this->cartProvider, $this->getStore(), TRUE);

      // Fill the Cart.
      foreach ($order_items as $order_item) {
        $order_item->save();
        $cartOrder->addItem($order_item);
      }
      $cartOrder->save();

      $cartOrder = $this->entityTypeManager->getStorage('commerce_order')->load($cartOrder->id());

      // Save the Cart (Order) with Submission data.
      $this->setOrderCheckoutProcess($cartOrder);
      $this->setOrderLinkReference($cartOrder, $webform_submission);
      $this->setOrderCustomer($cartOrder, $webform_submission);

      // Save the submission with Cart data.
      $this->setSubmissionTotalPrice($webform_submission, $cartOrder);
      WebformProductController::setSubmissionOrderStatus($webform_submission, WebformProductController::PAYMENT_STATUS_INITIALIZED);
      $this->setSubmissionOrderReference($webform_submission, $cartOrder);
      $webform_submission->set('in_draft', TRUE);
      $webform_submission->resave();

      // Protect order from adding new products.
      $cartOrder->lock();

      // Save the cart:
      $cartOrder->save();

      // Reload the order.
      $cartOrder = $this->entityTypeManager->getStorage('commerce_order')->load($cartOrder->id());

      $this->redirectToCheckout($cartOrder);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get($this->pluginId)->error($e->getMessage());
    }
  }

  /**
   * Get option list of Entities.
   *
   * @param string $entity_type
   *   The entity type to load.
   * @param array $properties
   *   The loaded entity condtions.
   *
   * @return array
   *   List with ids as key and label as value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntityOptions($entity_type, array $properties = []) {
    $options = [];

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface[] $payment_gateways */
    $entities = $this->entityTypeManager
      ->getStorage($entity_type)
      ->loadByProperties($properties);

    foreach ($entities as $entity) {
      $options[$entity->id()] = $entity->label();
    }

    return $options;
  }

  /**
   * Get option list of Entities.
   *
   * @return array
   *   List with ids as key and label as value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getCheckOutFlowPanesOptions() {
    $options = [];
    $entity_type = 'commerce_checkout_flow';
    $properties = [
      'status' => TRUE,
    ];

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface[] $payment_gateways */
    $entities = $this->entityTypeManager
      ->getStorage($entity_type)
      ->loadByProperties($properties);

    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlow $entity */
    foreach ($entities as $entity) {
      $steps = $entity->getPlugin()->getSteps();
      foreach ($steps as $id => $step) {
        $options[$id] = $step['label'];
      }
    }

    return $options;
  }

  /**
   * Gather all Order Items from the webform Submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface[]
   *   List of Order Items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getOrderItems(WebformSubmissionInterface $webform_submission) {
    $price_fields = $this->getWebform()->getThirdPartySettings($this->pluginId);

    // No prices, no Order.
    if (!$price_fields) {
      return [];
    }

    $payment_status = $this->getSavedPaymentStatus($webform_submission);

    // Create an order only for new webform submissions.
    if ($payment_status != WebformProductController::PAYMENT_STATUS_NULL) {
      return [];
    }

    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $this->getStore();
    $currencyCode = $store->getDefaultCurrency()->getCurrencyCode();

    $webform = $this->getWebform();
    $orderItems = [];
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];

    // @todo Make this also available for multiple elements.
    $order_item_title = $this->replaceTokens($settings[self::COMMERCE_ORDER_ITEM_TITLE], $webform_submission);

    if ($this->useElementBasedOrder()) {
      // Create Order Item for each:
      // - element option with a price.
      // - element with a top price.
      $submiss = $webform_submission->getData();
      foreach ($submiss as $key => $value) {
        if (empty($price_fields[$key])) {
          continue;
        }

        $value_to_validate = $value_to_composite = [];

        // Get element:
        $element = $webform->getElement($key);

        // Element with 'top'.
        if (!empty($price_fields[$key]['top'])) {
          $orderItems[] = OrderItem::create([
            'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
            'title' => $order_item_title,
            'quantity' => 1,
            'unit_price' => [
              'number' => $price_fields[$key]['top'],
              'currency_code' => $currencyCode,
            ],
          ]);
        }

        if (!empty($price_fields[$key]['options'])) {
          // Fix for when value is not an array.
          if (!is_array($value)) {
            $value_to_validate = [$value];
          }
          else {
            foreach ($value as $value_key => $item) {
              if (is_array($item)) {
                $value_to_composite[$value_key] = $item;
              }
              else {
                $value_to_validate[$value_key] = $item;
              }
            }
          }

          $options = array_keys($price_fields[$key]['options']);
          $price_options = array_intersect($value_to_validate, $options);

          // Other values.
          if (isset($element['#other_type']) && 'number' === $element['#other__type'] && empty($price_options)) {
            $orderItems[] = OrderItem::create([
              'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
              'title' => $order_item_title,
              'quantity' => 1,
              'unit_price' => [
                'number' => $value,
                'currency_code' => $currencyCode,
              ],
            ]);
          }
          else {
            // Set order item title from element title.
            $element_title = $element['#title'];

            // Check if there is a parent element:
            if (!empty($element['#webform_parent_key'])) {
              // Get the parent element:
              $parent_element = $webform->getElement($element['#webform_parent_key']);

              // Check if parent is a field set:
              if ('fieldset' === $parent_element['#type']) {
                // Prepend fieldset title to order item title:
                $element_title = $parent_element['#title'] . ' - ' . $element_title;
              }
            }

            // Option elements with price as option (checkboxes or radios).
            foreach ($price_options as $option) {
              if (isset($price_fields[$key]['options'][$option]) && is_numeric($price_fields[$key]['options'][$option])) {
                $value = (int) $price_fields[$key]['options'][$option];
                $orderItems[] = OrderItem::create([
                  'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
                  'title' => $order_item_title . ' - ' . $element_title,
                  'quantity' => 1,
                  'unit_price' => [
                    'number' => $value,
                    'currency_code' => $currencyCode,
                  ],
                ]);
              }
            }
          }

          if (!empty($value_to_composite)) {
            foreach ($value_to_composite as $composite) {
              foreach ($composite as $element_id => $e_value) {
                if (isset($price_fields[$key]['options'][$element_id][$e_value])) {
                  $orderItems[] = OrderItem::create([
                    'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
                    'title' => $order_item_title,
                    'quantity' => 1,
                    'unit_price' => [
                      'number' => $price_fields[$key]['options'][$element_id][$e_value],
                      'currency_code' => $currencyCode,
                    ],
                  ]);
                }
              }
            }
          }
        }
      }
    }
    else {
      if (!empty($price_fields[$settings[self::ORDER_TOTAL]])) {
        $orderItems = [];
        $element = $webform->getElement($settings[self::ORDER_TOTAL]);

        if (in_array($element['#type'], ['hidden', 'checkbox', 'radios'])) {
          if ('radios' === $element['#type']) {
            $value = $webform_submission->getElementData($settings[self::ORDER_TOTAL]);

            if (!empty($price_fields[$settings[self::ORDER_TOTAL]]['options'])) {
              $price = $price_fields[$settings[self::ORDER_TOTAL]]['options'][$value];
            }
          }
          else {
            $price = $price_fields[$settings[self::ORDER_TOTAL]]['top'];
          }
        }
        else {
          $price = $this->formatPrice($webform_submission->getElementData($settings[self::ORDER_TOTAL]));
        }

        if (!empty($price)) {
          $orderItems[] = OrderItem::create([
            'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
            'title' => $order_item_title,
            'quantity' => 1,
            'unit_price' => [
              'number' => $price,
              'currency_code' => $currencyCode,
            ],
          ]);
        }
      }
    }

    // Let other modules alter the order_item list.
    $order_item_event = new OrderItemEvent($webform_submission, $orderItems, $this->configuration);
    $this->eventDispatcher->dispatch($order_item_event, OrderItemEvent::EVENT_NAME);

    return $orderItems;
  }

  /**
   * Determine if Element Based order must be used.
   *
   * The commerce order is either created with one order item per priced field
   * or with one item based on a single field value. The latter is usually a
   * calculated value or the result of a (if/else) condition.
   *
   * @return bool
   *   Returns true if element based orders are used.
   */
  private function useElementBasedOrder() {
    return empty($this->configuration[self::ORDER_TOTAL]);
  }

  /**
   * Get webform elements selectors as options.
   *
   * @param array $types
   *   List of types to filter.
   *   - Leave empty skip filtering of types.
   *
   * @see \Drupal\webform\Entity\Webform::getElementsSelectorOptions()
   *
   * @return array
   *   Webform elements selectors as options.
   */
  private function getElementsSelectOptions(array $types = []) {
    $options = [];
    $elements = $this->getWebform()->getElementsInitializedAndFlattened();
    foreach ($elements as $key => $element) {
      // Skip element if not in given 'types' array.
      if ($types && !in_array($element['#type'], $types)) {
        continue;
      }

      $options[$key] = $element['#title'];
    }
    return $options;
  }

  /**
   * Get the payment status of the submission.
   *
   * - Nothing if there isn't any payment at all.
   * - Initilized for started, but not completed payments.
   * - Canceled for payments canceled by the user.
   * - Exception for payments canceled by the provider.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @return string
   *   The status of the payment.
   *
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_NULL;
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_INITIALIZED;
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_CANCELED;
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_COMPLETED;
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_EXCEPTION;
   */
  private function getSavedPaymentStatus(WebformSubmissionInterface $webform_submission) {
    $value = $webform_submission->getElementData($this->configuration[self::PAYMENT_STATUS]);

    return $value;
  }

  /**
   * Set total price of the Order in the Submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform Submission.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   */
  protected function setSubmissionTotalPrice(WebformSubmissionInterface $webform_submission, OrderInterface $order) {
    // Save Total price of order.
    if ($this->configuration[self::TOTAL_PRICE]) {
      $currency_formatter = \Drupal::service('commerce_price.currency_formatter');

      /** @var \Drupal\commerce_price\Price $price */
      $total_price = $order->getTotalPrice();
      $webform_submission->setElementData($this->configuration[self::TOTAL_PRICE], $currency_formatter->format($total_price->getNumber(), $total_price->getCurrencyCode()));
    }
  }

  /**
   * Set the Order reference in the webform Submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform Submission.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function setSubmissionOrderReference(WebformSubmissionInterface $webform_submission, OrderInterface $order) {
    // Save order id to the webform for back reference.
    if ($this->configuration[self::ORDER_ID]) {
      $webform_submission
        ->setElementData($this->configuration[self::ORDER_ID], $order->id());
    }

    if ($this->configuration[self::ORDER_URL]) {
      $webform_submission
        ->setElementData($this->configuration[self::ORDER_URL], $order->toUrl()->toString());
    }
  }

  /**
   * Redirect to the configured checkout step in the Checkout Flow.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   */
  protected function redirectToCheckout(OrderInterface $order) {
    // Redirect to checkout process.
    $response = new RedirectResponse(Url::fromRoute($this->configuration['route'], [
      'commerce_order' => $order->id(),
      'step' => $this->configuration[self::COMMERCE_CHECKOUT_STEP],
    ])->toString());

    $this->redirect->setRedirectResponse($response);
  }

  /**
   * Set Customer data for Order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @todo Create full commerce profile for order with address and mail info.
   */
  protected function setOrderCustomer(OrderInterface $order, WebformSubmissionInterface $webform_submission) {
    // Add customer email:
    if (!empty($this->configuration[self::CONTACT_EMAIL])) {
      $email = $webform_submission->getElementData($this->configuration[self::CONTACT_EMAIL]);
      if (!empty($email)) {
        $order->setEmail($email);
      }
    }

    // Default profile:
    $billing_profile = Profile::create([
      'uid' => 0,
      'type' => 'customer',
    ]);
    $billing_profile->save();

    // Add profile information.
    $order->setBillingProfile($billing_profile);
  }

  /**
   * Save back reference to the webform as link.
   *
   * Order info can't be referenced, if the referenced entity doesn't have the
   * same lifespan.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @todo Make field FIELD_LINK_ORDER_ORIGIN configurable.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function setOrderLinkReference(OrderInterface $order, WebformSubmissionInterface $webform_submission) {
    if ($order->hasField(self::FIELD_LINK_ORDER_ORIGIN)) {
      $order->set(self::FIELD_LINK_ORDER_ORIGIN, $webform_submission->toUrl()->toUriString());
    }
  }

  /**
   * Set Checkout Process variables for Order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function setOrderCheckoutProcess(OrderInterface $order) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')->load($this->configuration[self::COMMERCE_GATEWAY]);

    if (!$payment_gateway) {
      $this->loggerFactory->get($this->pluginId)->error(t('Failed to get a Payment Gateway'));
      return;
    }

    $payment_method = empty($this->configuration[self::COMMERCE_METHOD]) ? NULL : $this->configuration[self::COMMERCE_METHOD];

    // Save additional info to the order to speedup the checkout progress.
    $order
      ->set(self::COMMERCE_CHECKOUT_STEP, $this->configuration[self::COMMERCE_CHECKOUT_STEP])
      ->set(self::COMMERCE_GATEWAY, $payment_gateway->id())
      ->set(self::COMMERCE_METHOD, $payment_method);
  }

  /**
   * Get a Cart (Order) for the current user.
   *
   * Can be a new or existing cart.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cartProvider
   *   The Cart Provider.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The Store.
   * @param bool $remove_existing_items
   *   Flag to remove existing items from the Cart.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   Cart of current user.
   */
  protected function getCart(CartProviderInterface $cartProvider, StoreInterface $store, $remove_existing_items = TRUE) {
    $order_type = $this->configuration[self::COMMERCE_ORDER_TYPE];

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $cartProvider->getCart($order_type, $store) ?: $cartProvider->createCart($order_type, $store, \Drupal::currentUser());

    if (!$order) {
      $this->loggerFactory->get($this->pluginId)->error(t('Failed to get a Cart Order'));
      return NULL;
    }

    if ($remove_existing_items && $order->hasItems()) {
      foreach ($order->getItems() as $item) {
        $order->removeItem($item);
      }
    }

    return $order;
  }

  /**
   * Get the selected store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The Store.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getStore() {
    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $this->entityTypeManager->getStorage('commerce_store')
      ->load($this->configuration[self::COMMERCE_STORE]);

    if (!$store) {
      $this->loggerFactory->get($this->pluginId)->error(t('Failed to get a Store'));
      return NULL;
    }

    return $store;
  }

  /**
   * Format the price value.
   *
   * We allow various field types as price input. This converts them to a float
   * value.
   *
   * @param mixed $value
   *   Raw price value.
   *
   * @return float
   *   Converted value.
   */
  private function formatPrice($value) {
    // Convert Computed Twig.
    if ($value instanceof MarkupInterface) {
      $value = (string) $value;
      $value = preg_replace('/[\n\r\t]/', '', $value);
    }
    // Convert text.
    $value = (string) $value;
    $value = trim($value);
    $value = str_replace(',', '.', str_replace('.', '', $value));
    $value = empty($value) ? '0' : $value;
    if (!is_numeric($value)) {
      throw new WebformException($this->t('Can not make price from %value.', ['%value' => $value]));
    }

    return $value;
  }

}
