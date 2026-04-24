/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./application/EggBlocks/blocks/where-to-buy/src/Edit.js"
/*!***************************************************************!*\
  !*** ./application/EggBlocks/blocks/where-to-buy/src/Edit.js ***!
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
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _shared_js_tocAnchorDiagnostics__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../shared/js/tocAnchorDiagnostics */ "./application/EggBlocks/shared/js/tocAnchorDiagnostics.js");
/* harmony import */ var _shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../../shared/js/DebouncedServerSideRender */ "./application/EggBlocks/shared/js/DebouncedServerSideRender.js");
/* harmony import */ var _shared_js_ProductRefsControl__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../../shared/js/ProductRefsControl */ "./application/EggBlocks/shared/js/ProductRefsControl.js");
/* harmony import */ var _shared_js_useCeggEditorProducts__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../../../shared/js/useCeggEditorProducts */ "./application/EggBlocks/shared/js/useCeggEditorProducts.js");










const VARIANT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Default", "content-egg"),
  value: "default"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Compact", "content-egg"),
  value: "compact"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Table Compact", "content-egg"),
  value: "table-compact"
}];
const COLOR_SCHEME_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Auto (site default)", "content-egg"),
  value: "auto"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Light", "content-egg"),
  value: "light"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Dark", "content-egg"),
  value: "dark"
}];
function createItemId() {
  return `wtb-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}
function Edit({
  attributes,
  setAttributes
}) {
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: attributes.align ? `align${attributes.align}` : ""
  });
  const snapshot = (0,_shared_js_useCeggEditorProducts__WEBPACK_IMPORTED_MODULE_9__["default"])();
  const {
    variant,
    section_label,
    title,
    heading_tag,
    cta_label,
    footer_note,
    items = [],
    anchor,
    toc_label,
    include_in_toc,
    level,
    color_scheme
  } = attributes;
  const allBlocks = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => select("core/block-editor").getBlocks(), []);
  const hasDuplicateTocAnchor = !!include_in_toc && (0,_shared_js_tocAnchorDiagnostics__WEBPACK_IMPORTED_MODULE_6__.isDuplicateTocAnchor)(allBlocks, anchor);
  const suggestedTocLabel = (title || section_label || "").trim();
  const seededValuesRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)({});
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const nextItems = [...(items || [])];
    const nextSeededValues = {};
    let hasChanges = false;
    nextItems.forEach((item, index) => {
      const itemId = item?.id || createItemId();
      if (!item?.id) {
        nextItems[index] = {
          ...item,
          id: itemId
        };
        hasChanges = true;
      }
      const ref = item?.product_ref || {};
      const productKey = `${String(ref.module_id || "")}:${String(ref.unique_id || "")}`;
      const product = (snapshot.all || []).find(candidate => `${String(candidate.module_id || "")}:${String(candidate.unique_id || "")}` === productKey) || null;
      if (!product || productKey === ":") {
        return;
      }
      const previousSeeded = seededValuesRef.current[itemId] || {
        productKey: "",
        title: "",
        merchant: ""
      };
      const nextSeeded = {
        productKey,
        title: String(product.title || "").trim(),
        merchant: String(product.merchant || product.domain || "").trim()
      };
      nextSeededValues[itemId] = nextSeeded;
      if (previousSeeded.productKey === productKey) {
        return;
      }
      const currentTitle = String(nextItems[index]?.title || "").trim();
      const currentMerchant = String(nextItems[index]?.merchant || "").trim();
      const nextPatch = {};
      if (nextSeeded.title !== "" && (currentTitle === "" || currentTitle === previousSeeded.title)) {
        nextPatch.title = nextSeeded.title;
      }
      if (nextSeeded.merchant !== "" && (currentMerchant === "" || currentMerchant === previousSeeded.merchant)) {
        nextPatch.merchant = nextSeeded.merchant;
      }
      if (Object.keys(nextPatch).length > 0) {
        nextItems[index] = {
          ...nextItems[index],
          ...nextPatch
        };
        hasChanges = true;
      }
    });
    seededValuesRef.current = nextSeededValues;
    if (hasChanges) {
      setAttributes({
        items: nextItems
      });
    }
  }, [items, setAttributes, snapshot.all]);
  const updateItems = nextItems => setAttributes({
    items: nextItems
  });
  const hasPreviewContent = Array.isArray(items) && items.length > 0;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarDropdownMenu, {
    icon: "screenoptions",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Variant", "content-egg"),
    text: VARIANT_OPTIONS.find(o => o.value === variant)?.label,
    controls: VARIANT_OPTIONS.map(option => ({
      title: option.label,
      isActive: variant === option.value,
      onClick: () => setAttributes({
        variant: option.value
      })
    }))
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Layout", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Variant", "content-egg"),
    value: variant,
    options: VARIANT_OPTIONS,
    onChange: value => setAttributes({
      variant: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Color Scheme", "content-egg"),
    value: color_scheme,
    options: COLOR_SCHEME_OPTIONS,
    onChange: value => setAttributes({
      color_scheme: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Product Binding", "content-egg"),
    initialOpen: !hasPreviewContent
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_ProductRefsControl__WEBPACK_IMPORTED_MODULE_8__["default"], {
    items: items,
    onChange: updateItems,
    preserveUnboundRows: true,
    emptyMessage: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("No offers are currently bound to this block.", "content-egg"),
    createEmptyItem: productRef => ({
      id: createItemId(),
      product_ref: productRef,
      merchant: "",
      title: "",
      chips: []
    }),
    renderItemFields: ({
      item,
      updateItem
    }) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        display: "flex",
        flexDirection: "column",
        gap: "10px"
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Merchant", "content-egg"),
      value: item?.merchant || "",
      onChange: value => updateItem(currentItem => ({
        ...currentItem,
        merchant: value
      }))
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Title", "content-egg"),
      value: item?.title || "",
      onChange: value => updateItem(currentItem => ({
        ...currentItem,
        title: value
      })),
      __nextHasNoMarginBottom: true
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        fontSize: "11px",
        fontWeight: 600,
        marginBottom: "6px"
      }
    }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Chips", "content-egg")), (item?.chips || []).map((chip, chipIndex) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: chipIndex,
      style: {
        display: "flex",
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
      onChange: value => updateItem(currentItem => {
        const nextChips = [...(currentItem?.chips || [])];
        nextChips[chipIndex] = value;
        return {
          ...currentItem,
          chips: nextChips
        };
      }),
      __nextHasNoMarginBottom: true
    })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      icon: "no-alt",
      isDestructive: true,
      size: "small",
      onClick: () => updateItem(currentItem => {
        const nextChips = [...(currentItem?.chips || [])];
        nextChips.splice(chipIndex, 1);
        return {
          ...currentItem,
          chips: nextChips
        };
      })
    }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      variant: "secondary",
      size: "small",
      onClick: () => updateItem(currentItem => ({
        ...currentItem,
        chips: [...(currentItem?.chips || []), ""]
      }))
    }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Add chip", "content-egg"))))
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Content", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Section Label", "content-egg"),
    value: section_label,
    onChange: value => setAttributes({
      section_label: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Title", "content-egg"),
    value: title,
    onChange: value => setAttributes({
      title: value
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
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Heading Tag", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ButtonGroup, null, ["h1", "h2", "h3", "h4", "div"].map(tag => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    key: tag,
    variant: heading_tag === tag ? "primary" : "secondary",
    size: "small",
    onClick: () => setAttributes({
      heading_tag: tag
    })
  }, tag.toUpperCase())))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("CTA Label", "content-egg"),
    value: cta_label,
    onChange: value => setAttributes({
      cta_label: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Footer Note", "content-egg"),
    value: footer_note,
    onChange: value => setAttributes({
      footer_note: value
    }),
    rows: 3,
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("TOC", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Anchor", "content-egg"),
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
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("This anchor is already used by another TOC block.", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Include In TOC", "content-egg"),
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
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Set an anchor so the TOC can link to this block.", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("TOC Label", "content-egg"),
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
  }, suggestedTocLabel ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Suggested label: %s", "content-egg"), `"${suggestedTocLabel}"`) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Set a TOC label so this block can appear in automatic TOC mode.", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Level", "content-egg"),
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
  }, hasPreviewContent ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Disabled, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_7__["default"], {
    block: "eggb/where-to-buy",
    attributes: attributes
  })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      color: "#999",
      fontStyle: "italic",
      padding: "16px",
      margin: 0,
      fontSize: "13px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Bind Content Egg products to preview this block.", "content-egg"))));
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

/***/ "./application/EggBlocks/shared/js/ProductRefsControl.js"
/*!***************************************************************!*\
  !*** ./application/EggBlocks/shared/js/ProductRefsControl.js ***!
  \***************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ProductRefsControl)
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
function getProductKey(moduleId, uniqueId) {
  return `${String(moduleId || "")}:${String(uniqueId || "")}`;
}
function buildProductRef(product, postId) {
  return {
    module_id: String(product?.module_id || ""),
    unique_id: String(product?.unique_id || ""),
    post_id: postId
  };
}
function getModuleLabels() {
  const modules = window?.contentEggProductsBlockData?.modules;
  return modules && typeof modules === "object" ? modules : {};
}
function ProductRefsControl({
  items = [],
  onChange,
  createEmptyItem,
  renderItemFields,
  emptyMessage = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("No products are currently bound to this block.", "content-egg"),
  preserveUnboundRows = false
}) {
  const snapshot = (0,_useCeggEditorProducts__WEBPACK_IMPORTED_MODULE_5__["default"])();
  const currentPostId = useCurrentPostId();
  const postId = currentPostId || snapshot.postId || 0;
  const byModule = snapshot.byModule || {};
  const moduleLabels = getModuleLabels();
  const [isPickerOpen, setIsPickerOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const [moduleFilter, setModuleFilter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)("");
  const [groupFilter, setGroupFilter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)("");
  const [pickerMode, setPickerMode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)({
    type: "add",
    index: null
  });
  const [selectedProductKeys, setSelectedProductKeys] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)([]);
  const [expandedItemIds, setExpandedItemIds] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)({});
  const moduleIds = Object.keys(byModule).filter(moduleId => Array.isArray(byModule[moduleId]) && byModule[moduleId].length > 0);
  const filterOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("All modules", "content-egg"),
    value: ""
  }, ...moduleIds.map(moduleId => ({
    label: moduleLabels[moduleId] || moduleId,
    value: moduleId
  }))];
  const itemsWithProducts = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useMemo)(() => {
    return (items || []).map((item, index) => {
      const ref = item?.product_ref || {};
      const product = (snapshot.all || []).find(candidate => getProductKey(candidate.module_id, candidate.unique_id) === getProductKey(ref.module_id, ref.unique_id)) || null;
      return {
        item,
        index,
        product,
        productKey: getProductKey(ref.module_id, ref.unique_id)
      };
    });
  }, [items, snapshot.all]);
  const existingKeys = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useMemo)(() => itemsWithProducts.map(({
    productKey
  }) => productKey).filter(productKey => productKey !== ":"), [itemsWithProducts]);
  const moduleFilteredProducts = moduleFilter && Array.isArray(byModule[moduleFilter]) ? byModule[moduleFilter] : snapshot.all || [];
  const groupValues = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useMemo)(() => Array.from(new Set(moduleFilteredProducts.map(product => String(product?.group || "").trim()).filter(Boolean))).sort((left, right) => left.localeCompare(right)), [moduleFilteredProducts]);
  const groupFilterOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("All groups", "content-egg"),
    value: ""
  }, ...groupValues.map(group => ({
    label: group,
    value: group
  }))];
  const filteredProducts = groupFilter ? moduleFilteredProducts.filter(product => String(product?.group || "").trim() === groupFilter) : moduleFilteredProducts;
  const addableFilteredProducts = filteredProducts.filter(product => !existingKeys.includes(getProductKey(product.module_id, product.unique_id)));
  const currentReplaceKey = pickerMode.type === "replace" && pickerMode.index !== null ? itemsWithProducts[pickerMode.index]?.productKey || "" : "";
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (!snapshot.updatedAt) {
      return;
    }
    const nextItems = (items || []).filter(item => {
      const ref = item?.product_ref || {};
      if (!ref.module_id || !ref.unique_id) {
        return preserveUnboundRows;
      }
      return (snapshot.all || []).some(product => getProductKey(product.module_id, product.unique_id) === getProductKey(ref.module_id, ref.unique_id));
    });
    if (nextItems.length !== (items || []).length) {
      onChange(nextItems);
    }
  }, [items, onChange, snapshot.all, snapshot.updatedAt, preserveUnboundRows]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (groupFilter && !groupValues.includes(groupFilter)) {
      setGroupFilter("");
    }
  }, [groupFilter, groupValues]);
  const updateItem = (index, updater) => {
    const nextItems = [...(items || [])];
    const currentItem = nextItems[index];
    nextItems[index] = typeof updater === "function" ? updater(currentItem) : {
      ...currentItem,
      ...updater
    };
    onChange(nextItems);
  };
  const removeItem = index => {
    const nextItems = [...(items || [])];
    nextItems.splice(index, 1);
    onChange(nextItems);
  };
  const openAddPicker = () => {
    setPickerMode({
      type: "add",
      index: null
    });
    setSelectedProductKeys([]);
    setModuleFilter("");
    setGroupFilter("");
    setIsPickerOpen(true);
  };
  const openReplacePicker = (index, moduleId) => {
    setPickerMode({
      type: "replace",
      index
    });
    setSelectedProductKeys([]);
    setModuleFilter(moduleId || "");
    setGroupFilter("");
    setIsPickerOpen(true);
  };
  const toggleSelectedProduct = productKey => {
    setSelectedProductKeys(currentKeys => currentKeys.includes(productKey) ? currentKeys.filter(key => key !== productKey) : [...currentKeys, productKey]);
  };
  const toggleExpandedItem = (itemId, fallbackIndex) => {
    const key = itemId || `row-${fallbackIndex}`;
    setExpandedItemIds(current => ({
      ...current,
      [key]: !current[key]
    }));
  };
  const handleAddSelectedProducts = () => {
    const selectedProducts = (snapshot.all || []).filter(product => selectedProductKeys.includes(getProductKey(product.module_id, product.unique_id)));
    if (selectedProducts.length === 0) {
      return;
    }
    const nextItems = [...(items || []), ...selectedProducts.filter(product => !existingKeys.includes(getProductKey(product.module_id, product.unique_id))).map(product => createEmptyItem(buildProductRef(product, postId)))];
    onChange(nextItems);
    setIsPickerOpen(false);
  };
  const handleAddAllProducts = () => {
    if (addableFilteredProducts.length === 0) {
      return;
    }
    const nextItems = [...(items || []), ...addableFilteredProducts.map(product => createEmptyItem(buildProductRef(product, postId)))];
    onChange(nextItems);
    setIsPickerOpen(false);
  };
  const handleReplaceProduct = (index, product) => {
    updateItem(index, currentItem => ({
      ...currentItem,
      product_ref: buildProductRef(product, postId),
      seeded_for: ""
    }));
    setIsPickerOpen(false);
  };
  const hasAvailableProducts = moduleIds.length > 0;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "10px"
    }
  }, itemsWithProducts.length > 0 ? itemsWithProducts.map(({
    item,
    index,
    product
  }) => {
    const itemId = item?.id || `row-${index}`;
    const isExpanded = !!expandedItemIds[itemId];
    const hasRef = !!(item?.product_ref?.module_id && item?.product_ref?.unique_id);
    const fallbackTitle = String(item?.title || "").trim();
    const displayTitle = product?.title || fallbackTitle || product?.unique_id || (hasRef ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Missing product", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Unbound item", "content-egg"));
    const metaModuleId = product?.module_id || item?.product_ref?.module_id || "";
    const metaText = product ? (moduleLabels[metaModuleId] || metaModuleId) + (formatPrice(product) ? ` · ${formatPrice(product)}` : "") : hasRef ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Missing product", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Not bound to a product", "content-egg");
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: itemId,
      style: {
        borderTop: index > 0 ? "1px solid #e0e0e0" : "0",
        paddingTop: index > 0 ? "10px" : 0
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        display: "flex",
        gap: "8px",
        alignItems: "flex-start"
      }
    }, product ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        width: "48px",
        height: "48px",
        flexShrink: 0,
        borderRadius: "6px",
        overflow: "hidden",
        border: "1px solid #dcdcde",
        background: "#fff"
      }
    }, product.img ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
      src: product.img,
      alt: "",
      style: {
        width: "100%",
        height: "100%",
        objectFit: "contain",
        display: "block"
      }
    }) : null) : null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        minWidth: 0,
        flex: 1
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      title: displayTitle,
      style: {
        fontSize: "12px",
        fontWeight: 600,
        color: "#1e1e1e",
        whiteSpace: "nowrap",
        overflow: "hidden",
        textOverflow: "ellipsis",
        marginBottom: "2px"
      }
    }, displayTitle), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        fontSize: "11px",
        color: "#757575"
      }
    }, metaText))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        display: "flex",
        gap: "8px",
        flexWrap: "wrap",
        marginTop: "8px"
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      variant: "secondary",
      size: "small",
      onClick: () => openReplacePicker(index, item?.product_ref?.module_id || ""),
      disabled: !hasAvailableProducts
    }, product ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Change product", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Bind product", "content-egg")), renderItemFields && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      variant: "tertiary",
      size: "small",
      onClick: () => toggleExpandedItem(itemId, index)
    }, isExpanded ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Hide details", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Edit details", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      variant: "tertiary",
      isDestructive: true,
      size: "small",
      onClick: () => removeItem(index)
    }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Remove", "content-egg"))), isExpanded && renderItemFields ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        marginTop: "10px"
      }
    }, renderItemFields({
      item,
      index,
      product,
      updateItem: updater => updateItem(index, updater)
    })) : null);
  }) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      margin: 0,
      fontSize: "12px",
      color: "#757575"
    }
  }, !hasAvailableProducts ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("No Content Egg products are currently available for this post.", "content-egg") : emptyMessage), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      gap: "8px",
      flexWrap: "wrap"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
    variant: "secondary",
    size: "small",
    onClick: openAddPicker,
    disabled: !hasAvailableProducts
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Add products", "content-egg"))), !postId && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      margin: 0,
      fontSize: "12px",
      color: "#757575"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Save the post to ensure product bindings have a valid post ID.", "content-egg"))), isPickerOpen && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Modal, {
    title: pickerMode.type === "replace" ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Replace Content Egg Product", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Select Content Egg Products", "content-egg"),
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
  })) : null), filteredProducts.length > 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "row row-cols-2 row-cols-md-4 row-cols-xl-5 g-3"
  }, filteredProducts.map(product => {
    const productKey = getProductKey(product.module_id, product.unique_id);
    const isAlreadyBound = existingKeys.includes(productKey);
    const isUnavailable = pickerMode.type === "add" ? isAlreadyBound : isAlreadyBound && productKey !== currentReplaceKey;
    const isSelected = pickerMode.type === "replace" ? productKey === currentReplaceKey : selectedProductKeys.includes(productKey);
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: productKey,
      className: "col"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
      type: "button",
      className: "card h-100 text-start w-100",
      onClick: () => {
        if (pickerMode.type === "replace" && pickerMode.index !== null) {
          if (isUnavailable) {
            return;
          }
          handleReplaceProduct(pickerMode.index, product);
          return;
        }
        if (isUnavailable) {
          return;
        }
        toggleSelectedProduct(productKey);
      },
      disabled: isUnavailable,
      style: {
        borderColor: isSelected ? "var(--cegg-success)" : undefined,
        boxShadow: isSelected ? "0 0 0 1px var(--cegg-success)" : undefined,
        opacity: isUnavailable ? 0.55 : 1,
        background: "#fff",
        padding: 0,
        cursor: isUnavailable ? "not-allowed" : "pointer"
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
    }, formatPrice(product)), isUnavailable ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "small text-muted mt-1"
    }, pickerMode.type === "replace" ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Already bound", "content-egg") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Already added", "content-egg")) : null)));
  })), pickerMode.type === "add" ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "d-flex justify-content-end gap-2 mt-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
    variant: "secondary",
    onClick: handleAddAllProducts,
    disabled: addableFilteredProducts.length === 0
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Add all", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
    variant: "primary",
    onClick: handleAddSelectedProducts,
    disabled: selectedProductKeys.length === 0
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Add selected", "content-egg"))) : null) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
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

/***/ "./application/EggBlocks/blocks/where-to-buy/block.json"
/*!**************************************************************!*\
  !*** ./application/EggBlocks/blocks/where-to-buy/block.json ***!
  \**************************************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"eggb/where-to-buy","version":"1.0.0","title":"Where to Buy","category":"cegg-blocks","icon":"cart","description":"Multi-merchant buying options block powered by explicit Content Egg product bindings.","textdomain":"content-egg","editorScript":"file:./index.js","render":"file:./render.php","style":["eggb-base","file:./style-min.css"],"attributes":{"variant":{"type":"string","default":"default","enum":["default","compact","table-compact"]},"section_label":{"type":"string","default":"Where to buy"},"title":{"type":"string","default":""},"heading_tag":{"type":"string","default":"h2","enum":["h1","h2","h3","h4","div"]},"cta_label":{"type":"string","default":""},"footer_note":{"type":"string","default":""},"items":{"type":"array","default":[]},"anchor":{"type":"string","default":""},"toc_label":{"type":"string","default":""},"include_in_toc":{"type":"boolean","default":false},"level":{"type":"number","default":2},"color_scheme":{"type":"string","default":"auto","enum":["auto","light","dark"]},"style":{"type":"object","default":{"spacing":{"margin":{"bottom":"1.5rem"}}}}},"supports":{"html":false,"anchor":false,"align":["wide","full"],"spacing":{"margin":["bottom"]}}}');

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
  !*** ./application/EggBlocks/blocks/where-to-buy/src/index.js ***!
  \****************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _Edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Edit */ "./application/EggBlocks/blocks/where-to-buy/src/Edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../block.json */ "./application/EggBlocks/blocks/where-to-buy/block.json");



(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _Edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map