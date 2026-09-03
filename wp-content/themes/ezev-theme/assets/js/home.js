/**
 * Home Page Controller (Phase 4.1 P0)
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var mapsConfig = (window.ezevThemeData && window.ezevThemeData.mapsConfig) || {};
  var miniMap = null;
  var markers = [];

  // FAQs Accordion
  var faqItems = document.querySelectorAll('.ezev-faq-item');
  faqItems.forEach(function (item) {
    var q = item.querySelector('.ezev-faq-question');
    if (q) {
      q.addEventListener('click', function () {
        var isActive = item.classList.contains('active');
        faqItems.forEach(function (f) { f.classList.remove('active'); });
        if (!isActive) {
          item.classList.add('active');
        }
      });
    }
  });

  // Load Real Station Data for Dynamic Stats & Mini Map
  if (typeof EzevDataClient !== 'undefined') {
    EzevDataClient.getStations()
      .then(function (stations) {
        // Derive real stats (no fake data)
        var stats = EzevDataClient.getNetworkStats(stations);

        var stCountElem = document.getElementById('ezevStatTotalStations');
        var ptCountElem = document.getElementById('ezevStatTotalPorts');
        var coCountElem = document.getElementById('ezevStatCountries');

        if (stCountElem) stCountElem.textContent = (stats.totalStations || 0) + '+';
        if (ptCountElem) ptCountElem.textContent = (stats.totalPorts || 0) + '+';
        if (coCountElem) coCountElem.textContent = stats.countriesCount || '1';

        // Initialize Mini Map
        initMiniMap(stations);
      })
      .catch(function () {
        // Soft fallback
      });
  }

  function initMiniMap(stations) {
    var mapContainer = document.getElementById('ezevHomeMiniMap');
    if (!mapContainer || !mapsConfig.hasKey || typeof google === 'undefined' || !google.maps) {
      return;
    }

    miniMap = new google.maps.Map(mapContainer, {
      center: mapsConfig.defaultCenter || { lat: 14.5547, lng: 121.0244 },
      zoom: 11,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
      zoomControl: true
    });

    var bounds = new google.maps.LatLngBounds();
    var hasCoords = false;

    stations.forEach(function (s, index) {
      var lat = (s.location && typeof s.location.lat === 'number') ? s.location.lat : null;
      var lng = (s.location && typeof s.location.lng === 'number') ? s.location.lng : null;
      if (lat === null || lng === null) return;

      var pos = { lat: lat, lng: lng };
      bounds.extend(pos);
      hasCoords = true;

      var marker = new google.maps.Marker({
        position: pos,
        map: miniMap,
        title: s.name,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 8,
          fillColor: '#73B72A',
          fillOpacity: 1,
          strokeColor: '#FFFFFF',
          strokeWeight: 2
        }
      });

      marker.addListener('click', function () {
        updatePreviewCard(s);
        miniMap.panTo(pos);
      });

      markers.push(marker);

      // Set first station as preview initially
      if (index === 0) {
        updatePreviewCard(s);
      }
    });

    if (hasCoords && stations.length > 1) {
      miniMap.fitBounds(bounds);
    }
  }

  function updatePreviewCard(station) {
    var title = document.getElementById('ezevPreviewTitle');
    var addr = document.getElementById('ezevPreviewAddress');
    var ports = document.getElementById('ezevPreviewPorts');
    var power = document.getElementById('ezevPreviewPower');
    var link = document.getElementById('ezevPreviewLink');
    var img = document.getElementById('ezevPreviewImg');

    if (title) title.textContent = station.name || 'EZEV Station';
    if (addr) addr.textContent = (station.address && station.address.line) ? station.address.line : 'Vietnam';
    if (ports && station.ports) ports.textContent = (station.ports.available || 0) + ' / ' + (station.ports.total || 0) + ' Available';
    if (power) power.textContent = (station.max_power_kw || 0) + ' kW';
    if (link) link.href = station.url || ('/stations/' + (station.slug || ''));
    if (img && station.thumbnail) img.src = station.thumbnail;
  }
});