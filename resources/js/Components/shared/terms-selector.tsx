import { useState, useMemo, useCallback } from "react";
import { Plus, X, ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { ErrorMessage } from "@/Components/error-message";
import { Checkbox } from "@/Components/ui/checkbox";
import { ScrollArea } from "@/Components/ui/scroll-area";
import { Popover, PopoverTrigger, PopoverContent } from "@/Components/ui/popover";

interface TermOption {
  id: number | string;
  name: string;
  parent?: number | string | null;
  count?: number;
  slug?: string;
}

interface TermsSelectorProps {
  label?: string;
  description?: string;
  options?: TermOption[];
  value?: (number | string)[];
  onChange?: (_value: (number | string)[]) => void;
  canAdd?: boolean;
  placeholder?: string;
  error?: string;
  disabled?: boolean;
  taxonomyName?: string;
  onCreateTerm?: (_name: string) => Promise<TermOption | null>;
}

export function TermsSelector({
  label,
  description,
  options = [],
  value = [],
  onChange,
  canAdd = true,
  placeholder = "Select terms...",
  error,
  disabled = false,
  taxonomyName = "term",
  onCreateTerm,
}: TermsSelectorProps) {
  const [open, setOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [dialogOpen, setDialogOpen] = useState(false);
  const [newTermName, setNewTermName] = useState("");
  const [addingTerm, setAddingTerm] = useState(false);

  const selectedOptions = useMemo(() => {
    return options.filter((opt) => value.includes(Number(opt.id)));
  }, [options, value]);

  const handleToggle = useCallback(
    (termId: number | string) => {
      const numericId = Number(termId);
      const newValue = value.includes(numericId)
        ? value.filter((id) => id !== numericId)
        : [...value, numericId];
      onChange?.(newValue);
    },
    [value, onChange],
  );

  const handleRemove = useCallback(
    (termId: number | string, e?: React.MouseEvent) => {
      e?.stopPropagation();
      onChange?.(value.filter((id) => id !== Number(termId)));
    },
    [value, onChange],
  );

  const handleAddNewTerm = useCallback(() => {
    const searchTerm = searchQuery.trim();
    if (searchTerm) {
      const exists = options.some((opt) => opt.name.toLowerCase() === searchTerm.toLowerCase());
      if (!exists) {
        setNewTermName(searchTerm);
      }
    }
    setDialogOpen(true);
  }, [searchQuery, options]);

  const handleCreateTerm = useCallback(async () => {
    if (!newTermName.trim() || !onCreateTerm) return;
    setAddingTerm(true);
    try {
      const newTerm = await onCreateTerm(newTermName.trim());
      if (newTerm) {
        onChange?.([...value, Number(newTerm.id)]);
      }
      setNewTermName("");
      setDialogOpen(false);
    } catch {
    } finally {
      setAddingTerm(false);
    }
  }, [newTermName, onCreateTerm, onChange, value]);

  const filteredOptions = useMemo(() => {
    if (!searchQuery.trim()) return options;
    const search = searchQuery.toLowerCase();
    return options.filter((opt) => opt.name.toLowerCase().includes(search));
  }, [options, searchQuery]);

  const showCreateButton =
    searchQuery.trim() &&
    canAdd &&
    onCreateTerm &&
    options.every((opt) => opt.name.toLowerCase() !== searchQuery.trim().toLowerCase());

  return (
    <div className="space-y-2">
      {label && <Label className="text-sm font-medium">{label}</Label>}
      {description && <p className="text-sm text-muted-foreground">{description}</p>}

      <Popover
        open={open}
        onOpenChange={(newOpen) => {
          setOpen(newOpen);
          if (!newOpen) setSearchQuery("");
        }}
      >
        <PopoverTrigger asChild>
          <button
            type="button"
            disabled={disabled}
            className={cn(
              "w-full flex items-center justify-between gap-2 px-3 py-2 text-sm border rounded-md bg-transparent",
              "hover:bg-accent hover:text-accent-foreground",
              "focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
              "disabled:cursor-not-allowed disabled:opacity-50",
              "cursor-pointer",
            )}
          >
            <span
              className={cn(selectedOptions.length === 0 && "text-muted-foreground", "truncate")}
            >
              {selectedOptions.length > 0
                ? `${selectedOptions.length} ${taxonomyName}${selectedOptions.length !== 1 ? "s" : ""} selected`
                : placeholder}
            </span>
            <ChevronDown
              className={cn(
                "h-4 w-4 text-muted-foreground shrink-0 transition-transform",
                open && "rotate-180",
              )}
            />
          </button>
        </PopoverTrigger>

        <PopoverContent
          className="p-0"
          align="start"
          side="bottom"
          sideOffset={4}
          style={{ width: "var(--radix-popover-trigger-width)" }}
        >
          <div className="p-2 border-b">
            <div className="flex gap-2">
              <Input
                placeholder="Search..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="h-8 flex-1 min-w-0"
                autoFocus
              />
              {canAdd && onCreateTerm && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-8 w-8 p-0 shrink-0"
                  onClick={handleAddNewTerm}
                  title={`Add new ${taxonomyName.toLowerCase()}`}
                >
                  <Plus className="h-3.5 w-3.5" />
                </Button>
              )}
            </div>
          </div>

          <ScrollArea className="max-h-[200px]">
            <div className="p-1">
              {filteredOptions.length > 0 ? (
                <div className="space-y-0.5">
                  {filteredOptions.map((term) => {
                    const isSelected = value.includes(Number(term.id));
                    return (
                      <label
                        key={term.id}
                        className={cn(
                          "flex items-center gap-2 px-2 py-1.5 rounded-sm cursor-pointer text-sm",
                          "hover:bg-accent hover:text-accent-foreground",
                          isSelected && "bg-accent/50",
                        )}
                      >
                        <Checkbox
                          checked={isSelected}
                          onCheckedChange={() => handleToggle(term.id)}
                          className="shrink-0"
                        />
                        <span className="flex-1 min-w-0 truncate">{term.name}</span>
                      </label>
                    );
                  })}
                </div>
              ) : searchQuery.trim() ? (
                <div className="p-4 text-center text-sm text-muted-foreground">
                  No results for "{searchQuery}"
                </div>
              ) : (
                <div className="p-4 text-center text-sm text-muted-foreground">
                  No {taxonomyName.toLowerCase()}s available
                </div>
              )}
            </div>
          </ScrollArea>

          {showCreateButton && (
            <div className="p-2 border-t border-border">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="w-full gap-1.5 text-sm"
                onClick={handleAddNewTerm}
              >
                <Plus className="h-3.5 w-3.5" />
                Create "{searchQuery.trim()}"
              </Button>
            </div>
          )}

          {selectedOptions.length > 0 && !showCreateButton && (
            <div className="p-1.5 border-t border-border">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="w-full text-destructive hover:text-destructive/80 hover:bg-destructive/10 text-xs h-7"
                onClick={() => onChange?.([])}
              >
                Clear all
              </Button>
            </div>
          )}
        </PopoverContent>
      </Popover>

      {selectedOptions.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selectedOptions.map((term) => (
            <div
              key={term.id}
              className="inline-flex items-center gap-1 h-6 px-2 rounded-md bg-muted text-xs"
            >
              <span className="max-w-[120px] truncate">{term.name}</span>
              <button
                type="button"
                className="h-3.5 w-3.5 inline-flex items-center justify-center rounded-full hover:bg-muted-foreground/20 shrink-0"
                onClick={(e) => handleRemove(term.id, e)}
                disabled={disabled}
              >
                <X className="h-2.5 w-2.5" />
              </button>
            </div>
          ))}
        </div>
      )}

      {error && <ErrorMessage error={error} />}

      {dialogOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
          <div className="fixed inset-0 bg-black/50" onClick={() => setDialogOpen(false)} />
          <div className="relative bg-background rounded-lg shadow-lg p-6 w-full max-w-md mx-4">
            <h3 className="text-lg font-semibold mb-2">Add New {taxonomyName}</h3>
            <p className="text-sm text-muted-foreground mb-4">
              Create a new {taxonomyName.toLowerCase()} for your items.
            </p>
            <Label htmlFor="new-term-name" className="text-sm font-medium">
              {taxonomyName} Name
            </Label>
            <Input
              id="new-term-name"
              value={newTermName || searchQuery}
              onChange={(e) => setNewTermName(e.target.value)}
              placeholder={`Enter ${taxonomyName.toLowerCase()} name`}
              className="mt-2"
              onKeyDown={(e) => {
                if (e.key === "Enter") handleCreateTerm();
              }}
              autoFocus
            />
            <div className="flex justify-end gap-2 mt-4">
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Cancel
              </Button>
              <Button
                type="button"
                onClick={handleCreateTerm}
                disabled={!newTermName.trim() || addingTerm}
              >
                {addingTerm ? "Adding..." : "Add"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
