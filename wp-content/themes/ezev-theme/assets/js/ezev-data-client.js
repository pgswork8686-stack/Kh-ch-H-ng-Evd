/**
 * EZEV Shared Data Client SDK (Phase 4.1 P0)
 * Single entry point for public browser communication with EZEV Core REST API.
 */
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.EzevDataClient = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var apiRoot = (window.ezevThemeData && window.ezevThemeData.apiRoot) || '/wp-json/ezev/v1';
  var cachedStations = null;
  var cacheTimestamp = 0;
  var CACHE_TTL_MS = 60000; // 1 minute in-memory cache

  /**
   * Calculate distance between two coordinates in kilometers using Haversine formula.
   */
  function haversineDistance(lat1, lon1, lat2, lon2) {
    var R = 6371; // Earth radius in km
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  var EzevDataClient = {
    /**
     * Fetch public station master list from Core REST API.
     * @param {string} [countryCode] - Optional country filter (e.g. 'VN', 'PH')
     * @param {boolean} [forceRefresh=false]
     * @returns {Promise<Array>} Array of StationDTO objects
     */
    getStations: function (countryCode, forceRefresh) {
      var now = Date.now();
      if (!forceRefresh && cachedStations && (now - cacheTimestamp < CACHE_TTL_MS)) {
        if (!countryCode) {
          return Promise.resolve(cachedStations);
        }
        var filtered = cachedStations.filter(function (s) {
          return (s.address && s.address.country_code === countryCode) || s.country_code === countryCode;
        });
        return Promise.resolve(filtered);
      }

      var url = apiRoot + '/stations';
      if (countryCode) {
        url += '?country=' + encodeURIComponent(countryCode);
      }

      return fetch(url, {
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(function (res) {
          if (!res.ok) {
            throw new Error('Failed to fetch stations: HTTP ' + res.status);
          }
          return res.json();
        })
        .then(function (data) {
          var list = (data && data.stations) ? data.stations : [];
          if (!countryCode) {
            cachedStations = list;
            cacheTimestamp = Date.now();
          }
          return list;
        });
    },

    /**
     * Fetch single station details by station_id.
     * @param {string} stationId
     * @returns {Promise<Object>} StationDTO
     */
    getStation: function (stationId) {
      if (!stationId) {
        return Promise.reject(new Error('stationId is required'));
      }

      // Check cache first
      if (cachedStations) {
        var found = cachedStations.find(function (s) {
          return s.station_id === stationId;
        });
        if (found) {
          return Promise.resolve(found);
        }
      }

      var url = apiRoot + '/stations/' + encodeURIComponent(stationId);
      return fetch(url, {
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(function (res) {
          if (!res.ok) {
            throw new Error('Station not found: HTTP ' + res.status);
          }
          return res.json();
        })
        .then(function (data) {
          return (data && data.station) ? data.station : data;
        });
    },

    /**
     * Find station by SEO slug from cached or loaded stations.
     * @param {string} slug
     * @returns {Promise<Object|null>}
     */
    findStationBySlug: function (slug) {
      if (!slug) {
        return Promise.resolve(null);
      }
      return this.getStations().then(function (stations) {
        var match = stations.find(function (s) {
          return s.slug === slug || (s.url && s.url.indexOf(slug) !== -1);
        });
        return match || null;
      });
    },

    /**
     * Get nearby stations relative to user coordinates using client-side Haversine calculation.
     * @param {number} userLat
     * @param {number} userLng
     * @param {number} [radiusKm=50]
     * @param {number} [limit=6]
     * @returns {Promise<Array>} Array of stations sorted by distance with distanceKm property
     */
    getNearbyStations: function (userLat, userLng, radiusKm, limit) {
      var radius = radiusKm || 50;
      var maxLimit = limit || 6;

      return this.getStations().then(function (stations) {
        var withDistance = stations
          .map(function (s) {
            var lat = (s.location && typeof s.location.lat === 'number') ? s.location.lat : null;
            var lng = (s.location && typeof s.location.lng === 'number') ? s.location.lng : null;
            if (lat === null || lng === null) {
              return null;
            }
            var d = haversineDistance(userLat, userLng, lat, lng);
            var copy = Object.assign({}, s);
            copy.distanceKm = Math.round(d * 10) / 10;
            return copy;
          })
          .filter(function (s) {
            return s !== null && s.distanceKm <= radius;
          })
          .sort(function (a, b) {
            return a.distanceKm - b.distanceKm;
          });

        return withDistance.slice(0, maxLimit);
      });
    },

    /**
     * Derive true network statistics from StationDTO collection.
     * Only derives verifiable facts: total stations, total ports, countries, cities, connectors, max power.
     * Never fakes revenue, uptime, or charging sessions.
     * @param {Array} stations
     * @returns {Object}
     */
    getNetworkStats: function (stations) {
      var list = stations || cachedStations || [];
      var totalStations = list.length;
      var totalPorts = 0;
      var availablePorts = 0;
      var countries = {};
      var cities = {};
      var connectorCounts = {};
      var maxPower = 0;

      list.forEach(function (s) {
        // Ports
        if (s.ports) {
          totalPorts += (s.ports.total || 0);
          availablePorts += (s.ports.available || 0);
        }
        // Address breakdown
        if (s.address) {
          if (s.address.country) {
            countries[s.address.country] = (countries[s.address.country] || 0) + 1;
          }
          if (s.address.city) {
            cities[s.address.city] = (cities[s.address.city] || 0) + 1;
          }
        }
        // Connectors
        if (Array.isArray(s.connectors)) {
          s.connectors.forEach(function (c) {
            connectorCounts[c] = (connectorCounts[c] || 0) + 1;
          });
        }
        // Max power
        if (s.max_power_kw && s.max_power_kw > maxPower) {
          maxPower = s.max_power_kw;
        }
      });

      return {
        totalStations: totalStations,
        totalPorts: totalPorts,
        availablePorts: availablePorts,
        countriesCount: Object.keys(countries).length,
        citiesCount: Object.keys(cities).length,
        countries: countries,
        cities: cities,
        connectorDistribution: connectorCounts,
        maxPowerKw: maxPower
      };
    }
  };

  return EzevDataClient;
}));