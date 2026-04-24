const TOC_REGISTRY = {
    "eggb/section-header": { defaultLevel: 2, headingAttr: "title" },
    "eggb/intro": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/conclusion": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/faq": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/methodology": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/step-list": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/key-takeaways": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/criteria": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/definitions": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/myth-fact": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/specifications": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/quick-picks": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/where-to-buy": { defaultLevel: 2, headingAttr: "title", labelAttr: "section_label" },
    "eggb/product-card": { defaultLevel: 2, headingAttr: "block_title", labelAttr: "section_label" },
    "eggb/comparison-table": { defaultLevel: 2, headingAttr: "heading_title", labelAttr: "heading_label" },
};

export function isTocSupportedBlock(name) {
    return Object.prototype.hasOwnProperty.call(TOC_REGISTRY, name);
}

export function defaultTocLevel(name) {
    return TOC_REGISTRY[name]?.defaultLevel || 2;
}

export function headingAttr(name) {
    return TOC_REGISTRY[name]?.headingAttr || null;
}

export function labelAttr(name) {
    return TOC_REGISTRY[name]?.labelAttr || null;
}

export function allTocBlocks() {
    return TOC_REGISTRY;
}
