/**
 * SimpleAdsCharts library.
 */

(function( root, factory ) {
  if ( typeof define === 'function' && define.amd ) {
    define( factory );
  } else if ( typeof exports === 'object' ) {
    module.exports = factory();
  } else {
    root.SimpleAdsCharts = factory();
  }
}( this, function() {

  "use strict";

  var chartColors = {};
  var chartLabels = {};

  function SimpleAdsCharts(Drupal, drupalSettings) {
    this.Drupal = Drupal;
    this.drupalSettings = drupalSettings;
    chartColors = {
      clicks: isRgb(drupalSettings.simpleads.chartColors.clicks, 'rgb(27,163,156)'),
      clicks_unique: isRgb(drupalSettings.simpleads.chartColors.clicks_unique, 'rgb(40,162,40)'),
      impressions: isRgb(drupalSettings.simpleads.chartColors.impressions, 'rgb(255,69,0)'),
      impressions_unique: isRgb(drupalSettings.simpleads.chartColors.impressions_unique, 'rgb(255,0,255)'),
      ctr: isRgb(drupalSettings.simpleads.chartColors.ctr, 'rgb(255,0,0)')
    };
    chartLabels = {
      clicks: this.Drupal.t('Clicks'),
      clicks_unique: this.Drupal.t('Unique clicks'),
      impressions: this.Drupal.t('Impressions'),
      impressions_unique: this.Drupal.t('Unique impressions'),
      ctr: this.Drupal.t('CTR')
    };
    this.noData = Drupal.t('No data available');
  };

  SimpleAdsCharts.prototype.allTime = function(element, data) {
    var chartConfig = getLineChartOptions(this.Drupal.t('Dates'), this.Drupal.t('Values'));
    chartConfig.data.labels = [];
    chartConfig.data.datasets = [];
    var aggregated = [];
    for (var i = 0; i < data.length; i++) {
      var sDate = data[i].month + ' ' + data[i].year;
      if (chartConfig.data.labels.indexOf(sDate) === -1) {
        chartConfig.data.labels.push(sDate);
      }
      if (aggregated[sDate] === undefined) {
        aggregated[sDate] = [];
        aggregated[sDate]['clicks'] = 0;
        aggregated[sDate]['clicks_unique'] = 0;
        aggregated[sDate]['impressions'] = 0;
        aggregated[sDate]['impressions_unique'] = 0;
        aggregated[sDate]['ctr'] = 0;
      }
      aggregated[sDate]['clicks'] += parseInt(data[i].clicks);
      aggregated[sDate]['clicks_unique'] += parseInt(data[i].clicks_unique);
      aggregated[sDate]['impressions'] += parseInt(data[i].impressions);
      aggregated[sDate]['impressions_unique'] += parseInt(data[i].impressions_unique);
      aggregated[sDate]['ctr'] += parseFloat(data[i].ctr);
    }
    renderLineChart(element, chartConfig, aggregated);
  };

  SimpleAdsCharts.prototype.daysAgo = function(element, limit, data) {
    var chartConfig = getLineChartOptions(this.Drupal.t('Days'), this.Drupal.t('Values'));
    chartConfig.data.labels = [];
    chartConfig.data.datasets = [];
    var aggregated = [];
    for (var i = 0; i < data.length; i++) {
      var sDate = data[i].date;
      if (limit > i) {
        chartConfig.data.labels.push(sDate);
      }
      aggregated[sDate] = [];
      aggregated[sDate]['clicks'] = parseInt(data[i].clicks);
      aggregated[sDate]['clicks_unique'] = parseInt(data[i].clicks_unique);
      aggregated[sDate]['impressions'] = parseInt(data[i].impressions);
      aggregated[sDate]['impressions_unique'] = parseInt(data[i].impressions_unique);
      aggregated[sDate]['ctr'] = parseFloat(data[i].ctr);
    }
    renderLineChart(element, chartConfig, aggregated);
  };

  SimpleAdsCharts.prototype.today = function(clicksElement, impressionsElement, data) {
    var ctxClicks = clicksElement.getContext('2d');
    if (data.clicks !== undefined) {
      var chartConfig = getPieChartOptions(chartLabels.clicks, {
          datasets: [{
            data: [data.clicks_unique, data.clicks, (data.clicks_unique / data.clicks)],
            backgroundColor: [chartColors.clicks, chartColors.clicks_unique, chartColors.ctr],
            label: ''
          }],
          labels: [chartLabels.clicks_unique, chartLabels.clicks, chartLabels.ctr]
        });
      new Chart(ctxClicks, chartConfig);
    }
    else {
      noDataText(ctxClicks, this.noData);
    }
    var ctxImpressions = impressionsElement.getContext('2d');
    if (data.impressions !== undefined) {
      var chartConfig = getPieChartOptions(chartLabels.impressions, {
          datasets: [{
            data: [data.impressions_unique, data.impressions, (data.impressions_unique / data.impressions)],
            backgroundColor: [chartColors.impressions, chartColors.impressions_unique, chartColors.ctr],
            label: ''
          }],
          labels: [chartLabels.impressions_unique, chartLabels.impressions, chartLabels.ctr]
        });
      new Chart(ctxImpressions, chartConfig);
    }
    else {
      noDataText(ctxImpressions, this.noData);
    }
  };

  SimpleAdsCharts.prototype.table = function(element, data) {
    var arrayData = [];
    var arrayCsvData = [];
    var arrayTitles = [
      {title: this.Drupal.t('Date')},
      {title: this.Drupal.t('Clicks')},
      {title: this.Drupal.t('Unique clicks')},
      {title: this.Drupal.t('Impressions')},
      {title: this.Drupal.t('Unique impressions')},
      {title: this.Drupal.t('CTR')}
    ];
    for (var key of Object.keys(data)) {
      arrayData.push(new Array(
        data[key].date,
        data[key].clicks, data[key].clicks_unique,
        data[key].impressions, data[key].impressions_unique,
        data[key].ctr + '%'
      ));
      arrayCsvData.push(new Array(
        '"' + data[key].date + '"',
        data[key].clicks, data[key].clicks_unique,
        data[key].impressions, data[key].impressions_unique,
        data[key].ctr + '%'
      ));
    }
    element.DataTable({
      searching: false,
      sorting: false,
      data: arrayData,
      columns: arrayTitles,
      'language': {
        'info': this.Drupal.t('Showing page _PAGE_ of _PAGES_'),
        'infoEmpty': '',
        'emptyTable': this.noData,
        'lengthMenu': this.Drupal.t('Show _MENU_ records'),
        'paginate': {
          'next': this.Drupal.t('Next'),
          'previous': this.Drupal.t('Previous')
        }
      }
    });
    var filterWrapper = jQuery('#simpleads-table_length');
    filterWrapper.append('<a href="#" id="simpleads-export-csv" class="button">' + this.Drupal.t('Export as CSV') + '</a>');
    filterWrapper.find('#simpleads-export-csv').on('click', function(e) {

      // Export as CSV.
      var csvContent = '';
      var mimeType = 'text/csv;encoding:utf-8';
      var today = new Date().toISOString().slice(0, 10);
      var fileName = 'simpleads-' + today + '.csv';

      arrayTitles.forEach(function(infoArray, index) {
        csvContent += infoArray.title + (index < arrayTitles.length - 1 ? ',' : '\n');
      });
      arrayCsvData.forEach(function(infoArray, index) {
        var dataString = infoArray.join(',');
        csvContent += index < data.length ? dataString + '\n' : dataString;
      });
      var a = document.createElement('a');
      if (navigator.msSaveBlob) {
        navigator.msSaveBlob(new Blob([csvContent], {
          type: mimeType
        }), fileName);
      }
      else if (URL && 'download' in a) {
        a.href = URL.createObjectURL(new Blob([csvContent], {
          type: mimeType
        }));
        a.setAttribute('download', fileName);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      }
      else {
        location.href = 'data:application/octet-stream,' + encodeURIComponent(csvContent);
      }
      e.preventDefault();
    });
  };

  function renderLineChart(element, chartsConfig, data) {
    var clicks = [];
    for (var key of Object.keys(data)) {
      clicks.push(data[key].clicks);
    }
    chartsConfig.data.datasets.push({
      label: chartLabels.clicks,
      backgroundColor: chartColors.clicks,
      borderColor: chartColors.clicks,
      data: clicks,
      fill: false,
    });

    var clicks_unique = [];
    for (var key of Object.keys(data)) {
      clicks_unique.push(data[key].clicks_unique);
    }
    chartsConfig.data.datasets.push({
      label: chartLabels.clicks_unique,
      backgroundColor: chartColors.clicks_unique,
      borderColor: chartColors.clicks_unique,
      data: clicks_unique,
      fill: false,
    });

    var impressions = [];
    for (var key of Object.keys(data)) {
      impressions.push(data[key].impressions);
    }
    chartsConfig.data.datasets.push({
      label: chartLabels.impressions,
      backgroundColor: chartColors.impressions,
      borderColor: chartColors.impressions,
      data: impressions,
      fill: false,
    });

    var impressions_unique = [];
    for (var key of Object.keys(data)) {
      impressions_unique.push(data[key].impressions_unique);
    }
    chartsConfig.data.datasets.push({
      label: chartLabels.impressions_unique,
      backgroundColor: chartColors.impressions_unique,
      borderColor: chartColors.impressions_unique,
      data: impressions_unique,
      fill: false,
    });

    var ctr = [];
    for (var key of Object.keys(data)) {
      ctr.push(data[key].ctr);
    }
    chartsConfig.data.datasets.push({
      label: chartLabels.ctr,
      backgroundColor: chartColors.ctr,
      borderColor: chartColors.ctr,
      data: ctr,
      fill: false,
    });

    var ctx = element.getContext('2d');
    new Chart(ctx, chartsConfig);
  };

  function noDataText(ctx, text) {
    ctx.font = '14px Arial';
    ctx.fillText(text, 10, 50);
  };

  function getLineChartOptions(xAxesLabel, yAxesLabel) {
    return {
      type: 'line',
      data: {
        labels: [],
        datasets: []
      },
      options: {
        responsive: true,
        tooltips: {
          mode: 'index',
          intersect: false,
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          xAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: xAxesLabel
            }
          }],
          yAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: yAxesLabel
            }
          }]
        }
      }
    };
  };

  function getPieChartOptions(title, data) {
    return {
      type: 'pie',
      data: data,
      options: {
        title: {
          display: true,
          text: title
        },
        responsive: true
      }
    };
  };

  function isRgb(rgb, d) {
    var rxValidRgb = /([R][G][B][A]?[(]\s*([01]?[0-9]?[0-9]|2[0-4][0-9]|25[0-5])\s*,\s*([01]?[0-9]?[0-9]|2[0-4][0-9]|25[0-5])\s*,\s*([01]?[0-9]?[0-9]|2[0-4][0-9]|25[0-5])(\s*,\s*((0\.[0-9]{1})|(1\.0)|(1)))?[)])/i
    if (rxValidRgb.test(rgb)) {
      return rgb;
    }
    else {
      return d;
    }
  };

  return SimpleAdsCharts;

}));
