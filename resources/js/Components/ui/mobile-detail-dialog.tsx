import { useState } from "react";
import { Button } from "@/Components/ui/button";
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogCancel,
} from "@/Components/ui/alert-dialog";
import { Badge } from "@/Components/ui/badge";
import { Tag } from "lucide-react";
import type { LucideIcon } from "lucide-react";

interface MobileDetailRow {
  icon: LucideIcon;
  label: string;
  value: React.ReactNode;
}

interface MobileDetailDialogProps {
  trigger?: React.ReactNode;
  icon: LucideIcon;
  title: string;
  description: string;
  rows: MobileDetailRow[];
  statusBadge?: {
    label: string;
    className?: string;
  };
}

const MobileDetailDialog = ({
  trigger,
  icon: Icon,
  title,
  description,
  rows,
  statusBadge,
}: MobileDetailDialogProps) => {
  const [dialogOpen, setDialogOpen] = useState(false);

  return (
    <>
      {trigger ? (
        <span className="inline-block" onClick={() => setDialogOpen(true)}>
          {trigger}
        </span>
      ) : (
        <Button
          variant="outline"
          size="sm"
          className="h-8 px-3 lg:hidden"
          onClick={() => setDialogOpen(true)}
        >
          More
        </Button>
      )}

      <AlertDialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <AlertDialogContent className="max-w-sm p-4">
          <AlertDialogHeader className="pb-2">
            <AlertDialogTitle className="flex items-center gap-2">
              <Icon className="h-5 w-5" />
              {title}
            </AlertDialogTitle>
            <AlertDialogDescription>{description}</AlertDialogDescription>
          </AlertDialogHeader>

          <div className="flex flex-col gap-3 overflow-y-auto max-h-[50vh] py-2 px-3">
            {statusBadge && (
              <div className="flex items-center gap-3">
                <Tag className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                <div className="flex flex-col">
                  <span className="text-xs text-muted-foreground">Status</span>
                  <Badge className={statusBadge.className}>{statusBadge.label}</Badge>
                </div>
              </div>
            )}

            {rows.map((row, index) => (
              <div key={index} className="flex items-start gap-3">
                <row.icon className="h-4 w-4 text-muted-foreground flex-shrink-0 mt-0.5" />
                <div className="flex flex-col">
                  <span className="text-xs text-muted-foreground">{row.label}</span>
                  <div className="text-sm">{row.value}</div>
                </div>
              </div>
            ))}
          </div>

          <AlertDialogFooter>
            <AlertDialogCancel size="sm" variant="outline">
              Close
            </AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
};

export { MobileDetailDialog };
