import { type PropsWithChildren } from "react";
import { SeoHead } from "@nreljed/platform/Components/seo/SeoHead";
import { SiteRoot } from "../Components/site/SiteRoot";
import { SiteHeader } from "../Components/site/SiteHeader";
import { SiteFooter } from "../Components/site/SiteFooter";

interface PublicLayoutProps {
  title?: string;
  description?: string;
  ogImage?: string;
  ogType?: "website" | "article";
  canonical?: string;
  articlePublishedTime?: string;
  articleModifiedTime?: string;
  articleAuthor?: string;
  articleSection?: string;
  articleTags?: string[];
  twitterCard?: "summary" | "summary_large_image";
}

// Project-owned public layout for the marketing site. Mirrors the shared
// PublicLayout's props and structure (SeoHead + skip link + header + main +
// footer) but renders inside data-site-theme="canvasly" with the project's
// site-token chrome, so every interior page (About/Services/Contact/Blog)
// shares the Canvasly look without touching the platform mirror.
export default function PublicLayout({
  children,
  title,
  description,
  ogImage,
  ogType,
  canonical,
  articlePublishedTime,
  articleModifiedTime,
  articleAuthor,
  articleSection,
  articleTags,
  twitterCard,
}: PropsWithChildren<PublicLayoutProps>) {
  return (
    <SiteRoot>
      <SeoHead
        title={title}
        description={description}
        ogImage={ogImage}
        ogType={ogType}
        canonical={canonical}
        articlePublishedTime={articlePublishedTime}
        articleModifiedTime={articleModifiedTime}
        articleAuthor={articleAuthor}
        articleSection={articleSection}
        articleTags={articleTags}
        twitterCard={twitterCard}
      />
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-site-ink focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-site-bg-strong focus:shadow-lg"
      >
        Skip to content
      </a>
      <SiteHeader />
      <main id="main-content" className="flex-1">
        {children}
      </main>
      <SiteFooter />
    </SiteRoot>
  );
}
