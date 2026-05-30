/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./application/EggBlocks/blocks/contextual-cta/src/Edit.js"
/*!*****************************************************************!*\
  !*** ./application/EggBlocks/blocks/contextual-cta/src/Edit.js ***!
  \*****************************************************************/
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
/* harmony import */ var _shared_js_HydratedSourceControl__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../shared/js/HydratedSourceControl */ "./application/EggBlocks/shared/js/HydratedSourceControl.js");
/* harmony import */ var _shared_js_useLinkedProfile__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../shared/js/useLinkedProfile */ "./application/EggBlocks/shared/js/useLinkedProfile.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__);







const HEADING_TAG_OPTIONS = ["h1", "h2", "h3", "h4", "div"];
const VARIANT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Default", "content-egg"),
  value: "default"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Highlight", "content-egg"),
  value: "highlight"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Inline", "content-egg"),
  value: "inline"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Split", "content-egg"),
  value: "split"
}];
const DATA_SOURCE_OPTIONS = [{
  value: "auto",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Auto-fill from linked profile", "content-egg")
}, {
  value: "manual",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Manual override (custom URLs)", "content-egg")
}];
function Edit({
  attributes,
  setAttributes
}) {
  var _linkedProfile$availa;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: attributes.align ? `align${attributes.align}` : ""
  });
  const {
    variant,
    heading_tag,
    color_scheme,
    data_source,
    eyebrow,
    headline,
    text,
    primary_label,
    primary_url,
    primary_url_source,
    primary_meta,
    secondary_label,
    secondary_url,
    secondary_url_source,
    context,
    logo
  } = attributes;
  const {
    loading: profileLoading,
    summary: linkedProfile
  } = (0,_shared_js_useLinkedProfile__WEBPACK_IMPORTED_MODULE_5__["default"])();

  // Effective mode: auto only if a profile is actually linked AND user picked auto.
  const useSource = !!linkedProfile && data_source !== "manual";

  // Derive option lists for the source dropdowns.
  const urlOptions = ((_linkedProfile$availa = linkedProfile?.available_url_keys) !== null && _linkedProfile$availa !== void 0 ? _linkedProfile$availa : []).map(entry => ({
    value: entry.key,
    label: entry.label
  }));
  const hasData = !!(headline || primary_label);
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
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockAlignmentToolbar, {
    value: attributes.align,
    onChange: val => setAttributes({
      align: val
    }),
    controls: ["wide", "full"]
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Layout", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Variant", "content-egg"),
    value: variant,
    options: VARIANT_OPTIONS,
    onChange: val => setAttributes({
      variant: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Color Scheme", "content-egg"),
    value: color_scheme,
    options: [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Auto (site default)", "content-egg"),
      value: "auto"
    }, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Light", "content-egg"),
      value: "light"
    }, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Dark", "content-egg"),
      value: "dark"
    }],
    onChange: val => setAttributes({
      color_scheme: val
    }),
    __nextHasNoMarginBottom: true
  })), profileLoading && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Data source", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null)), !profileLoading && linkedProfile && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Data source", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      fontSize: "12px",
      color: "#555",
      margin: "0 0 8px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.sprintf)(/* translators: %s: linked profile name */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Linked profile: %s", "content-egg"), linkedProfile.profile_name)), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("URL & logo source", "content-egg"),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Auto-fill pulls URLs and logo from the linked profile at render time. Switch to manual to enter custom values for this block only.", "content-egg"),
    value: data_source || "auto",
    options: DATA_SOURCE_OPTIONS,
    onChange: val => setAttributes({
      data_source: val
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Content", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Eyebrow", "content-egg"),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Small section label above the headline.", "content-egg"),
    value: eyebrow,
    onChange: val => setAttributes({
      eyebrow: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Headline", "content-egg"),
    value: headline,
    onChange: val => setAttributes({
      headline: val
    }),
    __nextHasNoMarginBottom: true
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
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Heading Tag", "content-egg")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ButtonGroup, null, HEADING_TAG_OPTIONS.map(tag => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    key: tag,
    variant: heading_tag === tag ? "primary" : "secondary",
    size: "small",
    onClick: () => setAttributes({
      heading_tag: tag
    })
  }, tag.toUpperCase())))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Body text", "content-egg"),
    value: text,
    onChange: val => setAttributes({
      text: val
    }),
    __nextHasNoMarginBottom: true
  }), useSource && linkedProfile.has_logo ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: "info",
    isDismissible: false
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.sprintf)(/* translators: %s: linked profile name */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Logo will be hydrated from %s on save.", "content-egg"), linkedProfile.profile_name)) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Logo URL", "content-egg"),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Used by the Highlight variant only.", "content-egg"),
    value: logo,
    onChange: val => setAttributes({
      logo: val
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Primary action", "content-egg"),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Label", "content-egg"),
    value: primary_label,
    onChange: val => setAttributes({
      primary_label: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_HydratedSourceControl__WEBPACK_IMPORTED_MODULE_4__["default"], {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("URL", "content-egg"),
    useSource: useSource,
    sourceValue: primary_url_source,
    literalValue: primary_url,
    options: urlOptions,
    onSourceChange: val => setAttributes({
      primary_url_source: val
    }),
    onLiteralChange: val => setAttributes({
      primary_url: val
    }),
    noOptionsNotice: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("The linked profile has no URLs set.", "content-egg")
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Meta", "content-egg"),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Trust/context line at the point of action, e.g. '★ 4.8 · 9,400+ teams'.", "content-egg"),
    value: primary_meta,
    onChange: val => setAttributes({
      primary_meta: val
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Secondary action", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Label", "content-egg"),
    value: secondary_label,
    onChange: val => setAttributes({
      secondary_label: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_HydratedSourceControl__WEBPACK_IMPORTED_MODULE_4__["default"], {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("URL", "content-egg"),
    useSource: useSource,
    sourceValue: secondary_url_source,
    literalValue: secondary_url,
    options: urlOptions,
    onSourceChange: val => setAttributes({
      secondary_url_source: val
    }),
    onLiteralChange: val => setAttributes({
      secondary_url: val
    }),
    noOptionsNotice: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("The linked profile has no URLs set.", "content-egg")
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Context / footnote", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Context", "content-egg"),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Disclosure or footnote line. Not rendered in the Inline variant.", "content-egg"),
    value: context,
    onChange: val => setAttributes({
      context: val
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, hasData ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Disabled, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_3__["default"], {
    block: "eggb/contextual-cta",
    attributes: attributes
  })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      color: "#999",
      fontStyle: "italic",
      padding: "16px",
      margin: 0,
      fontSize: "13px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)("Add a headline or primary action label in the sidebar.", "content-egg"))));
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

/***/ "./application/EggBlocks/shared/js/HydratedSourceControl.js"
/*!******************************************************************!*\
  !*** ./application/EggBlocks/shared/js/HydratedSourceControl.js ***!
  \******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ HydratedSourceControl)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);

/**
 * HydratedSourceControl
 *
 * Reusable inspector control that renders either a source-key dropdown
 * (auto/hydration mode) or a literal text input (manual mode), based on the
 * `useSource` flag.
 *
 * Used by EggBlocks to expose attributes that can be hydrated from a TMN
 * profile via the `eggb/resolve_*_source` filter chain.
 *
 * Props:
 *   label              string  — control label
 *   help               string  — help text
 *   useSource          bool    — true → render source dropdown, false → text input
 *   sourceValue        string  — current value of the *_source attribute
 *   literalValue       string  — current value of the literal attribute
 *   options            array   — [{ value, label }] options for the source dropdown
 *                                (the empty "— None —" option is prepended automatically)
 *   onSourceChange     fn      — invoked when the source dropdown changes
 *   onLiteralChange    fn      — invoked when the text input changes
 *   noOptionsNotice    string  — optional message when the linked profile has no
 *                                non-empty values for this control's purpose
 */



const NONE_OPTION = {
  value: "",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("— None —", "content-egg")
};
function HydratedSourceControl({
  label,
  help,
  useSource,
  sourceValue,
  literalValue,
  options = [],
  onSourceChange,
  onLiteralChange,
  noOptionsNotice
}) {
  if (useSource) {
    const hasOptions = options.length > 0;
    const allOptions = [NONE_OPTION, ...options];
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
      label: label,
      help: help,
      value: sourceValue || "",
      options: allOptions,
      onChange: onSourceChange,
      __nextHasNoMarginBottom: true
    }), !hasOptions && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      status: "warning",
      isDismissible: false
    }, noOptionsNotice || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("The linked profile has no values for this field.", "content-egg")));
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
    label: label,
    help: help,
    value: literalValue || "",
    onChange: onLiteralChange,
    __nextHasNoMarginBottom: true
  });
}

/***/ },

/***/ "./application/EggBlocks/shared/js/useLinkedProfile.js"
/*!*************************************************************!*\
  !*** ./application/EggBlocks/shared/js/useLinkedProfile.js ***!
  \*************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ useLinkedProfile)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/**
 * useLinkedProfile
 *
 * Detects whether the current post is linked to a TMN profile (via post meta)
 * and, if so, fetches the editor-facing summary used to populate source-key
 * dropdowns in block inspectors.
 *
 * Returns:
 *   {
 *     loading: boolean,
 *     summary: null | {
 *       profile_type: 'offer' | 'business',
 *       profile_id: number,
 *       profile_name: string,
 *       has_logo: boolean,
 *       available_url_keys: Array<{ key: string, label: string, url: string }>
 *     }
 *   }
 *
 * `summary` is null when:
 *   - TMN is inactive (404 — route does not exist)
 *   - The post has no linked profile (404 — tmn_post_no_linked_profile)
 *   - The post hasn't been saved yet (no post id available)
 *
 * In all "no summary" cases the consuming block should fall back to manual mode.
 */




function useLinkedProfile() {
  const postId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => {
    var _select$getCurrentPos;
    return (_select$getCurrentPos = select("core/editor")?.getCurrentPostId()) !== null && _select$getCurrentPos !== void 0 ? _select$getCurrentPos : null;
  }, []);
  const [state, setState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    loading: true,
    summary: null
  });
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    let cancelled = false;
    if (!postId) {
      setState({
        loading: false,
        summary: null
      });
      return;
    }
    setState(prev => ({
      ...prev,
      loading: true
    }));
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
      path: `/tmn/v1/posts/${postId}/linked-profile`
    }).then(data => {
      if (cancelled) return;
      setState({
        loading: false,
        summary: data || null
      });
    }).catch(() => {
      if (cancelled) return;
      setState({
        loading: false,
        summary: null
      });
    });
    return () => {
      cancelled = true;
    };
  }, [postId]);
  return state;
}

/***/ },

/***/ "react"
/*!************************!*\
  !*** external "React" ***!
  \************************/
(module) {

module.exports = window["React"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

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

/***/ "./application/EggBlocks/blocks/contextual-cta/block.json"
/*!****************************************************************!*\
  !*** ./application/EggBlocks/blocks/contextual-cta/block.json ***!
  \****************************************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"eggb/contextual-cta","version":"1.0.0","title":"Contextual CTA","category":"cegg-blocks","icon":"megaphone","description":"Single-action conversion block for in-article calls-to-action.","textdomain":"content-egg","editorScript":"file:./index.js","render":"file:./render.php","style":["eggb-base","file:./style-min.css"],"attributes":{"variant":{"type":"string","default":"default","enum":["default","highlight","inline","split"]},"color_scheme":{"type":"string","default":"auto","enum":["auto","light","dark"]},"heading_tag":{"type":"string","default":"h3","enum":["h1","h2","h3","h4","div"]},"data_source":{"type":"string","default":"auto","enum":["auto","manual"]},"eyebrow":{"type":"string","default":""},"headline":{"type":"string","default":""},"text":{"type":"string","default":""},"primary_label":{"type":"string","default":""},"primary_url":{"type":"string","default":""},"primary_url_source":{"type":"string","default":""},"primary_meta":{"type":"string","default":""},"secondary_label":{"type":"string","default":""},"secondary_url":{"type":"string","default":""},"secondary_url_source":{"type":"string","default":""},"context":{"type":"string","default":""},"logo":{"type":"string","default":""},"style":{"type":"object","default":{"spacing":{"margin":{"bottom":"1.5rem"}}}}},"supports":{"html":false,"anchor":false,"align":["wide","full"],"spacing":{"margin":["bottom"]}}}');

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
/*!******************************************************************!*\
  !*** ./application/EggBlocks/blocks/contextual-cta/src/index.js ***!
  \******************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _Edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Edit */ "./application/EggBlocks/blocks/contextual-cta/src/Edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../block.json */ "./application/EggBlocks/blocks/contextual-cta/block.json");



(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _Edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map