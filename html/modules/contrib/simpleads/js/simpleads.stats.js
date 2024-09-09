(function ($, Drupal, drupalSettings) {

  var lib = new SimpleAds();
  var charts = new SimpleAdsCharts(Drupal, drupalSettings);
  var tabsIndexed = false;
  var statsLoaded = false;

  Drupal.behaviors.SimpleAdsStats = {
    attach: function (context, settings) {

      var entity_id = settings.simpleads.entity_id;
      var activeTabClass = 'is-active';
      var defaultTab = settings.simpleads.statsDefaultTab;
      var $tabs = $('#simpleads-stats-tabs');
      var $tabItem = $tabs.find('li');
      var $tabsContent = $('#simpleads-stats-tabs-content');
      var $tabContentItem = $tabsContent.find('.tab-content');

      if (settings.simpleads.stats_tabs !== null && !tabsIndexed) {
        $.each(settings.simpleads.stats_tabs, function(key, val) {
          $tabs.append('<li class="tabs__tab el-' + key + ' ' + (key == defaultTab ? activeTabClass : '') + '"><a href="#' + key + '" data-report="' + key + '" class="button ' + activeTabClass + '">' + val + '</a></li>');
          $tabsContent.append('<div class="tab-content '  + (key == defaultTab ? activeTabClass : '')  + ' ' + key + '">' + settings.simpleads.stats_tabs_content[key] + '</div>');
        });
        tabsIndexed = true;
        $tabItem = $tabs.find('li');
        $tabContentItem = $tabsContent.find('.tab-content');
      }

      $tabItem.find('a').on('click', function(e) {
        var $el = $(this);
        var report = $el.data('report');
        $tabItem.removeClass(activeTabClass);
        $el.parent().addClass(activeTabClass);
        $tabContentItem.removeClass(activeTabClass);
        $tabsContent.find('.tab-content.' + report).addClass(activeTabClass);
        e.preventDefault();
      });

      lib.loadStats(entity_id, function(data) {
        if (data.data !== null && !statsLoaded) {
          charts.allTime($('#simpleads-all-time').get(0), data.data.all);
          charts.daysAgo($('#simpleads-last-month').get(0), 30, data.data.all);
          charts.daysAgo($('#simpleads-last-week').get(0), 7, data.data.all);
          charts.today($('#simpleads-pie-clicks').get(0), $('#simpleads-pie-impressions').get(0), data.data.today);
          charts.table($('#simpleads-table'), data.data.all);
          statsLoaded = true;
        }
      });

      $tabs.find('.tabs__tab a').on('click', function(e) {
        e.preventDefault();
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
