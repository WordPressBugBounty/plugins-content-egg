import { Button, Modal, SelectControl } from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";
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

function getModuleLabels() {
    const modules = window?.contentEggProductsBlockData?.modules;
    return modules && typeof modules === "object" ? modules : {};
}

export default function ProductRefControl({ value = {}, onChange }) {
    const snapshot = useCeggEditorProducts();
    const currentPostId = useCurrentPostId();
    const postId = currentPostId || snapshot.postId || Number(value?.post_id) || 0;
    const byModule = snapshot.byModule || {};
    const moduleLabels = getModuleLabels();
    const selectedModuleId = String(value?.module_id || "");
    const selectedUniqueId = String(value?.unique_id || "");
    const [isPickerOpen, setIsPickerOpen] = useState(false);
    const [moduleFilter, setModuleFilter] = useState(selectedModuleId);
    const [groupFilter, setGroupFilter] = useState("");

    const moduleIds = Object.keys(byModule).filter((moduleId) => Array.isArray(byModule[moduleId]) && byModule[moduleId].length > 0);
    const filterOptions = [
        { label: __("All modules", "content-egg"), value: "" },
        ...moduleIds.map((moduleId) => ({ label: moduleLabels[moduleId] || moduleId, value: moduleId })),
    ];

    const selectedProduct = (snapshot.all || []).find((product) => (
        String(product.module_id || "") === selectedModuleId &&
        String(product.unique_id || "") === selectedUniqueId
    )) || null;

    const moduleFilteredProducts = moduleFilter && Array.isArray(byModule[moduleFilter])
        ? byModule[moduleFilter]
        : (snapshot.all || []);
    const groupValues = Array.from(new Set(
        moduleFilteredProducts
            .map((product) => String(product?.group || "").trim())
            .filter(Boolean)
    )).sort((left, right) => left.localeCompare(right));
    const groupFilterOptions = [
        { label: __("All groups", "content-egg"), value: "" },
        ...groupValues.map((group) => ({ label: group, value: group })),
    ];
    const filteredProducts = groupFilter
        ? moduleFilteredProducts.filter((product) => String(product?.group || "").trim() === groupFilter)
        : moduleFilteredProducts;

    useEffect(() => {
        setModuleFilter(selectedModuleId);
    }, [selectedModuleId]);

    useEffect(() => {
        if (groupFilter && !groupValues.includes(groupFilter)) {
            setGroupFilter("");
        }
    }, [groupFilter, groupValues]);

    useEffect(() => {
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

    const setValue = (nextValue) => onChange(nextValue || {});
    const hasAvailableProducts = moduleIds.length > 0;

    const clearBinding = () => setValue({});

    const handleProductSelect = (product) => {
        setValue({
            module_id: String(product?.module_id || ""),
            unique_id: String(product?.unique_id || ""),
            post_id: postId,
        });
        setIsPickerOpen(false);
    };

    return (
        <>
            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
                {selectedProduct ? (
                    <div className="cegg5-container">
                        <div className="d-flex gap-2 align-items-start">
                            <div
                                style={{
                                    width: "56px",
                                    height: "56px",
                                    flexShrink: 0,
                                    borderRadius: "6px",
                                    overflow: "hidden",
                                    background: "#f6f7f7",
                                    border: "1px solid #dcdcde",
                                }}
                            >
                                {selectedProduct.img ? (
                                    <img
                                        src={selectedProduct.img}
                                        alt=""
                                        style={{ width: "100%", height: "100%", objectFit: "cover", display: "block" }}
                                    />
                                ) : null}
                            </div>
                            <div style={{ minWidth: 0, fontSize: "12px", color: "#50575e", flex: 1 }}>
                                <div
                                    title={selectedProduct.title || selectedProduct.unique_id}
                                    style={{
                                        fontWeight: 600,
                                        color: "#1e1e1e",
                                        marginBottom: "2px",
                                        whiteSpace: "nowrap",
                                        overflow: "hidden",
                                        textOverflow: "ellipsis",
                                    }}
                                >
                                    {selectedProduct.title || selectedProduct.unique_id}
                                </div>
                                <div>{moduleLabels[selectedProduct.module_id] || selectedProduct.module_id}</div>
                                {formatPrice(selectedProduct) && <div>{formatPrice(selectedProduct)}</div>}
                            </div>
                        </div>
                    </div>
                ) : (
                    <p style={{ margin: 0, fontSize: "12px", color: "#757575" }}>
                        {!hasAvailableProducts
                            ? __("No Content Egg products are currently available for this post.", "content-egg")
                            : __("No product is currently bound to this block.", "content-egg")}
                    </p>
                )}

                <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                    <Button
                        variant="secondary"
                        size="small"
                        onClick={() => setIsPickerOpen(true)}
                        disabled={!hasAvailableProducts}
                    >
                        {selectedProduct ? __("Change product", "content-egg") : __("Select product", "content-egg")}
                    </Button>
                    {selectedProduct && (
                        <Button
                            variant="tertiary"
                            isDestructive
                            size="small"
                            onClick={clearBinding}
                        >
                            {__("Clear binding", "content-egg")}
                        </Button>
                    )}
                </div>

                {!postId && (
                    <p style={{ margin: 0, fontSize: "12px", color: "#757575" }}>
                        {__("Save the post to ensure the product binding has a valid post ID.", "content-egg")}
                    </p>
                )}
            </div>

            {isPickerOpen && (
                <Modal
                    title={__("Select Content Egg Product", "content-egg")}
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
                            <div className="row row-cols-2 row-cols-md-4 row-cols-xl-5 g-3">
                                {filteredProducts.map((product) => {
                                    const isSelected =
                                        String(product.module_id || "") === selectedModuleId &&
                                        String(product.unique_id || "") === selectedUniqueId;

                                    return (
                                        <div key={`${product.module_id}-${product.unique_id}`} className="col">
                                            <button
                                                type="button"
                                                className="card h-100 text-start w-100"
                                                onClick={() => handleProductSelect(product)}
                                                style={{
                                                    borderColor: isSelected ? "var(--cegg-success)" : undefined,
                                                    boxShadow: isSelected ? "0 0 0 1px var(--cegg-success)" : undefined,
                                                    background: "#fff",
                                                    padding: 0,
                                                    cursor: "pointer",
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
                                                </div>
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
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
