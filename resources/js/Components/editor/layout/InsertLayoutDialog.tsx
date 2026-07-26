import { useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { INSERT_LAYOUT_V2_COMMAND } from "./ColumnsPlugin";
import type { ResponsiveMode } from "./LayoutContainerNode";
import { RESPONSIVE_MODE_LABELS } from "./LayoutContainerNode";
import { Columns3, MonitorSmartphone, Smartphone, ScreenShare } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { Label } from "@/Components/ui/label";
import { cn } from "@/lib/utils";

const LAYOUTS = [
  {
    label: "2 columns (equal width)",
    value: "1fr 1fr",
  },
  {
    label: "2 columns (25% - 75%)",
    value: "1fr 3fr",
  },
  {
    label: "3 columns (equal width)",
    value: "1fr 1fr 1fr",
  },
  {
    label: "3 columns (25% - 50% - 25%)",
    value: "1fr 2fr 1fr",
  },
  {
    label: "4 columns (equal width)",
    value: "1fr 1fr 1fr 1fr",
  },
];

const RESPONSIVE_OPTIONS: {
  mode: ResponsiveMode;
  label: string;
  icon: typeof Smartphone;
  description: string;
}[] = [
  {
    mode: "full",
    label: RESPONSIVE_MODE_LABELS["full"],
    icon: MonitorSmartphone,
    description: "Tablet + mobile",
  },
  {
    mode: "mobile-only",
    label: RESPONSIVE_MODE_LABELS["mobile-only"],
    icon: Smartphone,
    description: "Mobile only",
  },
  {
    mode: "none",
    label: RESPONSIVE_MODE_LABELS["none"],
    icon: ScreenShare,
    description: "Keep columns",
  },
];

function LayoutPreview({ value }: { value: string }) {
  const fractions = value.split(" ").filter(Boolean);
  return (
    <div className="flex h-8 w-full gap-1 rounded border bg-muted/50 p-1">
      {fractions.map((f, i) => {
        const num = Number(f.replace("fr", ""));
        return <div key={`${f}-${i}`} className="rounded-sm bg-border" style={{ flex: num }} />;
      })}
    </div>
  );
}

interface InsertLayoutDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
}

export function InsertLayoutDialog({ open, onOpenChange }: InsertLayoutDialogProps) {
  const [editor] = useLexicalComposerContext();
  const [selectedLayout, setSelectedLayout] = useState<string>(LAYOUTS[0]?.value ?? "1fr 1fr");
  const [responsiveMode, setResponsiveMode] = useState<ResponsiveMode>("full");

  const handleInsert = () => {
    editor.dispatchCommand(INSERT_LAYOUT_V2_COMMAND, { template: selectedLayout, responsiveMode });
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Columns3 className="h-4 w-4" />
            Insert Columns Layout
          </DialogTitle>
          <DialogDescription>Choose a column layout to insert into the editor.</DialogDescription>
        </DialogHeader>
        <div className="grid gap-2 py-2">
          {LAYOUTS.map(({ label, value }) => (
            <button
              key={value}
              type="button"
              className={cn(
                "flex w-full flex-col gap-1.5 rounded-md border p-3 text-left text-sm transition-colors",
                selectedLayout === value ? "border-primary bg-primary/5" : "hover:bg-muted/50",
              )}
              onClick={() => setSelectedLayout(value)}
            >
              <span className="font-medium">{label}</span>
              <LayoutPreview value={value} />
            </button>
          ))}
        </div>
        <div className="space-y-2">
          <Label className="text-sm font-medium">Responsive behavior</Label>
          <div className="grid grid-cols-3 gap-1.5">
            {RESPONSIVE_OPTIONS.map(({ mode, label, icon: Icon, description }) => (
              <button
                key={mode}
                type="button"
                className={cn(
                  "flex flex-col items-center gap-1 rounded-md border p-2.5 text-xs transition-colors",
                  responsiveMode === mode
                    ? "border-primary bg-primary/5 text-primary"
                    : "hover:bg-muted/50",
                )}
                onClick={() => setResponsiveMode(mode)}
              >
                <Icon className="h-4 w-4 shrink-0" />
                <span className="font-medium leading-tight text-center">{label}</span>
                <span className="text-[10px] text-muted-foreground leading-tight text-center">
                  {description}
                </span>
              </button>
            ))}
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleInsert}>Insert</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
