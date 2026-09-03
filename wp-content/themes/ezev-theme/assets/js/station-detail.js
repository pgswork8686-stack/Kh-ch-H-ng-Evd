/**
 * Station Detail Page Controller (Phase 4.1 P0)
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var stationData = window.ezevStationData || null;

  // Mini Map Initializer
  var mapsConfig = (window.ezevThemeData && window.ezevThemeData.mapsConfig) || {};
  if (stationData && stationData.location && mapsConfig.hasKey && typeof google !== 'undefined' && google.maps) {
    var lat = stationData.location.lat;
    var lng = stationData.location.lng;

    if (typeof lat === 'number' && typeof lng === 'number') {
      var mapElem = document.getElementById('ezevStationMiniMap');
      if (mapElem) {
        var map = new google.maps.Map(mapElem, {
          center: { lat: lat, lng: lng },
          zoom: 15,
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: true
        });

        new google.maps.Marker({
          position: { lat: lat, lng: lng },
          map: map,
          title: stationData.name,
          icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 12,
            fillColor: '#73B72A',
            fillOpacity: 1,
            strokeColor: '#FFFFFF',
            strokeWeight: 3
          }
        });
      }
    }
  }

  // Tabs Navigation
  var tabButtons = document.querySelectorAll('.ezev-tab-btn');
  tabButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-target');
      tabButtons.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');

      if (targetId) {
        var targetElem = document.getElementById(targetId);
        if (targetElem) {
          targetElem.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    });
  });

  // Start Charging Action (App prompt)
  var startBtn = document.getElementById('ezevStartChargingBtn');
  if (startBtn) {
    startBtn.addEventListener('click', function () {
      alert('To start charging at this station, please open or download the official EZEV Mobile App from App Store or Google Play.');
    });
  }

  // Load Nearby Stations dynamically via EzevDataClient
  var nearbyContainer = document.getElementById('ezevNearbyStationsContainer');
  if (nearbyContainer && stationData && stationData.location && typeof EzevDataClient !== 'undefined') {
    var sLat = stationData.location.lat;
    var sLng = stationData.location.lng;

    if (typeof sLat === 'number' && typeof sLng === 'number') {
      EzevDataClient.getNearbyStations(sLat, sLng, 50, 5)
        .then(function (nearbyList) {
          // Filter out current station
          var filtered = nearbyList.filter(function (s) {
            return s.station_id !== stationData.station_id;
          }).slice(0, 4);

          if (filtered.length === 0) {
            nearbyContainer.innerHTML = '<p style="color: #64748B; font-size: 14px;">No other stations found within 50km.</p>';
            return;
          }

          var defaultThumb = (window.ezevThemeData && window.ezevThemeData.imagesUrl ? window.ezevThemeData.imagesUrl + '/station-hero.jpg' : '');
          var html = '';

          filtered.forEach(function (s) {
            var sName = s.name || 'EZEV Station';
            var sAddr = (s.address && s.address.city) ? s.address.city : (s.address && s.address.line ? s.address.line : 'Vietnam');
            var sPower = s.max_power_kw ? (s.max_power_kw + ' kW') : '';
            var sUrl = s.url || ('/stations/' + (s.slug || ''));
            var sDist = s.distanceKm ? (s.distanceKm + ' km away') : '';
            var sThumb = s.thumbnail || defaultThumb;

            html += '<article class="ezev-station-card">' +
              '<div class="ezev-card-media" style="height: 120px;">' +
              '<img src="' + sThumb + '" alt="' + sName + '" loading="lazy" class="ezev-card-img" />' +
              '</div>' +
              '<div class="ezev-card-body">' +
              '<h4 style="font-size: 15px; font-weight: 700; margin-bottom: 4px;"><a href="' + sUrl + '">' + sName + '</a></h4>' +
              '<div style="font-size: 12px; color: #64748B; margin-bottom: 8px;">' + sAddr + (sDist ? ' · ' + sDist : '') + '</div>' +
              '<div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto;">' +
              '<strong style="font-size: 13px; color: #0F172A;">' + sPower + '</strong>' +
              '<a href="' + sUrl + '" class="ezev-btn ezev-btn-primary ezev-btn-sm" style="padding: 4px 10px; font-size: 11px;">View &rarr;</a>' +
              '</div>' +
              '</div>' +
              '</article>';
          });

          nearbyContainer.innerHTML = html;
        })
        .catch(function () {
          nearbyContainer.innerHTML = '';
        });
    }
  }
});