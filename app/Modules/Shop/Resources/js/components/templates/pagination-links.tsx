import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/Components/ui/pagination";

interface Link {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginationLinksProps {
  links: Link[];
  preserveState?: boolean;
}

export default function PaginationLinks({ links, preserveState }: PaginationLinksProps) {
  if (links.length === 0) return null;

  return (
    <Pagination>
      <PaginationContent>
        {links.map((link, i) => {
          if (link.label.includes("Previous")) {
            return (
              <PaginationItem key={i}>
                <PaginationPrevious
                  href={link.url ?? "#"}
                  {...(preserveState ? { "data-preserve-state": "true" } : {})}
                />
              </PaginationItem>
            );
          }
          if (link.label.includes("Next")) {
            return (
              <PaginationItem key={i}>
                <PaginationNext
                  href={link.url ?? "#"}
                  {...(preserveState ? { "data-preserve-state": "true" } : {})}
                />
              </PaginationItem>
            );
          }
          return (
            <PaginationItem key={i}>
              {link.url ? (
                <PaginationLink
                  href={link.url}
                  isActive={link.active}
                  {...(preserveState ? { "data-preserve-state": "true" } : {})}
                >
                  {link.label}
                </PaginationLink>
              ) : (
                <span className="px-3 py-1 text-muted-foreground">{link.label}</span>
              )}
            </PaginationItem>
          );
        })}
      </PaginationContent>
    </Pagination>
  );
}
