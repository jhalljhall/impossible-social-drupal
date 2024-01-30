/**
 * @file
 * JavaScript behaviors for Commerce Webform Order handlers.
 */

(($, Drupal, once) => {
  /**
   * Trigger other input field events.
   *
   * @param {boolean} show
   *   TRUE will display the text field. FALSE with hide and clear the text
   *   field.
   * @param {object} $element
   *   The input (text) field to be toggled.
   * @param {string} effect
   *   Effect.
   */
  function toggleOther(show, $element) {
    const $input = $element.find('input');

    if (show) {
      const value = $input.data('webform-value');
      if (typeof value !== 'undefined') {
        // Trigger change and autocomplete close event.
        $input.trigger('change').trigger('autocompleteclose');
      }
    } else {
      // Trigger change event.
      $input.trigger('change');
    }
  }

  /**
   * Attach handlers to select other elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.commerceWebformOrderHandlerCommerceWebformOrder = {
    attach(context) {
      $(
        once(
          'commerce-webform-order--handler',
          '.commerce-webform-order--purchasable-entity',
          context,
        ),
      ).each(() => {
        const $element = $(this);

        const $select = $element.find('select');
        const $input = $element.find('.js-webform-select-other-input');

        $select.on('change', () => {
          const isOtherSelected = $select
            .find('option[value="_other_"]')
            .is(':selected');
          toggleOther(isOtherSelected, $input);
        });

        const isOtherSelected = $select
          .find('option[value="_other_"]')
          .is(':selected');
        toggleOther(isOtherSelected, $input);
      });
    },
  };
})(jQuery, Drupal, once);
