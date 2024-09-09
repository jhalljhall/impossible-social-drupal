(function ($, Drupal) {

  var lib = new SimpleAds();

  Drupal.behaviors.SimpleAdsReference = {
    attach: function (context, settings) {

      $('.simpleads-reference-field', context).each(function() {

        var $el = $(this);
        var entity_type = $el.data('entity-type'),
            field_name = $el.data('field-name'),
            entity_id = $el.data('entity-id'),
            rotationType = $el.data('rotation-type'),
            rotationOptions = $el.data('rotation-options'),
            countImpressionsOnce = $el.data('impressions'),
            isModal = $el.data('is-modal'),
            modalOptions = $el.data('modal-options'),
            shownAds = {};

        var showAdGroup = function() {
          // Get all ads.
          lib.getAdsReference(entity_type, field_name, entity_id, function(data) {
            if (rotationType == 'loop') {
              // Slick slider
              $el.html(lib.getAdsHtml('slider', data));
              var slider = $el.find('.simpleads-slider');
              slider.slick(rotationOptions);
              var initialEntityId = slider.find('li a').first().data('id');
              lib.trackImpression(initialEntityId);
              if (countImpressionsOnce === true) {
                shownAds[entity_type + ':' + initialEntityId] = true;
              }

              slider.on('afterChange', function(e, slick, currentSlide, nextSlide) {
                var entityId = $(slick.$slides[currentSlide]).find('a').data('id');
                // Track impression.
                if (countImpressionsOnce === true) {
                  if (shownAds[entity_type + ':' + entityId] === undefined) {
                    lib.trackImpression(entityId);
                    shownAds[entity_type + ':' + entityId] = true;
                  }
                }
                else {
                  lib.trackImpression(entityId);
                }
              });
              lib.clickAd(slider.find('li a'), function() {
                if ($.modal.isActive()) {
                  $.modal.close();
                }
              });
            }
            else {
              // Random
              $el.html(lib.getAdsHtml('default', data));
              var ad = $el.find('.simpleads-random a');
              lib.trackImpression(ad.data('id'));
              lib.clickAd(ad, function() {
                if ($.modal.isActive()) {
                  $.modal.close();
                }
              });
            }
          });
        };

        if (isModal) {
          lib.countPageVisits();
          if (modalOptions.page_visits > 0 && lib.getPageVisitsCount() >= modalOptions.page_visits) {
            $el.modal();
            if ($.modal.isActive()) {
              lib.setPageVisitsTimeout(modalOptions.modal_visits_timeout);
              showAdGroup();
            }
            lib.resetPageVisits();
          }
        }
        else {
          showAdGroup();
        }

      });

    }
  };

})(jQuery, Drupal);
