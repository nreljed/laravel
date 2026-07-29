import * as React from "react";
import { Plus, X, Pencil, ChevronDown } from "lucide-react";

import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Checkbox } from "@/Components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { ScrollArea } from "@/Components/ui/scroll-area";
import { Separator } from "@/Components/ui/separator";

interface TermOption {
  id: number | string;
  name: string;
  parent?: number | string | null;
  count?: number;
  slug?: string;
}

interface NewTermResult {
  id: number | string;
  term?: Record<string, unknown>;
}

interface TermGroup {
  root: TermOption;
  children: TermOption[];
  hasChildren: boolean;
}

interface TermsSelectorProps {
  label?: string;
  description?: string;
  options?: TermOption[];
  value?: (number | string)[];
  onChange?: (_value: (number | string)[]) => void;
  onAddTerm?: (_name: string) => Promise<NewTermResult>;
  onUpdateTerm?: (_id: number | string, _name: string) => Promise<unknown>;
  canAdd?: boolean;
  canEdit?: boolean;
  placeholder?: string;
  error?: string;
  disabled?: boolean;
  taxonomyName?: string;
  hierarchical?: boolean;
}

function TermsSelector({
  label,
  description,
  options = [],
  value = [],
  onChange,
  onAddTerm,
  onUpdateTerm,
  canAdd = true,
  canEdit = false,
  placeholder = "Select terms...",
  error: _error,
  disabled = false,
  taxonomyName = "term",
  hierarchical = false,
}: TermsSelectorProps) {
  const [open, setOpen] = React.useState(false);
  const [searchQuery, setSearchQuery] = React.useState("");
  const [dialogOpen, setDialogOpen] = React.useState(false);
  const [editingTerm, setEditingTerm] = React.useState<TermOption | null>(null);
  const [newTermName, setNewTermName] = React.useState("");
  const [editTermName, setEditTermName] = React.useState("");
  const [addingTerm, setAddingTerm] = React.useState(false);
  const containerRef = React.useRef<HTMLDivElement | null>(null);

  const optionsMap = React.useMemo(() => {
    const map: Record<number, TermOption> = {};
    for (const opt of options) {
      map[Number(opt.id)] = opt;
    }
    return map;
  }, [options]);

  const getAncestors = React.useCallback(
    (termId: number | string): number[] => {
      const ancestors: number[] = [];
      let current = optionsMap[Number(termId)];
      while (current && current.parent && current.parent !== 0) {
        ancestors.push(Number(current.parent));
        current = optionsMap[Number(current.parent)];
      }
      return ancestors;
    },
    [optionsMap],
  );

  const getDescendants = React.useCallback(
    (termId: number | string): number[] => {
      const descendants: number[] = [];
      const findChildren = (parentId: number | string) => {
        for (const opt of options) {
          if (Number(opt.parent) !== Number(parentId)) {
            continue;
          }
          descendants.push(Number(opt.id));
          findChildren(opt.id);
        }
      };
      findChildren(termId);
      return descendants;
    },
    [options],
  );

  const handleToggle = React.useCallback(
    (termId: number | string) => {
      const numericId = Number(termId);
      const currentTerm = optionsMap[numericId];
      if (!currentTerm) return;

      const hasChildren = options.some((opt) => Number(opt.parent) === numericId);
      const isParentWithChildren = hierarchical && hasChildren;

      let newValue;
      if (value.includes(numericId)) {
        newValue = value.filter((id) => id !== numericId);
        if (hierarchical && isParentWithChildren) {
          const descendants = getDescendants(numericId);
          newValue = newValue.filter((id) => !descendants.includes(Number(id)));
        }
      } else {
        newValue = [...value, numericId];
        if (hierarchical && !isParentWithChildren && currentTerm.parent) {
          const ancestors = getAncestors(numericId);
          for (const ancestorId of ancestors) {
            if (!newValue.includes(ancestorId)) {
              newValue.push(ancestorId);
            }
          }
        }
      }
      onChange?.(newValue);
    },
    [value, onChange, optionsMap, hierarchical, getAncestors, getDescendants, options],
  );

  const handleRemove = React.useCallback(
    (termId: number | string, e?: React.MouseEvent) => {
      e?.stopPropagation();
      const numericId = Number(termId);
      const hasChildren = options.some((opt) => Number(opt.parent) === numericId);
      const isParentWithChildren = hierarchical && hasChildren;

      let newValue = value.filter((id) => id !== numericId);
      if (hierarchical && isParentWithChildren) {
        const descendants = getDescendants(numericId);
        newValue = newValue.filter((id) => !descendants.includes(Number(id)));
      }
      onChange?.(newValue);
    },
    [value, onChange, hierarchical, getDescendants, options],
  );

  const handleAddTerm = React.useCallback(async () => {
    if (!onAddTerm || !newTermName.trim()) return;
    setAddingTerm(true);
    try {
      const newTerm = await onAddTerm(newTermName.trim());
      if (newTerm && newTerm.id) {
        onChange?.([...value, Number(newTerm.id)]);
      }
      setNewTermName("");
      setDialogOpen(false);
    } catch {
      // Error handled by parent
    } finally {
      setAddingTerm(false);
    }
  }, [newTermName, onAddTerm, onChange, value]);

  const handleEditTerm = React.useCallback(async () => {
    if (!onUpdateTerm || !editingTerm || !editTermName.trim()) return;
    try {
      await onUpdateTerm(editingTerm.id, editTermName.trim());
      setEditingTerm(null);
      setEditTermName("");
    } catch {
      // Error handled by parent
    }
  }, [editTermName, editingTerm, onUpdateTerm]);

  const selectedOptions = React.useMemo(() => {
    return options.filter((opt) => value.includes(Number(opt.id)));
  }, [options, value]);

  const openEditDialog = React.useCallback((term: TermOption, e?: React.MouseEvent) => {
    e?.stopPropagation();
    setEditingTerm(term);
    setEditTermName(term.name);
  }, []);

  const handleAddNewTerm = React.useCallback(() => {
    const searchTerm = searchQuery.trim();
    if (searchTerm) {
      const exists = options.some((opt) => opt.name.toLowerCase() === searchTerm.toLowerCase());
      if (!exists) {
        setNewTermName(searchTerm);
        setDialogOpen(true);
      }
    } else {
      setDialogOpen(true);
    }
  }, [searchQuery, options]);

  const filteredOptions = React.useMemo(() => {
    if (!searchQuery.trim()) return options;
    const search = searchQuery.toLowerCase();
    return options.filter((opt) => opt.name.toLowerCase().includes(search));
  }, [options, searchQuery]);

  const buildGroups = React.useCallback((opts: TermOption[]): TermGroup[] => {
    const groups: TermGroup[] = [];
    const rootTerms = opts.filter((opt) => Number(opt.parent) === 0 || !opt.parent);

    for (const rootTerm of rootTerms) {
      const children = opts.filter((opt) => Number(opt.parent) === Number(rootTerm.id));
      const hasChildren = children.length > 0;
      groups.push({
        root: rootTerm,
        children: children.toSorted((a, b) => a.name.localeCompare(b.name)),
        hasChildren,
      });
    }
    return groups.toSorted((a, b) => a.root.name.localeCompare(b.root.name));
  }, []);

  const groups = React.useMemo(() => buildGroups(filteredOptions), [filteredOptions, buildGroups]);

  React.useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (!containerRef.current || containerRef.current.contains(event.target as Node)) {
        return;
      }
      setOpen(false);
      setSearchQuery("");
    };
    if (open) {
      document.addEventListener("mousedown", handleClickOutside);
    }
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [open]);

  return (
    <div className="space-y-2">
      {label && <Label className="text-sm font-medium dark:text-foreground">{label}</Label>}
      {description && <p className="text-sm text-muted-foreground">{description}</p>}

      <div ref={containerRef} className="relative">
        <button
          type="button"
          onClick={() => !disabled && setOpen(!open)}
          disabled={disabled}
          className={cn(
            "w-full flex items-center justify-between gap-2 px-3 py-2 text-sm border rounded-md bg-transparent",
            "hover:bg-accent hover:text-accent-foreground",
            "focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
            "disabled:cursor-not-allowed disabled:opacity-50",
            "dark:bg-input/30 dark:border-input dark:text-foreground dark:hover:bg-accent dark:hover:text-accent-foreground",
          )}
        >
          <span className={cn(selectedOptions.length === 0 && "text-muted-foreground")}>
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

        {open && (
          <div className="absolute z-50 w-full mt-1 border rounded-md bg-popover shadow-md dark:bg-popover dark:border-input">
            <div className="p-2 border-b dark:border-input">
              <div className="flex gap-2">
                <Input
                  type="text"
                  placeholder="Search..."
                  value={searchQuery}
                  onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                    setSearchQuery(e.target.value)
                  }
                  className="h-8 dark:text-foreground"
                  autoFocus
                />
                {canAdd && onAddTerm && (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-8 gap-1 shrink-0"
                    onClick={handleAddNewTerm}
                  >
                    <Plus className="h-3.5 w-3.5" />
                  </Button>
                )}
              </div>
            </div>

            <ScrollArea className="h-[250px]">
              <div className="p-1">
                {groups.length > 0 ? (
                  <div className="space-y-1">
                    {groups.map((group, groupIndex) => {
                      const isParent = hierarchical && group.hasChildren;

                      if (isParent) {
                        return (
                          <React.Fragment key={group.root.id}>
                            <div className="px-2 py-1.5">
                              <span className="text-xs font-medium text-muted-foreground dark:text-muted-foreground">
                                {group.root.name}
                              </span>
                            </div>
                            {group.children.map((child) => (
                              <label
                                key={child.id}
                                className={cn(
                                  "flex items-center gap-2 p-2 rounded-sm cursor-pointer",
                                  "hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent dark:hover:text-accent-foreground",
                                )}
                              >
                                <Checkbox
                                  checked={value.includes(Number(child.id))}
                                  onCheckedChange={() => handleToggle(child.id)}
                                  className="dark:border-input dark:bg-input/30"
                                />
                                <span className="text-sm flex-1 dark:text-foreground">
                                  {child.name}
                                </span>
                                {canEdit && onUpdateTerm && (
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    className="h-5 w-5 p-0 hover:bg-transparent dark:hover:bg-transparent"
                                    onClick={(e: React.MouseEvent) => openEditDialog(child, e)}
                                  >
                                    <Pencil className="h-3 w-3" />
                                  </Button>
                                )}
                              </label>
                            ))}
                            {groupIndex < groups.length - 1 && <Separator className="my-1" />}
                          </React.Fragment>
                        );
                      }

                      return (
                        <React.Fragment key={group.root.id}>
                          <label
                            className={cn(
                              "flex items-center gap-2 p-2 rounded-sm cursor-pointer",
                              "hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent dark:hover:text-accent-foreground",
                            )}
                          >
                            <Checkbox
                              checked={value.includes(Number(group.root.id))}
                              onCheckedChange={() => handleToggle(group.root.id)}
                              className="dark:border-input dark:bg-input/30"
                            />
                            <span className="text-sm flex-1 dark:text-foreground">
                              {group.root.name}
                            </span>
                            {canEdit && onUpdateTerm && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon-xs"
                                className="h-5 w-5 p-0 hover:bg-transparent dark:hover:bg-transparent"
                                onClick={(e: React.MouseEvent) => openEditDialog(group.root, e)}
                              >
                                <Pencil className="h-3 w-3" />
                              </Button>
                            )}
                          </label>
                          {groupIndex < groups.length - 1 && <Separator className="my-1" />}
                        </React.Fragment>
                      );
                    })}
                  </div>
                ) : searchQuery.trim() ? (
                  <div className="p-3 text-center text-sm text-muted-foreground">
                    No results for "{searchQuery}"
                    {canAdd && onAddTerm && (
                      <Button
                        type="button"
                        variant="link"
                        size="sm"
                        className="block mx-auto mt-1 h-auto p-0"
                        onClick={handleAddNewTerm}
                      >
                        <Plus className="h-3 w-3 mr-1" />
                        Add new
                      </Button>
                    )}
                  </div>
                ) : (
                  <div className="p-3 text-center text-sm text-muted-foreground">
                    No {taxonomyName.toLowerCase()}s available
                  </div>
                )}
              </div>
            </ScrollArea>

            {selectedOptions.length > 0 && (
              <div className="p-2 border-t dark:border-input">
                <Button
                  type="button"
                  variant="link"
                  size="sm"
                  className="text-destructive hover:text-destructive/80 dark:text-destructive p-0 h-auto"
                  onClick={() => onChange?.([])}
                >
                  Clear all
                </Button>
              </div>
            )}
          </div>
        )}
      </div>

      {selectedOptions.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selectedOptions.map((term) => (
            <div
              key={term.id}
              className="flex items-center gap-1 h-7 px-2 rounded-md bg-muted text-sm dark:bg-muted dark:text-foreground"
            >
              <span>{term.name}</span>
              {canEdit && onUpdateTerm && (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  className="h-4 w-4 p-0 hover:bg-transparent dark:text-foreground dark:hover:bg-transparent"
                  onClick={(e: React.MouseEvent) => openEditDialog(term, e)}
                  disabled={disabled}
                >
                  <Pencil className="h-3 w-3" />
                </Button>
              )}
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                className="h-4 w-4 p-0 hover:bg-transparent"
                onClick={(e: React.MouseEvent) => handleRemove(term.id, e)}
                disabled={disabled}
              >
                <X className="h-3 w-3" />
              </Button>
            </div>
          ))}
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Add New {taxonomyName}</DialogTitle>
            <DialogDescription>
              Create a new {taxonomyName.toLowerCase()} for your items.
            </DialogDescription>
          </DialogHeader>
          <div className="py-4">
            <Label htmlFor="new-term-name" className="text-sm font-medium dark:text-foreground">
              {taxonomyName} Name
            </Label>
            <Input
              id="new-term-name"
              value={newTermName}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewTermName(e.target.value)}
              placeholder={`Enter ${taxonomyName.toLowerCase()} name`}
              className="mt-2 dark:text-foreground"
              onKeyDown={(e: React.KeyboardEvent<HTMLInputElement>) => {
                if (e.key === "Enter") {
                  handleAddTerm();
                }
              }}
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              onClick={handleAddTerm}
              disabled={!newTermName.trim() || addingTerm}
            >
              {addingTerm ? "Adding..." : "Add"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!editingTerm} onOpenChange={(open_) => !open_ && setEditingTerm(null)}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Edit {taxonomyName}</DialogTitle>
            <DialogDescription>
              Update the name of this {taxonomyName.toLowerCase()}.
            </DialogDescription>
          </DialogHeader>
          <div className="py-4">
            <Label htmlFor="edit-term-name" className="text-sm font-medium dark:text-foreground">
              {taxonomyName} Name
            </Label>
            <Input
              id="edit-term-name"
              value={editTermName}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => setEditTermName(e.target.value)}
              placeholder={`Enter ${taxonomyName.toLowerCase()} name`}
              className="mt-2 dark:text-foreground"
              onKeyDown={(e: React.KeyboardEvent<HTMLInputElement>) => {
                if (e.key === "Enter") {
                  handleEditTerm();
                }
              }}
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setEditingTerm(null)}>
              Cancel
            </Button>
            <Button type="button" onClick={handleEditTerm} disabled={!editTermName.trim()}>
              Save Changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

export { TermsSelector };
