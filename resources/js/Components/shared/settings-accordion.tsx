import * as React from "react";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/Components/ui/accordion";
import { cn } from "@/lib/utils";

function getStorageKey(value: string) {
  if (typeof window === "undefined") return null;
  return `settings-accordion:${location.pathname}:${value}`;
}

interface SettingsAccordionItemProps {
  value: string;
  icon?: React.ComponentType<{ className?: string }>;
  title: string;
  description?: string;
  children: React.ReactNode;
  variant?: "default" | "destructive";
  className?: string;
  defaultOpen?: boolean;
  isOpen?: boolean;
  onOpenChange?: (_open: boolean) => void;
  persist?: boolean;
}

function SettingsAccordionItem({
  value,
  icon: Icon,
  title,
  description,
  children,
  variant = "default",
  className,
  defaultOpen = false,
  isOpen: controlledIsOpen,
  onOpenChange,
  persist = false,
}: SettingsAccordionItemProps) {
  const isDestructive = variant === "destructive";
  const controlled = controlledIsOpen !== undefined;
  const storageKey = persist ? getStorageKey(value) : null;

  const getInitialState = () => {
    if (controlled) return controlledIsOpen;
    if (storageKey) {
      try {
        const saved = localStorage.getItem(storageKey);
        if (saved !== null) return saved === "true";
      } catch {
        //
      }
    }
    return defaultOpen;
  };

  const [internalOpen, setInternalOpen] = React.useState(getInitialState);
  const open = controlled ? controlledIsOpen : internalOpen;

  return (
    <div
      className={cn(
        "rounded-xl border bg-card",
        isDestructive && "border-destructive/30 bg-destructive/5",
        className,
      )}
    >
      <Accordion
        className="w-full"
        value={open ? [value] : []}
        onValueChange={(newValue: string[]) => {
          const newOpen = newValue.includes(value);
          if (!controlled) {
            setInternalOpen(newOpen);
            if (storageKey) {
              try {
                localStorage.setItem(storageKey, String(newOpen));
              } catch {
                //
              }
            }
          }
          onOpenChange?.(newOpen);
        }}
      >
        <AccordionItem value={value} className="border-0 m-0">
          <AccordionTrigger
            className={cn(
              "hover:no-underline transition-colors px-4 py-3",
              isDestructive ? "hover:bg-destructive/10" : "hover:bg-muted/50",
            )}
          >
            <div className="flex items-start gap-3 min-w-0 flex-1">
              {Icon && (
                <Icon
                  className={cn(
                    "h-5 w-5 shrink-0 mt-px",
                    isDestructive ? "text-destructive" : "text-muted-foreground",
                  )}
                />
              )}
              <div className="text-left min-w-0 flex-1">
                <span
                  className={cn(
                    "font-medium block truncate",
                    isDestructive ? "text-destructive" : "text-foreground",
                  )}
                >
                  {title}
                </span>
                {description && (
                  <p className="text-sm text-muted-foreground font-normal line-clamp-2">
                    {description}
                  </p>
                )}
              </div>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <div className="space-y-4 pt-4 pb-3 px-4 border-t border-border">{children}</div>
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </div>
  );
}

interface SettingsAccordionItemConfig {
  value: string;
  icon?: React.ComponentType<{ className?: string }>;
  title: string;
  description?: string;
  content: React.ReactNode;
  variant?: "default" | "destructive";
  className?: string;
  defaultOpen?: boolean;
  isOpen?: boolean;
  onOpenChange?: (_open: boolean) => void;
  persist?: boolean;
}

function SettingsAccordion({
  items = [],
  className,
  defaultOpen = false,
  persist = false,
}: {
  items?: SettingsAccordionItemConfig[];
  className?: string;
  defaultOpen?: boolean;
  persist?: boolean;
}) {
  if (items.length === 0) return null;

  return (
    <div className={cn("space-y-6", className)}>
      {items.map((item) => (
        <SettingsAccordionItem
          key={item.value}
          value={item.value}
          icon={item.icon}
          title={item.title}
          description={item.description}
          variant={item.variant}
          className={item.className}
          defaultOpen={item.defaultOpen ?? defaultOpen}
          isOpen={item.isOpen}
          onOpenChange={item.onOpenChange}
          persist={item.persist ?? persist}
        >
          {item.content}
        </SettingsAccordionItem>
      ))}
    </div>
  );
}

export { SettingsAccordion, SettingsAccordionItem };
