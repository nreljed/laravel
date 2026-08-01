import Prism from "prismjs";

const state: { promise: Promise<void> | null } = { promise: null };

const LANGUAGES = [
  "markup",
  "css",
  "clike",
  "javascript",
  "markup-templating",
  "jsx",
  "typescript",
  "tsx",
  "json",
  "markdown",
  "python",
  "bash",
  "sql",
  "yaml",
  "php",
];

async function doHighlight(): Promise<void> {
  await Promise.all(LANGUAGES.map((lang) => import(`prismjs/components/prism-${lang}`)));
  Prism.highlightAll();
}

export function highlightAllCodeBlocks(): Promise<void> {
  if (typeof window === "undefined" || typeof document === "undefined") {
    return Promise.resolve();
  }

  state.promise ??= doHighlight();
  return state.promise;
}
