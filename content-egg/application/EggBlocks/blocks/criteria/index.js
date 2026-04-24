/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./application/EggBlocks/blocks/criteria/src/Edit.js"
/*!***********************************************************!*\
  !*** ./application/EggBlocks/blocks/criteria/src/Edit.js ***!
  \***********************************************************/
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
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../shared/js/DebouncedServerSideRender */ "./application/EggBlocks/shared/js/DebouncedServerSideRender.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _shared_js_tocAnchorDiagnostics__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../shared/js/tocAnchorDiagnostics */ "./application/EggBlocks/shared/js/tocAnchorDiagnostics.js");







const VARIANT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Default", "content-egg"),
  value: "default"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("List", "content-egg"),
  value: "list"
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
const IMPORTANCE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("High", "content-egg"),
  value: "high"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Medium", "content-egg"),
  value: "medium"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Low", "content-egg"),
  value: "low"
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
    section_label,
    title,
    heading_tag,
    criteria = [],
    anchor,
    toc_label,
    include_in_toc,
    level,
    color_scheme
  } = attributes;
  const allBlocks = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select("core/block-editor").getBlocks(), []);
  const hasDuplicateTocAnchor = !!include_in_toc && (0,_shared_js_tocAnchorDiagnostics__WEBPACK_IMPORTED_MODULE_6__.isDuplicateTocAnchor)(allBlocks, anchor);
  const suggestedTocLabel = (title || section_label || "").trim();
  const updateCriteria = nextCriteria => setAttributes({
    criteria: nextCriteria
  });
  const addCriterion = () => updateCriteria([...(criteria || []), {
    title: "",
    description: "",
    importance: "medium",
    look_for: "",
    avoid: ""
  }]);
  const updateCriterion = (index, key, value) => {
    const next = [...(criteria || [])];
    next[index] = {
      title: "",
      description: "",
      importance: "medium",
      look_for: "",
      avoid: "",
      ...(next[index] || {}),
      [key]: value
    };
    updateCriteria(next);
  };
  const removeCriterion = index => {
    const next = [...(criteria || [])];
    next.splice(index, 1);
    updateCriteria(next);
  };
  const hasPreviewContent = (criteria || []).some(item => (item?.title || "").trim() !== "" || (item?.description || "").trim() !== "" || (item?.look_for || "").trim() !== "" || (item?.avoid || "").trim() !== "");
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
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Color Scheme", "content-egg"),
    value: color_scheme,
    options: COLOR_SCHEME_OPTIONS,
    onChange: value => setAttributes({
      color_scheme: value
    }),
    __nextHasNoMarginBottom: true
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
  }, tag.toUpperCase()))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Criteria", "content-egg"),
    initialOpen: false
  }, (criteria || []).map((item, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: index,
    style: {
      marginBottom: index === criteria.length - 1 ? "0" : "10px",
      paddingBottom: index === criteria.length - 1 ? "0" : "10px",
      borderBottom: index === criteria.length - 1 ? "none" : "1px solid #e0e0e0"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      justifyContent: "flex-end",
      marginBottom: "6px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    icon: "no-alt",
    isDestructive: true,
    size: "small",
    onClick: () => removeCriterion(index),
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Remove", "content-egg")
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Title", "content-egg"),
    value: item?.title || "",
    onChange: value => updateCriterion(index, "title", value)
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Importance", "content-egg"),
    value: item?.importance || "medium",
    options: IMPORTANCE_OPTIONS,
    onChange: value => updateCriterion(index, "importance", value)
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Description", "content-egg"),
    value: item?.description || "",
    onChange: value => updateCriterion(index, "description", value),
    rows: 3
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Look For", "content-egg"),
    value: item?.look_for || "",
    onChange: value => updateCriterion(index, "look_for", value),
    rows: 2
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Avoid", "content-egg"),
    value: item?.avoid || "",
    onChange: value => updateCriterion(index, "avoid", value),
    rows: 2,
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    variant: "secondary",
    size: "small",
    onClick: addCriterion,
    style: {
      marginTop: "10px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Add criterion", "content-egg"))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
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
  }, hasPreviewContent ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Disabled, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "eggb/criteria",
    attributes: attributes
  })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      color: "#999",
      fontStyle: "italic",
      padding: "16px",
      margin: 0,
      fontSize: "13px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)("Add criteria in the sidebar.", "content-egg"))));
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

/***/ "./application/EggBlocks/blocks/criteria/block.json"
/*!**********************************************************!*\
  !*** ./application/EggBlocks/blocks/criteria/block.json ***!
  \**********************************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"eggb/criteria","version":"1.0.0","title":"Criteria","category":"cegg-blocks","icon":"search","description":"Buying criteria with importance levels, guidance, and avoid notes.","textdomain":"content-egg","editorScript":"file:./index.js","render":"file:./render.php","style":["eggb-base","file:./style-min.css"],"attributes":{"variant":{"type":"string","default":"default","enum":["default","list"]},"section_label":{"type":"string","default":"What to look for"},"title":{"type":"string","default":""},"heading_tag":{"type":"string","default":"h2","enum":["h1","h2","h3","h4","div"]},"criteria":{"type":"array","default":[]},"anchor":{"type":"string","default":""},"toc_label":{"type":"string","default":""},"include_in_toc":{"type":"boolean","default":false},"level":{"type":"number","default":2},"color_scheme":{"type":"string","default":"auto","enum":["auto","light","dark"]},"style":{"type":"object","default":{"spacing":{"margin":{"bottom":"1.5rem"}}}}},"supports":{"html":false,"align":["wide","full"],"spacing":{"margin":["bottom"]}}}');

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
/*!************************************************************!*\
  !*** ./application/EggBlocks/blocks/criteria/src/index.js ***!
  \************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _Edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Edit */ "./application/EggBlocks/blocks/criteria/src/Edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../block.json */ "./application/EggBlocks/blocks/criteria/block.json");



(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _Edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map