/* ----------------------------------------------------
   service.js
   ---------------------------------------------------- */
(function () {
  'use strict';

  /**
   * ContentEggService
   *  – search()  : wraps action=content-egg-module-api
   *  – enqueue() : wraps action=cegg_import_enqueue
   *  – enqueueBulkWithLimit() : bulk-enqueue with N-concurrency
   */
  function ContentEggService(
    $http,
    $q,
    $httpParamSerializerJQLike,
    ajaxurl,
    contentEggNonce,   // for module search
    importNonce,       // for queue API
    defaultPresetId    // current preset
  ) {

    /* ------------------------------------------------
       Product search
    ------------------------------------------------ */
    this.search = function (module, query) {
      var params = {
        action            : 'content-egg-module-api',
        module            : module,
        query             : JSON.stringify(query),
        _contentegg_nonce : contentEggNonce
      };

      return $http({
        method  : 'POST',
        url     : ajaxurl,
        data    : $httpParamSerializerJQLike(params),
        headers : { 'Content-Type': 'application/x-www-form-urlencoded' }
      });
    };

    /* ------------------------------------------------
       Single enqueue
    ------------------------------------------------ */
    this.enqueue = function (product, importSettings) {
      importSettings = importSettings || {};

      var params = {
        action    : 'cegg_import_enqueue',
        nonce     : importNonce,
        preset_id : importSettings.preset_id || defaultPresetId,
        module_id : product.module_id || product.module || importSettings.module,
        payload   : JSON.stringify(product)
      };

      if (importSettings.post_cat)      { params.post_cat      = importSettings.post_cat; }
      if (importSettings.woo_cat)       { params.woo_cat       = importSettings.woo_cat; }
      if (importSettings.scheduled_at)  { params.scheduled_at  = importSettings.scheduled_at; }

      return $http({
        method  : 'POST',
        url     : ajaxurl,
        data    : $httpParamSerializerJQLike(params),
        headers : { 'Content-Type': 'application/x-www-form-urlencoded' }
      });
    };

    /* ------------------------------------------------
       Bulk enqueue with limited concurrency
       -----------------------------------------------
       @param products        Array<Object>
       @param importSettings  Object
       @param limit           Number   (defaults to 5)
       @return $q.Promise -> Array<{ok:boolean,res/err}>
    ------------------------------------------------ */
    this.enqueueBulkWithLimit = function (products, importSettings, limit) {
      var self      = this;
      var deferred  = $q.defer();
      var total     = products.length;
      var results   = new Array(total);
      var inFlight  = 0;
      var idx       = 0;

      limit = limit || 5;   // default parallelism

      function launchNext() {
        while (inFlight < limit && idx < total) {
          (function (i) {
            inFlight++;

            self.enqueue(products[i], importSettings)
              .then(function (res)  { results[i] = { ok: true,  res : res  }; })
              .catch(function (err) { results[i] = { ok: false, err : err }; })
              .finally(function () {
                inFlight--;
                if (idx < total) {
                  launchNext();          // refill the pipeline
                } else if (inFlight === 0) {
                  deferred.resolve(results);  // all done
                }
              });
          })(idx++);
        }
      }

      if (!total) {
        deferred.resolve([]);            // nothing to do
      } else {
        launchNext();
      }

      return deferred.promise;
    };

    /* ------------------------------------------------
       Legacy helper – keeps previous behaviour
       (unlimited parallelism)
    ------------------------------------------------ */
    this.enqueueBulk = function (products, importSettings) {
      return this.enqueueBulkWithLimit(products, importSettings, products.length || 1);
    };
  }

  ContentEggService.$inject = [
    '$http',
    '$q',
    '$httpParamSerializerJQLike',
    'ajaxurl',
    'contentEggNonce',
    'importNonce',
    'defaultPresetId'
  ];

  angular.module('contentEggApp')
         .service('ContentEggService', ContentEggService);
})();
