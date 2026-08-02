import { useState } from "react";
import { Badge } from "@/Components/ui/badge";
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogCancel,
} from "@/Components/ui/alert-dialog";

interface TermsBadgeProps {
  terms?: Array<{ id?: number | string; name: string }>;
  type?: string;
  maxVisible?: number;
  badgeVariant?: "default" | "secondary" | "destructive" | "outline";
}

const TermsBadge = ({
  terms = [],
  type = "category",
  maxVisible = 1,
  badgeVariant = "secondary",
}: TermsBadgeProps) => {
  const [dialogOpen, setDialogOpen] = useState(false);

  if (!terms || !Array.isArray(terms) || terms.length === 0) {
    return <span className="text-muted-foreground">-</span>;
  }

  if (terms.length === 1) {
    const termName = terms[0]?.name;
    return (
      <Badge variant={badgeVariant} className="mr-1 mb-1">
        {termName}
      </Badge>
    );
  }

  const visibleTerms = terms.slice(0, maxVisible);
  const remainingCount = terms.length - maxVisible;

  return (
    <div className="flex flex-wrap items-center gap-1">
      {visibleTerms.map((term, index) => (
        <Badge key={term.id || index} variant={badgeVariant} className="mr-1 mb-1">
          {term.name}
        </Badge>
      ))}

      {remainingCount > 0 && (
        <>
          <Badge
            variant="outline"
            className="mr-1 mb-1 cursor-pointer hover:bg-accent hover:text-accent-foreground transition-colors"
            onClick={() => setDialogOpen(true)}
          >
            +{remainingCount} more
          </Badge>

          <AlertDialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <AlertDialogContent className="max-w-md max-h-[80vh] overflow-hidden flex flex-col dark:bg-card dark:border-border">
              <AlertDialogHeader>
                <AlertDialogTitle className="flex items-center gap-2 dark:text-foreground capitalize">
                  {type === "category" ? "Categories" : "Tags"} ({terms.length})
                </AlertDialogTitle>
                <AlertDialogDescription className="dark:text-foreground/80">
                  All {type === "category" ? "categories" : "tags"} associated with this post.
                </AlertDialogDescription>
              </AlertDialogHeader>

              <div className="flex flex-wrap gap-2 overflow-y-auto max-h-[40vh] py-2 px-1">
                {terms.map((term, index) => (
                  <Badge key={term.id || index} variant={badgeVariant} className="cursor-default">
                    {term.name}
                  </Badge>
                ))}
              </div>

              <AlertDialogFooter>
                <AlertDialogCancel>Close</AlertDialogCancel>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </>
      )}
    </div>
  );
};

export { TermsBadge };
