(function ($, Drupal) {

  var lib = new SimpleAds();

  Drupal.behaviors.SimpleAdsViews = {
    attach: function (context, settings) {

      $('.simpleads-views-view', context).each(function() {

        var $el = $(this);
        var view_id = $el.data('view-id'),
            display_id = $el.data('display-id'),
            rotationType = $el.data('rotation-type'),
            multipleRandomLimit = $el.data('random-limit'),
            rotationOptions = $el.data('rotation-options'),
            countImpressionsOnce = $el.data('impressions'),
            isModal = $el.data('is-modal'),
            modalOptions = $el.data('modal-options'),
            shownAds = {};

        var showAdView = function() {
          // Get all ads.
          lib.getAdsViews(view_id, display_id, function(data) {
            if (rotationType == 'loop') {
              // Slick slider
              $el.html(lib.getAdsHtml('slider', data));
              var slider = $el.find('.simpleads-slider');
              slider.slick(rotationOptions);
              var initialEntityId = slider.find('li a').first().data('id');
              lib.trackImpression(initialEntityId);
              if (countImpressionsOnce === true) {
                shownAds[view_id + ':' + initialEntityId] = true;
              }

              slider.on('afterChange', function(e, slick, currentSlide, nextSlide) {
                var entityId = $(slick.$slides[currentSlide]).find('a').data('id');
                // Track impression.
                if (countImpressionsOnce === true) {
                  if (shownAds[view_id + ':' + entityId] === undefined) {
                    lib.trackImpression(entityId);
                    shownAds[view_id + ':' + entityId] = true;
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
            else if (rotationType == 'multiple') {
              $el.html(lib.getRandomAdsHtml(data, multipleRandomLimit));
              var ad = $el.find('.simpleads-multiple-random a');
              lib.trackImpression(ad.data('id'));
              lib.clickAd(ad, function() {});
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
              showAdView();
            }
            lib.resetPageVisits();
          }
        }
        else {
          showAdView();
        }

      });

    }
  };

})(jQuery, Drupal);
