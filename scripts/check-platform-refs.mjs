import fs from "node:fs";
import path from "node:path";

const editorRoot = process.argv[2];
const platformRoot = process.argv[3];
const refs = new Set();

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const f = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(f);
    else if (/\.(ts|tsx)$/.test(entry.name)) {
      const s = fs.readFileSync(f, "utf8");
      for (const m of s.matchAll(/from\s+['"](@nreljed\/platform\/[^'"]+)['"]/g)) {
        refs.add(m[1].replace(/^@nreljed\/platform\//, ""));
      }
    }
  }
}

walk(editorRoot);

const missing = [];
for (const r of refs) {
  const rp = path.join(platformRoot, r);
  const candidates = [rp, `${rp}.ts`, `${rp}.tsx`, path.join(rp, "index.ts"), path.join(rp, "index.tsx")];
  if (!candidates.some((c) => fs.existsSync(c))) missing.push(r);
}

if (missing.length) {
  console.log(`MISSING (${missing.length}):\n${missing.join("\n")}`);
} else {
  console.log(`OK - all ${refs.size} referenced platform targets resolve`);
}