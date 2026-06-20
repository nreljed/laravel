import * as React from "react";
import { Link } from "@inertiajs/react";
import { FieldError } from "@/Components/ui/field";
import { AlertDescription } from "@/Components/ui/alert";
import { cn } from "@/lib/utils";

export function ErrorMessage({
  error,
  variant = "field",
  className,
  children,
}: {
  error?: string;
  variant?: "field" | "alert" | "inline";
  className?: string;
  children?: React.ReactNode;
}) {
  if (!error && !children) return null;

  if (children) {
    if (variant === "alert") {
      return <AlertDescription className={className}>{children}</AlertDescription>;
    }
    return (
      <FieldError className={className} errors={undefined}>
        {children}
      </FieldError>
    );
  }

  const content = parseErrorContent(error);

  if (variant === "alert") {
    return (
      <AlertDescription className={cn("flex flex-wrap items-center gap-1", className)}>
        {content}
      </AlertDescription>
    );
  }

  return (
    <FieldError className={className} errors={undefined}>
      {content}
    </FieldError>
  );
}

function parseErrorContent(error: string | undefined) {
  if (!error || typeof error !== "string") {
    return error;
  }

  const linkPattern = /<a\s+href=["'"]([^"']+)["'"][^>]*>([^<]+)<\/a>/gi;
  const linkMatch = linkPattern.exec(error);

  if (linkMatch) {
    const parts = error.split(linkPattern);
    const links: Array<{ href: string; text: string }> = [];
    let match: RegExpExecArray | null;

    while ((match = linkPattern.exec(error)) !== null) {
      links.push({ href: match[1]!, text: match[2]! });
    }

    const elements: React.ReactNode[] = [];
    let linkIndex = 0;

    for (const part of parts) {
      if (part) {
        elements.push(parseTextWithBold(part));
      }

      const link = links[linkIndex];
      if (link) {
        elements.push(
          <Link key={`link-${linkIndex}`} href={link.href} className="text-primary hover:underline">
            {link.text}
          </Link>,
        );
        linkIndex++;
      }
    }

    return elements;
  }

  return parseTextWithBold(error);
}

function parseTextWithBold(text: string): React.ReactNode | React.ReactNode[] {
  if (!text.includes("<strong>")) {
    return text;
  }

  const parts = text.split(/(<strong>|<\/strong>)/gi);
  const elements: React.ReactNode[] = [];
  let partIndex = 0;

  for (const part of parts) {
    if (part.toLowerCase() === "<strong>" || part.toLowerCase() === "</strong>") {
      continue;
    }

    if (part) {
      const inlineLinkPattern = /<a\s+href=["'"]([^"']+)["'"][^>]*>([^<]+)<\/a>/gi;
      let lastIndex = 0;
      const segmentElements = [];
      let inlineMatch;

      inlineLinkPattern.lastIndex = 0;

      while ((inlineMatch = inlineLinkPattern.exec(part)) !== null) {
        if (inlineMatch.index > lastIndex) {
          segmentElements.push(part.slice(lastIndex, inlineMatch.index));
        }
        segmentElements.push(
          <Link
            key={`inline-${partIndex}-${lastIndex}`}
            href={inlineMatch[1]}
            className="text-primary hover:underline"
          >
            {inlineMatch[2]}
          </Link>,
        );
        lastIndex = inlineMatch.index + inlineMatch[0].length;
      }

      if (lastIndex < part.length) {
        segmentElements.push(part.slice(lastIndex));
      }

      elements.push(
        <span key={partIndex}>{segmentElements.length > 0 ? segmentElements : part}</span>,
      );
      partIndex++;
    }
  }

  return elements;
}

export default ErrorMessage;
