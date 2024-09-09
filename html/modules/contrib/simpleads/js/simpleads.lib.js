/**
 * SimpleAds library.
 */

(function( root, factory ) {
  if ( typeof define === 'function' && define.amd ) {
    define( factory );
  } else if ( typeof exports === 'object' ) {
    module.exports = factory();
  } else {
    root.SimpleAds = factory();
  }
}( this, function() {

  "use strict";

  var pvCookieName = 'sads_pvc';
  var pvCookieTimeoutName = 'sads_pvt';
  var pvCounter = Cookies.get(pvCookieName);
  var pvTimeout = Cookies.get(pvCookieTimeoutName);
  var pvCounterCookieParams = {expires: 1, path: '/'};

  function SimpleAds() {};

  /**
   * Click advertisement element.
   */
  SimpleAds.prototype.clickAd = function(adElement, callback) {
    adElement.on('click', function() {
      // Track click.
      trackClick(jQuery(this).data('id'));
      callback();
    });
  };

  /**
   * Track click.
   */
  SimpleAds.prototype.trackClick = function(entity_id) {
    trackClick(entity_id);
  };

  /**
   * Track impression.
   */
  SimpleAds.prototype.trackImpression = function(entity_id) {
    trackImpression(entity_id);
  };

  /**
   * Load group ads.
   */
  SimpleAds.prototype.getAds = function(group, current_node_id, node_ref_field, simpleads_ref_field, callback) {
    jQuery.ajax({url: getDomainWithPort() + 'simpleads/group/' + group + '/' + current_node_id + '/' + node_ref_field + '/' + simpleads_ref_field + '?_format=json'})
      .done(function(data) {
        callback(data);
    });
  };

  /**
   * Load referenced ads.
   */
  SimpleAds.prototype.getAdsReference = function(entity_type, field_name, entity_id, callback) {
    jQuery.ajax({url: getDomainWithPort() + 'simpleads/reference/' + entity_type + '/' + field_name + '/' + entity_id + '?_format=json'})
      .done(function(data) {
        callback(data);
    });
  };

  /**
   * Load views ads.
   */
  SimpleAds.prototype.getAdsViews = function(view_id, display_id, callback) {
    jQuery.ajax({url: getDomainWithPort() + 'simpleads/views/' + view_id + '/' + display_id + '?_format=json'})
      .done(function(data) {
        callback(data);
    });
  };

  /**
   * Get templated ads.
   */
  SimpleAds.prototype.getAdsHtml = function(type, data) {
    if (data.data !== null) {
      var items = shuffleItems(data.data.items);
      if (type == 'slider') {
        var output = '';
        for (var i = 0; i < data.data.count; ++i) {
          output += '<li>' + itemTemplate(items[i]) + '</li>';
        }
        return '<ul class="simpleads-slider">' + output + '</ul>';
      }
      else {
        return '<div class="simpleads-random">' + itemTemplate(items[0]) + '</div>';
      }
    }
  };

  /**
   * Get random templated ads.
   */
  SimpleAds.prototype.getRandomAdsHtml = function(data, limit) {
    if (data.data !== null) {
      var items = shuffleItems(data.data.items);
      var output = '';
      for (var i = 0; i < data.data.count; ++i) {
        if (i < limit) {
          output += '<li>' + itemTemplate(items[i]) + '</li>';
          trackImpression(items[i].entity_id);
        }
      }
      return '<ul class="simpleads-multiple-random">' + output + '</ul>';
    }
  };

  /**
   * Count page visits and track counter in cookies.
   */
  SimpleAds.prototype.countPageVisits = function() {
    if (pvTimeout === undefined || pvTimeout === 0) {
      // Count page views.
      if ( pvCounter === undefined ) {
        pvCounter = 1;
      }
      else {
        pvCounter = parseInt( pvCounter ) + 1;
      }
      Cookies.set(pvCookieName, pvCounter, pvCounterCookieParams);
    }
  };

  /**
   * Get page counter.
   */
  SimpleAds.prototype.getPageVisitsCount = function() {
    if (pvTimeout === undefined || pvTimeout === 0) {
      return pvCounter;
    }
    return 0;
  };

  /**
   * Reset page counter.
   */
  SimpleAds.prototype.resetPageVisits = function() {
    Cookies.set(pvCookieName, 0, pvCounterCookieParams);
  };

  /**
   * Set page visits timeout.
   */
  SimpleAds.prototype.setPageVisitsTimeout = function(hours) {
    Cookies.set(pvCookieTimeoutName, 1, {expires: hours / 24, path: '/'});
  };

  /**
   * Load all statistics.
   */
  SimpleAds.prototype.loadStats = function(entity_id, callback) {
    jQuery.ajax({url: getDomainWithPort() + '/simpleads/stats/data/' + entity_id + '?_format=json'})
      .done(function(data) {
        callback(data);
    });
  };

  /**
   * Advertisement clickable template.
   */
  function itemTemplate(ad) {
    return '<a href="' + ad.url + '" data-id="' + ad.entity_id + '" target="' + ad.url_target + '">' + ad.html + '</a>';
  }

  /**
   * Track clicks private method.
   */
  function trackClick(entity_id) {
    requestCsrf('simpleads/click/' + entity_id);
  };

  /**
   * Track impressions private method.
   */
  function trackImpression(entity_id) {
    requestCsrf('simpleads/impression/' + entity_id);
  };

  /**
   * Get CSRF token before making POST request.
   */
  function requestCsrf(endpoint, callback) {
    jQuery.ajax({
      url: getDomainWithPort() + 'session/token',
    })
    .done(function(csrf) {
      jQuery.ajax({
        type: 'post',
        url: drupalSettings.path.baseUrl + endpoint + '?_format=json',
        headers: {
          'X-CSRF-Token': csrf,
          'Content-Type': 'application/json'
        }
      });
    });
  };

  /**
   * Shuffle ads.
   */
  function shuffleItems(array) {
    var counter = array.length;
    while (counter > 0) {
      var index = Math.floor(Math.random() * counter);
      counter--;
      var temp = array[counter];
      array[counter] = array[index];
      array[index] = temp;
    }
    return array;
  };

  /**
   * Helper function to get current domain.
   */
  function getDomainWithPort() {
    if (!window.location.origin) {
      window.location.origin = window.location.protocol + "//" + window.location.hostname + (window.location.port ? ':' + window.location.port: '');
    }
    return window.location.origin + drupalSettings.path.baseUrl;
  };

  return SimpleAds;

}));
