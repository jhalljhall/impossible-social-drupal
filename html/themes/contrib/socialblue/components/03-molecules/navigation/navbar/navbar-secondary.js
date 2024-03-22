(function ($, once, debounce) {

  /*
  ** Behavior when the number of items in the secondary navigation
  * is too big.
   */
  Drupal.behaviors.navbarSecondaryScrollable = {
    attach: function (context) {
      const navbarSecondary = (context.classList && context.classList.contains('block--banner-auto-generated')) ? [context] : context.querySelectorAll('.navbar-secondary .navbar-scrollable');
      if (!navbarSecondary.length) { return; }
        // Sometimes after reload page, we can not find elements on the
        // secondary navigation. Promise function fixed it.
      navbarSecondary.forEach(function (navScrollElem) {
        const navScroll = $(navScrollElem);
        const navSecondary = navScroll.find('.nav', context);
        const items = navSecondary.find('li', context);
        const navScrollWidth = navScroll.width();
        const navSecondaryWidth = navSecondary.width();
        const regionContent = $('.region--content');

        // Secondary navigation behaviour,
        function secondaryNavBehaviour() {
          if($(window).width() >= 900) {
            if (navSecondaryWidth > navScrollWidth) {
              navSecondary.each(function () {
                const $this = $(this);
                let total = 0;

                // Add `visible-item` class to the list items which displayed in the current secondary
                // navigation width
                items.removeClass('visible-item');
                $this.find('.caret').remove();

                if(items.parent().is('div')) {
                  items.unwrap();
                }

                for(let i = 0; i < items.length; ++i) {
                  total += $(items[i]).width();

                  if((navScroll.width() - 50) <= total) {
                    break;
                  }

                  $(items[i]).addClass('visible-item');
                }

                // Create wrapper for visible items.
                $this.find('li.visible-item')
                  .wrapAll('<div class="visible-list"></div>');

                // Create wrapper for hidden items.
                $this.find('li:not(.visible-item)')
                  .wrapAll('<div class="hidden-list card" />');

                // Add caret.
                $this.append('<span class="caret"></span>');

                const hiddenList = $this.find('.hidden-list');
                const cart = $this.find('.caret');

                cart.on('click', function () {
                  if (hiddenList.is(":hidden")) {
                    regionContent.addClass('js--z-index');
                    hiddenList.slideDown('300');
                  } else {
                    hiddenList.slideUp('300', function() {
                      regionContent.removeClass('js--z-index');
                    });
                  }

                  $(this).toggleClass('active');
                });

                $(document).on('click', function(event) {
                  event.stopPropagation();

                  if ($(event.target).closest('.navbar-secondary').length) return;
                  hiddenList.slideUp(300, function() {
                    regionContent.removeClass('js--z-index');
                  });
                  cart.removeClass('active');
                });
              });
            } else {
              navSecondary.css('display', 'flex');
            }
          }
          else {
            navSecondary.each(function () {
              const $this = $(this);

              // Unwrap list items.
              // Remove extra classes/elements.
              items.removeClass('visible-item');
              $this.find('.caret').remove();

              if(items.parent().is('div')) {
                items.unwrap();
              }
            });
          }
        }
        secondaryNavBehaviour();

        const returnedFunction = debounce(function() {
          secondaryNavBehaviour();
        }, 250);

        window.addEventListener('resize', returnedFunction);
      });
    }
  };

})(jQuery, once, Drupal.debounce);
