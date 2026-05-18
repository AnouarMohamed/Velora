// Converts src .ts/.tsx and vite.config.ts to .js/.jsx (run from frontend/: node scripts/convert-ts-to-js.mjs)
import * as ts from 'typescript';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const srcRoot = path.join(root, 'src');

function walk(dir, files = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p, files);
    else if (/\.(ts|tsx)$/.test(e.name) && !e.name.endsWith('.d.ts')) files.push(p);
  }
  return files;
}

const toConvert = walk(srcRoot).filter((p) => !p.endsWith(`${path.sep}types${path.sep}index.ts`));
toConvert.push(path.join(root, 'vite.config.ts'));

for (const fp of toConvert) {
  const source = fs.readFileSync(fp, 'utf8');
  const isTsx = fp.endsWith('.tsx');
  const result = ts.transpileModule(source, {
    compilerOptions: {
      target: ts.ScriptTarget.ES2022,
      module: ts.ModuleKind.ESNext,
      jsx: isTsx ? ts.JsxEmit.ReactJSX : ts.JsxEmit.Preserve,
      verbatimModuleSyntax: false,
    },
    fileName: fp,
  });

  let out = result.outputText;
  out = out.replace(/from\s+(['"])(\.[^'"]+)\.tsx\1/g, 'from $1$2.jsx$1');
  out = out.replace(/from\s+(['"])(\.[^'"]+)\.ts\1/g, 'from $1$2.js$1');

  const rel = path.relative(root, fp);
  const outRel = rel.replace(/\.tsx$/, '.jsx').replace(/\.ts$/, '.js');
  const outPath = path.join(root, outRel);
  fs.writeFileSync(outPath, out);
  fs.unlinkSync(fp);
  console.log(rel, '->', outRel);
}

const typesDir = path.join(srcRoot, 'types');
if (fs.existsSync(typesDir)) {
  fs.rmSync(typesDir, { recursive: true, force: true });
  console.log('removed src/types');
}
