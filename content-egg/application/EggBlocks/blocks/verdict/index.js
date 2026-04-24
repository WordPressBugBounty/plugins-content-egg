/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./application/EggBlocks/blocks/verdict/src/Edit.js"
/*!**********************************************************!*\
  !*** ./application/EggBlocks/blocks/verdict/src/Edit.js ***!
  \**********************************************************/
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
/* harmony import */ var _shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../shared/js/DebouncedServerSideRender */ "./application/EggBlocks/shared/js/DebouncedServerSideRender.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _shared_js_ProductRefControl__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../shared/js/ProductRefControl */ "./application/EggBlocks/shared/js/ProductRefControl.js");






const VARIANT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Default", "content-egg"),
  value: "default"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Compact", "content-egg"),
  value: "compact"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Summary", "content-egg"),
  value: "summary"
}];
const COLOR_SCHEME_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Auto (site default)", "content-egg"),
  value: "auto"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Light", "content-egg"),
  value: "light"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Dark", "content-egg"),
  value: "dark"
}];
function Edit({
  attributes,
  setAttributes
}) {
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: attributes.align ? `align${attributes.align}` : ""
  });
  const {
    variant,
    score,
    score_denom,
    score_label,
    title,
    award_label,
    chips = [],
    verdict_text,
    cta_label,
    cta_url,
    product_ref,
    band_label,
    color_scheme
  } = attributes;
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
  const hasPreviewContent = (score || "").trim() !== "" || (title || "").trim() !== "" || (award_label || "").trim() !== "" || (verdict_text || "").trim() !== "" || (chips || []).some(chip => (chip || "").trim() !== "");
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarDropdownMenu, {
    icon: "screenoptions",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Variant", "content-egg"),
    text: VARIANT_OPTIONS.find(o => o.value === variant)?.label,
    controls: VARIANT_OPTIONS.map(option => ({
      title: option.label,
      isActive: variant === option.value,
      onClick: () => setAttributes({
        variant: option.value
      })
    }))
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Layout", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Variant", "content-egg"),
    value: variant,
    options: VARIANT_OPTIONS,
    onChange: value => setAttributes({
      variant: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Color Scheme", "content-egg"),
    value: color_scheme,
    options: COLOR_SCHEME_OPTIONS,
    onChange: value => setAttributes({
      color_scheme: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Product Binding", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_ProductRefControl__WEBPACK_IMPORTED_MODULE_5__["default"], {
    value: product_ref,
    onChange: value => setAttributes({
      product_ref: value
    })
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Content", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Score", "content-egg"),
    value: score,
    onChange: value => setAttributes({
      score: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Score Denominator", "content-egg"),
    value: score_denom,
    onChange: value => setAttributes({
      score_denom: value
    })
  }), variant === "summary" && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Score Label", "content-egg"),
    value: score_label,
    onChange: value => setAttributes({
      score_label: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Title", "content-egg"),
    value: title,
    onChange: value => setAttributes({
      title: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Award Label", "content-egg"),
    value: award_label,
    onChange: value => setAttributes({
      award_label: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Verdict Text", "content-egg"),
    value: verdict_text,
    onChange: value => setAttributes({
      verdict_text: value
    }),
    rows: variant === "compact" ? 3 : 4
  }), variant === "summary" && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Band Label", "content-egg"),
    value: band_label,
    onChange: value => setAttributes({
      band_label: value
    })
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Chips", "content-egg"),
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
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Remove", "content-egg"),
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
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Add chip", "content-egg"))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("CTA", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("CTA Label", "content-egg"),
    value: cta_label,
    onChange: value => setAttributes({
      cta_label: value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("CTA URL", "content-egg"),
    value: cta_url,
    onChange: value => setAttributes({
      cta_url: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Used as a manual fallback when no bound Content Egg product URL is available.", "content-egg"),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, hasPreviewContent ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Disabled, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_3__["default"], {
    block: "eggb/verdict",
    attributes: attributes
  })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      color: "#999",
      fontStyle: "italic",
      padding: "16px",
      margin: 0,
      fontSize: "13px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Add verdict content in the sidebar.", "content-egg"))));
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

/***/ "./application/EggBlocks/blocks/verdict/block.json"
/*!*********************************************************!*\
  !*** ./application/EggBlocks/blocks/verdict/block.json ***!
  \*********************************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"eggb/verdict","version":"1.0.0","title":"Verdict","category":"cegg-blocks","icon":"star-filled","description":"Single-product verdict block with score, highlights, and CTA.","textdomain":"content-egg","editorScript":"file:./index.js","render":"file:./render.php","style":["eggb-base","file:./style-min.css"],"attributes":{"variant":{"type":"string","default":"default","enum":["default","compact","summary"]},"score":{"type":"string","default":""},"score_denom":{"type":"string","default":"/ 10"},"score_label":{"type":"string","default":"Score"},"title":{"type":"string","default":""},"award_label":{"type":"string","default":""},"chips":{"type":"array","default":[]},"verdict_text":{"type":"string","default":""},"cta_label":{"type":"string","default":""},"cta_url":{"type":"string","default":""},"product_ref":{"type":"object","default":{}},"band_label":{"type":"string","default":"Our Verdict"},"color_scheme":{"type":"string","default":"auto","enum":["auto","light","dark"]},"style":{"type":"object","default":{"spacing":{"margin":{"bottom":"1.5rem"}}}}},"supports":{"html":false,"anchor":false,"align":["wide","full"],"spacing":{"margin":["bottom"]}}}');

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
/*!***********************************************************!*\
  !*** ./application/EggBlocks/blocks/verdict/src/index.js ***!
  \***********************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _Edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Edit */ "./application/EggBlocks/blocks/verdict/src/Edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../block.json */ "./application/EggBlocks/blocks/verdict/block.json");



(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _Edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map