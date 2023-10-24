(function ($, Drupal) {
  Drupal.behaviors.calculate_fees = {
    attach: function (context, settings) {
      // Make sure behavior is added only once.
      if (context !== document) {
        return;
      }
      var description = drupalSettings.funds.fees;
      var fees = description.match(/.+: (\d+\.?(\d+)?)(%| .+)( \(min\. (\d+) .+\))?\./);
      console.log(fees);
      if (fees) {
        var rate = fees[1];
        var fixed = fees[5] != undefined ? fees[5] : false;
        // No rates, only fixed fees.
        if (fees[3] != '%') {
          fixed = 'rate';
        }
        $('.funds-amount').each(function() {
          var descriptionField = $(this).siblings('.description');
          $(this).on('keyup', function() {
            // Calculate total.
            var total_rate = total = (parseFloat($(this).val()) + parseFloat($(this).val() * rate / 100)).toFixed(2);
            if (fixed && fixed !== 'rate') {
              var total_fixed = (parseFloat($(this).val()) + parseFloat(fixed)).toFixed(2);
              if (total_fixed > total_rate) {
                total = total_fixed;
              }
            }
            else if (fixed && fixed === 'rate') {
              total = (parseFloat($(this).val()) + parseFloat(rate)).toFixed(2);
            }

            descriptionField.html(description + '<br>' + Drupal.t('Total paid: @total', {'@total': isNaN(total) ? 0 : total}));
          })
        })
      }
    }
  }
})(jQuery, Drupal);
