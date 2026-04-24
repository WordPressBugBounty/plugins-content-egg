/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./application/EggBlocks/blocks/pros-cons/src/Edit.js"
/*!************************************************************!*\
  !*** ./application/EggBlocks/blocks/pros-cons/src/Edit.js ***!
  \************************************************************/
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





const VARIANT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Default", "content-egg"),
  value: "default"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Compact", "content-egg"),
  value: "compact"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Highlight", "content-egg"),
  value: "highlight"
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Inline", "content-egg"),
  value: "inline"
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
    pros,
    cons,
    quick_take,
    color_scheme
  } = attributes;
  const addItem = key => {
    setAttributes({
      [key]: [...(attributes[key] || []), ""]
    });
  };
  const updateItem = (key, index, value) => {
    const updated = [...(attributes[key] || [])];
    updated[index] = value;
    setAttributes({
      [key]: updated
    });
  };
  const removeItem = (key, index) => {
    const updated = [...(attributes[key] || [])];
    updated.splice(index, 1);
    setAttributes({
      [key]: updated
    });
  };
  const renderList = (key, label) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: label,
    initialOpen: true
  }, (attributes[key] || []).map((item, i) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: i,
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
    value: item,
    onChange: val => updateItem(key, i, val),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    icon: "no-alt",
    isDestructive: true,
    size: "small",
    onClick: () => removeItem(key, i),
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Remove", "content-egg"),
    style: {
      flexShrink: 0,
      marginTop: 0
    }
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    variant: "secondary",
    size: "small",
    onClick: () => addItem(key),
    style: {
      marginTop: "4px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Add item", "content-egg")));
  const hasData = pros.length > 0 || cons.length > 0 || (attributes.best_for || []).length > 0 || (attributes.not_for || []).length > 0;
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
    onChange: val => setAttributes({
      variant: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Color Scheme", "content-egg"),
    value: color_scheme,
    options: [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Auto (site default)", "content-egg"),
      value: "auto"
    }, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Light", "content-egg"),
      value: "light"
    }, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Dark", "content-egg"),
      value: "dark"
    }],
    onChange: val => setAttributes({
      color_scheme: val
    }),
    __nextHasNoMarginBottom: true
  })), renderList("pros", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Pros", "content-egg")), renderList("cons", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Cons", "content-egg")), renderList("best_for", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Best for", "content-egg")), renderList("not_for", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Not for", "content-egg")), variant === "highlight" && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Quick Take", "content-egg")
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    value: quick_take,
    onChange: val => setAttributes({
      quick_take: val
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Labels", "content-egg"),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Pros label", "content-egg"),
    value: attributes.label_pros,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Pros", "content-egg"),
    onChange: val => setAttributes({
      label_pros: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Cons label", "content-egg"),
    value: attributes.label_cons,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Cons", "content-egg"),
    onChange: val => setAttributes({
      label_cons: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Best for label", "content-egg"),
    value: attributes.label_best_for,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Best for", "content-egg"),
    onChange: val => setAttributes({
      label_best_for: val
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Not for label", "content-egg"),
    value: attributes.label_not_for,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Not for", "content-egg"),
    onChange: val => setAttributes({
      label_not_for: val
    }),
    __nextHasNoMarginBottom: true
  }), variant === "highlight" && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Quick take label", "content-egg"),
    value: attributes.label_quick_take,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Quick take:", "content-egg"),
    onChange: val => setAttributes({
      label_quick_take: val
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, hasData ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Disabled, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_js_DebouncedServerSideRender__WEBPACK_IMPORTED_MODULE_3__["default"], {
    block: "eggb/pros-cons",
    attributes: attributes
  })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      color: "#999",
      fontStyle: "italic",
      padding: "16px",
      margin: 0,
      fontSize: "13px"
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)("Add items in the sidebar panel.", "content-egg"))));
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

/***/ "./application/EggBlocks/blocks/pros-cons/block.json"
/*!***********************************************************!*\
  !*** ./application/EggBlocks/blocks/pros-cons/block.json ***!
  \***********************************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"eggb/pros-cons","version":"1.0.0","title":"Pros & Cons","category":"cegg-blocks","icon":"yes-alt","description":"Structured pros and cons list for product reviews.","textdomain":"content-egg","editorScript":"file:./index.js","render":"file:./render.php","style":["eggb-base","file:./style-min.css"],"attributes":{"variant":{"type":"string","default":"default"},"color_scheme":{"type":"string","default":"auto","enum":["auto","light","dark"]},"pros":{"type":"array","default":[]},"cons":{"type":"array","default":[]},"best_for":{"type":"array","default":[]},"not_for":{"type":"array","default":[]},"quick_take":{"type":"string","default":""},"label_pros":{"type":"string","default":""},"label_cons":{"type":"string","default":""},"label_best_for":{"type":"string","default":""},"label_not_for":{"type":"string","default":""},"label_quick_take":{"type":"string","default":""},"style":{"type":"object","default":{"spacing":{"margin":{"bottom":"1.5rem"}}}}},"supports":{"html":false,"anchor":false,"spacing":{"margin":["bottom"]}}}');

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
/*!*************************************************************!*\
  !*** ./application/EggBlocks/blocks/pros-cons/src/index.js ***!
  \*************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _Edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Edit */ "./application/EggBlocks/blocks/pros-cons/src/Edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../block.json */ "./application/EggBlocks/blocks/pros-cons/block.json");



(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _Edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map