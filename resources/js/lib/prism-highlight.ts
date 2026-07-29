const state: { promise: Promise<void> | null } = { promise: null };

const PRISM_CDN = "https://cdn.jsdelivr.net/npm/prismjs@1.29.0";

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

function loadScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${CSS.escape(src)}"]`)) {
      resolve();
      return;
    }
    const script = document.createElement("script");
    script.src = src;
    script.async = true;
    script.addEventListener("load", () => resolve(), { once: true });
    script.addEventListener("error", () => reject(new Error(`Failed to load ${src}`)), {
      once: true,
    });
    document.head.appendChild(script);
  });
}

async function doHighlight(): Promise<void> {
  await loadScript(`${PRISM_CDN}/prism.min.js`);

  await Promise.all(
    LANGUAGES.map((lang) => loadScript(`${PRISM_CDN}/components/prism-${lang}.min.js`)),
  );

  globalThis.Prism?.highlightAll();
}

export function highlightAllCodeBlocks(): Promise<void> {
  if (typeof window === "undefined" || typeof document === "undefined") {
    return Promise.resolve();
  }

  state.promise ??= doHighlight();
  return state.promise;
}
