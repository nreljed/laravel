import { Head, usePage } from "@inertiajs/react";

interface SeoHeadProps {
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

export function SeoHead({
  title,
  description,
  ogImage,
  ogType = "website",
  canonical,
  articlePublishedTime,
  articleModifiedTime,
  articleAuthor,
  articleSection,
  articleTags,
  twitterCard = "summary_large_image",
}: SeoHeadProps) {
  const pageProps = usePage().props as Record<string, unknown>;
  const siteSeo =
    (pageProps.seo as { title?: string; description?: string; og_image?: string }) || {};
  const appUrl = (pageProps.app_url as string) || "";
  const brand = (pageProps.brand as { name?: string }) || {};

  const fullTitle = title
    ? `${title} — ${siteSeo.title || "Cost Breaker 360"}`
    : siteSeo.title || "Cost Breaker 360";
  const metaDescription = description || siteSeo.description || "";
  const ogImageUrl = ogImage || siteSeo.og_image || "";
  const siteName = brand.name || siteSeo.title || "Cost Breaker 360";
  const resolvedCanonical = canonical || appUrl || undefined;

  return (
    <Head title={fullTitle}>
      {metaDescription && <meta name="description" content={metaDescription} />}

      {/* Open Graph */}
      {ogImageUrl && <meta property="og:image" content={ogImageUrl} />}
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

      {/* Canonical */}
      {resolvedCanonical && <link rel="canonical" href={resolvedCanonical} />}
    </Head>
  );
}
