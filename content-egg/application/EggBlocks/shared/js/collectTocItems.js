import { defaultTocLevel, headingAttr, isTocSupportedBlock, labelAttr } from "./tocRegistry";

export function normalizeAnchor(anchor) {
    let value = String(anchor || "").trim();
    if (!value) {
        return "";
    }

    if (value.startsWith("#")) {
        value = value.slice(1);
    }

    return value
        .toLowerCase()
        .replace(/[^a-z0-9\s_-]/g, "")
        .trim()
        .replace(/[\s_]+/g, "-")
        .replace(/-+/g, "-");
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
        if (name && name !== "eggb/toc" && isTocSupportedBlock(name)) {
            const attrs = block.attributes || {};
            if (attrs.include_in_toc) {
                const anchor = normalizeAnchor(attrs.anchor);
                let label = normalizeLabel(attrs.toc_label);
                if (!label) {
                    const ha = headingAttr(name);
                    if (ha) {
                        label = normalizeLabel(attrs[ha]);
                    }
                }
                if (!label) {
                    const la = labelAttr(name);
                    if (la) {
                        label = normalizeLabel(attrs[la]);
                    }
                }
                const level = normalizeLevel(attrs.level, defaultTocLevel(name));

                if (anchor && label) {
                    items.push({ anchor, label, level });
                }
            }
        }

        if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
            walk(block.innerBlocks, items);
        }
    }
}

export default function collectTocItems(blocks) {
    const items = [];
    walk(blocks, items);
    return items;
}

export function buildNestedTocItems(items) {
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
            sub_items: [],
        };

        if (normalizedItem.level <= baseLevel || currentParentIndex === -1) {
            nestedItems.push(normalizedItem);
            currentParentIndex = nestedItems.length - 1;
            continue;
        }

        nestedItems[currentParentIndex].sub_items.push({
            ...normalizedItem,
            level: normalizedItem.level,
        });
    }

    return nestedItems;
}
