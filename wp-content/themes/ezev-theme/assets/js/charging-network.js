/**
 * Charging Network Page Controller (Phase 4.1 P0)
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var mapsConfig = (window.ezevThemeData && window.ezevThemeData.mapsConfig) || {};
  var macroMap = null;
  var markers = [];

  // Load Real Station Collection
  if (typeof EzevDataClient !== 'undefined') {
    EzevDataClient.getStations()
      .then(function (stations) {
        var stats = EzevDataClient.getNetworkStats(stations);
        updateNetworkKPIs(stats);
        renderDynamicRegionalCards(stations, stats);
        initMacroMap(stations);
      })
      .catch(function () {
        // Fallback
      });
  }

  function updateNetworkKPIs(stats) {
    var stCount = document.getElementById('ezevNetTotalStations');
    var ptCount = document.getElementById('ezevNetTotalPorts');
    var coCount = document.getElementById('ezevNetTotalCountries');

    if (stCount) stCount.textContent = (stats.totalStations || 0) + '+';
    if (ptCount) ptCount.textContent = (stats.totalPorts || 0) + '+';
    if (coCount) coCount.textContent = stats.countriesCount || '1';
  }

  function renderDynamicRegionalCards(stations, stats) {
    var container = document.getElementById('ezevRegionalCardsContainer');
    if (!container) return;

    var countries = stats.countries || {};
    var countryKeys = Object.keys(countries);

    if (countryKeys.length === 0) {
      container.innerHTML = '<p style="color: #94A3B8;">No regional data available.</p>';
      return;
    }

    var html = '';
    countryKeys.forEach(function (cName) {
      // Filter stations in this country
      var cStations = stations.filter(function (s) {
        return (s.address && s.address.country === cName) || (s.address && s.address.country_code === cName);
      });

      var totalSt = cStations.length;
      var totalPorts = 0;
      var cities = {};

      cStations.forEach(function (s) {
        if (s.ports && typeof s.ports.total === 'number') totalPorts += s.ports.total;
        if (s.address && s.address.city) cities[s.address.city] = true;
      });

      var cityList = Object.keys(cities).slice(0, 5).join(' · ');

      html += '<div class="ezev-regional-card">' +
        '<div class="ezev-regional-card-head">' +
        '<div class="ezev-regional-country-name">🌐 ' + cName + '</div>' +
        '<span class="ezev-badge ezev-badge-available" style="font-size: 11px;">Active</span>' +
        '</div>' +
        '<div class="ezev-regional-metrics">' +
        '<div class="ezev-regional-metric-item"><strong>' + totalSt + '+</strong><span>Stations</span></div>' +
        '<div class="ezev-regional-metric-item"><strong>' + totalPorts + '+</strong><span>Charging Points</span></div>' +
        '</div>' +
        '<div class="ezev-regional-cities"><strong>Key Cities:</strong> ' + (cityList || 'Nationwide') + '</div>' +
        '</div>';
    });

    container.innerHTML = html;
  }

  function initMacroMap(stations) {
    var mapContainer = document.getElementById('ezevMacroMap');
    if (!mapContainer || !mapsConfig.hasKey || typeof google === 'undefined' || !google.maps) {
      return;
    }

    macroMap = new google.maps.Map(mapContainer, {
      center: mapsConfig.defaultCenter || { lat: 14.5547, lng: 121.0244 },
      zoom: 6,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
      zoomControl: true
    });

    var bounds = new google.maps.LatLngBounds();
    var hasCoords = false;

    stations.forEach(function (s) {
      var lat = (s.location && typeof s.location.lat === 'number') ? s.location.lat : null;
      var lng = (s.location && typeof s.location.lng === 'number') ? s.location.lng : null;
      if (lat === null || lng === null) return;

      var pos = { lat: lat, lng: lng };
      bounds.extend(pos);
      hasCoords = true;

      var marker = new google.maps.Marker({
        position: pos,
        map: macroMap,
        title: s.name,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 9,
          fillColor: '#73B72A',
          fillOpacity: 1,
          strokeColor: '#FFFFFF',
          strokeWeight: 2
        }
      });

      marker.addListener('click', function () {
        if (s.url) {
          window.location.href = s.url;
        }
      });

      markers.push(marker);
    });

    if (hasCoords && stations.length > 1) {
      macroMap.fitBounds(bounds);
    }
  }
});