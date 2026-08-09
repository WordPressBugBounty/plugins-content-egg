const { __, sprintf, _n } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { InspectorControls, BlockControls } = wp.blockEditor;
const {
  PanelBody,
  RangeControl,
  TextControl,
  TextareaControl,
  Button,
  ToggleControl,
  FormTokenField,
  ToolbarButton,
  ToolbarDropdownMenu,
  ToolbarGroup,
  Placeholder,
} = wp.components;
const { serverSideRender: ServerSideRender } = wp;
const { useSelect } = wp.data;
const { useEffect, useState } = wp.element;

const eggIcon = (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width="24"
    height="24"
    fill="currentColor"
    viewBox="0 0 16 16"
    aria-hidden="true"
  >
    <path d="M8 15a5 5 0 0 1-5-5c0-1.956.69-4.286 1.742-6.12.524-.913 1.112-1.658 1.704-2.164C7.044 1.206 7.572 1 8 1s.956.206 1.554.716c.592.506 1.18 1.251 1.704 2.164C12.31 5.714 13 8.044 13 10a5 5 0 0 1-5 5m0 1a6 6 0 0 0 6-6c0-4.314-3-10-6-10S2 5.686 2 10a6 6 0 0 0 6 6" />
  </svg>
);

// Shared visible/hidden vocabulary the media templates understand.
const hideVisibleOptions = [
  { value: "img", label: "Image" },
  { value: "title", label: "Title" },
  { value: "description", label: "Description" },
];

const blockData = window.contentEggMediaBlockData || {
  imageModules: [],
  videoModules: [],
  imageTemplates: [],
  videoTemplates: [],
  defaultImageTemplate: "images",
  defaultVideoTemplate: "videos_stacked",
  imagesBaseUrl: "",
};

// Open the full Content Egg manager (search + manage) to a given family. The
// media block lives in its own bundle, so it reaches the Product Manager (a
// separate bundle) through a window event it listens for — no import coupling.
function openMediaManager(family) {
  window.dispatchEvent(
    new CustomEvent("cegg:open-manager", { detail: { family } })
  );
}

const EmptyPlaceholder = ({ family }) => (
  <Placeholder icon={eggIcon} label={__("Nothing to display", "content-egg")}>
    <p className="components-placeholder__instructions">
      {__(
        "Search and add media to this post, or relax the block filters.",
        "content-egg"
      )}
    </p>
    {family && (
      // The SSR preview is wrapped in pointer-events:none (to keep the block
      // selectable); re-enable it here. width:100% drops the CTA onto its own
      // line, left-aligned with the instructions above.
      <div style={{ width: "100%", marginTop: "4px", pointerEvents: "auto" }}>
        <Button variant="primary" onClick={() => openMediaManager(family)}>
          {__("Add / manage media", "content-egg")}
        </Button>
      </div>
    )}
  </Placeholder>
);

const ErrorPlaceholder = ({ error }) => (
  <div className="components-notice is-error">
    {__("Cannot render block:", "content-egg")}{" "}
    {error?.message || __("Unknown error", "content-egg")}
  </div>
);

const LoadingPlaceholder = () => (
  <div className="components-placeholder">{__("Loading…", "content-egg")}</div>
);

function MediaEdit({ attributes, setAttributes, blockName, config }) {
  const {
    _refresh,
    template,
    color_mode,
    limit,
    offset,
    next,
    modules,
    exclude_modules,
    groups,
    hide,
    visible,
    cols,
    post_id,
    async,
    lazy,
    products,
  } = attributes;

  // Item-ID binding set by sidebar drag-and-drop (composite "module:uid" refs).
  // When present the block renders exactly these items, so the module/group
  // filters are hidden in favor of a bound-mode notice.
  const boundRefs = String(products || "")
    .split(",")
    .map((r) => r.trim())
    .filter(Boolean);

  const moduleOptions = (config.modules || []).map((m) => ({
    label: m.label,
    value: m.value,
  }));
  const moduleLabelByValue = {};
  const moduleValueByLabel = {};
  moduleOptions.forEach((option) => {
    moduleLabelByValue[option.value] = option.label;
    moduleValueByLabel[option.label] = option.value;
  });

  const elementLabelByValue = {};
  const elementValueByLabel = {};
  hideVisibleOptions.forEach((option) => {
    elementLabelByValue[option.value] = option.label;
    elementValueByLabel[option.label] = option.value;
  });

  const templateOptions = config.templates || [];
  const IMAGES_BASE_URL = blockData.imagesBaseUrl;
  const DEFAULT_IMAGE = "default-placeholder.webp";

  const isCustomTemplate = useSelect(() => {
    return templateOptions.some(
      (t) => t.value === template && t.is_custom === true
    );
  }, [template]);

  const defaultTemplate =
    (config.defaultTemplate &&
    templateOptions.some((t) => t.value === config.defaultTemplate)
      ? config.defaultTemplate
      : templateOptions[0] && templateOptions[0].value) || "";

  // Show the template chooser on insert whenever there's a real choice (2+
  // templates), matching the Videos/Products blocks. Only auto-preselect when a
  // single template exists (no choice to make); the toolbar dropdown still switches.
  const PRESELECT_MAX = 1;
  useEffect(() => {
    if (!template && defaultTemplate && templateOptions.length <= PRESELECT_MAX) {
      setAttributes({ template: defaultTemplate });
    }
  }, [template, defaultTemplate, templateOptions.length]);

  // Auto-refresh the preview when media is added/managed via the manager modal
  // opened from this block. Bump a LOCAL key to remount ServerSideRender (forcing
  // a refetch); this is not a persisted attribute, so it never marks the post dirty.
  const [ssrKey, setSsrKey] = useState(0);
  useEffect(() => {
    function onMediaUpdated(e) {
      const f = e && e.detail && e.detail.family;
      if (!f || f === config.family) {
        setSsrKey((k) => k + 1);
      }
    }
    window.addEventListener("cegg:media-updated", onMediaUpdated);
    return () =>
      window.removeEventListener("cegg:media-updated", onMediaUpdated);
  }, []);

  if (!template && templateOptions.length > PRESELECT_MAX) {
    return (
      <div className="cegg5-container components-placeholder is-large">
        <div className="container">
          <div className="components-placeholder__label mb-2">
            {eggIcon} {config.title}
          </div>
          <div className="components-placeholder__instructions mb-2">
            {__("Select a template.", "content-egg")}
          </div>
          <div className="pt-2" style={{ maxHeight: "500px", overflowY: "auto" }}>
            <div
              className="row g-3 row-cols-3 row-cols-md-3"
              style={{ marginRight: "5px" }}
            >
              {templateOptions.map((option) => (
                <div key={option.value}>
                  <div
                    className="cegg-card cegg-template-card h-100"
                    onClick={() => setAttributes({ template: option.value })}
                  >
                    <img
                      src={IMAGES_BASE_URL + (option.preview || DEFAULT_IMAGE)}
                      alt={option.label}
                      className="card-img-top img-fluid"
                    />
                    <div className="card-body p-2 d-flex align-items-center justify-content-center">
                      <div className="text-center card-text lh-sm">
                        {option.label}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <>
      <BlockControls>
        <ToolbarGroup>
          <ToolbarDropdownMenu
            icon="editor-table"
            label={__("Select Template", "content-egg")}
            controls={templateOptions.map((option) => ({
              title: option.label,
              icon: "admin-page",
              isActive: attributes.template === option.value,
              onClick: () => setAttributes({ template: option.value }),
            }))}
          />
          <ToolbarButton
            icon="admin-appearance"
            label={__("Dark Mode", "content-egg")}
            isPressed={attributes.color_mode === "dark"}
            onClick={() =>
              setAttributes({
                color_mode: attributes.color_mode === "dark" ? "" : "dark",
              })
            }
          />
        </ToolbarGroup>
        <ToolbarGroup>
          <ToolbarButton
            icon="update"
            label={__("Refresh Block", "content-egg")}
            onClick={() =>
              setAttributes({ _refresh: (attributes._refresh || 0) + 1 })
            }
          />
        </ToolbarGroup>
      </BlockControls>

      <InspectorControls>
        <PanelBody className="cegg_nomargin_components">
          <Button
            variant="primary"
            className="cegg-add-media-btn"
            style={{ width: "100%", justifyContent: "center" }}
            onClick={() => openMediaManager(config.family)}
          >
            {__("Add / manage media", "content-egg")}
          </Button>
        </PanelBody>

        <PanelBody
          title={__("Data Filtering", "content-egg")}
          className="cegg_nomargin_components"
        >
          <RangeControl
            label={__("Limit", "content-egg")}
            value={limit}
            onChange={(value) => setAttributes({ limit: value })}
            min={1}
            max={50}
            allowReset
          />
          <RangeControl
            label={__("Offset", "content-egg")}
            value={offset}
            onChange={(value) => setAttributes({ offset: value })}
            min={0}
            max={50}
            allowReset
          />

          <div className="cegg-control-separator"></div>
          {boundRefs.length > 0 ? (
            <>
              <label className="cegg-components-label">
                {__("Media Binding", "content-egg")}
              </label>
              <div className="cegg-pm-bound-notice">
              <p style={{ margin: "0 0 8px" }}>
                {sprintf(
                  /* translators: %d: number of selected media items */
                  _n(
                    "Showing %d selected item.",
                    "Showing %d selected items.",
                    boundRefs.length,
                    "content-egg"
                  ),
                  boundRefs.length
                )}
              </p>
              <TextareaControl
                label={__("Selected items", "content-egg")}
                help={__(
                  "Comma-separated module:ID references. Edit to remove or reorder items; drag more from the Content Egg sidebar to add.",
                  "content-egg"
                )}
                value={products}
                onChange={(value) => setAttributes({ products: value })}
                rows={3}
                __nextHasNoMarginBottom
              />
              <div style={{ marginTop: "8px" }}>
                <Button
                  variant="secondary"
                  size="small"
                  onClick={() => setAttributes({ products: "" })}
                >
                  {__("Clear selection (show by filter)", "content-egg")}
                </Button>
              </div>
            </div>
            </>
          ) : (
            <>
              <FormTokenField
                label={__("Include modules", "content-egg")}
                value={modules.map((key) => moduleLabelByValue[key] || key)}
                suggestions={moduleOptions.map((option) => option.label)}
                __experimentalExpandOnFocus
                __experimentalShowHowTo={false}
                __experimentalValidateInput={(token) =>
                  Object.prototype.hasOwnProperty.call(
                    moduleValueByLabel,
                    token
                  )
                }
                onChange={(tokens) =>
                  setAttributes({
                    modules: tokens
                      .map((t) => moduleValueByLabel[t])
                      .filter(Boolean),
                  })
                }
              />
              <div className="cegg-control-separator"></div>
              <FormTokenField
                label={__("Exclude modules", "content-egg")}
                value={exclude_modules.map(
                  (key) => moduleLabelByValue[key] || key
                )}
                suggestions={moduleOptions.map((option) => option.label)}
                __experimentalExpandOnFocus
                __experimentalShowHowTo={false}
                __experimentalValidateInput={(token) =>
                  Object.prototype.hasOwnProperty.call(
                    moduleValueByLabel,
                    token
                  )
                }
                onChange={(tokens) =>
                  setAttributes({
                    exclude_modules: tokens
                      .map((t) => moduleValueByLabel[t])
                      .filter(Boolean),
                  })
                }
              />
              <div className="cegg-control-separator"></div>
              <TextControl
                label={__("Groups", "content-egg")}
                placeholder={__(
                  "Group names separated by commas",
                  "content-egg"
                )}
                value={(groups || []).join(",")}
                onChange={(value) =>
                  setAttributes({
                    groups: String(value || "")
                      .split(",")
                      .map((g) => g.trim())
                      .filter(Boolean),
                  })
                }
              />
            </>
          )}
          <div className="cegg-control-separator"></div>
          <TextControl
            label={__("Source Post ID", "content-egg")}
            placeholder={__("Leave empty to use this post", "content-egg")}
            value={post_id > 0 ? String(post_id) : ""}
            inputMode="numeric"
            onChange={(val) => {
              const n = parseInt(val, 10);
              setAttributes({ post_id: Number.isFinite(n) && n > 0 ? n : 0 });
            }}
          />
        </PanelBody>

        <PanelBody
          title={__("Display Options", "content-egg")}
          className="cegg_nomargin_components"
        >
          {!isCustomTemplate && (
            <>
              {config.showCols && (
                <>
                  <RangeControl
                    label={__("Columns", "content-egg")}
                    value={cols}
                    onChange={(value) => setAttributes({ cols: value })}
                    min={1}
                    max={6}
                    allowReset
                  />
                  <div className="cegg-control-separator"></div>
                </>
              )}
              <FormTokenField
                label={__("Visible Elements", "content-egg")}
                value={visible.map((v) => elementLabelByValue[v] || v)}
                suggestions={hideVisibleOptions.map((option) => option.label)}
                __experimentalExpandOnFocus
                __experimentalShowHowTo={false}
                __experimentalValidateInput={(token) =>
                  Object.prototype.hasOwnProperty.call(elementValueByLabel, token)
                }
                onChange={(tokens) =>
                  setAttributes({
                    visible: tokens
                      .map((t) => elementValueByLabel[t])
                      .filter(Boolean),
                  })
                }
              />
              <div className="cegg-control-separator"></div>
              <FormTokenField
                label={__("Hidden Elements", "content-egg")}
                value={hide.map((v) => elementLabelByValue[v] || v)}
                suggestions={hideVisibleOptions.map((option) => option.label)}
                __experimentalExpandOnFocus
                __experimentalShowHowTo={false}
                __experimentalValidateInput={(token) =>
                  Object.prototype.hasOwnProperty.call(elementValueByLabel, token)
                }
                onChange={(tokens) =>
                  setAttributes({
                    hide: tokens
                      .map((t) => elementValueByLabel[t])
                      .filter(Boolean),
                  })
                }
              />
              <div className="cegg-control-separator"></div>
            </>
          )}

          <ToggleControl
            label={__("Render via AJAX (async)", "content-egg")}
            checked={!!async}
            onChange={(value) => {
              setAttributes({ async: !!value });
              if (!value && lazy) setAttributes({ lazy: false });
            }}
          />
          <div className="cegg-control-separator"></div>
          <ToggleControl
            label={__("Lazy load (when visible)", "content-egg")}
            checked={!!lazy}
            disabled={!async}
            onChange={(value) => setAttributes({ lazy: !!value })}
          />
        </PanelBody>
      </InspectorControls>

      <div style={{ pointerEvents: "none" }}>
        <ServerSideRender
          key={ssrKey}
          block={blockName}
          attributes={{
            _refresh,
            template,
            color_mode,
            limit,
            offset,
            next,
            modules,
            exclude_modules,
            groups,
            hide,
            visible,
            cols,
            post_id,
            async,
            lazy,
            products,
          }}
          EmptyResponsePlaceholder={() => (
            <EmptyPlaceholder family={config.family} />
          )}
          ErrorResponsePlaceholder={ErrorPlaceholder}
          LoadingResponsePlaceholder={LoadingPlaceholder}
        />
      </div>
    </>
  );
}

const sharedAttributes = {
  _refresh: { type: "number", default: 0 },
  template: { type: "string", default: "" },
  color_mode: { type: "string", default: "" },
  limit: { type: "number" },
  offset: { type: "number" },
  next: { type: "number" },
  modules: { type: "array", default: [] },
  exclude_modules: { type: "array", default: [] },
  groups: { type: "array", default: [] },
  hide: { type: "array", default: [] },
  visible: { type: "array", default: [] },
  cols: { type: "number" },
  products: { type: "string", default: "" },
  post_id: { type: "number", default: 0 },
  async: { type: "boolean", default: false },
  lazy: { type: "boolean", default: false },
};

const sharedSupports = {
  html: false,
  customClassName: false,
  align: false,
  alignWide: false,
  anchor: false,
  background: false,
};

registerBlockType("content-egg/images", {
  title: __("CE Images", "content-egg"),
  icon: "format-image",
  keywords: [
    __("cegg", "content-egg"),
    __("content egg", "content-egg"),
    __("image", "content-egg"),
    __("photo", "content-egg"),
  ],
  category: "content-egg",
  supports: sharedSupports,
  attributes: sharedAttributes,
  edit(props) {
    return (
      <MediaEdit
        {...props}
        blockName="content-egg/images"
        config={{
          title: __("Content Egg Images", "content-egg"),
          family: "IMAGE",
          modules: blockData.imageModules,
          templates: blockData.imageTemplates,
          defaultTemplate: blockData.defaultImageTemplate,
          showCols: true,
        }}
      />
    );
  },
  save() {
    return null;
  },
});

registerBlockType("content-egg/videos", {
  title: __("CE Videos", "content-egg"),
  icon: "format-video",
  keywords: [
    __("cegg", "content-egg"),
    __("content egg", "content-egg"),
    __("video", "content-egg"),
    __("youtube", "content-egg"),
  ],
  category: "content-egg",
  supports: sharedSupports,
  attributes: sharedAttributes,
  edit(props) {
    return (
      <MediaEdit
        {...props}
        blockName="content-egg/videos"
        config={{
          title: __("Content Egg Videos", "content-egg"),
          family: "VIDEO",
          modules: blockData.videoModules,
          templates: blockData.videoTemplates,
          defaultTemplate: blockData.defaultVideoTemplate,
          showCols: false,
        }}
      />
    );
  },
  save() {
    return null;
  },
});
