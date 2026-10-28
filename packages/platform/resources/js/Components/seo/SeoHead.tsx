import { Head, usePage } from "@inertiajs/react";

interface SeoHeadProps {
  title?: string;
  description?: string;
  ogImage?: string;
  ogImageAlt?: string;
  ogType?: "website" | "article";
  canonical?: string;
  noindex?: boolean;
  twitterCard?: "summary" | "summary_large_image";
  twitterSite?: string;
  articlePublishedTime?: string;
  articleModifiedTime?: string;
  articleAuthor?: string;
  articleSection?: string;
  articleTags?: string[];
}

export function SeoHead({
  title,
  description,
  ogImage,
  ogImageAlt,
  ogType = "website",
  canonical,
  noindex = false,
  twitterCard = "summary_large_image",
  twitterSite,
  articlePublishedTime,
  articleModifiedTime,
  articleAuthor,
  articleSection,
  articleTags,
}: SeoHeadProps) {
  const page = usePage();
  const pageProps = page.props as Record<string, unknown>;
  const siteSeo =
    (pageProps.seo as { title?: string; description?: string; og_image?: string }) || {};
  const appUrl = ((pageProps.app_url as string) || "").replace(/\/+$/, "");
  const brand = (pageProps.brand as { name?: string }) || {};

  const siteTitle = siteSeo.title || "";
  // Append the site title only when the caller's title is distinct AND does not
  // already carry it. Authored `meta_title`s commonly include the brand (e.g.
  // "Pricing | Acme"), so an unguarded append produced a double-branded title
  // ("Pricing | Acme — Acme").
  const fullTitle = title
    ? siteTitle && title !== siteTitle && !title.includes(siteTitle)
      ? `${title} — ${siteTitle}`
      : title
    : siteTitle;
  const metaDescription = description || siteSeo.description || "";
  const siteName = brand.name || siteTitle;

  // Resolve a possibly-relative URL against the configured app origin so OG
  // and canonical tags are always absolute (crawlers require absolute URLs).
  const absolute = (value?: string): string => {
    if (!value) {
      return "";
    }

    if (/^https?:\/\//i.test(value) || value.startsWith("//")) {
      return value;
    }

    if (!appUrl) {
      return value;
    }

    return `${appUrl}${value.startsWith("/") ? value : `/${value}`}`;
  };

  const ogImageUrl = absolute(ogImage || siteSeo.og_image || "");

  // Canonical defaults to the CURRENT page (path only), resolved against the
  // app origin — never the site root, which would collapse every page's
  // canonical onto "/".
  const currentPath = (page.url || "/").split(/[?#]/)[0] || "/";
  const resolvedCanonical = absolute(canonical || currentPath) || undefined;

  return (
    <Head title={fullTitle}>
      {metaDescription && <meta name="description" content={metaDescription} />}
      {noindex && <meta name="robots" content="noindex, follow" />}

      {/* Open Graph */}
      {ogImageUrl && <meta property="og:image" content={ogImageUrl} />}
      {ogImageUrl && ogImageAlt && <meta property="og:image:alt" content={ogImageAlt} />}
      <meta property="og:title" content={fullTitle} />
      {metaDescription && <meta property="og:description" content={metaDescription} />}
      <meta property="og:type" content={ogType} />
      <meta property="og:site_name" content={siteName} />
      {resolvedCanonical && <meta property="og:url" content={resolvedCanonical} />}

      {/* Article-specific OG */}
      {ogType === "article" && articlePublishedTime && (
        <meta property="article:published_time" content={articlePublishedTime} />
      )}
      {ogType === "article" && articleModifiedTime && (
        <meta property="article:modified_time" content={articleModifiedTime} />
      )}
      {ogType === "article" && articleAuthor && (
        <meta property="article:author" content={articleAuthor} />
      )}
      {ogType === "article" && articleSection && (
        <meta property="article:section" content={articleSection} />
      )}
      {ogType === "article" &&
        articleTags?.map((tag) => <meta key={tag} property="article:tag" content={tag} />)}

      {/* Twitter Card */}
      <meta name="twitter:card" content={twitterCard} />
      <meta name="twitter:title" content={fullTitle} />
      {metaDescription && <meta name="twitter:description" content={metaDescription} />}
      {ogImageUrl && <meta name="twitter:image" content={ogImageUrl} />}
      {twitterSite && <meta name="twitter:site" content={twitterSite} />}

      {/* Canonical */}
      {resolvedCanonical && <link rel="canonical" href={resolvedCanonical} />}
    </Head>
  );
}
