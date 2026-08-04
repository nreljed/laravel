const THEME_ID = "github-dark";

const state: {
  highlighter: Promise<import("shiki/core").HighlighterCore> | null;
} = { highlighter: null };

async function getHighlighter(): Promise<import("shiki/core").HighlighterCore> {
  state.highlighter ??= (async () => {
    const [{ createHighlighterCore }, { createJavaScriptRegexEngine }, { bundledThemesInfo }] =
      await Promise.all([
        import("shiki/core"),
        import("@shikijs/engine-javascript"),
        import("shiki/themes"),
      ]);
    const theme = bundledThemesInfo.find((info) => info.id === THEME_ID);
    return createHighlighterCore({
      engine: createJavaScriptRegexEngine(),
      langs: [],
      themes: theme ? [theme.import()] : [],
    });
  })();

  return state.highlighter;
}

async function loadLanguage(
  highlighter: import("shiki/core").HighlighterCore,
  langId: string,
): Promise<boolean> {
  if (highlighter.getLoadedLanguages().includes(langId)) {
    return true;
  }
  const { bundledLanguagesInfo } = await import("shiki/langs");
  const info = bundledLanguagesInfo.find(
    (lang) => lang.id === langId || (lang.aliases ?? []).includes(langId),
  );
  if (!info) {
    return false;
  }
  await highlighter.loadLanguage(info.import());
  return true;
}

const SPECIAL_LANGUAGES = new Set(["plain", "plaintext", "text", "txt"]);

async function highlightBlock(code: HTMLElement): Promise<void> {
  const langClass = [...code.classList].find((cls) => cls.startsWith("language-"));
  const langId = langClass ? langClass.slice("language-".length) : "plain";
  if (SPECIAL_LANGUAGES.has(langId)) {
    return;
  }

  const highlighter = await getHighlighter();
  const loaded = await loadLanguage(highlighter, langId);
  if (!loaded) {
    return;
  }

  const html = highlighter.codeToHtml(code.textContent ?? "", {
    lang: langId,
    theme: THEME_ID,
  });
  const highlighted = new DOMParser().parseFromString(html, "text/html").querySelector("code");
  if (highlighted) {
    code.setHTMLUnsafe(highlighted.getHTML());
  }

  const pre = code.closest("pre");
  if (pre) {
    pre.dataset.shikiHighlighted = "true";
  }
}

export async function highlightAllCodeBlocks(): Promise<void> {
  if (typeof window === "undefined" || typeof document === "undefined") {
    return;
  }
  for (const pre of document.querySelectorAll<HTMLElement>("pre")) {
    const code = pre.querySelector<HTMLElement>("code[class*='language-']");
    if (code) {
      await highlightBlock(code);
    }
  }
}
