(function ($, Drupal) {
  Drupal.behaviors.delayed_submit = {
    attach: function (context, settings) {
      $('.delayed-input-submit').each(function () {
        var self = $(this);
        var timeout = null;
        var delay = self.data('delay') || 1000;
        var triggerEvent = self.data('event') || "end_typing";

        self.unbind('keyup').keyup(function () {
          clearTimeout(timeout);
          timeout = setTimeout(function () {
            if (self.val()) {
              self.trigger(triggerEvent);
            }
          }, delay);
        });
      });
    }
  }
})(jQuery, Drupal);
