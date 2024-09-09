(function ($, Drupal, drupalSettings) {

  var typeFieldMapping = drupalSettings.simpleads.advertisement_form;

  Drupal.behaviors.SimpleAdsAdvertisementForm = {
    attach: function (context, settings) {
      var $campaignSelect = $('.field--name-campaign select', context);
      _isCampaign($campaignSelect.find(':selected').val(), context);
      $campaignSelect.on('change', function(e) {
        _isCampaign($(this).val(), context);
      });
      var $typeSelect = $('.field--name-type select', context);
      _typeFields($typeSelect.find(':selected').val(), context);
      $typeSelect.on('change', function(e) {
        _typeFields($(this).val(), context);
      });
    }
  };

  /**
   * Hide/Show start/end date fields when campaign is selected.
   */
  function _isCampaign(val, context) {
    var dateFields = '.field--name-start-date, .field--name-end-date';
    $(dateFields, context).hide();
    if (val === '_none') {
      $(dateFields, context).show();
    }
  };

  /**
   * Make sure selected ad type fields are visible.
   */
  function _typeFields(type, context) {
    if (type in typeFieldMapping) {
      for (var key in typeFieldMapping) {
        $(typeFieldMapping[key], context).hide();
      }
      $(typeFieldMapping[type], context).show();
    }
  };

})(jQuery, Drupal, drupalSettings);
