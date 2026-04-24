import { isTocSupportedBlock } from "./tocRegistry";
import { normalizeAnchor } from "./collectTocItems";

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

export function getDuplicateTocAnchors(blocks) {
    const counts = new Map();

    walk(blocks, (block) => {
        const name = block.name || "";
        if (name === "eggb/toc" || !isTocSupportedBlock(name)) {
            return;
        }

        const attrs = block.attributes || {};
        if (!attrs.include_in_toc) {
            return;
        }

        const anchor = normalizeAnchor(attrs.anchor);
        if (!anchor) {
            return;
        }

        counts.set(anchor, (counts.get(anchor) || 0) + 1);
    });

    return Array.from(counts.entries())
        .filter(([, count]) => count > 1)
        .map(([anchor]) => anchor);
}

export function isDuplicateTocAnchor(blocks, anchor) {
    const normalizedAnchor = normalizeAnchor(anchor);
    if (!normalizedAnchor) {
        return false;
    }

    return getDuplicateTocAnchors(blocks).includes(normalizedAnchor);
}
