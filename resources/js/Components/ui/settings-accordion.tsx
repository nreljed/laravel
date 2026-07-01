import { type LucideIcon } from "lucide-react";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/Components/ui/accordion";
import { cn } from "@/lib/utils";

interface SettingsAccordionItem {
  value: string;
  icon: LucideIcon;
  title: string;
  description: string;
  content: React.ReactNode;
  variant?: "default" | "destructive";
}

interface SettingsAccordionProps {
  items: SettingsAccordionItem[];
  defaultOpen?: boolean;
}

export function SettingsAccordion({ items, defaultOpen }: SettingsAccordionProps) {
  return (
    <Accordion type="single" collapsible defaultValue={defaultOpen ? items[0]?.value : undefined}>
      {items.map((item) => {
        const Icon = item.icon;
        return (
          <AccordionItem key={item.value} value={item.value}>
            <AccordionTrigger className="hover:no-underline hover:bg-muted/50 px-2 rounded-md [&[data-state=open]]:border-b [&[data-state=open]]:border-border [&[data-state=open]]:rounded-b-none [&[data-state=open]>div>svg]:!text-foreground">
              <div className="flex items-center gap-3 text-left">
                <div
                  className={cn(
                    "rounded-lg p-2",
                    item.variant === "destructive" ? "bg-destructive/10" : "bg-primary/10",
                  )}
                >
                  <Icon
                    className={cn(
                      "h-4 w-4",
                      item.variant === "destructive" ? "text-destructive" : "text-primary",
                    )}
                  />
                </div>
                <div>
                  <div
                    className={cn(
                      "font-medium",
                      item.variant === "destructive" && "text-destructive",
                    )}
                  >
                    {item.title}
                  </div>
                  <div className="text-xs text-muted-foreground font-normal">
                    {item.description}
                  </div>
                </div>
              </div>
            </AccordionTrigger>
            <AccordionContent>
              <div className="px-2 pt-2">{item.content}</div>
            </AccordionContent>
          </AccordionItem>
        );
      })}
    </Accordion>
  );
}
