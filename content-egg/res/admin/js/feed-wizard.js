/**
 * Content Egg — Feed setup wizard.
 * Vanilla JS, no build step. Server side: FeedWizardController.
 */
(function () {
  "use strict";

  var cfg = window.ceggFeedWizard;
  if (!cfg) return;

  var state = {
    analysis: null,
    mapping: {}, // ce field key -> feed field (or custom expression)
    pollTimer: null,
    pollCount: 0,
  };

  var MAX_POLLS = 100; // 100 * 3s = 5 minutes

  // ---------- tiny helpers ----------

  function $(sel) {
    return document.querySelector(sel);
  }

  function show(el, on) {
    el.classList.toggle("d-none", !on);
  }

  function escapeHtml(value) {
    var div = document.createElement("div");
    div.textContent = value == null ? "" : String(value);
    return div.innerHTML;
  }

  function truncate(value, len) {
    value = value == null ? "" : String(value);
    return value.length > len ? value.slice(0, len) + "…" : value;
  }

  function ajax(action, data) {
    var body = new URLSearchParams();
    body.set("action", action);
    body.set("module", cfg.module);
    body.set("_wizard_nonce", cfg.nonce);
    Object.keys(data || {}).forEach(function (key) {
      body.set(key, data[key]);
    });

    return fetch(window.ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (json && json.error) throw new Error(json.error);
        return json;
      });
  }

  var STEP_SUBTITLES = {
    1: "subtitleStep1",
    2: "subtitleStep2",
    3: "subtitleStep3",
  };

  function setStep(step) {
    show($("#cfw-step-1"), step === 1);
    show($("#cfw-step-2"), step === 2);
    show($("#cfw-step-3"), step === 3);
    document.querySelectorAll("#cfw-steps .cfw-step").forEach(function (item) {
      var n = parseInt(item.getAttribute("data-step"), 10);
      item.classList.toggle("is-active", n === step);
      item.classList.toggle("is-done", n < step);
      item.classList.toggle("is-upcoming", n > step);
    });
    $("#cfw-subtitle").textContent = cfg.i18n[STEP_SUBTITLES[step]] || "";
    window.scrollTo({ top: 0 });
  }

  // ---------- step 1: analyze ----------

  function isValidFeedUrl(value) {
    if (!value) return false;
    try {
      var parsed = new URL(value);
      return parsed.protocol === "http:" || parsed.protocol === "https:";
    } catch (e) {
      return false;
    }
  }

  function updateUrlState() {
    var value = $("#cfw-url").value.trim();
    var valid = isValidFeedUrl(value);
    $("#cfw-analyze").disabled = !valid;
    show($("#cfw-url-error"), value.length > 0 && !valid);
  }

  $("#cfw-url").addEventListener("input", updateUrlState);
  updateUrlState();

  $("#cfw-analyze").addEventListener("click", analyze);
  $("#cfw-url").addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      analyze();
    }
  });

  function analyze() {
    var url = $("#cfw-url").value.trim();
    if (!isValidFeedUrl(url)) {
      updateUrlState();
      return;
    }

    var errorBox = $("#cfw-analyze-error");
    show(errorBox, false);
    show($("#cfw-analyze-progress"), true);
    $("#cfw-analyze").disabled = true;

    ajax("cegg_feed_wizard_analyze", { url: url })
      .then(function (json) {
        state.analysis = json.data;
        state.mapping = Object.assign({}, json.data.mapping_prefill || {});
        buildDetected();
        buildMapping();
        buildSample();
        renderPreview();
        buildSummaryDefaults();
        setStep(2);
      })
      .catch(function (error) {
        errorBox.textContent = cfg.i18n.analyzeFailed + ": " + error.message;
        show(errorBox, true);
      })
      .finally(function () {
        show($("#cfw-analyze-progress"), false);
        $("#cfw-analyze").disabled = false;
      });
  }

  // ---------- step 2: detected summary + mapping + sample ----------

  function chip(label, value) {
    return (
      '<span class="cfw-chip">' +
      escapeHtml(label) +
      ': <strong>' +
      escapeHtml(value) +
      "</strong></span>"
    );
  }

  function buildDetected() {
    var a = state.analysis;
    var html = "";

    html += chip("Format", a.format.toUpperCase());
    if (a.archive_format !== "none") html += chip("Archive", a.archive_format.toUpperCase());
    html += chip("Encoding", a.encoding);
    if (a.format === "csv") html += chip("Delimiter", a.csv_delimiter === "tab" ? "Tab" : a.csv_delimiter);
    if (a.product_node) html += chip("Product node", a.product_node);
    if (a.currency) html += chip("Currency", a.currency);
    if (a.domain) html += chip("Merchant", a.domain);
    if (a.estimated_rows) {
      html += chip(a.complete ? "Products" : "Products (approx.)", a.estimated_rows.toLocaleString());
    }

    (a.warnings || []).forEach(function (warning) {
      html += '<div class="alert alert-warning py-2 small mt-2 mb-0">' + escapeHtml(warning) + "</div>";
    });

    $("#cfw-detected").innerHTML = html;

    var aiBtn = $("#cfw-ai-map");
    var aiInfo = $("#cfw-ai-map-info");
    aiBtn.disabled = !a.has_ai_key;
    show(aiInfo, !a.has_ai_key);
    aiInfo.title = a.has_ai_key ? "" : cfg.i18n.aiKeyMissing;
  }

  function buildMapping() {
    var container = $("#cfw-mapping");
    container.innerHTML = "";

    cfg.mappingFields.forEach(function (field) {
      var row = document.createElement("div");
      row.className = "cfw-map-row";

      var label = document.createElement("div");
      label.className = "cfw-map-label";
      label.appendChild(document.createTextNode(field.label));

      if (field.required) {
        var required = document.createElement("span");
        required.className = "text-danger";
        required.title = cfg.i18n.required;
        required.textContent = " *";
        label.appendChild(required);
      }

      if (field.hint) {
        var hint = document.createElement("i");
        hint.className = "bi bi-info-circle cfw-map-hint";
        hint.title = field.hint;
        hint.setAttribute("aria-hidden", "true");
        label.appendChild(document.createTextNode(" "));
        label.appendChild(hint);
      }

      var selectWrap = document.createElement("div");
      selectWrap.className = "cfw-map-control";

      var select = document.createElement("select");
      select.className = "form-select form-select-sm";
      select.setAttribute("data-ce-field", field.key);

      var optNone = new Option(cfg.i18n.notMapped, "");
      select.add(optNone);
      (state.analysis.feed_fields || []).forEach(function (feedField) {
        select.add(new Option(feedField, feedField));
      });
      select.add(new Option(cfg.i18n.custom, "__custom__"));

      var current = state.mapping[field.key] || "";
      if (current && (state.analysis.feed_fields || []).indexOf(current) === -1) {
        // custom / regex / xpath value
        select.value = "__custom__";
      } else {
        select.value = current;
      }

      var custom = document.createElement("input");
      custom.type = "text";
      custom.className = "form-control form-control-sm mt-1";
      custom.placeholder = "XPath / [regex][pattern][field]";
      custom.setAttribute("data-ce-custom", field.key);
      custom.value = select.value === "__custom__" ? current : "";
      custom.style.display = select.value === "__custom__" ? "" : "none";

      select.addEventListener("change", function () {
        if (select.value === "__custom__") {
          custom.style.display = "";
          state.mapping[field.key] = custom.value.trim();
        } else {
          custom.style.display = "none";
          if (select.value) state.mapping[field.key] = select.value;
          else delete state.mapping[field.key];
        }
        renderPreview();
        validateMapping(true);
      });

      custom.addEventListener("input", function () {
        state.mapping[field.key] = custom.value.trim();
        renderPreview();
      });

      selectWrap.appendChild(select);
      selectWrap.appendChild(custom);
      row.appendChild(label);
      row.appendChild(selectWrap);
      container.appendChild(row);
    });
  }

  function buildSample() {
    var table = $("#cfw-sample");
    var records = state.analysis.sample_records || [];
    var fields = state.analysis.feed_fields || [];
    var shown = records.slice(0, 3);

    var html = '<thead><tr><th style="min-width: 140px;">Feed field</th>';
    shown.forEach(function (record, index) {
      html += "<th>#" + (index + 1) + "</th>";
    });
    html += "</tr></thead><tbody>";

    fields.forEach(function (field) {
      html += "<tr><td><code>" + escapeHtml(field) + "</code></td>";
      shown.forEach(function (record) {
        html += "<td>" + escapeHtml(truncate(record[field], 90)) + "</td>";
      });
      html += "</tr>";
    });

    table.innerHTML = html + "</tbody>";
  }

  function mappedValue(ceField) {
    var records = state.analysis.sample_records || [];
    if (!records.length) return "";
    var feedField = state.mapping[ceField];
    if (!feedField) return "";
    return records[0][feedField] != null ? String(records[0][feedField]) : "";
  }

  function findMappingKey(labelNeedle) {
    var found = "";
    cfg.mappingFields.forEach(function (field) {
      if (field.label === labelNeedle) found = field.key;
    });
    return found;
  }

  function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  // The raw feed value sometimes already includes the currency (e.g. "95.00 USD");
  // only append the detected currency when it isn't already present, to avoid "95.00 USD USD".
  function withCurrency(value, currency) {
    if (!value || !currency) return value;

    var already = new RegExp("(^|[^a-z0-9])" + escapeRegExp(currency) + "([^a-z0-9]|$)", "i").test(value);

    return already ? value : value + " " + currency;
  }

  function renderPreview() {
    var title = mappedValue(findMappingKey("title"));
    var price = mappedValue(findMappingKey("price"));
    var salePrice = mappedValue(findMappingKey("sale price"));
    var image = mappedValue(findMappingKey("image link"));
    var link = mappedValue(findMappingKey("affiliate link"));
    var currency = state.analysis.currency;

    var html = '<div class="d-flex align-items-center">';
    if (image && /^https?:\/\//i.test(image)) {
      html +=
        '<img src="' +
        escapeHtml(image.split(",")[0]) +
        '" alt="" style="width:64px;height:64px;object-fit:contain;" class="border rounded me-3" onerror="this.style.display=\'none\'" />';
    }
    html += "<div>";
    html += '<div class="fw-bold">' + (title ? escapeHtml(truncate(title, 120)) : "<em>—</em>") + "</div>";
    html += '<div class="small text-muted">';
    if (salePrice) {
      html +=
        '<span class="text-danger fw-bold me-2">' +
        escapeHtml(withCurrency(salePrice, currency)) +
        "</span><s>" +
        escapeHtml(withCurrency(price, currency)) +
        "</s>";
    } else {
      html += escapeHtml(withCurrency(price, currency) || "—");
    }
    html += "</div>";
    if (link) html += '<div class="small text-truncate" style="max-width: 640px;">' + escapeHtml(link) + "</div>";
    html += "</div></div>";

    $("#cfw-preview").innerHTML = html;
  }

  function validateMapping(silent) {
    var missing = [];
    cfg.mappingFields.forEach(function (field) {
      if (field.required && !(state.mapping[field.key] || "").length) missing.push(field.label);
    });

    var errorBox = $("#cfw-mapping-error");
    if (missing.length && !silent) {
      errorBox.textContent = cfg.i18n.mapRequired + " (" + missing.join(", ") + ")";
      show(errorBox, true);
    } else if (!missing.length) {
      show(errorBox, false);
    }

    return missing.length === 0;
  }

  $("#cfw-ai-map").addEventListener("click", function () {
    var button = this;
    var a = state.analysis;
    if (!a || !a.ai_sample) return;

    var original = button.innerHTML;
    button.disabled = true;
    button.textContent = cfg.i18n.aiMapping;

    ajax("cegg_feed_wizard_ai_map", {
      format: a.format,
      sample: a.format === "xml" ? a.ai_sample : JSON.stringify(a.ai_sample),
    })
      .then(function (json) {
        Object.keys(json.mapping || {}).forEach(function (ceField) {
          state.mapping[ceField] = json.mapping[ceField];
        });
        buildMapping();
        renderPreview();
        validateMapping(true);
      })
      .catch(function (error) {
        window.alert(error.message);
      })
      .finally(function () {
        button.disabled = false;
        button.innerHTML = original;
      });
  });

  $("#cfw-back-1").addEventListener("click", function () {
    setStep(1);
  });

  $("#cfw-continue-2").addEventListener("click", function () {
    if (!validateMapping(false)) return;
    buildSummaryDefaults();
    setStep(3);
  });

  // ---------- step 3: confirm + save + progress ----------

  function buildSummaryDefaults() {
    var a = state.analysis;
    if (!a) return;

    $("#cfw-name").value = a.feed_name || "";

    var items = [
      ["URL", truncate(a.url, 80)],
      ["Format", a.format.toUpperCase() + (a.archive_format !== "none" ? " (" + a.archive_format + ")" : "")],
      ["Currency", a.currency],
      ["Merchant domain", a.domain],
      ["Mapped fields", String(Object.keys(state.mapping).length)],
    ];
    if (a.estimated_rows) items.push([a.complete ? "Products" : "Products (approx.)", a.estimated_rows.toLocaleString()]);

    $("#cfw-summary").innerHTML = items
      .map(function (item) {
        return "<li><strong>" + escapeHtml(item[0]) + ":</strong> " + escapeHtml(item[1]) + "</li>";
      })
      .join("");
  }

  $("#cfw-back-2").addEventListener("click", function () {
    setStep(2);
  });

  $("#cfw-failed-back").addEventListener("click", function () {
    show($("#cfw-progress"), false);
    show($("#cfw-finish-row"), true);
    setStep(2);
  });

  $("#cfw-finish").addEventListener("click", function () {
    var a = state.analysis;
    var button = this;
    button.disabled = true;
    button.textContent = cfg.i18n.saving;

    var settings = {
      feed_url: a.url,
      feed_name: $("#cfw-name").value.trim(),
      feed_format: a.format,
      archive_format: a.archive_format,
      encoding: a.encoding,
      currency: a.currency,
      domain: a.domain,
      csv_delimiter: a.csv_delimiter,
      csv_enclosure: a.csv_enclosure,
      product_node: a.product_node,
      price_decimal_separator: a.price_decimal_separator,
      mapping: state.mapping,
      sync_interval: $("#cfw-interval").value,
      in_stock: $("#cfw-instock").checked ? 1 : 0,
    };

    ajax("cegg_feed_wizard_save", { settings: JSON.stringify(settings) })
      .then(function (json) {
        $("#cfw-done-settings").href = json.settings_url;
        $("#cfw-done-post").href = cfg.newPostUrl;
        show($("#cfw-finish-row"), false);
        show($("#cfw-progress"), true);
        startPolling();
      })
      .catch(function (error) {
        window.alert(error.message);
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-check2-circle me-1"></i>' + "Save & import products";
      });
  });

  function startPolling() {
    state.pollCount = 0;
    $("#cfw-progress-rows").textContent = "";
    $("#cfw-progress-fill").style.width = "0%";
    show($("#cfw-progress-track"), false);
    show($("#cfw-progress-pct"), false);
    show($("#cfw-progress-running"), true);
    show($("#cfw-progress-done"), false);
    show($("#cfw-progress-failed"), false);
    poll();
  }

  function poll() {
    state.pollCount++;

    ajax("cegg_feed_wizard_status", {})
      .then(function (json) {
        var status = json.status || {};

        if (status.state === "completed" && json.products > 0) {
          return finishProgress(true, json.products.toLocaleString() + " " + cfg.i18n.importDone);
        }
        if (status.state === "failed" || json.last_error) {
          return finishProgress(false, cfg.i18n.importFailed + " " + (status.error || json.last_error));
        }

        if (status.state === "running") {
          updateProgressStats(status);
        }

        if (state.pollCount >= MAX_POLLS) {
          return finishProgress(
            true,
            "The import is taking a while — it keeps running in the background. Check the module settings page later."
          );
        }

        state.pollTimer = window.setTimeout(poll, 3000);
      })
      .catch(function () {
        // transient AJAX failure: keep polling
        if (state.pollCount < MAX_POLLS) state.pollTimer = window.setTimeout(poll, 5000);
      });
  }

  function updateProgressStats(status) {
    var rowsRead = Number(status.rows_read) || 0;
    var inserted = Number(status.inserted) || 0;
    var skipped = Number(status.skipped) || 0;
    var estimated = (state.analysis && state.analysis.estimated_rows) || 0;

    if (rowsRead > 0) {
      var parts = [rowsRead.toLocaleString() + " " + cfg.i18n.rowsProcessed];
      if (inserted > 0) parts.push(inserted.toLocaleString() + " " + cfg.i18n.rowsInserted);
      if (skipped > 0) parts.push(skipped.toLocaleString() + " " + cfg.i18n.rowsSkipped);
      $("#cfw-progress-rows").textContent = parts.join(" · ");
    }

    if (estimated > 0) {
      var pct = Math.min(100, Math.round((rowsRead / estimated) * 100));
      show($("#cfw-progress-track"), true);
      show($("#cfw-progress-pct"), true);
      $("#cfw-progress-fill").style.width = pct + "%";
      $("#cfw-progress-pct").textContent = pct + "%" + (state.analysis.complete ? "" : " " + cfg.i18n.approxLabel);
    }
  }

  function finishProgress(success, message) {
    show($("#cfw-progress-running"), false);

    if (success) {
      $("#cfw-done-label").textContent = message;
      show($("#cfw-progress-done"), true);
    } else {
      $("#cfw-failed-label").textContent = message;
      show($("#cfw-progress-failed"), true);
    }
  }
})();
