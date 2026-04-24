/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./application/EggBlocks/blocks/product-card/src/Edit.js"
/*!***************************************************************!*\
  !*** ./application/EggBlocks/blocks/product-card/src/Edit.js ***!
  \***************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../shared/js/DebouncedServerSideRender */ "./application/EggBlocks/shared/js/DebouncedServerSideRender.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _shared_js_tocAnchorDiagnostics__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../../shared/js/tocAnchorDiagnostics */ "./application/EggBlocks/shared/js/tocAnchorDiagnostics.js");
/* harmony import */ var _shared_js_ProductRefControl__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../../shared/js/ProductRefControl */ "./application/EggBlocks/shared/js/ProductRefControl.js");
/* harmony import */ var _shared_js_useCeggEditorProducts__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../../../shared/js/useCeggEditorProducts */ "./application/EggBlocks/shared/js/useCeggEditorProducts.js");










const VARIANT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Default", "content-egg"),
  value: "default"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Featured", "content-egg"),
  value: "featured"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Compact", "content-egg"),
  value: "compact"
}];
const COLOR_SCHEME_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Auto (site default)", "content-egg"),
  value: "auto"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Light", "content-egg"),
  value: "light"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Dark", "content-egg"),
  value: "dark"
}];
function Edit({
  attributes,
  setAttributes
}) {
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: attributes.align ? `align${attributes.align}` : ""
  });
  const productSnapshot = (0,_shared_js_useCeggEditorProducts__WEBPACK_IMPORTED_MODULE_9__["default"])();
  const {
    variant,
    product_ref,
    section_label,
    block_title,
    heading_tag,
    title,
    rank,
    badge,
    score,
    subtitle,
    chips = [],
    description,
    merchant,
    cta_label,
    anchor,
    toc_label,
    include_in_toc,
    level,
    color_scheme,
    seeded_for,
    enable_schema
  } = attributes;
  const allBlocks = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => select("core/block-editor").getBlocks(), []);
  const hasDuplicateTocAnchor = !!include_in_toc && (0,_shared_js_tocAnchorDiagnostics__WEBPACK_IMPORTED_MODULE_7__.isDuplicateTocAnchor)(allBlocks, anchor);
  const suggestedTocLabel = (block_title || section_label || "").trim();
  const lastPrefilledProductKeyRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)("");
  const lastSeededValuesRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)({
    title: "",
    subtitle: "",
    merchant: "",
    badge: ""
  });
  const selectedProduct = (productSnapshot.all || []).find(product => String(product.module_id || "") === String(product_ref?.module_id || "") && String(product.unique_id || "") === String(product_ref?.unique_id || "")) || null;
  const hasConfiguredProductRef = !!(product_ref?.module_id && product_ref?.unique_id);
  const updateChips = nextChips => setAttributes({
    chips: nextChips
  });
  const addChip = () => updateChips([...(chips || []), ""]);
  const updateChip = (index, value) => {
    const next = [...(chips || [])];
    next[index] = value;
    updateChips(next);
  };
  const removeChip = index => {
    const next = [...(chips || [])];
    next.splice(index, 1);
    updateChips(next);
  };
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const productKey = `${String(product_ref?.module_id || "")}:${String(product_ref?.unique_id || "")}`;
    if (!selectedProduct || productKey === ":") {
      lastPrefilledProductKeyRef.current = "";
      lastSeededValuesRef.current = {
        title: "",
        subtitle: "",
        merchant: "",
        badge: ""
      };
      return;
    }
    if (lastPrefilledProductKeyRef.current === productKey) {
      return;
    }
    if (String(seeded_for || "") === productKey) {
      lastPrefilledProductKeyRef.current = productKey;
      lastSeededValuesRef.current = {
        title: String(selectedProduct.title || "").trim(),
        subtitle: String(selectedProduct.subtitle || "").trim(),
        merchant: String(selectedProduct.merchant || selectedProduct.domain || "").trim(),
        badge: String(selectedProduct.badge || "").trim()
      };
      return;
    }
    const nextSeededValues = {
      title: String(selectedProduct.title || "").trim(),
      subtitle: String(selectedProduct.subtitle || "").trim(),
      merchant: String(selectedProduct.merchant || selectedProduct.domain || "").trim(),
      badge: String(selectedProduct.badge || "").trim()
    };
    const previousSeededValues = lastSeededValuesRef.current;
    const nextAttributes = {};
    const currentTitle = (title || "").trim();
    if (nextSeededValues.title !== "" && (currentTitle === "" || currentTitle === previousSeededValues.title)) {
      nextAttributes.title = nextSeededValues.title;
    }
    const currentSubtitle = (subtitle || "").trim();
    if (nextSeededValues.subtitle !== "" && (currentSubtitle === "" || currentSubtitle === previousSeededValues.subtitle)) {
      nextAttributes.subtitle = nextSeededValues.subtitle;
    }
    const currentMerchant = (merchant || "").trim();
    if (nextSeededValues.merchant !== "" && (currentMerchant === "" || currentMerchant === previousSeededValues.merchant)) {
      nextAttributes.merchant = nextSeededValues.merchant;
    }
    const currentBadge = (badge || "").trim();
    if (nextSeededValues.badge !== "" && (currentBadge === "" || currentBadge === previousSeededValues.badge)) {
      nextAttributes.badge = nextSeededValues.badge;
    }
    lastPrefilledProductKeyRef.current = productKey;
    lastSeededValuesRef.current = nextSeededValues;
    nextAttributes.seeded_for = productKey;
    setAttributes(nextAttributes);
  }, [badge, merchant, product_ref?.module_id, product_ref?.unique_id, seeded_for, selectedProduct, setAttributes, subtitle, title]);
  const hasAuthoredContent = !!((title || "").trim() || (description || "").trim() || (subtitle || "").trim() || Array.isArray(chips) && chips.some(chip => (chip || "").trim() !== ""));
  const hasPreviewContent = !!(selectedProduct || hasConfiguredProductRef || hasAuthoredContent);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarDropdownMenu, {
    icon: "screenoptions",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Variant", "content-egg"),
    text: VARIANT_OPTIONS.find(o => o.value === variant)?.label,
    controls: VARIANT_OPTIONS.map(option => ({
      title: option.label,
      isActive: variant === option.value,
      onClick: () => setAttributes({
        variant: option.value
      })
    }))
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Layout", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Variant", "content-egg"),
    value: variant,
    options: VARIANT_OPTIONS,
    onChange: value => setAttributes({
      variant: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Color Scheme", "content-egg"),
    value: color_scheme,
    options: COLOR_SCHEME_OPTIONS,
    onChange: value => setAttributes({
      color_scheme: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Product Binding", "content-egg"),
    initialOpen: !hasConfiguredProductRef
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_ProductRefControl__WEBPACK_IMPORTED_MODULE_8__["default"], {
    value: product_ref,
    onChange: value => setAttributes({
      product_ref: value,
      seeded_for: ""
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginTop: "16px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Enable product schema (JSON-LD)", "content-egg"),
    checked: !!enable_schema,
    onChange: value => setAttributes({
      enable_schema: value
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Header", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Section Label", "content-egg"),
    value: section_label,
    onChange: value => setAttributes({
      section_label: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Title", "content-egg"),
    value: block_title,
    onChange: value => setAttributes({
      block_title: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "8px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      fontSize: "11px",
      fontWeight: 500,
      marginBottom: "6px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Heading Tag", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ButtonGroup, null, ["h1", "h2", "h3", "h4", "div"].map(tag => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    key: tag,
    variant: heading_tag === tag ? "primary" : "secondary",
    size: "small",
    onClick: () => setAttributes({
      heading_tag: tag
    })
  }, tag.toUpperCase()))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Content", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Title", "content-egg"),
    value: title,
    onChange: value => setAttributes({
      title: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Rank", "content-egg"),
    value: rank,
    onChange: value => setAttributes({
      rank: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Badge", "content-egg"),
    value: badge,
    onChange: value => setAttributes({
      badge: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Score", "content-egg"),
    value: score,
    onChange: value => setAttributes({
      score: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Subtitle", "content-egg"),
    value: subtitle,
    onChange: value => setAttributes({
      subtitle: value
    }),
    rows: 2
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Description", "content-egg"),
    value: description,
    onChange: value => setAttributes({
      description: value
    }),
    rows: 3
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Merchant & CTA", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Merchant", "content-egg"),
    value: merchant,
    onChange: value => setAttributes({
      merchant: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("CTA Label", "content-egg"),
    value: cta_label,
    onChange: value => setAttributes({
      cta_label: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Chips", "content-egg"),
    initialOpen: false
  }, (chips || []).map((chip, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: index,
    style: {
      display: "flex",
      alignItems: "center",
      gap: "4px",
      marginBottom: "6px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    value: chip,
    onChange: value => updateChip(index, value),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    icon: "no-alt",
    isDestructive: true,
    size: "small",
    onClick: () => removeChip(index),
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Remove", "content-egg"),
    style: {
      flexShrink: 0
    }
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    variant: "secondary",
    size: "small",
    onClick: addChip,
    style: {
      marginTop: "4px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Add chip", "content-egg"))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("TOC", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Anchor", "content-egg"),
    value: anchor || "",
    onChange: value => setAttributes({
      anchor: value
    })
  }), hasDuplicateTocAnchor && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      marginTop: "-4px",
      marginBottom: "12px",
      color: "#8a1f11",
      fontSize: "12px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("This anchor is already used by another TOC block.", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Include In TOC", "content-egg"),
    checked: !!include_in_toc,
    onChange: value => setAttributes({
      include_in_toc: value
    })
  }), include_in_toc && !(anchor || "").trim() && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      marginTop: "-4px",
      marginBottom: "12px",
      color: "#757575",
      fontSize: "12px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Set an anchor so the TOC can link to this block.", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("TOC Label", "content-egg"),
    value: toc_label || "",
    onChange: value => setAttributes({
      toc_label: value
    })
  }), include_in_toc && !(toc_label || "").trim() && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      marginTop: "-4px",
      marginBottom: "12px",
      color: "#757575",
      fontSize: "12px"
    }
  }, suggestedTocLabel ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Suggested label: %s", "content-egg"), `"${suggestedTocLabel}"`) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Set a TOC label so this block can appear in automatic TOC mode.", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Level", "content-egg"),
    type: "number",
    min: 1,
    max: 6,
    value: level !== null && level !== void 0 ? level : 2,
    onChange: value => setAttributes({
      level: value === "" ? 2 : Number(value)
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, hasPreviewContent ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Disabled, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_5__["default"], {
    block: "eggb/product-card",
    attributes: attributes
  })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      color: "#999",
      fontStyle: "italic",
      padding: "16px",
      margin: 0,
      fontSize: "13px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Bind a Content Egg product to preview this block.", "content-egg"))));
}

/***/ },

/***/ "./application/EggBlocks/shared/js/DebouncedServerSideRender.js"
/*!**********************************************************************!*\
  !*** ./application/EggBlocks/shared/js/DebouncedServerSideRender.js ***!
  \**********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ DebouncedServerSideRender)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_2__);

/**
 * DebouncedServerSideRender
 *
 * Drop-in wrapper around @wordpress/server-side-render that debounces
 * attribute changes so the REST render call isn't fired on every keystroke.
 *
 * Usage (identical API to ServerSideRender):
 *
 *   import DebouncedServerSideRender from "../../../shared/js/DebouncedServerSideRender";
 *
 *   <DebouncedServerSideRender
 *       block="eggb/pros-cons"
 *       attributes={attributes}
 *       debounceMs={300}          // optional, default 300
 *   />
 */



function DebouncedServerSideRender({
  attributes,
  debounceMs = 300,
  ...rest
}) {
  const [debouncedAttrs, setDebouncedAttrs] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(attributes);
  const timer = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (timer.current) {
      clearTimeout(timer.current);
    }
    timer.current = setTimeout(() => {
      setDebouncedAttrs(attributes);
    }, debounceMs);
    return () => {
      if (timer.current) {
        clearTimeout(timer.current);
      }
    };
  }, [attributes, debounceMs]);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_2___default()), {
    attributes: debouncedAttrs,
    ...rest
  });
}

/***/ },

/***/ "./application/EggBlocks/shared/js/ProductRefControl.js"
/*!**************************************************************!*\
  !*** ./application/EggBlocks/shared/js/ProductRefControl.js ***!
  \**************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ProductRefControl)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _useCeggEditorProducts__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./useCeggEditorProducts */ "./application/EggBlocks/shared/js/useCeggEditorProducts.js");






function useCurrentPostId() {
  return (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    const store = select("core/editor");
    if (!store || typeof store.getCurrentPostId !== "function") {
      return 0;
    }
    return Number(store.getCurrentPostId()) || 0;
  }, []);
}
function formatPrice(product) {
  const price = String(product?.price || "").trim();
  const currencyCode = String(product?.currencyCode || "").trim();
  if (!price) {
    return "";
  }
  return currencyCode ? `${price} ${currencyCode}` : price;
}
function getModuleLabels() {
  const modules = window?.contentEggProductsBlockData?.modules;
  return modules && typeof modules === "object" ? modules : {};
}
function ProductRefControl({
  value = {},
  onChange
}) {
  const snapshot = (0,_useCeggEditorProducts__WEBPACK_IMPORTED_MODULE_5__["default"])();
  const currentPostId = useCurrentPostId();
  const postId = currentPostId || snapshot.postId || Number(value?.post_id) || 0;
  const byModule = snapshot.byModule || {};
  const moduleLabels = getModuleLabels();
  const selectedModuleId = String(value?.module_id || "");
  const selectedUniqueId = String(value?.unique_id || "");
  const [isPickerOpen, setIsPickerOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const [moduleFilter, setModuleFilter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(selectedModuleId);
  const [groupFilter, setGroupFilter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)("");
  const moduleIds = Object.keys(byModule).filter(moduleId => Array.isArray(byModule[moduleId]) && byModule[moduleId].length > 0);
  const filterOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("All modules", "content-egg"),
    value: ""
  }, ...moduleIds.map(moduleId => ({
    label: moduleLabels[moduleId] || moduleId,
    value: moduleId
  }))];
  const selectedProduct = (snapshot.all || []).find(product => String(product.module_id || "") === selectedModuleId && String(product.unique_id || "") === selectedUniqueId) || null;
  const moduleFilteredProducts = moduleFilter && Array.isArray(byModule[moduleFilter]) ? byModule[moduleFilter] : snapshot.all || [];
  const groupValues = Array.from(new Set(moduleFilteredProducts.map(product => String(product?.group || "").trim()).filter(Boolean))).sort((left, right) => left.localeCompare(right));
  const groupFilterOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("All groups", "content-egg"),
    value: ""
  }, ...groupValues.map(group => ({
    label: group,
    value: group
  }))];
  const filteredProducts = groupFilter ? moduleFilteredProducts.filter(product => String(product?.group || "").trim() === groupFilter) : moduleFilteredProducts;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    setModuleFilter(selectedModuleId);
  }, [selectedModuleId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (groupFilter && !groupValues.includes(groupFilter)) {
      setGroupFilter("");
    }
  }, [groupFilter, groupValues]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (!snapshot.updatedAt) {
      return;
    }
    if (!selectedModuleId && !selectedUniqueId) {
      return;
    }
    if (selectedProduct) {
      return;
    }
    setValue({});
  }, [selectedModuleId, selectedUniqueId, selectedProduct, snapshot.updatedAt]);
  const setValue = nextValue => onChange(nextValue || {});
  const hasAvailableProducts = moduleIds.length > 0;
  const clearBinding = () => setValue({});
  const handleProductSelect = product => {
    setValue({
      module_id: String(product?.module_id || ""),
      unique_id: String(product?.unique_id || ""),
      post_id: postId
    });
    setIsPickerOpen(false);
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "10px"
    }
  }, selectedProduct ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "cegg5-container"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "d-flex gap-2 align-items-start"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      width: "56px",
      height: "56px",
      flexShrink: 0,
      borderRadius: "6px",
      overflow: "hidden",
      background: "#f6f7f7",
      border: "1px solid #dcdcde"
    }
  }, selectedProduct.img ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    src: selectedProduct.img,
    alt: "",
    style: {
      width: "100%",
      height: "100%",
      objectFit: "cover",
      display: "block"
    }
  }) : null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      minWidth: 0,
      fontSize: "12px",
      color: "#50575e",
      flex: 1
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    title: selectedProduct.title || selectedProduct.unique_id,
    style: {
      fontWeight: 600,
      color: "#1e1e1e",
      marginBottom: "2px",
      whiteSpace: "nowrap",
      overflow: "hidden",
      textOverflow: "ellipsis"
    }
  }, selectedProduct.title || selectedProduct.unique_id), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, moduleLabels[selectedProduct.module_id] || selectedProduct.module_id), formatPrice(selectedProduct) && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, formatPrice(selectedProduct))))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      margin: 0,
      fontSize: "12px",
      color: "#757575"
    }
  }, !hasAvailableProducts ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("No Content Egg products are currently available for this post.", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("No product is currently bound to this block.", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      gap: "8px",
      flexWrap: "wrap"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
    variant: "secondary",
    size: "small",
    onClick: () => setIsPickerOpen(true),
    disabled: !hasAvailableProducts
  }, selectedProduct ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Change product", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Select product", "content-egg")), selectedProduct && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
    variant: "tertiary",
    isDestructive: true,
    size: "small",
    onClick: clearBinding
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Clear binding", "content-egg"))), !postId && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      margin: 0,
      fontSize: "12px",
      color: "#757575"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Save the post to ensure the product binding has a valid post ID.", "content-egg"))), isPickerOpen && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Modal, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Select Content Egg Product", "content-egg"),
    onRequestClose: () => setIsPickerOpen(false),
    size: "large",
    className: "modal-xl"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "cegg5-container"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "row g-3 align-items-end mb-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: groupValues.length > 0 ? "col-12 col-md-6" : "col-12"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Filter by Module", "content-egg"),
    value: moduleFilter,
    options: filterOptions,
    onChange: setModuleFilter
  })), groupValues.length > 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "col-12 col-md-6"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Filter by Group", "content-egg"),
    value: groupFilter,
    options: groupFilterOptions,
    onChange: setGroupFilter
  })) : null), filteredProducts.length > 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "row row-cols-2 row-cols-md-4 row-cols-xl-5 g-3"
  }, filteredProducts.map(product => {
    const isSelected = String(product.module_id || "") === selectedModuleId && String(product.unique_id || "") === selectedUniqueId;
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: `${product.module_id}-${product.unique_id}`,
      className: "col"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
      type: "button",
      className: "card h-100 text-start w-100",
      onClick: () => handleProductSelect(product),
      style: {
        borderColor: isSelected ? "var(--cegg-success)" : undefined,
        boxShadow: isSelected ? "0 0 0 1px var(--cegg-success)" : undefined,
        background: "#fff",
        padding: 0,
        cursor: "pointer"
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "ratio ratio-1x1",
      style: {
        overflow: "hidden",
        borderBottom: "1px solid #e0e0e0"
      }
    }, product.img ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
      src: product.img,
      alt: "",
      className: "object-fit-scale",
      style: {
        maxHeight: "250px",
        width: "100%",
        height: "100%",
        objectFit: "contain",
        display: "block"
      }
    }) : null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "card-body p-2"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "small text-muted mb-1"
    }, moduleLabels[product.module_id] || product.module_id), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "fw-semibold lh-sm mb-1 text-truncate",
      title: product.title || product.unique_id
    }, product.title || product.unique_id), formatPrice(product) && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "small"
    }, formatPrice(product)))));
  })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "mb-0 text-muted"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("No products found for the current filter.", "content-egg")))));
}

/***/ },

/***/ "./application/EggBlocks/shared/js/collectTocItems.js"
/*!************************************************************!*\
  !*** ./application/EggBlocks/shared/js/collectTocItems.js ***!
  \************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   buildNestedTocItems: () => (/* binding */ buildNestedTocItems),
/* harmony export */   "default": () => (/* binding */ collectTocItems),
/* harmony export */   normalizeAnchor: () => (/* binding */ normalizeAnchor)
/* harmony export */ });
/* harmony import */ var _tocRegistry__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./tocRegistry */ "./application/EggBlocks/shared/js/tocRegistry.js");

function normalizeAnchor(anchor) {
  let value = String(anchor || "").trim();
  if (!value) {
    return "";
  }
  if (value.startsWith("#")) {
    value = value.slice(1);
  }
  return value.toLowerCase().replace(/[^a-z0-9\s_-]/g, "").trim().replace(/[\s_]+/g, "-").replace(/-+/g, "-");
}
function normalizeLabel(label) {
  return String(label || "").replace(/<[^>]*>/g, "").trim();
}
function normalizeLevel(level, fallback = 2) {
  const numeric = Number(level);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }
  const normalized = Math.trunc(numeric);
  if (normalized < 1 || normalized > 6) {
    return fallback;
  }
  return normalized;
}
function walk(blocks, items) {
  for (const block of blocks || []) {
    if (!block || typeof block !== "object") {
      continue;
    }
    const name = block.name || "";
    if (name && name !== "eggb/toc" && (0,_tocRegistry__WEBPACK_IMPORTED_MODULE_0__.isTocSupportedBlock)(name)) {
      const attrs = block.attributes || {};
      if (attrs.include_in_toc) {
        const anchor = normalizeAnchor(attrs.anchor);
        let label = normalizeLabel(attrs.toc_label);
        if (!label) {
          const ha = (0,_tocRegistry__WEBPACK_IMPORTED_MODULE_0__.headingAttr)(name);
          if (ha) {
            label = normalizeLabel(attrs[ha]);
          }
        }
        if (!label) {
          const la = (0,_tocRegistry__WEBPACK_IMPORTED_MODULE_0__.labelAttr)(name);
          if (la) {
            label = normalizeLabel(attrs[la]);
          }
        }
        const level = normalizeLevel(attrs.level, (0,_tocRegistry__WEBPACK_IMPORTED_MODULE_0__.defaultTocLevel)(name));
        if (anchor && label) {
          items.push({
            anchor,
            label,
            level
          });
        }
      }
    }
    if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
      walk(block.innerBlocks, items);
    }
  }
}
function collectTocItems(blocks) {
  const items = [];
  walk(blocks, items);
  return items;
}
function buildNestedTocItems(items) {
  if (!Array.isArray(items) || items.length === 0) {
    return [];
  }
  const baseLevel = items.reduce((minLevel, item) => {
    const level = Number(item?.level || 2);
    return Number.isFinite(level) ? Math.min(minLevel, level) : minLevel;
  }, 6);
  const nestedItems = [];
  let currentParentIndex = -1;
  for (const item of items) {
    const normalizedItem = {
      ...item,
      level: Number(item?.level || baseLevel),
      sub_items: []
    };
    if (normalizedItem.level <= baseLevel || currentParentIndex === -1) {
      nestedItems.push(normalizedItem);
      currentParentIndex = nestedItems.length - 1;
      continue;
    }
    nestedItems[currentParentIndex].sub_items.push({
      ...normalizedItem,
      level: normalizedItem.level
    });
  }
  return nestedItems;
}

/***/ },

/***/ "./application/EggBlocks/shared/js/tocAnchorDiagnostics.js"
/*!*****************************************************************!*\
  !*** ./application/EggBlocks/shared/js/tocAnchorDiagnostics.js ***!
  \*****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getDuplicateTocAnchors: () => (/* binding */ getDuplicateTocAnchors),
/* harmony export */   isDuplicateTocAnchor: () => (/* binding */ isDuplicateTocAnchor)
/* harmony export */ });
/* harmony import */ var _tocRegistry__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./tocRegistry */ "./application/EggBlocks/shared/js/tocRegistry.js");
/* harmony import */ var _collectTocItems__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./collectTocItems */ "./application/EggBlocks/shared/js/collectTocItems.js");


function walk(blocks, callback) {
  for (const block of blocks || []) {
    if (!block || typeof block !== "object") {
      continue;
    }
    callback(block);
    if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
      walk(block.innerBlocks, callback);
    }
  }
}
function getDuplicateTocAnchors(blocks) {
  const counts = new Map();
  walk(blocks, block => {
    const name = block.name || "";
    if (name === "eggb/toc" || !(0,_tocRegistry__WEBPACK_IMPORTED_MODULE_0__.isTocSupportedBlock)(name)) {
      return;
    }
    const attrs = block.attributes || {};
    if (!attrs.include_in_toc) {
      return;
    }
    const anchor = (0,_collectTocItems__WEBPACK_IMPORTED_MODULE_1__.normalizeAnchor)(attrs.anchor);
    if (!anchor) {
      return;
    }
    counts.set(anchor, (counts.get(anchor) || 0) + 1);
  });
  return Array.from(counts.entries()).filter(([, count]) => count > 1).map(([anchor]) => anchor);
}
function isDuplicateTocAnchor(blocks, anchor) {
  const normalizedAnchor = (0,_collectTocItems__WEBPACK_IMPORTED_MODULE_1__.normalizeAnchor)(anchor);
  if (!normalizedAnchor) {
    return false;
  }
  return getDuplicateTocAnchors(blocks).includes(normalizedAnchor);
}

/***/ },

/***/ "./application/EggBlocks/shared/js/tocRegistry.js"
/*!********************************************************!*\
  !*** ./application/EggBlocks/shared/js/tocRegistry.js ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   allTocBlocks: () => (/* binding */ allTocBlocks),
/* harmony export */   defaultTocLevel: () => (/* binding */ defaultTocLevel),
/* harmony export */   headingAttr: () => (/* binding */ headingAttr),
/* harmony export */   isTocSupportedBlock: () => (/* binding */ isTocSupportedBlock),
/* harmony export */   labelAttr: () => (/* binding */ labelAttr)
/* harmony export */ });
const TOC_REGISTRY = {
  "eggb/section-header": {
    defaultLevel: 2,
    headingAttr: "title"
  },
  "eggb/intro": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/conclusion": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/faq": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/methodology": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/step-list": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/key-takeaways": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/criteria": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/definitions": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/myth-fact": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/specifications": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/quick-picks": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/where-to-buy": {
    defaultLevel: 2,
    headingAttr: "title",
    labelAttr: "section_label"
  },
  "eggb/product-card": {
    defaultLevel: 2,
    headingAttr: "block_title",
    labelAttr: "section_label"
  },
  "eggb/comparison-table": {
    defaultLevel: 2,
    headingAttr: "heading_title",
    labelAttr: "heading_label"
  }
};
function isTocSupportedBlock(name) {
  return Object.prototype.hasOwnProperty.call(TOC_REGISTRY, name);
}
function defaultTocLevel(name) {
  return TOC_REGISTRY[name]?.defaultLevel || 2;
}
function headingAttr(name) {
  return TOC_REGISTRY[name]?.headingAttr || null;
}
function labelAttr(name) {
  return TOC_REGISTRY[name]?.labelAttr || null;
}
function allTocBlocks() {
  return TOC_REGISTRY;
}

/***/ },

/***/ "./application/EggBlocks/shared/js/useCeggEditorProducts.js"
/*!******************************************************************!*\
  !*** ./application/EggBlocks/shared/js/useCeggEditorProducts.js ***!
  \******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ useCeggEditorProducts)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);

function readSnapshot() {
  const snapshot = window.ceggEditorProducts;
  if (!snapshot || typeof snapshot !== "object") {
    return {
      postId: 0,
      byModule: {},
      all: [],
      updatedAt: 0
    };
  }
  return {
    postId: Number(snapshot.postId) || 0,
    byModule: snapshot.byModule && typeof snapshot.byModule === "object" ? snapshot.byModule : {},
    all: Array.isArray(snapshot.all) ? snapshot.all : [],
    updatedAt: Number(snapshot.updatedAt) || 0
  };
}
function useCeggEditorProducts() {
  const [snapshot, setSnapshot] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(readSnapshot);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const handleUpdate = event => {
      if (event?.detail && typeof event.detail === "object") {
        setSnapshot(readSnapshot());
        return;
      }
      setSnapshot(readSnapshot());
    };
    window.addEventListener("ceggEditorProductsUpdated", handleUpdate);
    return () => window.removeEventListener("ceggEditorProductsUpdated", handleUpdate);
  }, []);
  return snapshot;
}

/***/ },

/***/ "react"
/*!************************!*\
  !*** external "React" ***!
  \************************/
(module) {

module.exports = window["React"];

/***/ },

/***/ "@wordpress/block-editor"
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
(module) {

module.exports = window["wp"]["blockEditor"];

/***/ },

/***/ "@wordpress/blocks"
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
(module) {

module.exports = window["wp"]["blocks"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/data"
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["data"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/server-side-render"
/*!******************************************!*\
  !*** external ["wp","serverSideRender"] ***!
  \******************************************/
(module) {

module.exports = window["wp"]["serverSideRender"];

/***/ },

/***/ "./application/EggBlocks/blocks/product-card/block.json"
/*!**************************************************************!*\
  !*** ./application/EggBlocks/blocks/product-card/block.json ***!
  \**************************************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"eggb/product-card","version":"1.0.0","title":"Product Card","category":"cegg-blocks","icon":"products","description":"Roundup-style product card with default, featured, and compact layouts.","textdomain":"content-egg","editorScript":"file:./index.js","render":"file:./render.php","style":["eggb-base","file:./style-min.css"],"attributes":{"variant":{"type":"string","default":"default","enum":["default","featured","compact"]},"product_ref":{"type":"object","default":{}},"section_label":{"type":"string","default":""},"block_title":{"type":"string","default":""},"heading_tag":{"type":"string","default":"h2","enum":["h1","h2","h3","h4","div"]},"title":{"type":"string","default":""},"rank":{"type":"string","default":""},"badge":{"type":"string","default":""},"score":{"type":"string","default":""},"subtitle":{"type":"string","default":""},"chips":{"type":"array","default":[]},"description":{"type":"string","default":""},"merchant":{"type":"string","default":""},"cta_label":{"type":"string","default":""},"anchor":{"type":"string","default":""},"toc_label":{"type":"string","default":""},"include_in_toc":{"type":"boolean","default":false},"level":{"type":"number","default":2},"color_scheme":{"type":"string","default":"auto","enum":["auto","light","dark"]},"enable_schema":{"type":"boolean","default":true},"seeded_for":{"type":"string","default":""},"style":{"type":"object","default":{"spacing":{"margin":{"bottom":"1.5rem"}}}}},"supports":{"html":false,"anchor":false,"align":["wide","full"],"spacing":{"margin":["bottom"]}}}');

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!****************************************************************!*\
  !*** ./application/EggBlocks/blocks/product-card/src/index.js ***!
  \****************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _Edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Edit */ "./application/EggBlocks/blocks/product-card/src/Edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../block.json */ "./application/EggBlocks/blocks/product-card/block.json");



(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _Edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map