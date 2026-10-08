import fs from "node:fs";
import path from "node:path";

const root = process.argv[2];
const bad = [];

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const f = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(f);
    else if (/\.(ts|tsx)$/.test(entry.name)) check(f);
  }
}

function check(file) {
  const s = fs.readFileSync(file, "utf8");
  for (const m of s.matchAll(/from\s+['"]([^'"]+)['"]/g)) {
    const sp = m[1];
    if (!sp.startsWith("..")) continue;
    const ups = sp.split("/").filter((x) => x === "..").length;
    const depth = path.relative(root, file).split(path.sep).length - 1;
    if (ups > depth) bad.push(`${path.relative(root, file)} -> ${sp}`);
  }
}

walk(root);
console.log(bad.length ? `ESCAPES FOUND:\n${bad.join("\n")}` : "CLEAN - all relative imports stay inside editor root");