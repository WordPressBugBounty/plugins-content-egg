const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { InspectorControls, BlockControls } = wp.blockEditor;
const { PanelBody, SelectControl, RangeControl, Button, TextControl, ComboboxControl, ButtonGroup, ExternalLink, PanelRow } = wp.components;
const { serverSideRender: ServerSideRender } = wp;
const { ToolbarButton, ToolbarDropdownMenu, ToolbarGroup } = wp.components;
const { useState, useEffect } = wp.element;
const { useSelect } = wp.data;

import Select from '../../../res/vendor/react-select/react-select.esm.min.js';

const eggIcon = (<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15a5 5 0 0 1-5-5c0-1.956.69-4.286 1.742-6.12.524-.913 1.112-1.658 1.704-2.164C7.044 1.206 7.572 1 8 1s.956.206 1.554.716c.592.506 1.18 1.251 1.704 2.164C12.31 5.714 13 8.044 13 10a5 5 0 0 1-5 5m0 1a6 6 0 0 0 6-6c0-4.314-3-10-6-10S2 5.686 2 10a6 6 0 0 0 6 6"/></svg>);

registerBlockType('content-egg/products', {
    title: __('Content Egg Products', 'content-egg'),
    icon: eggIcon,
    keywords: [__('cegg', 'content-egg'), __('product', 'content-egg')],
    category: 'widgets',
    supports: {
        html: false,
        customClassName: false,
        align: false,
        alignWide: false,
        anchor: false,
        background: false,
    },
    attributes: {
        _refresh: { type: 'number', default: 0 },
        template: { type: 'string', default: '' },
        color_mode: { type: 'string', default: '' },
        limit: { type: 'number' },
        offset: { type: 'number' },
        next: { type: 'number' },
        products: { type: 'string', default: '' },
        border: { type: 'number' },
        btn_variant: { type: 'string', default: '' },
        cols: { type: 'number' },
        cols_xs: { type: 'number' },
        modules: { type: 'array', default: [] },
        exclude_modules: { type: 'array', default: [] },
        groups: { type: 'array', default: [] },
        hide: { type: 'array', default: [] },
        visible: { type: 'array', default: [] },
        title_tag: { type: 'string', default: '' },
        currency: { type: 'string', default: '' },
        add_query_arg: { type: 'string', default: '' },
        btn_text: { type: 'string', default: '' },
        img_ratio: { type: 'string', default: '' },
        border_color: { type: 'string', default: '' },
        tabs_type: { type: 'string', default: '' },
        cols_order: { type: 'string', default: '' },
        start_number: { type: 'number' },
    },
    edit({ attributes, setAttributes }) {
        const {_refresh, template, color_mode, limit, offset, next, border, btn_variant, cols, cols_xs, modules, exclude_modules, groups, hide, visible, title_tag, currency, add_query_arg, products, btn_text, img_ratio, border_color, tabs_type, cols_order, start_number } = attributes;

        const borderColorOptions = [
            'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'
        ];

        const tabsTypeOptions = [
            '', 'tabs', 'pills', 'underline'
        ];

        const hideVisibleOptions = [
            { value: 'badge', label: 'Badge' },
            { value: 'button', label: 'Button' },
            { value: 'code', label: 'Code' },
            { value: 'coupons', label: 'Coupons' },
            { value: 'coupon_revival', label: 'Coupon Revival' },
            { value: 'delivery_at_checkout', label: 'Delivery at Checkout' },
            { value: 'description', label: 'Description' },
            { value: 'disclaimer', label: 'Disclaimer' },
            { value: 'domain', label: 'Domain' },
            { value: 'endDate', label: 'End Date' },
            { value: 'img', label: 'Image' },
            { value: 'logo', label: 'Logo' },
            { value: 'merchant', label: 'Merchant' },
            { value: 'new_used_price', label: 'New/Used Price' },
            { value: 'number', label: 'Number' },
            { value: 'percentageSaved', label: 'Percentage Saved' },
            { value: 'price', label: 'Price' },
            { value: 'priceOld', label: 'Old Price' },
            { value: 'price_update', label: 'Price Update' },
            { value: 'prime', label: 'Prime' },
            { value: 'promo', label: 'Promo' },
            { value: 'rating', label: 'Rating' },
            { value: 'shipping_cost', label: 'Shipping Cost' },
            { value: 'shop_info', label: 'Shop Info' },
            { value: 'startDate', label: 'Start Date' },
            { value: 'stock_status', label: 'Stock Status' },
            { value: 'subtitle', label: 'Subtitle' },
            { value: 'title', label: 'Title' }
        ];

        const buttonVariants = [
            'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'link',
            'outline-primary', 'outline-secondary', 'outline-success', 'outline-danger', 'outline-warning',
            'outline-info', 'outline-light', 'outline-dark'
        ];

        const moduleOptions = Object.entries(contentEggProductsBlockData.modules).map(([key, value]) => ({
            label: value,
            value: key
        }));

        const templateOptions = contentEggProductsBlockData.templates;

        const isCustomTemplate = useSelect(() => {
            return templateOptions.some(t =>
            t.value === template && t.is_custom === true);
        }, [template]);

        // product groups
        const [allGroups, setAllGroups] = useState(
            (window.ceggProductGroups || []).map(group => ({
                label: group,
                value: group
            }))
        );

        useEffect(() => {
            const handleCeggProductGroupsUpdate = () => {
                // Build the fresh list once
                const newAllGroups = (window.ceggProductGroups || []).map(g => ({
                    label: g,
                    value: g,
                }));
                setAllGroups(newAllGroups);

                // Keep only those already-selected values that still exist
                const validValues = newAllGroups.map(g => g.value);
                const updatedGroups = groups.filter(v => validValues.includes(v));

                // Update the attribute only if something actually changed
                if (updatedGroups.length !== groups.length) {
                    setAttributes({ groups: updatedGroups });
                }
            };

            window.addEventListener(
                'ceggProductGroupsUpdated',
                handleCeggProductGroupsUpdate,
            );
            return () =>
                window.removeEventListener(
                    'ceggProductGroupsUpdated',
                    handleCeggProductGroupsUpdate,
                );
        }, [groups, setAttributes]); // include dependencies you *read*

        // Trigger a re-render when the post is saved
        const isSavingPost = useSelect((select) => select('core/editor').isSavingPost());
        const isAutosavingPost = useSelect((select) => select('core/editor').isAutosavingPost());

        useEffect(() => {
            if (isSavingPost && !isAutosavingPost) {
                const randomTimeout = 2500 + Math.random() * 1000;
                setTimeout(() => {
                    setAttributes({ _refresh: (attributes._refresh || 0) + 1 });
                }, randomTimeout);
            }
        }, [isSavingPost, isAutosavingPost]);

        const handleMultiAttributeChange = (attributeName, selectedOptions) => {
            setAttributes({ [attributeName]: selectedOptions ? selectedOptions.map(option => option.value) : [] });
        };

        // templates
        const IMAGES_BASE_URL = contentEggProductsBlockData.imagesBaseUrl;
        const DEFAULT_IMAGE = 'default-placeholder.webp';
        const TEMPLATE_USAGE_KEY = 'cegg_global_template_usage';

        // Retrieve template usage timestamps from local storage
        const getTemplateUsageData = () => {
            const data = localStorage.getItem(TEMPLATE_USAGE_KEY);
            return data ? JSON.parse(data) : {};
        };

        // Update template usage timestamp in local storage
        const updateTemplateUsage = (template) => {
            const usageData = getTemplateUsageData();
            usageData[template] = Date.now(); // Store the current timestamp
            localStorage.setItem(TEMPLATE_USAGE_KEY, JSON.stringify(usageData));
        };

        // Sort templates by most recent usage
        const sortTemplatesByRecentUsage = (templates) => {
            const usageData = getTemplateUsageData();
            return templates.sort((a, b) => (usageData[b.value] || 0) - (usageData[a.value] || 0));
        };

        // Template selection view
        if (!template) {

            // Sort templates based on usage
            const sortedTemplateOptions = sortTemplatesByRecentUsage(templateOptions);

            return (
                <div className="cegg5-container components-placeholder is-large">
                    <div className="container">
                        <div className="components-placeholder__label mb-2">{eggIcon} Content Egg Products</div>
                        <div className="components-placeholder__instructions mb-2">{__('Select a template.', 'content-egg')}</div>
                        <div className="pt-2" style={{ maxHeight: '500px', overflowY: 'scroll' }}>
                        <div className="row g-3 row-cols-3 row-cols-md-3" style={{ marginRight: '5px'}}>
                            {sortedTemplateOptions.map((option) => (
                                <div key={option.value} className="">
                                    <div
                                        className="cegg-card cegg-template-card h-100"
                                        onClick={() => {
                                            setAttributes({ template: option.value });
                                            updateTemplateUsage(option.value); // Update usage on selection
                                        }}
                                    >
                                        <img
                                            src={IMAGES_BASE_URL + (option.preview || DEFAULT_IMAGE)}
                                            alt={option.label}
                                            className="card-img-top img-fluid"
                                        />
                                        <div className="card-body p-2 d-flex align-items-center justify-content-center">
                                            <div className="text-center card-text lh-sm">{option.label}</div>
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
                            label={__('Select Template', 'content-egg')}
                            controls={templateOptions.map((option) => ({
                                title: option.label,
                                icon: 'admin-page',
                                isActive: attributes.template === option.value,
                                onClick: () => setAttributes({ template: option.value }),
                            }))}
                        />
                        <ToolbarButton
                            icon="admin-appearance"
                            label={__('Dark Mode', 'content-egg')}
                            isPressed={attributes.color_mode === 'dark'}
                            onClick={() => setAttributes({ color_mode: attributes.color_mode === 'dark' ? '' : 'dark' })}
                        />
                    </ToolbarGroup>

                    <ToolbarGroup>

                        <ToolbarButton
                            icon="update"
                            label={__('Refresh Block', 'content-egg')}
                            onClick={() => setAttributes({ _refresh: attributes._refresh + 1 })}
                            />
                    </ToolbarGroup>

                </BlockControls>

                <InspectorControls>

                    <PanelBody title={__('Help & Documentation', 'content-egg')} initialOpen={false}>
                        <PanelRow>
                            <p>
                                {__("For a detailed list of all customization options, visit our ", "content-egg")}
                                <ExternalLink href="https://ce-docs.keywordrush.com/frontend/shortcode-parameters">
                                    {__("Shortcode Parameters Guide", "content-egg")}
                                </ExternalLink>
                                .
                            </p>
                        </PanelRow>
                    </PanelBody>

                    <PanelBody title={__('Data Filtering', 'content-egg')} className="cegg_nomargin_components">

                        <RangeControl
                            label={__('Limit', 'content-egg')}
                            value={limit}
                            onChange={(value) => setAttributes({ limit: value })}
                            min={1}
                            max={50}
                            allowReset={true}
                        />
                        <RangeControl
                            label={__('Offset', 'content-egg')}
                            value={offset}
                            onChange={(value) => setAttributes({ offset: value })}
                            min={0}
                            max={50}
                            allowReset={true}
                        />

                        <div className="cegg-control-separator"></div>
                        <label htmlFor="ceggIncludeModules" className="cegg-components-label">
                            {__('Include modules', 'content-egg')}
                        </label>
                        <Select
                            id="ceggIncludeModules"
                            isMulti={true}
                            isClearable={false}
                            value={moduleOptions.filter(option => modules.includes(option.value))}
                            options={moduleOptions}
                            onChange={(selectedOptions) => handleMultiAttributeChange('modules', selectedOptions)}
                            placeholder={__('Select modules...', 'content-egg')}
                        />
                        <div className="cegg-control-separator"></div>
                        <label htmlFor="ceggExcludeModules" className="cegg-components-label">
                            {__('Exclude modules', 'content-egg')}
                        </label>
                        <Select
                            id="ceggExcludeModules"
                            isMulti={true}
                            isClearable={false}
                            value={moduleOptions.filter(option => exclude_modules.includes(option.value))}
                            options={moduleOptions}
                            onChange={(selectedOptions) => handleMultiAttributeChange('exclude_modules', selectedOptions)}
                            placeholder={__('Select modules...', 'content-egg')}
                        />

                    <div className="cegg-control-separator"></div>
                    <label
                        htmlFor="ceggProductGroups"
                        className="cegg-components-label"
                    >
                        {__('Product Groups', 'content-egg')}
                    </label>
                    {allGroups.length > 0 ? (
                        <>
                            <Select
                                id="ceggProductGroups"
                                isMulti={true}
                                isClearable={false}
                                value={allGroups.filter(option => groups.includes(option.value))}
                                options={allGroups}
                                onChange={(selectedOptions) => handleMultiAttributeChange('groups', selectedOptions)}
                                placeholder={__('Select product groups...', 'content-egg')}
                            />
                        </>
                    ) : (
                        <p
                            style={{color: '#777', fontStyle: 'italic', fontSize: '12px',}}
                            title={__('No groups are currently available. Please add groups to see them listed here.', 'content-egg')}
                        >
                            {__('No groups found', 'content-egg')}
                        </p>
                        )}
                        <div className="cegg-control-separator"></div>

                        <TextControl
                            label={__('Products', 'content-egg')}
                            placeholder={__('Product IDs separated by commas', 'content-egg')}
                            value={products}
                            onChange={(value) => setAttributes({ products: value })}
                        />

                    </PanelBody>

                    { !isCustomTemplate && (
                    <PanelBody title={__('Display Options', 'content-egg')} className="cegg_nomargin_components">
                        <label htmlFor="ceggVisibleElementsSelect" className="cegg-components-label">
                            {__('Visible Elements', 'content-egg')}
                        </label>
                        <Select
                            id="ceggVisibleElementsSelect"
                            isMulti={true}
                            isClearable={false}
                            value={hideVisibleOptions.filter(option => visible.includes(option.value))}
                            options={hideVisibleOptions}
                            onChange={(selectedOptions) => handleMultiAttributeChange('visible', selectedOptions)}
                            placeholder={__('Select elements to show...', 'content-egg')}
                        />
                        <div className="cegg-control-separator"></div>
                        <label htmlFor="ceggHideElementsSelect" className="cegg-components-label">
                            {__('Hidden Elements', 'content-egg')}
                        </label>
                        <Select
                            id="ceggHideElementsSelect"
                            isMulti={true}
                            isClearable={false}
                            value={hideVisibleOptions.filter(option => hide.includes(option.value))}
                            options={hideVisibleOptions}
                            onChange={(selectedOptions) => handleMultiAttributeChange('hide', selectedOptions)}
                            placeholder={__('Select elements to hide...', 'content-egg')}
                        />
                    </PanelBody>
                    )}
                    { !isCustomTemplate && (
                    <PanelBody title={__('Output Customization', 'content-egg')} className="cegg_nomargin_components">
                        {(template.includes('grid') || template.includes('product_images_row')) && (
                            <>
                            <RangeControl
                                label={__('Columns', 'content-egg')}
                                value={cols}
                                onChange={(value) => setAttributes({ cols: value })}
                                min={1}
                                max={6}
                                allowReset
                            />
                            {template.includes('grid') && (
                                <RangeControl
                                    label={__('Columns (Small)', 'content-egg')}
                                    value={cols_xs}
                                    onChange={(value) => setAttributes({ cols_xs: value })}
                                    min={1}
                                    max={6}
                                    allowReset
                                />
                            )}

                            <div className="cegg-control-separator"></div>
                            </>
                        )}

                        {template.includes('groups') && (
                            <>
                            <SelectControl
                                label={__('Tabs Type', 'content-egg')}
                                    value={tabs_type}
                                    options={tabsTypeOptions.map(type => ({
                                        label: type === '' ? __('- Default -', 'content-egg') : __(type.charAt(0).toUpperCase() + type.slice(1), 'content-egg'),
                                        value: type,
                                    }))}
                                onChange={(value) => setAttributes({ tabs_type: value })}
                            />
                            <div className="cegg-control-separator"></div>
                            </>
                        )}

                        <ComboboxControl
                            label={__('Button Variant', 'content-egg')}
                            value={btn_variant}
                            options={buttonVariants.map(variant => ({
                                label: __(variant.charAt(0).toUpperCase() + variant.slice(1).replace('-', ' '), 'content-egg'),
                                value: variant,
                            }))}
                            onChange={(value) => setAttributes({ btn_variant: value })}
                        />
                        <div className="cegg-control-separator"></div>
                        <RangeControl
                            label={__('Border', 'content-egg')}
                            value={border || ''}
                            onChange={(value) => setAttributes({ border: value })}
                            min={0}
                            max={5}
                            allowReset={true}
                        />
                        <div className="cegg-control-separator"></div>
                        <ComboboxControl
                            label={__('Border Color', 'content-egg')}
                            value={border_color}
                            options={borderColorOptions.map(color => ({
                                label: __(color.charAt(0).toUpperCase() + color.slice(1).replace('-', ' '), 'content-egg'),
                                value: color,
                            }))}
                            onChange={(value) => setAttributes({ border_color: value })}
                        />
                        <div className="cegg-control-separator"></div>

                        <label className="cegg-components-label">
                                {__('Title Tag', 'content-egg')}
                        </label>
                        <ButtonGroup label={__('Title Tag', 'content-egg')}>
                            {['div', 'h1', 'h2', 'h3', 'h4', 'h5'].map((tag) => (
                                <Button
                                    isSmall={true}
                                    key={tag}
                                    isPrimary={title_tag === tag}
                                    isSecondary={title_tag !== tag}
                                    onClick={() => setAttributes({ title_tag: tag })}
                                >
                                    {tag.toUpperCase()}
                                </Button>
                            ))}
                            <Button
                                isSmall={true}
                                isDestructive={true}
                                onClick={() => setAttributes({ title_tag: '' })}
                            >
                                {__('Reset', 'content-egg')}
                            </Button>
                        </ButtonGroup>

                        <div className="cegg-control-separator"></div>
                        <TextControl
                            label={__('Button Text', 'content-egg')}
                            value={btn_text}
                            onChange={(value) => setAttributes({ btn_text: value })}
                        />
                        <div className="cegg-control-separator"></div>
                        <SelectControl
                            label={__('Image Ratio', 'content-egg')}
                            value={img_ratio}
                            options={[
                                { label: '', value: '' },
                                { label: '1x1', value: '1x1' },
                                { label: '4x3', value: '4x3' },
                                { label: '16x9', value: '16x9' },
                                { label: '21x9', value: '21x9' },
                            ]}
                            onChange={(value) => setAttributes({ img_ratio: value })}
                        />
                        <div className="cegg-control-separator"></div>
                        <TextControl
                            label={__('Columns Order', 'content-egg')}
                            value={cols_order}
                            onChange={(value) => setAttributes({ cols_order: value })}
                        />
                        <div className="cegg-control-separator"></div>

                        <RangeControl
                            label={__('Start Number', 'content-egg')}
                            value={start_number}
                            onChange={(value) => setAttributes({ start_number: value })}
                            min={1}
                            max={50}
                            allowReset
                        />
                    </PanelBody>
                    )}

                    <PanelBody title={__('Data Changing', 'content-egg')} className="cegg_nomargin_components">

                        <ComboboxControl
                            label={__('Currency', 'content-egg')}
                            value={currency}
                            options={[
                                { label: 'AED', value: 'AED' },
                                { label: 'ARS', value: 'ARS' },
                                { label: 'AUD', value: 'AUD' },
                                { label: 'BDT', value: 'BDT' },
                                { label: 'BGN', value: 'BGN' },
                                { label: 'BOB', value: 'BOB' },
                                { label: 'BRL', value: 'BRL' },
                                { label: 'CAD', value: 'CAD' },
                                { label: 'CHF', value: 'CHF' },
                                { label: 'CLP', value: 'CLP' },
                                { label: 'CNY', value: 'CNY' },
                                { label: 'COP', value: 'COP' },
                                { label: 'CRC', value: 'CRC' },
                                { label: 'CZK', value: 'CZK' },
                                { label: 'DKK', value: 'DKK' },
                                { label: 'DOP', value: 'DOP' },
                                { label: 'EGP', value: 'EGP' },
                                { label: 'EUR', value: 'EUR' },
                                { label: 'GBP', value: 'GBP' },
                                { label: 'GTQ', value: 'GTQ' },
                                { label: 'HKD', value: 'HKD' },
                                { label: 'HNL', value: 'HNL' },
                                { label: 'HRK', value: 'HRK' },
                                { label: 'HUF', value: 'HUF' },
                                { label: 'IDR', value: 'IDR' },
                                { label: 'ILS', value: 'ILS' },
                                { label: 'INR', value: 'INR' },
                                { label: 'JMD', value: 'JMD' },
                                { label: 'JOD', value: 'JOD' },
                                { label: 'JPY', value: 'JPY' },
                                { label: 'KES', value: 'KES' },
                                { label: 'KRW', value: 'KRW' },
                                { label: 'KWD', value: 'KWD' },
                                { label: 'LKR', value: 'LKR' },
                                { label: 'MDL', value: 'MDL' },
                                { label: 'MXN', value: 'MXN' },
                                { label: 'MYR', value: 'MYR' },
                                { label: 'NGN', value: 'NGN' },
                                { label: 'NIO', value: 'NIO' },
                                { label: 'NOK', value: 'NOK' },
                                { label: 'NPR', value: 'NPR' },
                                { label: 'NZD', value: 'NZD' },
                                { label: 'PAB', value: 'PAB' },
                                { label: 'PCT', value: 'PCT' },
                                { label: 'PEN', value: 'PEN' },
                                { label: 'PHP', value: 'PHP' },
                                { label: 'PKR', value: 'PKR' },
                                { label: 'PLN', value: 'PLN' },
                                { label: 'RON', value: 'RON' },
                                { label: 'SAR', value: 'SAR' },
                                { label: 'SEK', value: 'SEK' },
                                { label: 'SGD', value: 'SGD' },
                                { label: 'SVC', value: 'SVC' },
                                { label: 'THB', value: 'THB' },
                                { label: 'TND', value: 'TND' },
                                { label: 'TRY', value: 'TRY' },
                                { label: 'UAH', value: 'UAH' },
                                { label: 'UMO', value: 'UMO' },
                                { label: 'USD', value: 'USD' },
                                { label: 'UYU', value: 'UYU' },
                                { label: 'VND', value: 'VND' },
                                { label: 'XOF', value: 'XOF' },
                                { label: 'ZAR', value: 'ZAR' }
                            ]}
                            onChange={(value) => setAttributes({ currency: value })}
                        />
                        <div className="cegg-control-separator"></div>

                        <TextControl
                            label={__('Add Query Argument', 'content-egg')}
                            placeholder={__('e.g., tag=CUSTOM_TAG', 'content-egg')}
                            value={add_query_arg}
                            onChange={(value) => setAttributes({ add_query_arg: value })}
                        />

                    </PanelBody>
                </InspectorControls>

                <code style={{ fontSize: '12px', padding: '7px', border: '1px dashed #ccc', backgroundColor: '#fef8ee', display: 'block', lineHeight: '1.2', marginBottom: '10px', marginTop: '10px' }}>
                {`[content-egg-block
                    ${template ? `template="${template}"` : ''}
                    ${color_mode ? `color_mode="${color_mode}"` : ''}
                    ${limit ? `limit="${limit}"` : ''}
                    ${offset ? `offset="${offset}"` : ''}
                    ${next ? `next="${next}"` : ''}
                    ${border !== undefined ? `border="${border}"` : ''}
                    ${btn_variant ? `btn_variant="${btn_variant}"` : ''}
                    ${cols ? `cols="${cols}"` : ''}
                    ${cols_xs ? `cols_xs="${cols_xs}"` : ''}
                    ${modules.length ? `modules="${modules.join(',')}"` : ''}
                    ${exclude_modules.length ? `exclude_modules="${exclude_modules.join(',')}"` : ''}
                    ${groups.length ? `groups="${groups.join(',')}"` : ''}
                    ${hide.length ? `hide="${hide.join(',')}"` : ''}
                    ${visible.length ? `visible="${visible.join(',')}"` : ''}
                    ${title_tag ? `title_tag="${title_tag}"` : ''}
                    ${currency ? `currency="${currency}"` : ''}
                    ${add_query_arg ? `add_query_arg="${add_query_arg}"` : ''}
                    ${products ? `products="${products}"` : ''}
                    ${btn_text ? `btn_text="${btn_text}"` : ''}
                    ${img_ratio ? `img_ratio="${img_ratio}"` : ''}
                    ${border_color ? `border_color="${border_color}"` : ''}
                    ${tabs_type ? `tabs_type="${tabs_type}"` : ''}
                    ${cols_order ? `cols_order="${cols_order}"` : ''}
                    ${start_number ? `start_number="${start_number}"` : ''}
                ]`}
                </code>
                <div style={{ pointerEvents: 'none' }}>
                    <ServerSideRender block="content-egg/products" attributes={{ _refresh, template, color_mode, limit, offset, next, products, border, btn_variant, cols, cols_xs, modules, exclude_modules, groups, hide, visible, title_tag, currency, add_query_arg, btn_text, img_ratio, border_color, tabs_type, cols_order, start_number }} />
                </div>
            </>
        );
    },
    save() {
        return null;
    },
});
