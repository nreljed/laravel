import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "./Components/ui/tooltip";
import { Toaster, toast } from "./Components/ui/toast";
import { ErrorBoundary } from "./Components/ErrorBoundary";
import type { PageImport } from "./dashboard";

import "./Components/ui/sidebar";

import { useEffect } from "react";

function InertiaFlashListener() {
    useEffect(() => {
        const showFlash = (event?: {
            detail?: { page?: { props?: { flash?: Record<string, unknown> } } };
        }) => {
            const page =
                event?.detail?.page ??
                ((router as { page?: unknown }).page as
                    | { props?: { flash?: Record<string, unknown> } }
                    | undefined);
            if (!page) return;
            const flash: Record<string, unknown> = page.props?.flash ?? {};
            if (flash.status) toast.add({ title: String(flash.status), type: "success" });
            if (flash.error) toast.add({ title: String(flash.error), type: "error" });
        };
        showFlash();
        return router.on("navigate", () => showFlash());
    }, []);
    return null;
}

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

export type FoundationAppConfig = {
    /**
     * The starting page map — normally `registerCoreTemplates()` from `./dashboard`
     * plus `registerStarterThemePages()` from `../themes/starter`. Core pages are
     * opt-in: a project decides which pages it uses by spreading the kit here
     * (and/or adding its own entries).
     */
    basePages?: Record<string, PageImport>;
    projectPages?: Record<string, PageImport>;
    projectPrefix?: string;
    /**
     * Explicit name → import overrides for core-owned pages (applied last, wins).
     */
    pageOverrides?: Record<string, PageImport>;
};

export type { PageImport } from "./dashboard";

export function buildPageMap(config: FoundationAppConfig = {}): Record<string, PageImport> {
    const pageMap: Record<string, PageImport> = {
        ...config.basePages,
    };

    if (config.projectPages) {
        const prefix = (config.projectPrefix ?? "project").toLowerCase();

        for (const [path, importFn] of Object.entries(config.projectPages)) {
            const componentPath = path.match(/\/Pages\/(.+)\.tsx$/)?.[1];

            if (componentPath) {
                pageMap[`${prefix}/${componentPath.toLowerCase()}`] = importFn;
            }
        }
    }

    if (config.pageOverrides) {
        for (const [name, importFn] of Object.entries(config.pageOverrides)) {
            pageMap[name.toLowerCase()] = importFn;
        }
    }

    return pageMap;
}

function resolvePage(pageMap: Record<string, PageImport>) {
    return async (name: string) => {
        const importPage = pageMap[name.toLowerCase()];
        if (!importPage) {
            throw new Error(`Page not found: ${name}`);
        }
        const result = typeof importPage === "function" ? importPage() : importPage;
        const module = await result;
        return module.default || module;
    };
}

/**
 * Whether the current document is an Inertia response at all.
 *
 * The platform's error views (`resources/views/errors/minimal.blade.php`, and
 * the maintenance page built on it) load this same entry with `@vite` so the
 * font and the Tailwind classes resolve, but they are plain Blade: there is no
 * `data-page` payload and there never will be. Handing that to Inertia is what
 * produced `TypeError: Cannot read properties of null (reading 'component')` on
 * every 404, in the console of a page that otherwise rendered correctly.
 *
 * The second check keeps a project that mounts Inertia on a custom element id
 * booting: the mount point is what `@inertia` renders `data-page` onto, and
 * looking for the attribute anywhere is a more reliable signal than agreeing on
 * the id. A false negative here is silent and total - the whole app would fail
 * to mount - so the guard is written to err towards booting.
 */
export function hasInertiaPage(doc: Document = document, id = "app"): boolean {
    if (doc.getElementById(id)?.hasAttribute("data-page")) return true;

    return doc.querySelector("[data-page]") !== null;
}

/* eslint-disable @typescript-eslint/no-explicit-any */
function FoundationShell({ App, props }: { App: any; props: any }) {
    return (
        <ThemeProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange>
            <TooltipProvider>
                {/* Belt and braces: if Inertia is ever booted without a page,
                    the shell still renders instead of throwing on `.url`. */}
                <ErrorBoundary key={props?.initialPage?.url ?? "foundation"}>
                    <App {...props} />
                </ErrorBoundary>
                <InertiaFlashListener />
                <Toaster />
            </TooltipProvider>
        </ThemeProvider>
    );
}

export function createFoundationApp(config: FoundationAppConfig = {}): void {
    if (!hasInertiaPage()) return;

    const pageMap = buildPageMap(config);

    createInertiaApp({
        title: (title) => `${title} - ${appName}`,
        resolve: resolvePage(pageMap),
        setup({ el, App, props }) {
            const root = createRoot(el);
            root.render(<FoundationShell App={App} props={props} />);
        },
        progress: {
            color: "#4B5563",
        },
    });
}