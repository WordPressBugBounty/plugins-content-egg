const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { InspectorControls, BlockControls } = wp.blockEditor;
const {
  PanelBody,
  SelectControl,
  RangeControl,
  Button,
  TextControl,
  ComboboxControl,
  ButtonGroup,
  ExternalLink,
  PanelRow,
  ToggleControl,
  FormTokenField,
  Placeholder,
  ToolbarButton,
  ToolbarDropdownMenu,
  ToolbarGroup,
} = wp.components;
const { serverSideRender: ServerSideRender } = wp;
const { useSelect } = wp.data;
const { useEffect, useState } = wp.element;

import ProductRefsControl from "../../EggBlocks/shared/js/ProductRefsControl.js";

// Parse the flat `chosen_coupons` attribute into refs, splitting each token on
// the FIRST colon: "Coupon:abc" -> {module_id:"Coupon", unique_id:"abc"}.
function parseRefsAttr(value) {
  return String(value || "")
    .split(",")
    .map((token) => token.trim())
    .filter(Boolean)
    .map((token) => {
      const idx = token.indexOf(":");
      if (idx === -1) {
        return { module_id: "", unique_id: token };
      }
      return { module_id: token.slice(0, idx), unique_id: token.slice(idx + 1) };
    });
}

// Serialize ProductRefsControl items back into the composite string.
function serializeRefs(items) {
  return (items || [])
    .map((item) => (item && item.product_ref) || {})
    .filter((ref) => ref && ref.unique_id)
    .map((ref) =>
      ref.module_id ? `${ref.module_id}:${ref.unique_id}` : ref.unique_id
    )
    .join(",");
}

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

const EmptyPlaceholder = ({ mode }) => (
  <Placeholder icon={eggIcon} label={__("No coupons to display", "content-egg")}>
    <p className="components-placeholder__instructions">
      {mode === "coupons"
        ? __(
            "No coupons chosen yet. Use “Choose coupons” in the block settings to add coupons to display.",
            "content-egg"
          )
        : __(
            "Add coupons to this post, save it, or relax the block filters.",
            "content-egg"
          )}
    </p>
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

// Coupon visible/hidden element vocabulary — the subset the coupon markup
// (templates/block_coupons_list.php / block_coupons_ticket.php) understands.
const hideVisibleOptions = [
  { value: "number", label: "Number" },
  { value: "img", label: "Image" },
  { value: "badge", label: "Badge" },
  { value: "title", label: "Title" },
  { value: "subtitle", label: "Subtitle" },
  { value: "rating", label: "Rating" },
  { value: "description", label: "Description" },
  { value: "code", label: "Code" },
  { value: "coupon_reveal", label: "Reveal code on click" },
  { value: "shop_info", label: "Shop info" },
  { value: "merchant", label: "Merchant" },
  { value: "startDate", label: "Start date" },
  { value: "endDate", label: "End date" },
];

const buttonVariants = [
  "primary",
  "secondary",
  "success",
  "danger",
  "warning",
  "info",
  "light",
  "dark",
  "link",
  "outline-primary",
  "outline-secondary",
  "outline-success",
  "outline-danger",
  "outline-warning",
  "outline-info",
  "outline-light",
  "outline-dark",
];

registerBlockType("content-egg/coupons", {
  title: __("CE Coupons", "content-egg"),
  icon: "tickets-alt",
  keywords: [
    __("cegg", "content-egg"),
    __("content egg", "content-egg"),
    __("coupon", "content-egg"),
    __("deal", "content-egg"),
  ],
  category: "content-egg",
  supports: {
    html: false,
    customClassName: false,
    align: false,
    alignWide: false,
    anchor: false,
    background: false,
  },
  attributes: {
    _refresh: { type: "number", default: 0 },
    template: { type: "string", default: "" },
    color_mode: { type: "string", default: "" },
    limit: { type: "number" },
    offset: { type: "number" },
    next: { type: "number" },
    selection_mode: { type: "string", default: "" },
    chosen_coupons: { type: "string", default: "" },
    modules: { type: "array", default: [] },
    exclude_modules: { type: "array", default: [] },
    groups: { type: "array", default: [] },
    hide: { type: "array", default: [] },
    visible: { type: "array", default: [] },
    btn_variant: { type: "string", default: "" },
    start_number: { type: "number" },
    post_id: { type: "number", default: 0 },
    async: { type: "boolean", default: false },
    lazy: { type: "boolean", default: false },
  },
  edit({ attributes, setAttributes }) {
    const {
      _refresh,
      template,
      color_mode,
      limit,
      offset,
      next,
      selection_mode,
      chosen_coupons,
      modules,
      exclude_modules,
      groups,
      hide,
      visible,
      btn_variant,
      start_number,
      post_id,
      async,
      lazy,
    } = attributes;

    const blockData = window.contentEggCouponsBlockData || {
      modules: [],
      templates: [],
      imagesBaseUrl: "",
    };

    // Coupon modules (array of {value,label}) → lookup maps + allow-list.
    const moduleOptions = (blockData.modules || []).map((m) => ({
      label: m.label,
      value: m.value,
    }));
    const couponModuleIds = moduleOptions.map((o) => o.value);
    const moduleLabelByValue = {};
    const moduleValueByLabel = {};
    moduleOptions.forEach((option) => {
      moduleLabelByValue[option.value] = option.label;
      moduleValueByLabel[option.label] = option.value;
    });

    // FormTokenField works in display labels; map both ways for elements.
    const elementLabelByValue = {};
    const elementValueByLabel = {};
    hideVisibleOptions.forEach((option) => {
      elementLabelByValue[option.value] = option.label;
      elementValueByLabel[option.label] = option.value;
    });

    const templateOptions = blockData.templates || [];
    const IMAGES_BASE_URL = blockData.imagesBaseUrl;
    const DEFAULT_IMAGE = "default-placeholder.webp";

    const isCustomTemplate = useSelect(() => {
      return templateOptions.some(
        (t) => t.value === template && t.is_custom === true
      );
    }, [template]);

    // The default coupon template (localized) — falls back to the first option.
    const defaultTemplate =
      (blockData.defaultTemplate &&
      templateOptions.some((t) => t.value === blockData.defaultTemplate)
        ? blockData.defaultTemplate
        : templateOptions[0] && templateOptions[0].value) || "";

    // With only a couple of templates a full chooser is friction — preselect the
    // default so a fresh block renders immediately; the toolbar dropdown still
    // switches templates. The chooser only appears when there are several to
    // compare visually.
    const PRESELECT_MAX = 2;
    useEffect(() => {
      if (
        !template &&
        defaultTemplate &&
        templateOptions.length <= PRESELECT_MAX
      ) {
        setAttributes({ template: defaultTemplate });
      }
    }, [template, defaultTemplate, templateOptions.length]);

    // Live-refresh in Filter binding: when the sidebar/modal changes the coupon
    // roster (add, remove, or edit in the drawer), bump a LOCAL key to remount
    // ServerSideRender and refetch silently — no persisted attribute, so the post
    // is never marked dirty. Choose mode is driven by `chosen_coupons` directly,
    // so it opts out.
    const [ssrKey, setSsrKey] = useState(0);
    useEffect(() => {
      if (selection_mode === "coupons") return undefined;
      const onCouponsUpdated = () => setSsrKey((k) => k + 1);
      window.addEventListener("cegg:coupons-updated", onCouponsUpdated);
      return () =>
        window.removeEventListener("cegg:coupons-updated", onCouponsUpdated);
    }, [selection_mode]);

    // Template selection view — only when there are several templates to choose
    // from (few → preselected above).
    if (!template && templateOptions.length > PRESELECT_MAX) {
      return (
        <div className="cegg5-container components-placeholder is-large">
          <div className="container">
            <div className="components-placeholder__label mb-2">
              {eggIcon} {__("Content Egg Coupons", "content-egg")}
            </div>
            <div className="components-placeholder__instructions mb-2">
              {__("Select a template.", "content-egg")}
            </div>
            <div className="pt-2" style={{ maxHeight: "500px", overflowY: "auto" }}>
              <div className="row g-3 row-cols-3 row-cols-md-3" style={{ marginRight: "5px" }}>
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
              onClick={() => setAttributes({ _refresh: (attributes._refresh || 0) + 1 })}
            />
          </ToolbarGroup>
        </BlockControls>

        <InspectorControls>
          <PanelBody title={__("Help & Documentation", "content-egg")} initialOpen={false}>
            <PanelRow>
              <p>
                {__("For all customization options, visit our ", "content-egg")}
                <ExternalLink href="https://ce-docs.keywordrush.com/frontend/shortcode-parameters">
                  {__("Shortcode Parameters Guide", "content-egg")}
                </ExternalLink>
                .
              </p>
            </PanelRow>
          </PanelBody>

          <PanelBody title={__("Data Filtering", "content-egg")} className="cegg_nomargin_components">
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
            <label className="cegg-components-label">
              {__("Coupon Binding", "content-egg")}
            </label>
            <ButtonGroup>
              <Button
                isSmall
                isPrimary={selection_mode !== "coupons"}
                isSecondary={selection_mode === "coupons"}
                label={__("Show coupons by module, group, or all", "content-egg")}
                showTooltip
                onClick={() => setAttributes({ selection_mode: "" })}
              >
                {__("Filter", "content-egg")}
              </Button>
              <Button
                isSmall
                isPrimary={selection_mode === "coupons"}
                isSecondary={selection_mode !== "coupons"}
                label={__("Pick specific coupons to display", "content-egg")}
                showTooltip
                onClick={() => setAttributes({ selection_mode: "coupons" })}
              >
                {__("Choose coupons", "content-egg")}
              </Button>
            </ButtonGroup>
            <div className="cegg-control-separator"></div>

            {selection_mode === "coupons" ? (
              <ProductRefsControl
                items={parseRefsAttr(chosen_coupons).map((ref) => ({ product_ref: ref }))}
                onChange={(nextItems) =>
                  setAttributes({ chosen_coupons: serializeRefs(nextItems) })
                }
                createEmptyItem={(ref) => ({ product_ref: ref })}
                moduleIds={couponModuleIds}
                enableSearch={false}
                moduleLabels={moduleLabelByValue}
                labels={{
                  addItems: __("Add coupons", "content-egg"),
                  noneAvailable: __(
                    "No coupons are currently available for this post.",
                    "content-egg"
                  ),
                  pickerTitle: __("Select coupons", "content-egg"),
                  pickerReplaceTitle: __("Replace coupon", "content-egg"),
                  postTabTitle: __("Post coupons", "content-egg"),
                  noneFound: __(
                    "No coupons found for the current filter.",
                    "content-egg"
                  ),
                }}
                emptyMessage={__(
                  "No coupons chosen yet. Add coupons to this post, then pick them here.",
                  "content-egg"
                )}
              />
            ) : (
              <>
                <FormTokenField
                  label={__("Include modules", "content-egg")}
                  value={modules.map((key) => moduleLabelByValue[key] || key)}
                  suggestions={moduleOptions.map((option) => option.label)}
                  __experimentalExpandOnFocus
                  __experimentalShowHowTo={false}
                  __experimentalValidateInput={(token) =>
                    Object.prototype.hasOwnProperty.call(moduleValueByLabel, token)
                  }
                  onChange={(tokens) =>
                    setAttributes({
                      modules: tokens.map((t) => moduleValueByLabel[t]).filter(Boolean),
                    })
                  }
                />
                <div className="cegg-control-separator"></div>
                <FormTokenField
                  label={__("Exclude modules", "content-egg")}
                  value={exclude_modules.map((key) => moduleLabelByValue[key] || key)}
                  suggestions={moduleOptions.map((option) => option.label)}
                  __experimentalExpandOnFocus
                  __experimentalShowHowTo={false}
                  __experimentalValidateInput={(token) =>
                    Object.prototype.hasOwnProperty.call(moduleValueByLabel, token)
                  }
                  onChange={(tokens) =>
                    setAttributes({
                      exclude_modules: tokens.map((t) => moduleValueByLabel[t]).filter(Boolean),
                    })
                  }
                />
                <div className="cegg-control-separator"></div>
                <TextControl
                  label={__("Groups", "content-egg")}
                  placeholder={__("Group names separated by commas", "content-egg")}
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

          <PanelBody title={__("Display Options", "content-egg")} className="cegg_nomargin_components">
            {!isCustomTemplate && (
              <>
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
                      visible: tokens.map((t) => elementValueByLabel[t]).filter(Boolean),
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
                      hide: tokens.map((t) => elementValueByLabel[t]).filter(Boolean),
                    })
                  }
                />
                <div className="cegg-control-separator"></div>
                <ComboboxControl
                  label={__("Button Variant", "content-egg")}
                  value={btn_variant}
                  options={buttonVariants.map((variant) => ({
                    label: variant.charAt(0).toUpperCase() + variant.slice(1).replace("-", " "),
                    value: variant,
                  }))}
                  onChange={(value) => setAttributes({ btn_variant: value })}
                />
                <div className="cegg-control-separator"></div>
                <RangeControl
                  label={__("Start Number", "content-egg")}
                  value={start_number}
                  onChange={(value) => setAttributes({ start_number: value })}
                  min={1}
                  max={50}
                  allowReset
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

        <code
          style={{
            fontSize: "12px",
            padding: "7px",
            border: "1px dashed #ccc",
            backgroundColor: "#fef8ee",
            display: "block",
            lineHeight: "1.2",
            marginBottom: "10px",
            marginTop: "10px",
          }}
        >
          {`[content-egg-block template="${template}"` +
            `${color_mode ? ` color_mode="${color_mode}"` : ""}` +
            `${limit ? ` limit="${limit}"` : ""}` +
            `${offset ? ` offset="${offset}"` : ""}` +
            `${next ? ` next="${next}"` : ""}` +
            `${post_id ? ` post_id="${post_id}"` : ""}` +
            `${async ? ` async="1"` : ""}` +
            `${lazy ? ` lazy="1"` : ""}` +
            `${btn_variant ? ` btn_variant="${btn_variant}"` : ""}` +
            `${start_number ? ` start_number="${start_number}"` : ""}` +
            `${modules.length && selection_mode !== "coupons" ? ` modules="${modules.join(",")}"` : ""}` +
            `${exclude_modules.length && selection_mode !== "coupons" ? ` exclude_modules="${exclude_modules.join(",")}"` : ""}` +
            `${groups.length && selection_mode !== "coupons" ? ` groups="${groups.join(",")}"` : ""}` +
            `${selection_mode === "coupons" && chosen_coupons ? ` products="${chosen_coupons}"` : ""}` +
            `${hide.length ? ` hide="${hide.join(",")}"` : ""}` +
            `${visible.length ? ` visible="${visible.join(",")}"` : ""}` +
            `]`}
        </code>
        <div style={{ pointerEvents: "none" }}>
          <ServerSideRender
            key={ssrKey}
            block="content-egg/coupons"
            attributes={{
              _refresh,
              template,
              color_mode,
              limit,
              offset,
              next,
              selection_mode,
              chosen_coupons,
              modules,
              exclude_modules,
              groups,
              hide,
              visible,
              btn_variant,
              start_number,
              post_id,
              async,
              lazy,
            }}
            EmptyResponsePlaceholder={() => <EmptyPlaceholder mode={selection_mode} />}
            ErrorResponsePlaceholder={ErrorPlaceholder}
            LoadingResponsePlaceholder={LoadingPlaceholder}
          />
        </div>
      </>
    );
  },
  save() {
    return null;
  },
});
