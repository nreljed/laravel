import { PropsWithChildren } from "react";
import { SiteHeader } from "@/Components/frontend/SiteHeader";
import { SiteFooter } from "@/Components/frontend/SiteFooter";
import { SeoHead } from "@/Components/seo/SeoHead";

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
    <div className="flex min-h-screen flex-col">
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
      <SiteHeader />
      <main className="flex-1">{children}</main>
      <SiteFooter />
    </div>
  );
}
