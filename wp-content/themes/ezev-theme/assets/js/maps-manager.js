/**
 * EZEV Google Maps Manager
 * Handles map initialization, markers, info windows, and boundary synchronization.
 */
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.EzevMapsManager = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function MapsManager(options) {
    this.containerId = options.containerId || 'ezevMap';
    this.map = null;
    this.markers = [];
    this.infoWindow = null;
    this.selectedStationId = null;
    this.onMarkerClick = options.onMarkerClick || function () {};
    this.onBoundsChanged = options.onBoundsChanged || function () {};
  }

  MapsManager.prototype.init = function (defaultCenter, defaultZoom) {
    var container = document.getElementById(this.containerId);
    if (!container || typeof google === 'undefined' || !google.maps) {
      return false;
    }

    var center = defaultCenter || { lat: 14.5547, lng: 121.0244 };
    var zoom = defaultZoom || 12;

    this.map = new google.maps.Map(container, {
      center: center,
      zoom: zoom,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
      zoomControl: true,
      styles: [
        {
          featureType: 'poi.business',
          stylers: [{ visibility: 'off' }]
        }
      ]
    });

    this.infoWindow = new google.maps.InfoWindow();

    var self = this;
    this.map.addListener('idle', function () {
      self.onBoundsChanged(self.map.getBounds());
    });

    return true;
  };

  MapsManager.prototype.renderStations = function (stations) {
    if (!this.map) return;
    this.clearMarkers();

    var bounds = new google.maps.LatLngBounds();
    var self = this;
    var hasValidCoords = false;

    stations.forEach(function (s) {
      var lat = (s.location && typeof s.location.lat === 'number') ? s.location.lat : null;
      var lng = (s.location && typeof s.location.lng === 'number') ? s.location.lng : null;
      if (lat === null || lng === null) return;

      var pos = { lat: lat, lng: lng };
      bounds.extend(pos);
      hasValidCoords = true;

      // Custom marker color based on status
      var statusColor = '#10B981'; // Green
      if (s.status === 'maintenance') statusColor = '#F59E0B';
      if (s.status === 'offline') statusColor = '#EF4444';

      var marker = new google.maps.Marker({
        position: pos,
        map: self.map,
        title: s.name,
        stationId: s.station_id,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 10,
          fillColor: statusColor,
          fillOpacity: 1,
          strokeColor: '#FFFFFF',
          strokeWeight: 3
        }
      });

      marker.addListener('click', function () {
        self.selectMarker(s.station_id);
        self.showInfoWindow(s, marker);
        self.onMarkerClick(s);
      });

      self.markers.push(marker);
    });

    if (hasValidCoords && stations.length > 1) {
      this.map.fitBounds(bounds);
    }
  };

  MapsManager.prototype.clearMarkers = function () {
    this.markers.forEach(function (m) {
      m.setMap(null);
    });
    this.markers = [];
  };

  MapsManager.prototype.selectMarker = function (stationId) {
    this.selectedStationId = stationId;
    var self = this;
    this.markers.forEach(function (m) {
      if (m.stationId === stationId) {
        m.setAnimation(google.maps.Animation.BOUNCE);
        setTimeout(function () {
          m.setAnimation(null);
        }, 1200);
        self.map.panTo(m.getPosition());
      }
    });
  };

  MapsManager.prototype.showInfoWindow = function (station, marker) {
    if (!this.infoWindow || !this.map) return;

    var name = station.name || 'EZEV Station';
    var addr = (station.address && station.address.line) ? station.address.line : '';
    var power = station.max_power_kw ? (station.max_power_kw + ' kW') : '';
    var url = station.url || ('/stations/' + (station.slug || ''));
    var avail = (station.ports && typeof station.ports.available === 'number')
      ? (station.ports.available + '/' + station.ports.total + ' Available')
      : '';

    var html = '<div style="padding: 8px; max-width: 240px; font-family: Inter, sans-serif;">' +
      '<strong style="font-size: 14px; color: #0F172A; display: block; margin-bottom: 4px;">' + name + '</strong>' +
      '<div style="font-size: 12px; color: #64748B; margin-bottom: 6px;">' + addr + '</div>' +
      '<div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px;">' +
      '<span style="color: #10B981; font-weight: 600;">' + avail + '</span>' +
      '<span style="color: #0F172A; font-weight: 600;">' + power + '</span>' +
      '</div>' +
      '<a href="' + url + '" style="display: block; text-align: center; background: #73B72A; color: #090D1A; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; text-decoration: none;">View Details</a>' +
      '</div>';

    this.infoWindow.setContent(html);
    this.infoWindow.open(this.map, marker);
  };

  return MapsManager;
}));