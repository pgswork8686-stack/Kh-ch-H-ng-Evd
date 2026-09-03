/**
 * Find a Charger Page Controller (Phase 4.1 P0)
 * Integrates EzevDataClient + EzevMapsManager + UI Filters
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var allStations = [];
  var filteredStations = [];
  var mapsManager = null;

  // DOM Elements
  var listContainer = document.getElementById('ezevStationList');
  var countDisplay = document.getElementById('ezevResultCount');
  var searchInput = document.getElementById('ezevLocationSearch');
  var locateBtn = document.getElementById('ezevLocateBtn');
  var countrySelect = document.getElementById('ezevCountryFilter');
  var citySelect = document.getElementById('ezevCityFilter');
  var connectorSelect = document.getElementById('ezevConnectorFilter');
  var powerSelect = document.getElementById('ezevPowerFilter');
  var statusSelect = document.getElementById('ezevStatusFilter');
  var clearBtn = document.getElementById('ezevClearFiltersBtn');
  var searchAreaBtn = document.getElementById('ezevSearchAreaBtn');

  // Mobile switches
  var switchListBtn = document.getElementById('ezevSwitchList');
  var switchMapBtn = document.getElementById('ezevSwitchMap');
  var sidebarPanel = document.getElementById('ezevFindSidebar');
  var mapArea = document.getElementById('ezevFindMapArea');

  // Initialize Map
  var mapsConfig = (window.ezevThemeData && window.ezevThemeData.mapsConfig) || {};
  if (mapsConfig.hasKey && typeof EzevMapsManager !== 'undefined') {
    mapsManager = new EzevMapsManager({
      containerId: 'ezevMap',
      onMarkerClick: function (station) {
        highlightStationInList(station.station_id);
      },
      onBoundsChanged: function () {
        if (searchAreaBtn) {
          searchAreaBtn.classList.add('visible');
        }
      }
    });
    mapsManager.init(mapsConfig.defaultCenter, mapsConfig.defaultZoom);
  }

  // Load Stations Data
  if (typeof EzevDataClient !== 'undefined') {
    EzevDataClient.getStations()
      .then(function (stations) {
        allStations = stations;
        populateFilterOptions(allStations);
        applyFilters();
      })
      .catch(function (err) {
        if (listContainer) {
          listContainer.innerHTML = '<div style="padding: 24px; text-align: center; color: #EF4444;">Unable to load charging stations. Please try again.</div>';
        }
      });
  }

  // Populate dynamic dropdown options from real data
  function populateFilterOptions(stations) {
    var countries = {};
    var cities = {};
    var connectors = {};

    stations.forEach(function (s) {
      if (s.address) {
        if (s.address.country_code) countries[s.address.country_code] = s.address.country || s.address.country_code;
        if (s.address.city) cities[s.address.city] = true;
      }
      if (Array.isArray(s.connectors)) {
        s.connectors.forEach(function (c) { connectors[c] = true; });
      }
    });

    // Populate Country
    if (countrySelect) {
      Object.keys(countries).forEach(function (code) {
        var opt = document.createElement('option');
        opt.value = code;
        opt.textContent = countries[code];
        countrySelect.appendChild(opt);
      });
    }

    // Populate City
    if (citySelect) {
      Object.keys(cities).sort().forEach(function (city) {
        var opt = document.createElement('option');
        opt.value = city;
        opt.textContent = city;
        citySelect.appendChild(opt);
      });
    }

    // Populate Connectors
    if (connectorSelect) {
      Object.keys(connectors).sort().forEach(function (c) {
        var opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        connectorSelect.appendChild(opt);
      });
    }
  }

  // Filter Application Logic
  function applyFilters() {
    var keyword = (searchInput ? searchInput.value.toLowerCase().trim() : '');
    var selCountry = (countrySelect ? countrySelect.value : '');
    var selCity = (citySelect ? citySelect.value : '');
    var selConn = (connectorSelect ? connectorSelect.value : '');
    var minPower = (powerSelect && powerSelect.value ? parseFloat(powerSelect.value) : 0);
    var selStatus = (statusSelect ? statusSelect.value : '');

    filteredStations = allStations.filter(function (s) {
      // Keyword search (name, address, city)
      if (keyword) {
        var matchName = (s.name && s.name.toLowerCase().indexOf(keyword) !== -1);
        var matchAddr = (s.address && s.address.line && s.address.line.toLowerCase().indexOf(keyword) !== -1);
        var matchCity = (s.address && s.address.city && s.address.city.toLowerCase().indexOf(keyword) !== -1);
        if (!matchName && !matchAddr && !matchCity) return false;
      }

      // Country filter
      if (selCountry) {
        var cCode = (s.address && s.address.country_code) || s.country_code;
        if (cCode !== selCountry) return false;
      }

      // City filter
      if (selCity) {
        var city = (s.address && s.address.city) || '';
        if (city !== selCity) return false;
      }

      // Connector filter
      if (selConn) {
        if (!Array.isArray(s.connectors) || s.connectors.indexOf(selConn) === -1) return false;
      }

      // Minimum power filter
      if (minPower > 0) {
        var power = s.max_power_kw || 0;
        if (power < minPower) return false;
      }

      // Status filter
      if (selStatus) {
        var status = s.status || 'active';
        if (status !== selStatus) return false;
      }

      return true;
    });

    renderStationList(filteredStations);
    if (mapsManager) {
      mapsManager.renderStations(filteredStations);
    }
  }

  // Render station cards in sidebar
  function renderStationList(stations) {
    if (!listContainer) return;

    if (countDisplay) {
      countDisplay.textContent = stations.length + ' stations found';
    }

    if (stations.length === 0) {
      listContainer.innerHTML = '<div style="padding: 32px 16px; text-align: center; color: #64748B;">' +
        '<div style="font-size: 2rem; margin-bottom: 8px;">🔍</div>' +
        '<strong>No stations match your criteria</strong>' +
        '<p style="font-size: 13px; margin-top: 4px;">Try adjusting your filters or search location.</p>' +
        '</div>';
      return;
    }

    var defaultThumb = (window.ezevThemeData && window.ezevThemeData.imagesUrl ? window.ezevThemeData.imagesUrl + '/station-hero.jpg' : '');
    var html = '';

    stations.forEach(function (s) {
      var name = s.name || 'EZEV Station';
      var addr = (s.address && s.address.line) ? s.address.line : '';
      var city = (s.address && s.address.city) ? s.address.city : '';
      var fullAddr = addr ? (city ? addr + ', ' + city : addr) : 'Vietnam';
      var conns = Array.isArray(s.connectors) ? s.connectors.join(', ') : 'CCS2';
      var power = s.max_power_kw || 0;
      var avail = (s.ports && typeof s.ports.available === 'number') ? s.ports.available : 0;
      var total = (s.ports && typeof s.ports.total === 'number') ? s.ports.total : 0;
      var url = s.url || ('/stations/' + (s.slug || ''));
      var thumb = s.thumbnail || defaultThumb;
      var isDemo = (s.data && s.data.is_demo) || (s.data && s.data.mode === 'demo');
      var badgeMode = isDemo ? '<span class="ezev-badge ezev-badge-demo"><span class="ezev-badge-dot"></span>Demo Data</span>'
                             : '<span class="ezev-badge ezev-badge-manual"><span class="ezev-badge-dot"></span>Manual Data</span>';

      var status = s.status || 'active';
      var badgeStatus = '<span class="ezev-badge ezev-badge-available"><span class="ezev-badge-dot"></span>Available</span>';
      if (status === 'maintenance') {
        badgeStatus = '<span class="ezev-badge ezev-badge-maintenance"><span class="ezev-badge-dot"></span>Maintenance</span>';
      } else if (status === 'offline') {
        badgeStatus = '<span class="ezev-badge ezev-badge-offline"><span class="ezev-badge-dot"></span>Offline</span>';
      }

      html += '<article class="ezev-station-card" data-station-id="' + s.station_id + '">' +
        '<div class="ezev-card-media">' +
        '<img src="' + thumb + '" alt="' + name + '" loading="lazy" class="ezev-card-img" />' +
        '<div class="ezev-card-badges">' + badgeStatus + badgeMode + '</div>' +
        '</div>' +
        '<div class="ezev-card-body">' +
        '<h3 class="ezev-card-title"><a href="' + url + '">' + name + '</a></h3>' +
        '<div class="ezev-card-location"><span class="ezev-icon-pin">📍</span><span class="ezev-text-address">' + fullAddr + '</span></div>' +
        '<div class="ezev-card-specs">' +
        '<div class="ezev-spec-item"><span class="ezev-spec-label">Connectors</span><strong class="ezev-spec-val">' + conns + '</strong></div>' +
        '<div class="ezev-spec-item"><span class="ezev-spec-label">Max Power</span><strong class="ezev-spec-val">' + power + ' kW</strong></div>' +
        '</div>' +
        '<div class="ezev-card-footer">' +
        '<div class="ezev-port-status"><span class="ezev-port-count">' + avail + ' / ' + total + '</span><span class="ezev-port-label">Available</span></div>' +
        '<a href="' + url + '" class="ezev-btn ezev-btn-primary ezev-btn-sm">View details &rarr;</a>' +
        '</div>' +
        '</div>' +
        '</article>';
    });

    listContainer.innerHTML = html;

    // Attach click listeners to cards to sync with map
    var cards = listContainer.querySelectorAll('.ezev-station-card');
    cards.forEach(function (card) {
      card.addEventListener('click', function () {
        var stId = card.getAttribute('data-station-id');
        highlightStationInList(stId);
        if (mapsManager) {
          mapsManager.selectMarker(stId);
        }
      });
    });
  }

  function highlightStationInList(stationId) {
    if (!listContainer) return;
    var cards = listContainer.querySelectorAll('.ezev-station-card');
    cards.forEach(function (c) {
      if (c.getAttribute('data-station-id') === stationId) {
        c.classList.add('selected');
        c.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        c.classList.remove('selected');
      }
    });
  }

  // Event Listeners for Filters
  if (searchInput) searchInput.addEventListener('input', applyFilters);
  if (countrySelect) countrySelect.addEventListener('change', applyFilters);
  if (citySelect) citySelect.addEventListener('change', applyFilters);
  if (connectorSelect) connectorSelect.addEventListener('change', applyFilters);
  if (powerSelect) powerSelect.addEventListener('change', applyFilters);
  if (statusSelect) statusSelect.addEventListener('change', applyFilters);

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (searchInput) searchInput.value = '';
      if (countrySelect) countrySelect.value = '';
      if (citySelect) citySelect.value = '';
      if (connectorSelect) connectorSelect.value = '';
      if (powerSelect) powerSelect.value = '';
      if (statusSelect) statusSelect.value = '';
      applyFilters();
    });
  }

  // Geolocation Handler
  if (locateBtn) {
    locateBtn.addEventListener('click', function () {
      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
      }
      locateBtn.innerHTML = '⌛';
      navigator.geolocation.getCurrentPosition(
        function (position) {
          locateBtn.innerHTML = '📍';
          var userLat = position.coords.latitude;
          var userLng = position.coords.longitude;
          if (mapsManager && mapsManager.map) {
            mapsManager.map.panTo({ lat: userLat, lng: userLng });
            mapsManager.map.setZoom(13);
          }
          if (typeof EzevDataClient !== 'undefined') {
            EzevDataClient.getNearbyStations(userLat, userLng, 100, 20).then(function (nearby) {
              if (nearby.length > 0) {
                renderStationList(nearby);
                if (mapsManager) mapsManager.renderStations(nearby);
              }
            });
          }
        },
        function () {
          locateBtn.innerHTML = '📍';
          alert('Unable to access your location. Please check browser permissions.');
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    });
  }

  // Google Places Autocomplete (if loaded)
  if (searchInput && typeof google !== 'undefined' && google.maps && google.maps.places) {
    var autocomplete = new google.maps.places.Autocomplete(searchInput, {
      types: ['geocode', 'establishment']
    });
    autocomplete.addListener('place_changed', function () {
      var place = autocomplete.getPlace();
      if (!place.geometry || !place.geometry.location) return;
      var lat = place.geometry.location.lat();
      var lng = place.geometry.location.lng();
      if (mapsManager && mapsManager.map) {
        mapsManager.map.panTo({ lat: lat, lng: lng });
        mapsManager.map.setZoom(13);
      }
      applyFilters();
    });
  }

  // Mobile View Switchers
  if (switchListBtn && switchMapBtn && sidebarPanel && mapArea) {
    switchListBtn.addEventListener('click', function () {
      switchListBtn.classList.add('active');
      switchMapBtn.classList.remove('active');
      sidebarPanel.style.display = 'flex';
      mapArea.style.display = 'none';
    });

    switchMapBtn.addEventListener('click', function () {
      switchMapBtn.classList.add('active');
      switchListBtn.classList.remove('active');
      sidebarPanel.style.display = 'none';
      mapArea.style.display = 'block';
      if (mapsManager && mapsManager.map) {
        google.maps.event.trigger(mapsManager.map, 'resize');
      }
    });
  }
});