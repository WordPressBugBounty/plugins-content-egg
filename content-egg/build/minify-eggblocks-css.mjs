// Compresses EggBlocks' hand-written CSS sources into their loaded `-min.css`
// siblings. Neither CodeKit (folder-skipped since 4945159a) nor webpack
// (blocks don't import CSS) currently rebuild these on edit -- this closes
// that gap.
import { compile } from 'sass';
import fg from 'fast-glob';
import { writeFileSync } from 'node:fs';

const patterns = [
    'application/EggBlocks/blocks/*/style.css',
    'application/EggBlocks/shared/eggb-base.css',
    'application/EggBlocks/themes/*.css',
];

const files = (await fg(patterns)).filter((file) => !file.endsWith('-min.css'));

for (const file of files) {
    const outFile = file.replace(/\.css$/, '-min.css');
    const result = compile(file, { style: 'compressed', sourceMap: false, charset: false });
    writeFileSync(outFile, result.css);
    console.log(`${file} -> ${outFile}`);
}
