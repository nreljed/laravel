import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { execSync } from "node:child_process";

const mirror = path.resolve(process.argv[2]);
const repo = path.resolve(process.argv[3]);

function tracked(repoDir) {
  return execSync(`git -C "${repoDir}" ls-files`, { encoding: "utf8" })
    .split(/\r?\n/)
    .filter(Boolean);
}

function hash(file) {
  return crypto.createHash("sha256").update(fs.readFileSync(file)).digest("hex");
}

function readAll(dir, root) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === "node_modules" || entry.name === "vendor" || entry.name === ".git") continue;
    const f = path.join(dir, entry.name);
    const rel = path.relative(root, f);
    if (entry.isDirectory()) {
      out.push(...readAll(f, root));
    } else {
      out.push(rel);
    }
  }
  return out;
}

const trackedFiles = new Set(tracked(repo).map((f) => f.replace(/\//g, path.sep)));

const missing = [];
const differ = [];
const excess = [];

for (const rel of trackedFiles) {
  const m = path.join(mirror, rel);
  const r = path.join(repo, rel);
  if (!fs.existsSync(m)) {
    missing.push(rel);
  } else if (hash(m) !== hash(r)) {
    differ.push(rel);
  }
}

for (const rel of readAll(mirror, mirror)) {
  if (!trackedFiles.has(rel)) {
    excess.push(rel);
  }
}

console.log(JSON.stringify({ missing: [...missing].sort((a, b) => a.localeCompare(b)), differ: differ.sort((a, b) => a.localeCompare(b)), excess: [...excess].sort((a, b) => a.localeCompare(b)) }, null, 2));