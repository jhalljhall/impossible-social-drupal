(function ($, Drupal, drupalSettings) {

  var typeFieldMapping = drupalSettings.simpleads.campaign_form;

  Drupal.behaviors.SimpleAdsCampaignForm = {
    attach: function (context, settings) {
      var $typeSelect = $('.field--name-type input[type="checkbox"]', context);
      _typeFields($typeSelect, context);
      $typeSelect.on('click', function(e) {
        _typeFields($typeSelect, context);
      });
    }
  };

  /**
   * Hide/Show campaign type fields.
   */
  function _typeFields(elements, context) {
    elements.each(function() {
      if ($(this).is(':checked')) {
        $(typeFieldMapping[$(this).val()], context).show();
      }
      else {
        $(typeFieldMapping[$(this).val()], context).hide();
      }
    });
  };

})(jQuery, Drupal, drupalSettings);
