// Trims unused Bootstrap utility-API classes (d-flex, gap-*, mt-*, w-*, z-*, ...)
// from the compiled cegg-bootstrap.min.css. Everything else (reboot, grid,
// components, :root variables, dark-mode overrides) is structurally exempt and
// always kept, regardless of whether a literal match is found in content.
//
// "Utility candidate" selectors are identified by compiling a second, minimal
// stylesheet containing ONLY the utilities + utilities/api partials (no
// components, no grid, no :root) and comparing selector text against it --
// not by guessing utility-class name patterns.
import { readFileSync, writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import postcss from 'postcss';
import selectorParser from 'postcss-selector-parser';
import fg from 'fast-glob';
import * as sass from 'sass';

const CSS_FILE = 'res/site/bootstrap/css/cegg-bootstrap.min.css';

// Every known render site for cegg5-container-scoped markup (confirmed via
// repo-wide `cegg5-container` grep + manual review, see the migration plan).
const CONTENT_GLOBS = [
    'templates/*.php',
    'application/templates/**/*.php',
    'application/blocks/productblock/*.php',
    'application/EggBlocks/blocks/*/render.php',
    'application/EggBlocks/shared/**/*.php',
    'application/AffiliateDisclaimer.php',
    'application/admin/views/**/*.php',
    'application/admin/import/**/*.php',
];

// Classes assembled at runtime via PHP string concatenation (see
// application/helpers/TemplateHelper.php: border(), borderColor(), colsOrder(),
// rowCols()) -- a literal-token content scan can never find these.
const SAFELIST_PATTERNS = [/^border(-|$)/, /^order(-|$)/, /^row-cols(-|$)/];

function compileUtilitiesUniverse() {
    const scssDir = path.resolve('scss');
    const virtualUrl = pathToFileURL(path.join(scssDir, '__utilities_universe__.scss'));
    const source = `
$prefix: "cegg-";
@import "../bootstrap/scss/functions";
@import "../bootstrap/scss/mixins";
@import "../bootstrap/scss/variables";
@import "../bootstrap/scss/maps";
.cegg5-container {
  @import "../bootstrap/scss/utilities";
  @import "../bootstrap/scss/utilities/api";
}
`;
    const result = sass.compileString(source, {
        url: virtualUrl,
        style: 'compressed',
        sourceMap: false,
        charset: false,
        logger: sass.Logger.silent,
    });
    return result.css;
}

function collectSelectorTexts(css) {
    const root = postcss.parse(css);
    const selectors = new Set();
    root.walkRules((rule) => {
        selectorParser()
            .astSync(rule.selector)
            .each((sel) => selectors.add(sel.toString()));
    });
    return selectors;
}

function collectContentTokens() {
    const files = fg.sync(CONTENT_GLOBS);
    const tokens = new Set();
    for (const file of files) {
        const text = readFileSync(file, 'utf8');
        for (const match of text.matchAll(/[A-Za-z0-9_-]+/g)) {
            tokens.add(match[0]);
        }
    }
    return { tokens, fileCount: files.length };
}

// Safety net: whatever the currently-committed file already ships stays,
// even if the content scan above can't find where it's used. This makes the
// purge a strict narrowing of "definitely shows up in the classified
// utility-candidate set AND (referenced in scanned content OR already
// shipping OR safelisted)" -- it can never regress something live.
function collectCommittedClasses() {
    let committedCss;
    try {
        committedCss = execFileSync('git', ['show', `HEAD:${CSS_FILE}`], {
            encoding: 'utf8',
        });
    } catch {
        return new Set();
    }
    const classes = new Set();
    const root = postcss.parse(committedCss);
    root.walkRules((rule) => {
        selectorParser().astSync(rule.selector).each((sel) => {
            classesOf(sel.toString()).forEach((c) => classes.add(c));
        });
    });
    return classes;
}

function classesOf(selector) {
    const classes = [];
    selectorParser((sels) => {
        sels.walkClasses((node) => classes.push(node.value));
    }).processSync(selector);
    return classes;
}

function isSafelisted(className) {
    return SAFELIST_PATTERNS.some((re) => re.test(className));
}

const universeCss = compileUtilitiesUniverse();
const universeSelectors = collectSelectorTexts(universeCss);
const { tokens: contentTokens, fileCount } = collectContentTokens();
const committedClasses = collectCommittedClasses();

const mainCss = readFileSync(CSS_FILE, 'utf8');
const mainRoot = postcss.parse(mainCss);

let keptUtility = 0;
let droppedUtility = 0;
const droppedClasses = new Set();

mainRoot.walkRules((rule) => {
    const parsedSelectors = selectorParser().astSync(rule.selector);
    const keptSelectors = [];

    parsedSelectors.each((sel) => {
        const text = sel.toString();
        if (!universeSelectors.has(text)) {
            // Not a utilities-API selector (component/grid/root/etc.) -- always keep.
            keptSelectors.push(text);
            return;
        }

        const classes = classesOf(text).filter((c) => c !== 'cegg5-container');
        const used = classes.some(
            (c) => contentTokens.has(c) || committedClasses.has(c) || isSafelisted(c)
        );

        if (used) {
            keptSelectors.push(text);
            keptUtility++;
        } else {
            droppedUtility++;
            classes.forEach((c) => droppedClasses.add(c));
        }
    });

    if (keptSelectors.length === 0) {
        rule.remove();
    } else if (keptSelectors.length !== parsedSelectors.length) {
        rule.selector = keptSelectors.join(',');
    }
});

// Drop now-empty @media blocks left behind by fully-removed rules.
mainRoot.walkAtRules((atRule) => {
    if (atRule.nodes && atRule.nodes.length === 0) {
        atRule.remove();
    }
});

writeFileSync(CSS_FILE, mainRoot.toString());

console.log(`content files scanned: ${fileCount}`);
console.log(`utility selectors kept: ${keptUtility}`);
console.log(`utility selectors dropped: ${droppedUtility}`);
console.log(`dropped classes (${droppedClasses.size}):`, [...droppedClasses].sort().join(', '));
