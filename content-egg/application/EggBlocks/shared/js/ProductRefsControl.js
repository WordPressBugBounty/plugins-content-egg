import { Button, Modal, SelectControl } from "@wordpress/components";
import { useEffect, useMemo, useState } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import useCeggEditorProducts from "./useCeggEditorProducts";

function useCurrentPostId() {
    return useSelect((select) => {
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
        post_id: postId,
    };
}

function getModuleLabels() {
    const modules = window?.contentEggProductsBlockData?.modules;
    return modules && typeof modules === "object" ? modules : {};
}

export default function ProductRefsControl({
    items = [],
    onChange,
    createEmptyItem,
    renderItemFields,
    emptyMessage = __("No products are currently bound to this block.", "content-egg"),
    preserveUnboundRows = false,
}) {
    const snapshot = useCeggEditorProducts();
    const currentPostId = useCurrentPostId();
    const postId = currentPostId || snapshot.postId || 0;
    const byModule = snapshot.byModule || {};
    const moduleLabels = getModuleLabels();
    const [isPickerOpen, setIsPickerOpen] = useState(false);
    const [moduleFilter, setModuleFilter] = useState("");
    const [groupFilter, setGroupFilter] = useState("");
    const [pickerMode, setPickerMode] = useState({ type: "add", index: null });
    const [selectedProductKeys, setSelectedProductKeys] = useState([]);
    const [expandedItemIds, setExpandedItemIds] = useState({});

    const moduleIds = Object.keys(byModule).filter((moduleId) => Array.isArray(byModule[moduleId]) && byModule[moduleId].length > 0);
    const filterOptions = [
        { label: __("All modules", "content-egg"), value: "" },
        ...moduleIds.map((moduleId) => ({ label: moduleLabels[moduleId] || moduleId, value: moduleId })),
    ];

    const itemsWithProducts = useMemo(() => {
        return (items || []).map((item, index) => {
            const ref = item?.product_ref || {};
            const product = (snapshot.all || []).find((candidate) => (
                getProductKey(candidate.module_id, candidate.unique_id) === getProductKey(ref.module_id, ref.unique_id)
            )) || null;

            return {
                item,
                index,
                product,
                productKey: getProductKey(ref.module_id, ref.unique_id),
            };
        });
    }, [items, snapshot.all]);

    const existingKeys = useMemo(() => (
        itemsWithProducts
            .map(({ productKey }) => productKey)
            .filter((productKey) => productKey !== ":")
    ), [itemsWithProducts]);

    const moduleFilteredProducts = moduleFilter && Array.isArray(byModule[moduleFilter])
        ? byModule[moduleFilter]
        : (snapshot.all || []);
    const groupValues = useMemo(() => (
        Array.from(new Set(
            moduleFilteredProducts
                .map((product) => String(product?.group || "").trim())
                .filter(Boolean)
        )).sort((left, right) => left.localeCompare(right))
    ), [moduleFilteredProducts]);
    const groupFilterOptions = [
        { label: __("All groups", "content-egg"), value: "" },
        ...groupValues.map((group) => ({ label: group, value: group })),
    ];
    const filteredProducts = groupFilter
        ? moduleFilteredProducts.filter((product) => String(product?.group || "").trim() === groupFilter)
        : moduleFilteredProducts;
    const addableFilteredProducts = filteredProducts.filter((product) => !existingKeys.includes(getProductKey(product.module_id, product.unique_id)));
    const currentReplaceKey = pickerMode.type === "replace" && pickerMode.index !== null
        ? itemsWithProducts[pickerMode.index]?.productKey || ""
        : "";

    useEffect(() => {
        if (!snapshot.updatedAt) {
            return;
        }

        const nextItems = (items || []).filter((item) => {
            const ref = item?.product_ref || {};
            if (!ref.module_id || !ref.unique_id) {
                return preserveUnboundRows;
            }

            return (snapshot.all || []).some((product) => (
                getProductKey(product.module_id, product.unique_id) === getProductKey(ref.module_id, ref.unique_id)
            ));
        });

        if (nextItems.length !== (items || []).length) {
            onChange(nextItems);
        }
    }, [items, onChange, snapshot.all, snapshot.updatedAt, preserveUnboundRows]);

    useEffect(() => {
        if (groupFilter && !groupValues.includes(groupFilter)) {
            setGroupFilter("");
        }
    }, [groupFilter, groupValues]);

    const updateItem = (index, updater) => {
        const nextItems = [...(items || [])];
        const currentItem = nextItems[index];
        nextItems[index] = typeof updater === "function"
            ? updater(currentItem)
            : { ...currentItem, ...updater };
        onChange(nextItems);
    };

    const removeItem = (index) => {
        const nextItems = [...(items || [])];
        nextItems.splice(index, 1);
        onChange(nextItems);
    };

    const openAddPicker = () => {
        setPickerMode({ type: "add", index: null });
        setSelectedProductKeys([]);
        setModuleFilter("");
        setGroupFilter("");
        setIsPickerOpen(true);
    };

    const openReplacePicker = (index, moduleId) => {
        setPickerMode({ type: "replace", index });
        setSelectedProductKeys([]);
        setModuleFilter(moduleId || "");
        setGroupFilter("");
        setIsPickerOpen(true);
    };

    const toggleSelectedProduct = (productKey) => {
        setSelectedProductKeys((currentKeys) => (
            currentKeys.includes(productKey)
                ? currentKeys.filter((key) => key !== productKey)
                : [...currentKeys, productKey]
        ));
    };

    const toggleExpandedItem = (itemId, fallbackIndex) => {
        const key = itemId || `row-${fallbackIndex}`;
        setExpandedItemIds((current) => ({
            ...current,
            [key]: !current[key],
        }));
    };

    const handleAddSelectedProducts = () => {
        const selectedProducts = (snapshot.all || []).filter((product) => (
            selectedProductKeys.includes(getProductKey(product.module_id, product.unique_id))
        ));

        if (selectedProducts.length === 0) {
            return;
        }

        const nextItems = [
            ...(items || []),
            ...selectedProducts
                .filter((product) => !existingKeys.includes(getProductKey(product.module_id, product.unique_id)))
                .map((product) => createEmptyItem(buildProductRef(product, postId))),
        ];

        onChange(nextItems);
        setIsPickerOpen(false);
    };

    const handleAddAllProducts = () => {
        if (addableFilteredProducts.length === 0) {
            return;
        }

        const nextItems = [
            ...(items || []),
            ...addableFilteredProducts.map((product) => createEmptyItem(buildProductRef(product, postId))),
        ];

        onChange(nextItems);
        setIsPickerOpen(false);
    };

    const handleReplaceProduct = (index, product) => {
        updateItem(index, (currentItem) => ({
            ...currentItem,
            product_ref: buildProductRef(product, postId),
            seeded_for: "",
        }));
        setIsPickerOpen(false);
    };

    const hasAvailableProducts = moduleIds.length > 0;

    return (
        <>
            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
                {itemsWithProducts.length > 0 ? (
                    itemsWithProducts.map(({ item, index, product }) => {
                        const itemId = item?.id || `row-${index}`;
                        const isExpanded = !!expandedItemIds[itemId];
                        const hasRef = !!(item?.product_ref?.module_id && item?.product_ref?.unique_id);
                        const fallbackTitle = String(item?.title || "").trim();
                        const displayTitle = product?.title
                            || fallbackTitle
                            || product?.unique_id
                            || (hasRef ? __("Missing product", "content-egg") : __("Unbound item", "content-egg"));
                        const metaModuleId = product?.module_id || item?.product_ref?.module_id || "";
                        const metaText = product
                            ? (moduleLabels[metaModuleId] || metaModuleId) + (formatPrice(product) ? ` · ${formatPrice(product)}` : "")
                            : hasRef
                                ? __("Missing product", "content-egg")
                                : __("Not bound to a product", "content-egg");

                        return (
                            <div key={itemId} style={{ borderTop: index > 0 ? "1px solid #e0e0e0" : "0", paddingTop: index > 0 ? "10px" : 0 }}>
                                <div style={{ display: "flex", gap: "8px", alignItems: "flex-start" }}>
                                    {product ? (
                                        <div
                                            style={{
                                                width: "48px",
                                                height: "48px",
                                                flexShrink: 0,
                                                borderRadius: "6px",
                                                overflow: "hidden",
                                                border: "1px solid #dcdcde",
                                                background: "#fff",
                                            }}
                                        >
                                            {product.img ? (
                                                <img
                                                    src={product.img}
                                                    alt=""
                                                    style={{ width: "100%", height: "100%", objectFit: "contain", display: "block" }}
                                                />
                                            ) : null}
                                        </div>
                                    ) : null}
                                    <div style={{ minWidth: 0, flex: 1 }}>
                                        <div
                                            title={displayTitle}
                                            style={{
                                                fontSize: "12px",
                                                fontWeight: 600,
                                                color: "#1e1e1e",
                                                whiteSpace: "nowrap",
                                                overflow: "hidden",
                                                textOverflow: "ellipsis",
                                                marginBottom: "2px",
                                            }}
                                        >
                                            {displayTitle}
                                        </div>
                                        <div style={{ fontSize: "11px", color: "#757575" }}>
                                            {metaText}
                                        </div>
                                    </div>
                                </div>

                                <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", marginTop: "8px" }}>
                                    <Button
                                        variant="secondary"
                                        size="small"
                                        onClick={() => openReplacePicker(index, item?.product_ref?.module_id || "")}
                                        disabled={!hasAvailableProducts}
                                    >
                                        {product
                                            ? __("Change product", "content-egg")
                                            : __("Bind product", "content-egg")}
                                    </Button>
                                    {renderItemFields && (
                                        <Button
                                            variant="tertiary"
                                            size="small"
                                            onClick={() => toggleExpandedItem(itemId, index)}
                                        >
                                            {isExpanded ? __("Hide details", "content-egg") : __("Edit details", "content-egg")}
                                        </Button>
                                    )}
                                    <Button
                                        variant="tertiary"
                                        isDestructive
                                        size="small"
                                        onClick={() => removeItem(index)}
                                    >
                                        {__("Remove", "content-egg")}
                                    </Button>
                                </div>

                                {isExpanded && renderItemFields ? (
                                    <div style={{ marginTop: "10px" }}>
                                        {renderItemFields({
                                            item,
                                            index,
                                            product,
                                            updateItem: (updater) => updateItem(index, updater),
                                        })}
                                    </div>
                                ) : null}
                            </div>
                        );
                    })
                ) : (
                    <p style={{ margin: 0, fontSize: "12px", color: "#757575" }}>
                        {!hasAvailableProducts
                            ? __("No Content Egg products are currently available for this post.", "content-egg")
                            : emptyMessage}
                    </p>
                )}

                <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                    <Button
                        variant="secondary"
                        size="small"
                        onClick={openAddPicker}
                        disabled={!hasAvailableProducts}
                    >
                        {__("Add products", "content-egg")}
                    </Button>
                </div>

                {!postId && (
                    <p style={{ margin: 0, fontSize: "12px", color: "#757575" }}>
                        {__("Save the post to ensure product bindings have a valid post ID.", "content-egg")}
                    </p>
                )}
            </div>

            {isPickerOpen && (
                <Modal
                    title={pickerMode.type === "replace" ? __("Replace Content Egg Product", "content-egg") : __("Select Content Egg Products", "content-egg")}
                    onRequestClose={() => setIsPickerOpen(false)}
                    size="large"
                    className="modal-xl"
                >
                    <div className="cegg5-container">
                        <div className="row g-3 align-items-end mb-3">
                            <div className={groupValues.length > 0 ? "col-12 col-md-6" : "col-12"}>
                                <SelectControl
                                    label={__("Filter by Module", "content-egg")}
                                    value={moduleFilter}
                                    options={filterOptions}
                                    onChange={setModuleFilter}
                                />
                            </div>

                            {groupValues.length > 0 ? (
                                <div className="col-12 col-md-6">
                                    <SelectControl
                                        label={__("Filter by Group", "content-egg")}
                                        value={groupFilter}
                                        options={groupFilterOptions}
                                        onChange={setGroupFilter}
                                    />
                                </div>
                            ) : null}
                        </div>

                        {filteredProducts.length > 0 ? (
                            <>
                                <div className="row row-cols-2 row-cols-md-4 row-cols-xl-5 g-3">
                                    {filteredProducts.map((product) => {
                                        const productKey = getProductKey(product.module_id, product.unique_id);
                                        const isAlreadyBound = existingKeys.includes(productKey);
                                        const isUnavailable = pickerMode.type === "add"
                                            ? isAlreadyBound
                                            : (isAlreadyBound && productKey !== currentReplaceKey);
                                        const isSelected = pickerMode.type === "replace"
                                            ? productKey === currentReplaceKey
                                            : selectedProductKeys.includes(productKey);

                                        return (
                                            <div key={productKey} className="col">
                                                <button
                                                    type="button"
                                                    className="card h-100 text-start w-100"
                                                    onClick={() => {
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
                                                    }}
                                                    disabled={isUnavailable}
                                                    style={{
                                                        borderColor: isSelected ? "var(--cegg-success)" : undefined,
                                                        boxShadow: isSelected ? "0 0 0 1px var(--cegg-success)" : undefined,
                                                        opacity: isUnavailable ? 0.55 : 1,
                                                        background: "#fff",
                                                        padding: 0,
                                                        cursor: isUnavailable ? "not-allowed" : "pointer",
                                                    }}
                                                >
                                                    <div
                                                        className="ratio ratio-1x1"
                                                        style={{
                                                            overflow: "hidden",
                                                            borderBottom: "1px solid #e0e0e0",
                                                        }}
                                                    >
                                                        {product.img ? (
                                                            <img
                                                                src={product.img}
                                                                alt=""
                                                                className="object-fit-scale"
                                                                style={{
                                                                    maxHeight: "250px",
                                                                    width: "100%",
                                                                    height: "100%",
                                                                    objectFit: "contain",
                                                                    display: "block",
                                                                }}
                                                            />
                                                        ) : null}
                                                    </div>
                                                    <div className="card-body p-2">
                                                        <div className="small text-muted mb-1">{moduleLabels[product.module_id] || product.module_id}</div>
                                                        <div
                                                            className="fw-semibold lh-sm mb-1 text-truncate"
                                                            title={product.title || product.unique_id}
                                                        >
                                                            {product.title || product.unique_id}
                                                        </div>
                                                        {formatPrice(product) && <div className="small">{formatPrice(product)}</div>}
                                                        {isUnavailable ? (
                                                            <div className="small text-muted mt-1">
                                                                {pickerMode.type === "replace"
                                                                    ? __("Already bound", "content-egg")
                                                                    : __("Already added", "content-egg")}
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>

                                {pickerMode.type === "add" ? (
                                    <div className="d-flex justify-content-end gap-2 mt-3">
                                        <Button
                                            variant="secondary"
                                            onClick={handleAddAllProducts}
                                            disabled={addableFilteredProducts.length === 0}
                                        >
                                            {__("Add all", "content-egg")}
                                        </Button>
                                        <Button
                                            variant="primary"
                                            onClick={handleAddSelectedProducts}
                                            disabled={selectedProductKeys.length === 0}
                                        >
                                            {__("Add selected", "content-egg")}
                                        </Button>
                                    </div>
                                ) : null}
                            </>
                        ) : (
                            <p className="mb-0 text-muted">
                                {__("No products found for the current filter.", "content-egg")}
                            </p>
                        )}
                    </div>
                </Modal>
            )}
        </>
    );
}
