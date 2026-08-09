"use strict";
var contentEgg = angular.module("contentEgg", [
  "ui.sortable",
  "ngSanitize",
  "ui.tinymce",
]);

(function () {
  var params = window.contentegg_params || {};

  contentEgg
    .constant("ajaxurl", window.ajaxurl)
    .constant("importNonce", params.importNonce || "")
    .constant("contentEggNonce", "")
    .constant("defaultPresetId", null);
})();

contentEgg.controller(
  "ContentEggController",
  function (
    $scope,
    $element,
    $http,
    ModuleService,
    $rootScope,
    $timeout,
    ProductImportService,
    BsToast
  ) {
    $scope.models = {};
    $scope.query_params = {};
    $scope.keywords = {};
    $scope.selected = {};
    $scope.updateKeywords = {};
    $scope.updateParams = {};
    $scope.activeSearchTabs = {};
    $scope.activeResultTabs = {};
    $scope.shortcodes = {};
    $scope.productGroups = [];
    $scope.newProductGroup = "";
    $scope.aiProcessingTitle = {};
    $scope.aiProcessingDescription = [];
    $scope.smartGroupsError = "";
    $scope.aiProcessingSmartGroups = false;
    $scope.isProcessingImport = [];

    $scope.blockShortcodeBuillder = {
      template: "",
      group: "",
      limit: "",
      offset: "",
      next: "",
    };

    var editorProductsSyncPromise = null;

    $timeout(function () {
      $rootScope.$broadcast("$tinymce:refresh");
    }, 1000);

    $scope.tinymceOptions = {
      height: 100,
      plugins: "code,lists,link",
      toolbar:
        "undo redo | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist blockquote hr | link unlink wplink | formatselect | code",
      menubar: false,
      branding: false,
    };

    $scope.isHtmlContent = function (content) {
      var div = document.createElement("div");
      div.innerHTML = content;
      return div.children.length > 0;
    };

    $scope.processCounter = 0;
    $scope.blockShortcode = "[content-egg-block]";
    $scope.active_modules = contentegg_params.active_modules;
    $scope.productGroups = contentegg_params.initProductGroups;

    window.ceggProductGroups = $scope.productGroups;

    $scope.sortableOptions = {
      handle: ".cegg-item-handle",
      stop: function (e, ui) {
        $timeout(function () {
          $rootScope.$broadcast("$tinymce:refresh");
        }, 0);
        scheduleEditorProductsSync();
      },
    };

    function buildEditorProductsSnapshot() {
      var byModule = {};
      var all = [];

      angular.forEach($scope.active_modules, function (module_id) {
        var added =
          $scope.models[module_id] && Array.isArray($scope.models[module_id].added)
            ? $scope.models[module_id].added
            : [];

        byModule[module_id] = added.map(function (item) {
          return {
            unique_id: item && item.unique_id ? item.unique_id : "",
            module_id: item && item.module_id ? item.module_id : module_id,
            title: item && item.title ? item.title : "",
            subtitle: item && item.subtitle ? item.subtitle : "",
            merchant: item && item.merchant ? item.merchant : "",
            badge: item && item.badge ? item.badge : "",
            domain: item && item.domain ? item.domain : "",
            group: item && item.group ? item.group : "",
            img: item && item.img ? item.img : "",
            url: item && item.url ? item.url : "",
            price: item && item.price ? item.price : "",
            priceOld: item && item.priceOld ? item.priceOld : "",
            currencyCode: item && item.currencyCode ? item.currencyCode : "",
            ratingDecimal:
              item && item.ratingDecimal ? item.ratingDecimal : "",
            order_num: item && item.order_num ? item.order_num : "",
          };
        });

        all = all.concat(byModule[module_id]);
      });

      return {
        postId: getCurrentPostId(),
        byModule: byModule,
        all: all,
        updatedAt: Date.now(),
      };
    }

    function syncEditorProducts() {
      var snapshot = buildEditorProductsSnapshot();
      window.ceggEditorProducts = snapshot;
      window.dispatchEvent(
        new CustomEvent("ceggEditorProductsUpdated", {
          detail: snapshot,
        })
      );

      // Propagate to Gutenberg parent frame when CE meta box is rendered in
      // the meta-box iframe, so blocks in the main editor window can see it.
      try {
        if (window.parent && window.parent !== window) {
          window.parent.ceggEditorProducts = snapshot;
          window.parent.dispatchEvent(
            new CustomEvent("ceggEditorProductsUpdated", {
              detail: snapshot,
            })
          );
        }
      } catch (e) {
        // Cross-origin or other access error — safe to ignore.
      }
    }

    function scheduleEditorProductsSync() {
      if (editorProductsSyncPromise) {
        $timeout.cancel(editorProductsSyncPromise);
      }

      editorProductsSyncPromise = $timeout(function () {
        editorProductsSyncPromise = null;
        syncEditorProducts();
      }, 0);
    }

    // Merge products added from the block editor (REST write-through) into
    // the metabox model, so the classic post-save path keeps them. Items are
    // full product objects; dedupe by unique_id like $scope.add().
    window.addEventListener("ceggProductsChangedExternally", function (event) {
      var detail = event && event.detail ? event.detail : {};
      var module_id = detail.module_id;
      var items = detail.items && Array.isArray(detail.items) ? detail.items : [];

      if (!module_id || !$scope.models[module_id] || !items.length) return;

      $timeout(function () {
        angular.forEach(items, function (item) {
          if (!item || !item.unique_id) return;

          for (
            var i = 0;
            i < $scope.models[module_id].added.length;
            i++
          ) {
            if ($scope.models[module_id].added[i].unique_id == item.unique_id)
              return;
          }

          $scope.models[module_id].added.push(item);
          $scope.models[module_id].added_changed = true;
        });

        scheduleEditorProductsSync();
      });
    });

    angular.forEach($scope.active_modules, function (module_id, key) {
      $scope.models[module_id] = new ModuleService(module_id);
      $scope.keywords[module_id] = "";
      $scope.updateKeywords[module_id] = "";
      $scope.updateParams[module_id] = {};
      $scope.shortcodes[module_id] = "[content-egg module=" + module_id + "]";
      $scope.aiProcessingTitle[module_id] = false;
      $scope.aiProcessingDescription[module_id] = false;
      $scope.selected[module_id] = 0;

      // init module options
      $scope.query_params[module_id] = {};
      angular.forEach(
        contentegg_params.modulesOptions[module_id],
        function (value, option) {
          $scope.query_params[module_id][option] = value;
        }
      );

      // init post metadata
      if (contentegg_params.initData[module_id]) {
        $scope.models[module_id].added = contentegg_params.initData[module_id];
        $scope.activeSearchTabs[module_id] = false;
        $scope.activeResultTabs[module_id] = true;
      } else {
        $scope.activeSearchTabs[module_id] = true;
        $scope.activeResultTabs[module_id] = false;
      }

      // init keywords
      if (contentegg_params.initKeywords[module_id]) {
        $scope.updateKeywords[module_id] =
          contentegg_params.initKeywords[module_id];
      }
      if (contentegg_params.initUpdateParams[module_id]) {
        $scope.updateParams[module_id] =
          contentegg_params.initUpdateParams[module_id];
      }
    });

    syncEditorProducts();

    $scope.ai = function (module_id, title_method, description_method) {
      if (!$scope.models[module_id].added) return;

      if (title_method) $scope.aiProcessingTitle[module_id] = true;

      if (description_method) $scope.aiProcessingDescription[module_id] = true;

      var moduleElement = $element[0].querySelector("#" + module_id);
      if (moduleElement) {
        angular.element(moduleElement).addClass("cegg_wait");
      }

      var ai_tools_links = angular.element(
        document.querySelectorAll(".cegg-ai-tools a")
      );
      ai_tools_links.each(function (index, el) {
        angular.element(el).addClass("disabled");
      });

      $scope.models[module_id].aiError = "";
      $scope.models[module_id].importError = "";

      var ai_params = {};

      ai_params.data = $scope.models[module_id].added;
      ai_params.title_method = title_method;
      ai_params.description_method = description_method;
      $scope.models[module_id].ai(ai_params).then(function (response) {
        if (moduleElement) {
          angular.element(moduleElement).removeClass("cegg_wait");
        }
        ai_tools_links.each(function (index, el) {
          angular.element(el).removeClass("disabled");
        });

        $scope.aiProcessingTitle[module_id] = false;
        $scope.aiProcessingDescription[module_id] = false;
      });
    };

    function getCurrentPostId() {
      // Classic editor
      var el = document.getElementById("post_ID");
      if (el && el.value) {
        var id = parseInt(el.value, 10);
        if (isFinite(id) && id > 0) return id;
      }
      // Block editor (Gutenberg)
      try {
        if (window.wp && wp.data && wp.data.select) {
          var sel = wp.data.select("core/editor");
          if (sel && typeof sel.getCurrentPostId === "function") {
            var gid = sel.getCurrentPostId();
            if (typeof gid === "number" && gid > 0) return gid;
          }
        }
      } catch (e) {}
      return null;
    }

    $scope.import = function (module_id, preset_id) {
      // Guard: must have items
      if (
        !$scope.models[module_id] ||
        !$scope.models[module_id].added ||
        !$scope.models[module_id].added.length
      ) {
        return;
      }

      // Require a post ID (ask user to save first)
      var sourceId = getCurrentPostId();
      if (!sourceId) {
        BsToast.error(
          "Please save this post as a draft first, then run the import.",
          "No Post ID"
        );
        return;
      }

      $scope.isProcessingImport[module_id] = true;

      var moduleElement = $element[0].querySelector("#" + module_id);
      if (moduleElement) {
        angular.element(moduleElement).addClass("cegg_wait");
      }

      var ai_tools_links = angular.element(
        document.querySelectorAll(".cegg-ai-tools a")
      );
      ai_tools_links.each(function (index, el) {
        angular.element(el).addClass("disabled");
      });

      // Build product list: selected items, or all if none selected
      var list = $scope.models[module_id].added.filter(function (item) {
        return !!item._selected;
      });
      if (!list.length) {
        list = $scope.models[module_id].added.slice(0);
      }

      // Import settings (pass source_post_id)
      var importSettings = {
        preset_id: preset_id,
        module: module_id,
        source_post_id: sourceId,
        // post_cat  : ...,
        // woo_cat   : ...,
      };

      // Enqueue in one request
      ProductImportService.enqueueMany(list, importSettings)
        .then(function (response) {
          // Unwrap WP envelope: { success: true, data: {...} }
          var data = response && response.data ? response.data : {};
          var payload =
            data && typeof data.success !== "undefined" && data.data
              ? data.data
              : data;

          var created =
            typeof payload.created_count === "number"
              ? payload.created_count
              : Array.isArray(payload.job_ids)
              ? payload.job_ids.length
              : 0;

          var skipped = Array.isArray(payload.skipped)
            ? payload.skipped.length
            : 0;

          if (created > 0) {
            BsToast.success(
              "Queued " +
                created +
                " product(s)." +
                (skipped ? " Skipped " + skipped + "." : ""),
              "Bridge Page Import"
            );
          } else if (skipped > 0) {
            BsToast.warning(
              "No new jobs queued. Skipped " +
                skipped +
                " duplicate(s)/error(s).",
              "Bridge Page Import"
            );
          } else {
            BsToast.info(
              "Nothing to import for this selection.",
              "Bridge Page Import"
            );
          }
        })
        .catch(function (err) {
          var msg =
            (err && err.data && err.data.data && err.data.data.message) ||
            (err && err.data && err.data.message) ||
            (err && err.statusText) ||
            "Failed";
          BsToast.error("Import failed: " + msg, "Bridge Page Import");
        })
        .finally(function () {
          // Clear selection
          for (var i = 0; i < $scope.models[module_id].added.length; i++) {
            $scope.models[module_id].added[i]._selected = false;
          }

          if (moduleElement) {
            angular.element(moduleElement).removeClass("cegg_wait");
          }
          ai_tools_links.each(function (index, el) {
            angular.element(el).removeClass("disabled");
          });
          $scope.isProcessingImport[module_id] = false;
        });
    };

    $scope.smartGroups = function (method) {
      var ai_tools_links = angular.element(
        document.querySelectorAll(".cegg-ai-tools a")
      );
      ai_tools_links.each(function (index, el) {
        angular.element(el).addClass("disabled");
      });

      var ai_params = {};
      ai_params.data = {};
      ai_params.method = method;
      angular.forEach($scope.active_modules, function (module_id, key) {
        ai_params.data[module_id] = $scope.models[module_id].added;
      });

      var params = {
        action: "content-egg-smart-groups-api",
        params: JSON.stringify(ai_params),
        _contentegg_nonce: contentegg_params.nonce,
      };

      $scope.aiProcessingSmartGroups = true;
      $scope.smartGroupsError = "";
      document.body.classList.add("cegg_wait");

      $http({
        method: "post",
        url: ajaxurl,
        data: params,
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        timeout: 180000,
        transformRequest: function (obj) {
          var str = [];
          for (var p in obj)
            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
          return str.join("&");
        },
      })
        .then(function (response) {
          document.body.classList.remove("cegg_wait");
          $scope.aiProcessingSmartGroups = false;
          ai_tools_links.each(function (index, el) {
            angular.element(el).removeClass("disabled");
          });
          var data = response.data;
          if (!data.error && data.results) {
            angular.forEach($scope.active_modules, function (module_id, key) {
              if (data.results.hasOwnProperty(module_id)) {
                angular.forEach(
                  data.results[module_id],
                  function (item, index) {
                    $scope.addProductGroup(item.group);
                  }
                );
                $scope.models[module_id].added = data.results[module_id];
              }
            });
            scheduleEditorProductsSync();
          } else {
            $scope.smartGroupsError = data.error;
          }
        })
        .catch(function (error) {
          document.body.classList.remove("cegg_wait");
          $scope.smartGroupsError = error;
          $scope.aiProcessingSmartGroups = false;
          ai_tools_links.each(function (index, el) {
            angular.element(el).removeClass("disabled");
          });
        });
    };

    $scope.selectedCount = function (module_id) {
      var count = 0;
      angular.forEach($scope.models[module_id].added, function (item, key) {
        if (item._selected) count++;
      });
      $scope.selected[module_id] = count;
      return count;
    };

    $scope.find = function (module_id) {
      if (!$scope.keywords[module_id]) return;
      $scope.processCounter++;
      $scope.query_params[module_id].keyword = $scope.keywords[module_id];
      $scope.models[module_id]
        .find($scope.query_params[module_id])
        .then(function (response) {
          $scope.processCounter--;
        });
    };

    $scope.add = function (result, module_id) {
      var index = $scope.models[module_id].results.indexOf(result);
      if ($scope.models[module_id].results[index].added) return;
      $scope.models[module_id].results[index].added = true;
      // check for dublicates
      for (
        var i = 0, len = $scope.models[module_id].added.length;
        i < len;
        i++
      ) {
        var item = $scope.models[module_id].added[i];
        if (
          item["unique_id"] ==
          $scope.models[module_id].results[index]["unique_id"]
        )
          return;
      }
      $scope.models[module_id].results[index].keyword =
        $scope.keywords[module_id];
      $scope.models[module_id].added.push(
        $scope.models[module_id].results[index]
      );
      $scope.models[module_id].added_changed = true;
      scheduleEditorProductsSync();
    };

    $scope.addBlank = function (module_id, type = "contentProduct") {
      if (type != "contentProduct" && type != "contentCoupon") return;
      var contentProduct = angular.copy(contentegg_params[type]);
      contentProduct.unique_id = Math.random().toString(36).slice(2);
      $scope.models[module_id].added.push(contentProduct);
      $scope.models[module_id].added_changed = true;
      scheduleEditorProductsSync();
    };

    $scope.addAll = function (module_id) {
      if (!$scope.models[module_id].results.length) return;
      angular.forEach($scope.models[module_id].results, function (result, key) {
        $scope.add(result, module_id);
      });
      $scope.activeResultTabs[module_id] = true;
    };

    $scope.delete = function (data, module_id) {
      var index = $scope.models[module_id].added.indexOf(data);
      $scope.models[module_id].added.splice(index, 1);
      $scope.models[module_id].added_changed = true;
      scheduleEditorProductsSync();
    };

    $scope.deleteAll = function (module_id) {
      $scope.models[module_id].added = [];
      $scope.models[module_id].added_changed = true;
      $scope.activeSearchTabs[module_id] = true;
      $scope.activeResultTabs[module_id] = false;
      scheduleEditorProductsSync();
    };

    $scope.copyAllProductsToClipboard = function (event) {
      var icon = angular.element(event.currentTarget).find("i");
      icon.removeClass("bi-clipboard").addClass("bi-check");

      setTimeout(function () {
        icon.removeClass("bi-check").addClass("bi-clipboard");
      }, 1000);

      var items = [];
      angular.forEach($scope.active_modules, function (module_id) {
        var added =
          $scope.models[module_id] && Array.isArray($scope.models[module_id].added)
            ? $scope.models[module_id].added
            : [];
        angular.forEach(added, function (product) {
          items.push({
            title: product && product.title ? product.title : "",
            orig_url: product && product.orig_url ? product.orig_url : "",
            product_ref: {
              module_id:
                product && product.module_id ? product.module_id : module_id,
              unique_id: product && product.unique_id ? product.unique_id : "",
            },
          });
        });
      });

      navigator.clipboard.writeText(JSON.stringify(items, null, 2));
    };

    $scope.copyKeywordProductIdsToClipboard = function (module_id, event) {
      let keyword = $scope.keywords[module_id];
      $scope.copyProductIdsToClipboard(module_id, event, keyword);
    };

    $scope.copyProductIdsToClipboard = function (
      module_id,
      event,
      keyword = ""
    ) {
      var icon = angular.element(event.currentTarget).find("i");
      icon.removeClass("bi-magnet").addClass("bi-check");

      setTimeout(() => {
        icon.removeClass("bi-check").addClass("bi-magnet");
      }, 1000);

      var product_ids = [];

      angular.forEach(
        $scope.models[module_id].added,
        function (product, index) {
          if (module_id.indexOf("AE__") !== -1) {
            var asin = product["url"].match("/([a-zA-Z0-9]{10})(?:[/?]|$)");
            if (asin) {
              product_ids.push(asin[1]);
              return;
            }
          }

          if (module_id == "Bolcom") {
            if (product["ean"]) {
              product_ids.push(product["ean"]);
              return;
            }
          }

          var parts = product["unique_id"].split("-", 2);
          if (parts.length == 2) product_ids.push(parts[1]);
          else product_ids.push(parts[0]);
        }
      );

      var res = product_ids.join("\n");

      navigator.clipboard.writeText(res);
    };

    $scope.copyDescriptionShortcode = function (module_id, unique_id, event) {
      var icon = angular.element(event.currentTarget).find("i");
      icon.removeClass("bi-code-square").addClass("bi-check");

      setTimeout(() => {
        icon.removeClass("bi-check").addClass("bi-code-square");
      }, 1000);

      var res =
        "[content-egg module=" +
        module_id +
        ' template=description products="' +
        unique_id +
        '"]';
      navigator.clipboard.writeText(res);
    };

    $scope.addIdToShortcode = function (
      module_id,
      template = "",
      group = "",
      product = "",
      event
    ) {
      var icon = angular.element(event.currentTarget).find("i");
      icon.removeClass("bi-key").addClass("bi-check");

      $scope.buildShortcode(module_id, template, group, product);

      setTimeout(() => {
        icon.removeClass("bi-check").addClass("bi-key");
      }, 1000);
    };

    $scope.global_findAll = function () {
      if (!$scope.global_keywords) return;
      angular.forEach($scope.models, function (service, module_id) {
        $scope.keywords[module_id] = $scope.global_keywords;
        $scope.activeSearchTabs[module_id] = true;
        $scope.activeResultTabs[module_id] = false;
        $scope.find(module_id);
      });
    };

    $scope.global_addAll = function () {
      angular.forEach($scope.models, function (service, module_id) {
        $scope.addAll(module_id);
      });
    };

    $scope.global_deleteAll = function () {
      angular.forEach($scope.models, function (service, module_id) {
        $scope.deleteAll(module_id);
      });
    };

    $scope.global_isSearchResults = function () {
      for (var i = 0, len = $scope.active_modules.length; i < len; i++) {
        var module_id = $scope.active_modules[i];
        if (
          $scope.models[module_id].results &&
          $scope.models[module_id].results.length
        )
          return true;
      }
      return false;
    };

    $scope.global_isAddedResults = function () {
      for (var i = 0, len = $scope.active_modules.length; i < len; i++) {
        var module_id = $scope.active_modules[i];
        if ($scope.models[module_id].added.length) return true;
      }
      return false;
    };

    $scope.aiUndo = function (module_id) {
      if ($scope.models[module_id].undo.length) {
        $scope.models[module_id].added = $scope.models[module_id].undo;
        scheduleEditorProductsSync();
      }
      $scope.models[module_id].undo = [];
    };

    $scope.getYoutubeUri = function (id) {
      return "https://www.youtube.com/embed/" + id;
    };

    $scope.setUpdateKeyword = function (module_id, event) {
      var icon = angular.element(event.currentTarget).find("i");
      icon.removeClass("bi-plus-circle-dotted").addClass("bi-check");

      setTimeout(() => {
        icon.removeClass("bi-check").addClass("bi-plus-circle-dotted");
      }, 1000);

      $scope.updateKeywords[module_id] = $scope.keywords[module_id];
      //$scope.activeResultTabs[module_id] = true;
    };

    $scope.buildShortcode = function (
      module_id,
      template = "",
      group = "",
      product = ""
    ) {
      var shortcode = "[content-egg module=" + module_id;
      if (product) shortcode += ' products="' + product + '"';
      if (template) shortcode += " template=" + template;
      if (group) shortcode += ' groups="' + group + '"';
      shortcode += "]";
      $scope.shortcodes[module_id] = shortcode;
    };

    $scope.buildBlockShortcode = function () {
      $scope.blockShortcode =
        "[content-egg-block template=" + $scope.blockShortcodeBuillder.template;
      if ($scope.blockShortcodeBuillder.group)
        $scope.blockShortcode +=
          ' groups="' + $scope.blockShortcodeBuillder.group + '"';
      if ($scope.blockShortcodeBuillder.next) {
        var next = parseInt($scope.blockShortcodeBuillder.next);
        if (next) $scope.blockShortcode += " next=" + next;
      }
      $scope.blockShortcode += "]";
    };

    $scope.addProductGroup = function (group) {
      if (!group) group = $scope.newProductGroup.replace(/(<([^>]+)>)/gi, "-");
      group = group.trim();
      if (!group || group === "") return;
      if (group === "-" || $scope.productGroups.includes(group)) return;
      $scope.productGroups.unshift(group);
      $scope.newProductGroup = "";

      window.ceggProductGroups = $scope.productGroups;
      const event = new Event("ceggProductGroupsUpdated");
      window.dispatchEvent(event);
    };

    $scope.removeProductGroups = function () {
      $scope.productGroups = [];

      angular.forEach($scope.active_modules, function (module_id, key) {
        if ($scope.models.hasOwnProperty(module_id)) {
          angular.forEach($scope.models[module_id], function (item, index) {
            $scope.models[module_id][index].group = "";
          });
        }
      });

      window.ceggProductGroups = $scope.productGroups;
      const event = new Event("ceggProductGroupsUpdated");
      window.dispatchEvent(event);
    };

    $scope.wooRadioChange = function (unique_id, param_name) {
      angular.forEach($scope.models, function (service, module_id) {
        for (
          var i = 0, len = $scope.models[module_id].added.length;
          i < len;
          i++
        ) {
          if ($scope.models[module_id].added[i].unique_id != unique_id) {
            $scope.models[module_id].added[i][param_name] = false;
          }
        }
      });
    };

    $scope.confirmAndRemoveProductGroups = function () {
      if (confirm("Are you sure you want to remove all product groups?")) {
        $scope.removeProductGroups();
      }
    };
  }
);

contentEgg.filter("stockStatus", function () {
  return function (item) {
    if (item == -1) return "Out of stock";
    if (item == 1) return "In stock";
  };
});

contentEgg.config(function ($sceDelegateProvider) {
  $sceDelegateProvider.resourceUrlWhitelist([
    "self",
    "https://www.youtube.com/**",
  ]);
});

contentEgg.directive("onEnter", function () {
  var linkFn = function (scope, element, attrs) {
    element.on("keypress", function (event) {
      if (event.which === 13) {
        scope.$apply(function () {
          scope.$eval(attrs.onEnter);
        });
        event.preventDefault();
      }
    });
  };
  return {
    link: linkFn,
  };
});

contentEgg.directive("imageloaded", [
  function () {
    "use strict";
    return {
      restrict: "A",
      link: function (scope, element, attrs) {
        var cssClass = attrs.loadedclass;

        element.on("load", function (e) {
          angular.element(element).addClass(cssClass);
        });
      },
    };
  },
]);

contentEgg.directive("justifiedGallery", [
  "$timeout",
  function ($timeout) {
    return {
      restrict: "A",
      link: function (scope, el, attrs) {
        scope.$watch("$last", function (n, o) {
          if (n) {
            $timeout(function () {
              angular
                .element(el)
                .justifiedGallery(scope.$eval(attrs.justifiedGallery))
                .on("jg.complete", function (e) {});
              scope.$last = false;
            });
          }
        });
      },
    };
  },
]);

contentEgg.directive("repeatDone", [
  function () {
    return {
      restrict: "A",
      link: function (scope, element, iAttrs) {
        var parentScope = element.parent().scope();
        if (scope.$last) {
          parentScope.$last = true;
        }
      },
    };
  },
]);

contentEgg.directive("ngConfirmClick", function () {
  return {
    priority: -1,
    restrict: "A",
    link: function (scope, element, attrs) {
      element.on("click", function (e) {
        var message = attrs.ngConfirmClick;
        if (message && !confirm(message)) {
          e.stopImmediatePropagation();
          e.preventDefault();
        }
      });
    },
  };
});

contentEgg.directive("selectOnClick", function () {
  return {
    restrict: "A",
    link: function (scope, element, attrs) {
      element.on("click", function () {
        this.select();
      });
    },
  };
});

contentEgg.directive("convertToNumber", function () {
  return {
    require: "ngModel",
    link: function (scope, element, attrs, ngModel) {
      ngModel.$parsers.push(function (val) {
        return val != null ? parseInt(val, 10) : null;
      });
      ngModel.$formatters.push(function (val) {
        return val != null ? "" + val : null;
      });
    },
  };
});
