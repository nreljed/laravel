#!/usr/bin/env node
// Moves the Lexical editor from the platform package into a feature module
// (blog) by rewriting relative imports that escaped `Components/editor/*`.
//
// Intra-editor relative imports (../image, ../table, ...) are unchanged.
// Imports that climbed OUT of the editor root pointed at the platform layout
// (Components/ui, lib, Components/shared/media-library) and must become
// `@nreljed/platform/<path from resources/js>` because the feature module
// reuses platform shared UI instead of shipping its own copy.
import fs from 'node:fs';
import path from 'node:path';

const [, , editorDir] = process.argv;

if (!editorDir) {
  console.error('usage: node move-editor.js <editorDir>');
  process.exit(1);
}

const editorRoot = path.resolve(editorDir);
const importRe = /from\s*['"]([^'"]+)['"]/g;
let changed = 0;
let escaped = 0;

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (entry.isFile() && /\.(ts|tsx)$/.test(entry.name)) rewrite(full);
  }
}

function rewrite(file) {
  const rel = path.relative(editorRoot, file);
  const dirSegments = path.dirname(rel).split(/[\\/]/).filter(Boolean); // below editor root
  const src = fs.readFileSync(file, 'utf8');
  let changedThisFile = false;

  const out = src.replace(importRe, (match, spec) => {
    if (spec.includes('?') || spec === '.' || spec.startsWith('@') || /^[a-z]/i.test(spec)) return match;
    const parts = spec.split('/');

    // Resolve the import against the virtual path from resources/js, treating
    // the editor root as `Components/editor` (one level below Components).
    let resolved = ['Components', 'editor', ...dirSegments];
    for (const part of parts) {
      if (part === '..') resolved.pop();
      else if (part !== '.' && part !== '') resolved.push(part);
    }
    // Remove any accidental empty leading segment.
    while (resolved[0] === '') resolved.shift();

    const isInternal = resolved.length >= 2 && resolved[0] === 'Components' && resolved[1] === 'editor';
    if (isInternal) return match; // still inside editor root — keep relative

    const target = `@nreljed/platform/${resolved.join('/')}`;
    escaped++;
    changedThisFile = true;
    return `from "${target}"`;
  });

  if (changedThisFile) {
    fs.writeFileSync(file, out);
    changed++;
  }
}

walk(editorRoot);
console.log(`[move-editor] rewrote ${escaped} escaped import(s) across ${changed} file(s) in ${editorRoot}`);