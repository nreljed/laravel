import { useEffect, useRef, useState } from "react";
import { ArrowRight, ChevronDown, Sparkles } from "lucide-react";
import { cn } from "cn";
import PublicLayout from "../../layouts/PublicLayout";
import { PillButton } from "../../components/site/PillButton";
import { SitePill } from "../../components/site/SitePill";
import { SitePagination } from "../../components/site/SitePagination";

interface ColorSwatch {
  label: string;
  className: string;
  opacity?: number;
  varName?: string;
  hex?: string;
}

const colorGroups: { title: string; note: string; colors: ColorSwatch[] }[] = [
  {
    title: "Brand colors",
    note: "The accents that identify the site: primary, panels and callouts.",
    colors: [
      { label: "Accent · signal green", varName: "--site-accent", className: "bg-site-accent" },
      {
        label: "Secondary",
        varName: "--site-secondary",
        className: "bg-site-secondary",
      },
      { label: "Sand", varName: "--site-alt-1", className: "bg-site-alt-1" },
      { label: "Forest", varName: "--site-alt-2", className: "bg-site-alt-2" },
      { label: "Clay", varName: "--site-pink", className: "bg-site-pink" },
    ],
  },
  {
    title: "Grey scale",
    note: "Text, surfaces and hairlines. Ink doubles as the on-light text color.",
    colors: [
      { label: "Ink", varName: "--site-ink", className: "bg-site-ink" },
      { label: "Ink soft", varName: "--site-ink-soft", className: "bg-site-ink-soft" },
      { label: "Ink faint", varName: "--site-ink-faint", className: "bg-site-ink-faint" },
      { label: "Surface", varName: "--site-surface", className: "bg-site-surface" },
      { label: "Background", varName: "--site-bg", className: "bg-site-bg" },
      { label: "Border", varName: "--site-border", className: "bg-site-border" },
    ],
  },
  {
    title: "Other",
    note: "Text placed on brand colors. Outline is an alias of ink for strokes.",
    colors: [{ label: "On accent", varName: "--site-accent-ink", className: "bg-site-accent-ink" }],
  },
];

const spacingScale = [
  { key: "zero", em: 0 },
  { key: "4x-small", em: 0.125 },
  { key: "3x-small", em: 0.25 },
  { key: "2x-small", em: 0.35 },
  { key: "x-small", em: 0.5 },
  { key: "small", em: 0.75 },
  { key: "medium", em: 1 },
  { key: "large", em: 1.25 },
  { key: "x-large", em: 1.5 },
  { key: "2x-large", em: 2 },
  { key: "3x-large", em: 3 },
  { key: "4x-large", em: 4 },
  { key: "5x-large", em: 5 },
  { key: "6x-large", em: 6 },
  { key: "7x-large", em: 8 },
  { key: "8x-large", em: 10 },
  { key: "huge", em: 12 },
  { key: "x-huge", em: 14 },
];

const fonts = [
  {
    label: "Instrument Sans · 400–700",
    className: "font-site-body",
    note: "Single family platform-wide: display, body and labels share it. Hierarchy comes from weight plus tracking and size contrast, not a second decorative face.",
  },
];

const headingClasses: {
  tag: "p" | "h1" | "h2" | "h3" | "h4" | "h5" | "h6";
  className: string;
  sample: string;
  meta: string;
}[] = [
  {
    tag: "p",
    className: "site-hero",
    sample: "The display crown — the home hero head.",
    meta: "site-hero · clamp(3.25rem, 9vw, 7rem) · 700 · lh .85 · −.05em",
  },
  {
    tag: "p",
    className: "site-display",
    sample: "Display — the largest page title.",
    meta: "site-display · clamp(2.75rem, 7vw, 5.6rem) · 700 · lh .9 · −.05em",
  },
  {
    tag: "p",
    className: "site-title",
    sample: "Title — page heads and major section titles.",
    meta: "site-title · clamp(2.25rem, 5.5vw, 4.5rem) · 700 · lh .95 · −.04em",
  },
  {
    tag: "p",
    className: "site-title-sm",
    sample: "Title sm — subsection leads.",
    meta: "site-title-sm · clamp(1.875rem, 4vw, 3rem) · 700 · lh 1 · −.03em",
  },
  {
    tag: "p",
    className: "site-heading",
    sample: "Heading — card titles.",
    meta: "site-heading · clamp(1.5rem, 3vw, 2.25rem) · 700 · lh 1.1 · −.02em",
  },
  {
    tag: "p",
    className: "site-heading-sm",
    sample: "Heading sm — small card titles.",
    meta: "site-heading-sm · clamp(1.25rem, 2.5vw, 1.6rem) · 600 · lh 1.15 · −.02em",
  },
  {
    tag: "p",
    className: "site-heading-xs",
    sample: "Heading xs — eyebrow labels.",
    meta: "site-heading-xs · clamp(1.05rem, 1.6vw, 1.25rem) · 700 · lh 1.3 · −.01em",
  },
  {
    tag: "p",
    className: "site-lead",
    sample: "Lead paragraph — the intro line that follows a heading.",
    meta: "site-lead · clamp(1.125rem, 1.7vw, 1.5rem) · lh 1.65 · −.015em",
  },
];

const sampleLinks = [
  { url: "https://example.com?page=1", label: "&laquo; Previous", active: false },
  { url: "https://example.com?page=1", label: "1", active: false },
  { url: "https://example.com?page=2", label: "2", active: true },
  { url: "https://example.com?page=3", label: "3", active: false },
  { url: "https://example.com?page=3", label: "Next &raquo;", active: false },
];

const sections = [
  { id: "colors", label: "Colors", number: "01" },
  { id: "typography", label: "Typography", number: "02" },
  { id: "fonts", label: "Fonts", number: "03" },
  { id: "spacing", label: "Spacing", number: "04" },
  { id: "buttons", label: "Buttons", number: "05" },
  { id: "pills", label: "Tags & pills", number: "06" },
  { id: "menu", label: "Menu overlay", number: "07" },
  { id: "cards", label: "Cards", number: "08" },
  { id: "pagination", label: "Pagination", number: "09" },
];

export default function StyleGuide() {
  const rootRef = useRef<HTMLDivElement>(null);
  const mobileNavPanelRef = useRef<HTMLDivElement>(null);
  const [resolved, setResolved] = useState<Record<string, string>>({});
  const [active, setActive] = useState<string>("colors");
  const [mobileNavOpen, setMobileNavOpen] = useState(false);

  useEffect(() => {
    const root = rootRef.current?.closest("[data-site-theme]");
    if (!root) return;
    const style = getComputedStyle(root);
    const values: Record<string, string> = {};
    for (const group of colorGroups) {
      for (const swatch of group.colors) {
        if (swatch.varName) {
          values[swatch.varName] = style.getPropertyValue(swatch.varName).trim();
        }
      }
    }
    setResolved(values);
  }, []);

  // Scroll-spy: highlight the sidebar/mobile entry that matches the section
  // currently crossing the upper third of the viewport.
  useEffect(() => {
    const targets = sections
      .map((section) => document.querySelector<HTMLElement>(`#${section.id}`))
      .filter((el): el is HTMLElement => el !== null);
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .toSorted((a, b) => b.boundingClientRect.top - a.boundingClientRect.top);
        if (visible.length > 0) {
          setActive(visible[0]!.target.id);
        }
      },
      { rootMargin: "-25% 0px -65% 0px", threshold: 0 },
    );
    for (const target of targets) {
      observer.observe(target);
    }
    return () => observer.disconnect();
  }, []);

  const sectionHeading = (number: string, label: string) => (
    <h2 className="font-site-display text-2xl font-bold tracking-tight text-site-ink sm:text-3xl">
      <span className="mr-3 text-sm font-semibold tabular-nums tracking-[0.2em] text-site-ink-faint">
        {number}
      </span>
      {label}
    </h2>
  );

  const scrollToSection = (id: string) => {
    const el = document.querySelector<HTMLElement>(`#${id}`);
    if (!el) return;
    const y = el.getBoundingClientRect().top + scrollY - 112;
    scrollTo({ top: y, behavior: "smooth" });
    history.replaceState(null, "", `#${id}`);
  };

  const waitForMobileNavCollapse = async () => {
    // grid-template-rows transitions don't start until ~480ms after the class
    // flips, so wait until the panel's height is really 0 before measuring the
    // jump target — otherwise we compute it against the still-expanded layout.
    const panel = mobileNavPanelRef.current;
    if (!panel) return;
    while (panel.getBoundingClientRect().height > 0) {
      await new Promise((resolve) => setTimeout(resolve, 40));
    }
  };

  const navItemContent = (item: { id: string; number: string; label: string }) => (
    <>
      <span
        className={cn(
          "text-xs tabular-nums",
          active === item.id ? "text-site-bg-strong/60" : "text-site-ink-faint",
        )}
      >
        {item.number}
      </span>
      {item.label}
      {active === item.id ? (
        <span className="ml-auto h-2 w-2 rounded-full bg-site-accent" />
      ) : (
        <span className="ml-auto h-1.5 w-1.5 rounded-full bg-site-ink/0 transition-colors group-hover:bg-site-ink/20" />
      )}
    </>
  );

  const sidebarNav = (
    <nav className="space-y-1">
      {sections.map((item) => (
        <a
          key={item.id}
          href={`#${item.id}`}
          onClick={() => setMobileNavOpen(false)}
          className={cn(
            "group flex items-center gap-3 rounded-site-card px-3 py-2 text-sm transition-colors",
            active === item.id
              ? "bg-site-ink text-site-bg-strong"
              : "text-site-ink-soft hover:bg-site-surface hover:text-site-ink",
          )}
        >
          {navItemContent(item)}
        </a>
      ))}
    </nav>
  );

  const mobileNav = (
    <nav className="space-y-1">
      {sections.map((item) => (
        <button
          key={item.id}
          type="button"
          onClick={() => {
            setMobileNavOpen(false);
            void waitForMobileNavCollapse().then(() => scrollToSection(item.id));
          }}
          className={cn(
            "group flex w-full items-center gap-3 rounded-site-card px-3 py-2 text-left text-sm transition-colors",
            active === item.id
              ? "bg-site-ink text-site-bg-strong"
              : "text-site-ink-soft hover:bg-site-surface hover:text-site-ink",
          )}
        >
          {navItemContent(item)}
        </button>
      ))}
    </nav>
  );

  return (
    <PublicLayout
      title="Style Guide"
      description="The public site design system: tokens, type, spacing and components."
    >
      <div
        ref={rootRef}
        className="mx-auto w-full max-w-[1440px] px-4 py-10 sm:px-6 lg:px-10 lg:py-14"
      >
        {/* Sidebar (desktop sticky) + content */}
        <div className="grid grid-cols-1 gap-10 lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-16">
          {/* Sidebar column */}
          <aside className="min-w-0 lg:self-start lg:sticky lg:top-[86px] lg:max-h-[calc(100vh-106px)] lg:overflow-y-auto lg:pr-2">
            {/* Mobile nav — collapsed top bar with a dropdown */}
            <div className="lg:hidden">
              <button
                type="button"
                onClick={() => setMobileNavOpen((value) => !value)}
                className="flex w-full items-center justify-between rounded-site-card border border-site-border bg-site-surface px-4 py-3.5 text-sm font-medium text-site-ink transition-colors hover:border-site-ink"
              >
                <span>Jump to section</span>
                <ChevronDown
                  className={cn(
                    "h-4 w-4 text-site-ink-soft transition-transform duration-200",
                    mobileNavOpen && "rotate-180",
                  )}
                />
              </button>
              <div
                ref={mobileNavPanelRef}
                className={cn(
                  "grid min-w-0 transition-[grid-template-rows] duration-300",
                  mobileNavOpen ? "grid-rows-[1fr]" : "grid-rows-[0fr]",
                )}
              >
                <div className="min-w-0 overflow-hidden">
                  <div className="mt-2 rounded-site-card border border-site-border bg-site-surface p-3">
                    {mobileNav}
                  </div>
                </div>
              </div>
            </div>

            {/* Desktop sticky sidebar */}
            <div className="hidden rounded-site-card border border-site-border bg-site-surface p-6 lg:block">
              <div className="flex items-center gap-3 border-b border-site-border pb-5">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-site-ink text-xs font-bold text-site-bg-strong">
                  CB
                </span>
                <div className="min-w-0">
                  <p className="truncate font-site-display text-sm font-bold tracking-tight text-site-ink">
                    Style Guide
                  </p>
                  <p className="text-xs text-site-ink-faint">{sections.length} sections</p>
                </div>
              </div>
              <div className="mt-5">{sidebarNav}</div>
            </div>
          </aside>

          {/* Content column */}
          <div>
            {/* Hero */}
            <header className="mb-12 border-b border-site-border pb-8 lg:mb-8">
              <div className="flex flex-wrap items-start justify-between gap-6">
                <div>
                  <p className="text-sm font-medium uppercase tracking-[0.2em] text-site-secondary">
                    Design system
                  </p>
                  <h1 className="mt-3 font-site-display text-4xl font-bold tracking-tight text-site-ink sm:text-5xl">
                    Style Guide
                  </h1>
                </div>
              </div>

              <p className="mt-5 max-w-2xl text-base leading-7 text-site-ink-soft">
                A living reference for the public site. Every value below resolves from the{" "}
                <code className="rounded bg-site-surface px-1.5 py-0.5 text-sm text-site-secondary">
                  site-*
                </code>{" "}
                tokens scoped under{" "}
                <code className="rounded bg-site-surface px-1.5 py-0.5 text-sm text-site-secondary">
                  [data-site-theme="cb360"]
                </code>
                , the CB360 "Ledger" palette module in{" "}
                <code className="rounded bg-site-surface px-1.5 py-0.5 text-sm text-site-secondary">
                  resources/css/themes/
                </code>
                .
              </p>
            </header>

            {/* Colors */}
            <section id="colors" className="mb-16 scroll-mt-28">
              {sectionHeading("01", "Colors")}
              <p className="mt-3 max-w-2xl text-sm leading-6 text-site-ink-soft">
                Three groups, no more: brand accents, the grey scale and the helpers used on top of
                them. Every swatch resolves live from a{" "}
                <code className="rounded bg-site-surface px-1.5 py-0.5 text-sm text-site-secondary">
                  site-*
                </code>{" "}
                token.
              </p>
              <div className="mt-8 grid gap-8 sm:grid-cols-2 xl:grid-cols-3">
                {colorGroups.map((group) => (
                  <div key={group.title}>
                    <h3 className="text-sm font-medium uppercase tracking-[0.2em] text-site-secondary">
                      {group.title}
                    </h3>
                    <p className="mt-1 text-xs text-site-ink-faint">{group.note}</p>
                    <div className="mt-3 space-y-2">
                      {group.colors.map((swatch) => (
                        <div key={swatch.varName} className="flex items-center gap-3">
                          <span
                            className={cn(
                              "h-12 w-12 shrink-0 rounded-site-card border border-site-border",
                              swatch.className,
                            )}
                            style={{ opacity: swatch.opacity }}
                          />
                          <div className="min-w-0">
                            <p className="text-sm font-medium text-site-ink">{swatch.label}</p>
                            <p className="truncate text-xs text-site-ink-faint">
                              {resolved[swatch.varName ?? ""] || swatch.varName}
                            </p>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </section>

            {/* Typography */}
            <section id="typography" className="mb-16 scroll-mt-28">
              {sectionHeading("02", "Typography")}
              <p className="mt-3 max-w-2xl text-sm leading-6 text-site-ink-soft">
                One base size and a set of heading size tokens. There are no ad-hoc text sizes;
                everything else derives from these two layers.
              </p>

              <div className="mt-6 rounded-site-card border border-site-border bg-site-surface p-6">
                <p className="text-xs font-medium uppercase tracking-[0.2em] text-site-secondary">
                  Base font size
                </p>
                <div className="mt-2">
                  <span className="text-xs font-medium uppercase tracking-[0.1em] text-site-ink-faint">
                    body · 1rem · Instrument Sans 400 · lh 1.65 · site-ink-soft
                  </span>
                  <p className="mt-3 max-w-2xl text-base leading-[1.65] text-site-ink-soft">
                    Body copy is a single size: 16px Instrument Sans Regular at 1.65 line height,
                    set in ink soft. Long-form text, cards and form labels all read at this size.
                  </p>
                </div>
              </div>

              <div className="mt-6 rounded-site-card border border-site-border bg-site-surface p-6">
                <p className="text-xs font-medium uppercase tracking-[0.2em] text-site-secondary">
                  Heading classes
                </p>
                <p className="mt-2 max-w-2xl text-sm text-site-ink-soft">
                  These are visual size tokens, not heading levels — apply one to any heading tag
                  and let the tag carry the semantics (one <code>h1</code> per page).
                </p>
                <div className="mt-2">
                  {headingClasses.map((item) => {
                    const Tag = item.tag;
                    return (
                      <div
                        key={item.className}
                        className="flex flex-col gap-2 border-b border-site-border py-5 last:border-0"
                      >
                        <span className="text-xs font-medium uppercase tracking-[0.1em] text-site-ink-faint">
                          {item.meta}
                        </span>
                        <Tag className={item.className}>{item.sample}</Tag>
                      </div>
                    );
                  })}
                </div>
              </div>
            </section>

            {/* Fonts */}
            <section id="fonts" className="mb-16 scroll-mt-28">
              {sectionHeading("03", "Fonts")}
              <div className="mt-8 grid gap-4 sm:grid-cols-2">
                {fonts.map((font) => (
                  <div key={font.label} className="rounded-site-card bg-site-surface p-6">
                    <h3 className="text-sm font-medium uppercase tracking-[0.2em] text-site-secondary">
                      {font.label}
                    </h3>
                    <p className={cn("mt-4 text-2xl", font.className)}>
                      The quick brown fox jumps over the lazy dog
                    </p>
                    <p className="mt-2 text-sm leading-6 text-site-ink-soft">{font.note}</p>
                  </div>
                ))}
              </div>
            </section>

            {/* Spacing */}
            <section id="spacing" className="mb-16 scroll-mt-28">
              {sectionHeading("04", "Spacing scale")}
              <p className="mt-3 max-w-2xl text-sm leading-6 text-site-ink-soft">
                Em-based fluid gaps used across every section. Bars are 20px per em.
              </p>
              <div className="mt-8 space-y-2 overflow-hidden">
                {spacingScale.map((step) => (
                  <div key={step.key} className="flex items-center gap-4">
                    <span className="w-32 shrink-0 text-xs font-medium uppercase tracking-[0.1em] text-site-ink-faint">
                      {step.key}
                    </span>
                    <span className="w-16 shrink-0 text-xs tabular-nums text-site-ink-faint">
                      {step.em}em
                    </span>
                    <span
                      className="h-4 rounded-site-card border border-site-border bg-site-secondary"
                      style={{
                        width: `${Math.max(step.em, 0.125) * 20}px`,
                        opacity: step.em === 0 ? 0.15 : 1,
                      }}
                    />
                  </div>
                ))}
              </div>
            </section>

            {/* Buttons */}
            <section id="buttons" className="mb-16 scroll-mt-28">
              {sectionHeading("05", "Buttons")}
              <p className="mt-3 max-w-2xl text-sm leading-6 text-site-ink-soft">
                Five variants, three sizes and three states. Height 53px is the default; compact and
                large are the deliberate exceptions.
              </p>

              <div className="mt-8 rounded-site-card border border-site-border bg-site-surface p-6">
                <p className="text-xs font-medium uppercase tracking-[0.2em] text-site-secondary">
                  Variants
                </p>
                <div className="mt-4 flex flex-wrap items-center gap-4">
                  <PillButton href={route("about")}>Primary pill</PillButton>
                  <PillButton href={route("about")}>
                    With icon
                    <ArrowRight className="h-4 w-4" />
                  </PillButton>
                  <a
                    href={route("about")}
                    className="inline-flex h-[53px] items-center gap-2 rounded-site-pill border border-site-border bg-site-bg-strong px-8 text-site-ink transition-colors hover:border-site-ink"
                  >
                    Outline
                  </a>
                  <a
                    href={route("about")}
                    className="inline-flex h-[53px] items-center gap-2 rounded-site-card bg-site-accent px-8 text-site-accent-ink transition-colors hover:opacity-90"
                  >
                    <Sparkles className="h-4 w-4" />
                    Accent square
                  </a>
                  <a
                    href={route("about")}
                    className="inline-flex h-[53px] items-center gap-2 px-2 text-sm font-medium text-site-ink underline decoration-site-ink/40 underline-offset-8 transition-colors hover:decoration-site-ink"
                  >
                    Text link
                  </a>
                </div>
              </div>

              <div className="mt-4 rounded-site-card border border-site-border bg-site-surface p-6">
                <p className="text-xs font-medium uppercase tracking-[0.2em] text-site-secondary">
                  Sizes
                </p>
                <div className="mt-4 flex flex-wrap items-center gap-4">
                  <PillButton href={route("about")} className="h-11 px-6 text-sm">
                    Compact
                  </PillButton>
                  <PillButton href={route("about")}>Default</PillButton>
                  <PillButton href={route("about")} className="h-[60px] px-10 text-lg">
                    Large
                  </PillButton>
                </div>
              </div>

              <div className="mt-4 rounded-site-card border border-site-border bg-site-surface p-6">
                <p className="text-xs font-medium uppercase tracking-[0.2em] text-site-secondary">
                  States
                </p>
                <div className="mt-4 flex flex-wrap items-center gap-4">
                  <PillButton href={route("about")}>Default</PillButton>
                  <PillButton href={route("about")} className="pointer-events-none bg-site-ink/90">
                    Hover
                  </PillButton>
                  <PillButton
                    href={route("about")}
                    className="pointer-events-none bg-site-ink/25 text-site-bg-strong/60"
                  >
                    Disabled
                  </PillButton>
                </div>
              </div>
            </section>

            {/* Pills */}
            <section id="pills" className="mb-16 scroll-mt-28">
              {sectionHeading("06", "Tags & pills")}
              <div className="mt-8 flex flex-wrap items-center gap-3">
                <SitePill href={route("blog.index")}>Design</SitePill>
                <SitePill href={route("blog.index", { tag: "engineering" })}>Engineering</SitePill>
                <SitePill href={route("blog.index", { tag: "strategy" })}>Strategy</SitePill>
                <span className="inline-flex items-center rounded-site-pill bg-site-ink px-4 py-1.5 text-sm text-site-bg-strong">
                  Active filter
                </span>
              </div>
            </section>

            {/* Menu overlay */}
            <section id="menu" className="mb-16 scroll-mt-28">
              {sectionHeading("07", "Menu overlay links")}
              <p className="mt-3 max-w-2xl text-sm leading-6 text-site-ink-soft">
                Full-screen dark overlay: numbered uppercase links that skew/italicize on hover,
                with the brand chip in the footer.
              </p>
              <div className="mt-8 rounded-site-card bg-site-ink p-6 sm:p-10">
                <div>
                  {["Home", "About", "Works", "Contact"].map((label, index) => (
                    <div
                      key={label}
                      className="group flex items-baseline gap-4 border-b border-site-bg/15 py-2 sm:gap-6 sm:py-3"
                    >
                      <span className="text-xs font-medium text-site-bg/40">0{index + 1}</span>
                      <span className="font-site-display text-[clamp(1.8rem,5vw,4rem)] font-bold uppercase leading-[0.95] tracking-[-0.03em] text-site-bg transition-all duration-500 group-hover:-skew-x-6 group-hover:italic group-hover:text-site-accent">
                        {label}
                      </span>
                    </div>
                  ))}
                </div>
                <div className="mt-8 flex items-center gap-3 border-t border-site-bg/15 pt-6 text-site-bg/60">
                  <span className="flex h-9 w-9 items-center justify-center rounded-full bg-site-pink text-xs font-bold text-site-ink">
                    CB
                  </span>
                  <span className="text-sm">Menu footer: brand chip + meta links</span>
                </div>
              </div>
            </section>

            {/* Cards */}
            <section id="cards" className="mb-16 scroll-mt-28">
              {sectionHeading("08", "Cards")}
              <div className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div className="flex flex-col justify-between rounded-site-card bg-site-surface p-6">
                  <div className="mb-6 inline-flex h-10 w-10 items-center justify-center rounded-full bg-site-ink text-site-bg-strong">
                    <Sparkles className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-site-display text-lg font-bold tracking-tight">Surface</h3>
                    <p className="mt-2 text-sm leading-6 text-site-ink-soft">
                      Default content card on the cream background.
                    </p>
                  </div>
                </div>
                <div className="flex flex-col justify-between rounded-site-card bg-site-alt-1 p-6">
                  <div className="mb-6 inline-flex h-10 w-10 items-center justify-center rounded-full bg-site-ink text-site-bg-strong">
                    <Sparkles className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-site-display text-lg font-bold tracking-tight">Alt-1</h3>
                    <p className="mt-2 text-sm leading-6 text-site-ink-soft">
                      Soft callout panel for highlights.
                    </p>
                  </div>
                </div>
                <div className="flex flex-col justify-between rounded-site-card bg-site-alt-2 p-6">
                  <div className="mb-6 inline-flex h-10 w-10 items-center justify-center rounded-full bg-site-ink text-site-bg-strong">
                    <Sparkles className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="font-site-display text-lg font-bold tracking-tight">Alt-2</h3>
                    <p className="mt-2 text-sm leading-6 text-site-ink-soft">
                      Deep teal panel for emphasis.
                    </p>
                  </div>
                </div>
              </div>
            </section>

            {/* Pagination */}
            <section id="pagination" className="scroll-mt-28">
              {sectionHeading("09", "Pagination")}
              <div className="mt-8">
                <SitePagination links={sampleLinks} />
              </div>
            </section>

            <div className="mt-16 flex items-center justify-between border-t border-site-border pt-8">
              <p className="text-sm text-site-ink-faint">End of the style guide.</p>
              <button
                type="button"
                onClick={() => scrollTo({ top: 0, behavior: "smooth" })}
                className="rounded-site-card border border-site-border bg-site-surface px-4 py-2 text-sm font-medium text-site-ink transition-colors hover:border-site-ink"
              >
                Back to top
              </button>
            </div>
          </div>
        </div>
      </div>
    </PublicLayout>
  );
}
