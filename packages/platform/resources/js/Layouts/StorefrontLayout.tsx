import { PropsWithChildren, type ReactNode } from "react";
import { SiteHeader } from "../Components/frontend/SiteHeader";
import { SiteFooter } from "../Components/frontend/SiteFooter";

interface StorefrontLayoutProps {
    /**
     * Optional header block. When omitted the neutral SiteHeader is used; a
     * store module (or a white-label app) can replace it with its own chrome
     * (e.g. a currency topbar + store header) while keeping the shared shell.
     */
    header?: ReactNode;
}

export default function StorefrontLayout({ children, header }: PropsWithChildren<StorefrontLayoutProps>) {
    return (
        <div className="flex min-h-screen flex-col">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:shadow-lg"
            >
                Skip to content
            </a>
            {header ?? <SiteHeader />}
            <main id="main-content" className="flex-1">
                {children}
            </main>
            <SiteFooter />
        </div>
    );
}