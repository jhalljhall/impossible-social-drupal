<?php

namespace Drupal\commerce_funds\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\node\NodeInterface;
use Drupal\commerce_funds\Entity\Transaction;

/**
 * Provides a transaction form element.
 *
 * Usage example:
 * @code
 * $form['transaction'] = [
 *   '#type' => 'funds_transaction',
 *   '#available_currencies' => ['USD', 'EUR'],
 *   '#notes_enabled' => TRUE,
 *   '#transaction_type' => 'transfer',
 * ];
 * @endcode
 *
 * @FormElement("funds_transaction")
 */
class FundsTransaction extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      // List of currencies. If empty, all currencies will be available.
      '#available_currencies' => [],
      '#notes_enabled' => TRUE,
      '#transaction_type' => NULL,
      '#process' => [
        [$class, 'processFundsTransaction'],
      ],
      '#element_validate' => [
        [$class, 'moveInlineErrors'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $currency_input = $element['#transaction_type'] == 'conversion' ? 'currency_left' : 'currency';
    $op = $form_state->getUserInput()['op'] ?? NULL;
    if ($input && $input[$currency_input] && $op == 'Save') {
      // We need to validate before the value is actually set.
      $validated = self::validateTransaction($element, $input, $form_state);

      if ($validated) {
        // Prepares variables for later.
        $amount = $input['amount'];
        $currency = $input[$currency_input];
        $issuer_uid = \Drupal::currentUser()->id();
        $transaction_type = $element['#transaction_type'];
        // Calculates fees applied.
        if ($transaction_type == 'conversion') {
          $conversion = \Drupal::service('commerce_funds.fees_manager')->convertCurrencyAmount($amount, $currency, $input['currency_right']);
          $fee_applied = [
            'net_amount' => $conversion['new_amount'],
            'fee' => $conversion['rate'],
          ];
        }
        else {
          $method = $transaction_type == 'withdrawal_request' ? 'withdraw_' . $input['methods'] : $transaction_type;
          $fee_applied = \Drupal::service('commerce_funds.fees_manager')->calculateTransactionFee($amount, $currency, $method);
        }

        // Transfer and escrow.
        $recipient_uid = isset($input['username']) ? EntityAutocomplete::extractEntityIdFromAutocompleteInput($input['username']) : FALSE;
        // Deposit, Withdrawal request and conversion.
        if (!$recipient_uid) {
          $recipient_uid = \Drupal::currentUser()->id();
        }
        // Creates transaction.
        // We set it as canceled to validate it later.
        $transaction = Transaction::create([
          'issuer' => $issuer_uid,
          'recipient' => $recipient_uid,
          'type' => $transaction_type,
          'method' => $transaction_type == 'withdrawal_request' ? $input['methods'] : 'internal',
          'brut_amount' => $amount,
          'net_amount' => $fee_applied['net_amount'],
          'fee' => $fee_applied['fee'],
          'currency' => $currency_input == 'currency' ? $currency : $input['currency_right'],
          'status' => Transaction::TRANSACTION_STATUS['canceled'],
          'notes' => [
            'value' => $input['notes']['value'] ?? '',
            'format' => $input['notes']['format'] ?? '',
          ],
        ]);
        // Conversion ? add currency right and notes.
        if ($currency_input == 'currency_left') {
          $transaction->setFromCurrencyCode($input['currency_left']);
          $transaction->setNotes([
            'value' => t('@amount @currency_left converted into @new_amount @currency_right.', [
              '@amount' => $amount,
              '@currency_left' => $input['currency_left'],
              '@new_amount' => $fee_applied['net_amount'],
              '@currency_right' => $input['currency_right'],
            ]),
            'format' => 'basic_html',
          ]);
        }
        $transaction->save();

        $element['#processed'] = TRUE;
      }
    }

    return isset($transaction) ? $transaction->id() : NULL;
  }

  /**
   * Processes the funds_transaction form element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processFundsTransaction(array &$element, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce_funds\FeesManagerInterface $fees_manager */
    $fees_manager = \Drupal::service('commerce_funds.fees_manager');
    $element['#tree'] = TRUE;

    // Default widget - No store.
    if (!\Drupal::entityTypeManager()->getStorage('commerce_store')->loadDefault()) {
      \Drupal::messenger()->addError(t('You haven\'t configured a store yet. Please <a href=":url">configure one</a> before.', [
        ':url' => Url::fromRoute('entity.commerce_store.collection')->toString(),
      ]));
      return FALSE;
    }
    // Default widget - Set transaction type if changed.
    if (isset($form_state->getValue('settings')['transaction_type'])) {
      $element['#transaction_type'] = $form_state->getValue('settings')['transaction_type'];
    }
    // Default widget - Set selected currency if defined.
    if (isset($form_state->getValue('settings')['available_currencies'])) {
      $element['#available_currencies'] = $form_state->getValue('settings')['available_currencies'];
    }
    // Default widget - Set notes enabled.
    if (isset($form_state->getValue('settings')['enable_notes'])) {
      $element['#notes_enabled'] = $form_state->getValue('settings')['enable_notes'];
    }

    // Build default currency list.
    if (empty($element['#available_currencies'])) {
      $currencies = \Drupal::entityTypeManager()->getStorage('commerce_currency')->loadMultiple();
      $currency_codes = [];
      /** @var \Drupal\commerce_price\Entity\Currency $currency */
      foreach ($currencies as $currency) {
        $currency_codes[$currency->getCurrencyCode()] = $currency->getCurrencyCode();
      }
      // Make sure currencies are sorted.
      ksort($currency_codes);
    }

    if ($transaction_type = $element['#transaction_type']) {
      $fees_description = $fees_manager->printTransactionFees($transaction_type);
    }

    if ($transaction_type == 'conversion') {
      $element['currency_left'] = [
        '#type' => 'select',
        '#title' => t('From'),
        '#description' => t('The currency to convert.'),
        '#options' => $element['#available_currencies'] ?: $currency_codes,
        '#ajax' => [
          'callback' => [
            'Drupal\commerce_funds\Element\FundsTransaction',
            'printRate',
          ],
          'event' => 'change',
          'wrapper' => 'exchange-rate',
          'progress' => [
            'type' => 'throbber',
            'message' => t('Calculating rate...'),
          ],
        ],
      ];
    }

    $transaction_name = ucfirst(str_replace('_', ' ', $transaction_type));
    $element['amount'] = [
      '#type' => 'number',
      '#min' => 0.0,
      '#title' => t('@transaction_type amount', [
        '@transaction_type' => $transaction_name ,
      ]),
      '#description' => $fees_description ?? t('Fees applied will appear here.'),
      '#step' => 0.01,
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['funds-amount'],
      ],
    ];
    if (isset($fees_description) && \Drupal::config('commerce_funds.settings')->get('global')['add_rt_fee_calculation']) {
      $element['amount'] += [
        '#attached' => [
          'library' => ['commerce_funds/calculate_fees'],
          'drupalSettings' => [
            'funds' => ['fees' => $fees_description],
          ],
        ],
      ];
    }
    if ($transaction_type == 'conversion') {
      $element['amount'] = array_merge($element['amount'], [
        '#ajax' => [
          'callback' => [
            'Drupal\commerce_funds\Element\FundsTransaction',
            'printRate',
          ],
          'event' => 'end_typing',
          'wrapper' => 'exchange-rate',
          'progress' => [
            'type' => 'throbber',
            'message' => t('Calculating rate...'),
          ],
        ],
        '#attributes' => [
          'class' => ['delayed-input-submit'],
        ],
        '#attached' => [
          'library' => ['commerce_funds/delayed_submit'],
        ],
      ]);
    }

    if ($transaction_type == 'conversion') {
      $element['currency_right'] = [
        '#type' => 'select',
        '#title' => t('To'),
        '#description' => t('The to currency to convert into.'),
        '#options' => $element['#available_currencies'] ?: $currency_codes,
        '#ajax' => [
          'callback' => [
            'Drupal\commerce_funds\Element\FundsTransaction',
            'printRate',
          ],
          'event' => 'change',
          'wrapper' => 'exchange-rate',
          'progress' => [
            'type' => 'throbber',
            'message' => t('Calculating rate...'),
          ],
        ],
      ];

      $element['ajax_container'] = [
        '#type'       => 'container',
        '#attributes' => ['id' => 'exchange-rate'],
      ];
    }

    if ($transaction_type != 'conversion') {
      $element['currency'] = [
        '#type' => 'select',
        '#title' => t('Select Currency'),
        '#options' => $element['#available_currencies'] ?: $currency_codes,
      ];
    }

    if (in_array($transaction_type, ['transfer', 'escrow'])) {
      $element['username'] = [
        '#id' => 'commerce-funds-transaction-to',
        '#title' => t('@transaction_type to', [
          '@transaction_type' => $transaction_name,
        ]),
        '#description' => t('Please enter a username.'),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        '#required' => TRUE,
        '#size' => 30,
        '#maxlength' => 128,
        '#selection_settings' => [
          'include_anonymous' => FALSE,
        ],
      ];
    }

    if ($transaction_type == 'withdrawal_request') {
      $methods = array_filter(\Drupal::config('commerce_funds.settings')->get('withdrawal_methods'));
      foreach ($methods as $key => $method) {
        $fee = $fees_manager->printPaymentGatewayFees($key, t('unit(s)'), 'withdraw') ?: '';
        $enabled_method['methods'][$key] = ucfirst($method) . ' ' . $fee;
      }

      if (empty($enabled_method['methods'])) {
        $enabled_method['methods'] = [];
        \Drupal::messenger()->addWarning(t('No withdrawal method available.'));
      }

      $element['methods'] = [
        '#type' => 'radios',
        '#options' => str_replace('-', ' ', $enabled_method['methods']),
        '#title' => t('Select your preferred withdrawal method.'),
        '#required' => TRUE,
      ];
    }

    $transaction_types = ['transfer', 'escrow'];
    if ($element['#notes_enabled'] && in_array($transaction_type, $transaction_types)) {
      $element['notes'] = [
        '#type' => 'text_format',
        '#title' => t('Notes'),
        '#description' => t('Eventually add a message to the recipient.'),
      ];
    }

    // Check permissions on element.
    $permissions = [
      'deposit' => 'deposit funds',
      'escrow' => 'create escrow payment',
      'transfer' => 'transfer funds',
      'withdrawal_request' => 'withdraw funds',
      'conversion' => 'convert currencies',
    ];
    // If transaction type unknown or undefined,
    // give access to the field (new bundle or default widget).
    $element['#access'] = isset($permissions[$transaction_type]) ? \Drupal::currentUser()->hasPermission($permissions[$transaction_type]) : TRUE;

    return $element;
  }

  /**
   * Element validation handler.
   *
   * @param array $element
   *   The form element.
   * @param array $input
   *   The inputs inserted by the user.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Validated or not.
   */
  public static function validateTransaction(array $element, array $input, FormStateInterface $form_state) {
    $validated = TRUE;
    $transaction_type = $element['#transaction_type'];
    /** @var \Drupal\commerce_funds\FeesManagerInterface $fees_manager */
    $fees_manager = \Drupal::service('commerce_funds.fees_manager');
    // No validation for deposit.
    if ($transaction_type == 'deposit') {
      return $validated;
    }

    // Load variables for later.
    $amount = $input['amount'];
    $currency = $transaction_type == 'conversion' ? $input['currency_left'] : $input['currency'];
    $fee_applied = $fees_manager->calculateTransactionFee($amount, $currency, $transaction_type);
    $issuer = \Drupal::currentUser();
    $issuer_balance = \Drupal::service('commerce_funds.transaction_manager')->loadAccountBalance($issuer->getAccount(), $currency);
    $currency_balance = $issuer_balance[$currency] ?? 0;

    // Error if amount equals 0.
    if ($amount == 0) {
      $form_state->setErrorByName('amount', t('Amount must be a positive number.'));
      $validated = FALSE;

      return $validated;
    }

    // Error if the user doesn't have enough money
    // to cover the transaction + fee.
    if ($currency_balance < $fee_applied['net_amount']) {
      if (!$fee_applied['fee']) {
        $form_state->setErrorByName('amount', t("Not enough funds to cover this transaction."));
        $validated = FALSE;
      }
      if ($fee_applied['fee']) {
        $form_state->setErrorByName('amount', t("Not enough funds to cover this transaction (Total: %total @currency).", [
          '%total' => $fee_applied['net_amount'],
          '@currency' => $currency,
        ]));
        $validated = FALSE;
      }
    }
    if (isset($input['methods']) && $method = str_replace('-', '_', $input['methods'])) {
      $issuer_data = \Drupal::service('user.data')->get('commerce_funds', $issuer->id(), $method);
      if (!$issuer_data) {
        $form_state->setErrorByName('methods', t('Please <a href="@enter_details_link">enter your details</a> for this withdrawal method first.', [
          '@enter_details_link' => Url::fromRoute('commerce_funds.withdrawal_methods.edit', [
            'user' => $issuer->id(),
            'method' => str_replace('_', '-', $method),
          ], [
            'query' => [
              'destination' => \Drupal::request()->getRequestUri(),
            ],
          ])->toString(),
        ]));
        $validated = FALSE;
      }
    }

    if (in_array($transaction_type, ['transfer', 'escrow'])) {
      $match = EntityAutocomplete::extractEntityIdFromAutocompleteInput($input['username']);
      // Error if user try to send money to itself.
      if ($match && $issuer->id() == $match) {
        $form_state->setErrorByName('username', t("Operation impossible. You can't transfer money to yourself."));
        $validated = FALSE;
      }
      // Error recipient is not matching any user.
      // As our validation happens during the value callback,
      // we need to make our own checks and stop the process
      // before the transaction is created. The field reference then handle
      // the error message.
      if ($match) {
        if (!User::load($match)) {
          $validated = FALSE;
        }
      }
      if (!$match) {
        $validated = FALSE;
      }
    }

    // Conversion: amount after conversion equals 0.
    if ($transaction_type == 'conversion') {
      // You can't convert a currency into intself.
      if ($currency === $input['currency_right']) {
        $form_state->setErrorByName('currency_right', t('Operation impossible. Please chose another currency.'));
        $validated = FALSE;
      }
      else {
        // No exchange rates.
        if (!$fees_manager->getExchangeRates()) {
          $form_state->setErrorByName('currency_right', t("Operation impossible. No exchange rates found."));
          $validated = FALSE;
        }
        else {
          // If amount after conversion equals 0.
          $conversion = $fees_manager->convertCurrencyAmount($amount, $currency, $input['currency_right']);
          if (!(float) $conversion['new_amount']) {
            $form_state->setErrorByName('currency_right', t('Operation impossible. No exchange rates found.'));
            $validated = FALSE;
          }
        }
      }
    }

    return $validated;
  }

  /**
   * Entity builder handler.
   *
   * @see Drupal\commerce_funds\Plugin\Field\FieldWidget\FundsTransactionTransferWidget::formElement()
   */
  public static function updateFundsTransaction($entity_type, NodeInterface $node, &$form, FormStateInterface $form_state) {
    $op = $form_state->getUserInput()['op'] ?? NULL;
    // Only run this on submission with no errors
    // And on form save.
    if ($form_state->isSubmitted() && !$form_state->hasAnyErrors() && $op == 'Save') {
      // Find the funds_transaction field.
      $transaction_ids = [];
      foreach (Element::children($form) as $field_name) {
        if (isset($form[$field_name]['widget'][0]['target_id']['#type']) && $form[$field_name]['widget'][0]['target_id']['#type'] == 'funds_transaction') {
          $transaction_ids[] = $form_state->getValue($field_name)[0]['target_id'];
        }
      }

      foreach ($transaction_ids as $transaction_id) {
        if (!is_array($transaction_id) && !empty($transaction_id)) {
          $transaction = Transaction::load($transaction_id);
          // Make sure we never trigger a transaction
          // already completed or pending.
          $transaction_status = [
            Transaction::TRANSACTION_STATUS['completed'],
            Transaction::TRANSACTION_STATUS['pending'],
          ];
          if (!in_array($transaction->getStatus(), $transaction_status)) {
            $transaction_manager = \Drupal::service('commerce_funds.transaction_manager');
            $transaction_type = $transaction->bundle();
            // Deposit and withdrawal don't trigger
            // perform transaction on submission.
            if (!in_array($transaction_type, ['deposit', 'withdrawal_request'])) {
              // Performs transaction.
              $transaction_manager->performTransaction($transaction);
              // Send emails.
              if ($transaction_type != 'conversion') {
                $transaction_manager->sendTransactionMails($transaction);
              }
              // Generate confirmation message.
              $transaction_manager->generateConfirmationMessage($transaction);
            }
            elseif ($transaction_type == 'withdrawal_request') {
              // Set status to pending.
              $transaction->setStatus(Transaction::TRANSACTION_STATUS['pending']);
              $transaction->save();
              // Generate confirmation message.
              $transaction_manager->generateConfirmationMessage($transaction);
            }
          }
        }
      }
    }
  }

  /**
   * Moves inline errors from the global element sub elements.
   *
   * This ensures that they are displayed in the right place.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function moveInlineErrors(array $element, FormStateInterface $form_state) {
    $errors = $form_state->getErrors();
    foreach ($errors as $element_name => $error) {
      // In case error is generated by the field
      // and not our validation hander we clean it.
      $parts = explode('[', $element_name);
      $element_name = array_pop($parts);
      // Reset error on right element.
      $form_state->setError($element[$element_name], $error);
    }
  }

  /**
   * Ajax callback.
   */
  public static function printRate($form, FormStateInterface $form_state) {
    $fees_manager = \Drupal::service('commerce_funds.fees_manager');
    $exchange_rates = $fees_manager->getExchangeRates();
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = reset($triggering_element['#array_parents']);

    $rate_description = '';
    if (!empty($form_state->getUserInput()[$field_name])) {
      $inputs = $form_state->getUserInput()[$field_name][0]['target_id'];
      if ($inputs['currency_left'] != $inputs['currency_right']) {
        $new_amount = $fees_manager->printConvertedAmount($inputs['amount'], $inputs['currency_left'], $inputs['currency_right']);
        $rate_description = t('Conversion rate applied: @exchange-rate% <br> Amount after conversion: @new_amount', [
          '@exchange-rate' => $exchange_rates ? $exchange_rates[$inputs['currency_left']][$inputs['currency_right']]['value'] : 0,
          '@new_amount' => $new_amount,
        ]);
      }
    }

    $form[$field_name]['widget'][0]['target_id']['ajax_container']['markup'] = [
      '#markup' => $rate_description ?: t('Conversion rate applied: 1%'),
      '#attributes' => [
        'id' => ['rate-output'],
      ],
    ];

    return $form[$field_name]['widget'][0]['target_id']['ajax_container'];
  }

}
