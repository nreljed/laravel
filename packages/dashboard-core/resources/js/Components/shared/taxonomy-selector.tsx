import * as React from "react";
import { ChevronDown, Folder, FolderOpen, Search, Tag, X } from "lucide-react";

import { Button } from "@/Components/ui/button";
import { Checkbox } from "@/Components/ui/checkbox";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";

import { Collapsible, CollapsibleTrigger, CollapsibleContent } from "@/Components/ui/collapsible";
import { cn } from "@/lib/utils";

interface TaxonomyTerm {
  id: number | string;
  name: string;
  parent?: number | string | null;
  count?: number;
  description?: string;
  slug?: string;
}

interface TaxonomyTreeNode {
  term: TaxonomyTerm;
  children: TaxonomyTreeNode[];
}

interface TaxonomyUiState {
  expandedIds: (number | string)[];
}

interface TaxonomySelectorConfig {
  enableGlobalSearch?: boolean;
  enablePerPanelSearch?: boolean;
  searchDebounceMs?: number;
  multiSelect?: boolean;
  selectParentsAuto?: boolean;
  selectChildrenAuto?: boolean;
  indeterminateStates?: boolean;
  variant?: string;
  showCounts?: boolean;
  showExpandCollapseAll?: boolean;
  maxHeight?: number;
  ariaLabel?: string;
  ariaDescription?: string;
}

interface TaxonomySelectorProps {
  taxonomies?: TaxonomyTerm[];
  selectedIds?: (number | string)[];
  onSelectionChange?: (_ids: (number | string)[]) => void;
  config?: TaxonomySelectorConfig;
  className?: string;
  disabled?: boolean;
  loading?: boolean;
  uiState?: TaxonomyUiState | null;
  onUiStateChange?: (_state: TaxonomyUiState) => void;
}

const DEFAULT_CONFIG: TaxonomySelectorConfig = {
  enableGlobalSearch: true,
  enablePerPanelSearch: false,
  searchDebounceMs: 300,
  multiSelect: true,
  selectParentsAuto: true,
  selectChildrenAuto: false,
  indeterminateStates: true,
  variant: "default",
  showCounts: true,
  showExpandCollapseAll: true,
  maxHeight: 500,
  ariaLabel: "Taxonomy selector",
  ariaDescription: "",
};

const normalizeTaxonomies = (taxonomies: TaxonomyTerm[]): TaxonomyTerm[] => {
  return Array.isArray(taxonomies)
    ? taxonomies.map((term) => ({
        ...term,
        id: String(term.id),
        parent: term.parent == null || term.parent === "" ? null : String(term.parent),
      }))
    : [];
};

const slugify = (value = ""): string =>
  String(value)
    .toLowerCase()
    .replaceAll(/[^a-z0-9]+/g, "-")
    .replaceAll(/(^-|-$)/g, "");

const buildTree = (terms: TaxonomyTerm[]): TaxonomyTreeNode[] => {
  const childrenByParent: Record<string, TaxonomyTerm[]> = terms.reduce(
    (map, term) => {
      const key = term.parent == null ? "root" : term.parent;
      if (!Object.hasOwn(map, key)) {
        map[key] = [];
      }
      map[key]!.push(term);
      return map;
    },
    {} as Record<string, TaxonomyTerm[]>,
  );

  const buildNode = (term: TaxonomyTerm): TaxonomyTreeNode => ({
    term,
    children: (childrenByParent[term.id] || []).map(buildNode),
  });

  return (childrenByParent.root || [])
    .map(buildNode)
    .toSorted((a, b) => a.term.name.localeCompare(b.term.name));
};

const filterTree = (nodes: TaxonomyTreeNode[], search: string): TaxonomyTreeNode[] => {
  if (!search) {
    return nodes;
  }

  const normalizedSearch = search.toLowerCase();

  const filterNode = (node: TaxonomyTreeNode): TaxonomyTreeNode | null => {
    const matches =
      node.term.name.toLowerCase().includes(normalizedSearch) ||
      String(node.term.description || "")
        .toLowerCase()
        .includes(normalizedSearch);

    const children = node.children.map(filterNode).filter((n): n is TaxonomyTreeNode => n !== null);

    if (matches || children.length > 0) {
      return {
        term: node.term,
        children,
      };
    }
    return null;
  };

  return nodes.map(filterNode).filter((n): n is TaxonomyTreeNode => n !== null);
};

const getAncestorIds = (termId: string, termsById: Record<string, TaxonomyTerm>): string[] => {
  const ids: string[] = [];
  let current = termsById[termId];
  while (current && current.parent != null) {
    ids.push(String(current.parent));
    current = termsById[String(current.parent)];
  }
  return ids;
};

const getDescendantIds = (
  termId: string,
  childrenByParent: Record<string, TaxonomyTerm[]>,
): string[] => {
  const ids: string[] = [];
  const walk = (id: string) => {
    const children = childrenByParent[id] || [];
    for (const child of children) {
      ids.push(String(child.id));
      walk(String(child.id));
    }
  };
  walk(termId);
  return ids;
};

interface RenderTreeDeps {
  mergedConfig: TaxonomySelectorConfig;
  disabled: boolean;
  computeIndeterminate: (_termId: string) => boolean;
}

function TaxonomySelector({
  taxonomies = [],
  selectedIds = [],
  onSelectionChange,
  config = {},
  className = "",
  disabled = false,
  loading = false,
  uiState = null,
  onUiStateChange,
}: TaxonomySelectorProps) {
  const mergedConfig: TaxonomySelectorConfig = { ...DEFAULT_CONFIG, ...config };
  const normalizedTaxonomies = React.useMemo(() => normalizeTaxonomies(taxonomies), [taxonomies]);

  const termsById = React.useMemo(
    () =>
      normalizedTaxonomies.reduce(
        (map, term) => {
          map[term.id] = term;
          return map;
        },
        {} as Record<string, TaxonomyTerm>,
      ),
    [normalizedTaxonomies],
  );

  const childrenByParent = React.useMemo(() => {
    return normalizedTaxonomies.reduce(
      (map, term) => {
        const parentKey = term.parent == null ? "root" : term.parent;
        map[parentKey] ||= [];
        map[parentKey].push(term);
        return map;
      },
      {} as Record<string, TaxonomyTerm[]>,
    );
  }, [normalizedTaxonomies]);

  const rootTrees = React.useMemo(() => buildTree(normalizedTaxonomies), [normalizedTaxonomies]);

  const [searchQuery, setSearchQuery] = React.useState("");
  const [debouncedSearch, setDebouncedSearch] = React.useState("");
  const [statusMessage, setStatusMessage] = React.useState("");
  const [localExpandedIds, setLocalExpandedIds] = React.useState<Set<string | number>>(new Set());

  const onUiStateChangeRef = React.useRef(onUiStateChange);
  React.useEffect(() => {
    onUiStateChangeRef.current = onUiStateChange;
  });

  const externalExpandedIds = React.useMemo(() => {
    if (uiState !== null && uiState.expandedIds) {
      return new Set(Array.isArray(uiState.expandedIds) ? uiState.expandedIds.map(String) : []);
    }
    return null;
  }, [uiState]);

  const expandedIds = externalExpandedIds !== null ? externalExpandedIds : localExpandedIds;

  React.useEffect(() => {
    if (uiState !== null || localExpandedIds.size > 0 || rootTrees.length === 0) return;
    const allRootIds = rootTrees.map((node) => node.term.id);
    const timer = setTimeout(() => {
      setLocalExpandedIds(new Set<string | number>(allRootIds));
    }, 0);
    return () => clearTimeout(timer);
  }, [rootTrees, uiState, localExpandedIds]);

  React.useEffect(() => {
    if (externalExpandedIds === null || !onUiStateChangeRef.current) return;
    const timer = setTimeout(() => {
      setLocalExpandedIds(new Set(externalExpandedIds));
    }, 0);
    return () => clearTimeout(timer);
  }, [externalExpandedIds]);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(searchQuery.trim());
    }, mergedConfig.searchDebounceMs ?? 300);
    return () => clearTimeout(timer);
  }, [searchQuery, mergedConfig.searchDebounceMs]);

  const selectedSet = React.useMemo(
    () => new Set<string>((selectedIds || []).map(String)),
    [selectedIds],
  );

  const filteredTrees = React.useMemo(
    () => filterTree(rootTrees, debouncedSearch),
    [rootTrees, debouncedSearch],
  );

  const visibleAncestorIds = React.useMemo(() => {
    const ids = new Set<string | number>();
    const walk = (node: TaxonomyTreeNode) => {
      if (node.children.length > 0) {
        ids.add(node.term.id);
      }
      for (const child of node.children) walk(child);
    };
    for (const tree of filteredTrees) walk(tree);
    return ids;
  }, [filteredTrees]);

  const effectiveExpandedIds = React.useMemo(() => {
    if (debouncedSearch) {
      return visibleAncestorIds;
    }
    return expandedIds;
  }, [debouncedSearch, visibleAncestorIds, expandedIds]);

  const computeIndeterminate = React.useCallback(
    (termId: string): boolean => {
      if (!mergedConfig.indeterminateStates) {
        return false;
      }
      const descendantIds = getDescendantIds(termId, childrenByParent);
      if (descendantIds.length === 0) {
        return false;
      }
      const selectedChildren = descendantIds.filter((id) => selectedSet.has(id));
      return selectedChildren.length > 0 && selectedChildren.length < descendantIds.length;
    },
    [childrenByParent, mergedConfig.indeterminateStates, selectedSet],
  );

  const handleTogglePanel = React.useCallback(
    (termId: number | string) => {
      if (debouncedSearch) {
        return;
      }
      const id = String(termId);
      const nextExpanded = new Set(localExpandedIds.size > 0 ? [...localExpandedIds] : []);
      if (nextExpanded.has(id)) {
        nextExpanded.delete(id);
      } else {
        nextExpanded.add(id);
      }
      setLocalExpandedIds(nextExpanded);
      if (onUiStateChangeRef.current) {
        onUiStateChangeRef.current({ expandedIds: [...nextExpanded] });
      }
    },
    [debouncedSearch, localExpandedIds],
  );

  const handleSelection = React.useCallback(
    (termId: number | string) => {
      if (disabled || !onSelectionChange) {
        return;
      }
      const id = String(termId);
      const selected = selectedSet.has(id);
      const next = new Set(selectedSet);

      if (selected) {
        next.delete(id);
        if (mergedConfig.selectChildrenAuto) {
          for (const childId of getDescendantIds(id, childrenByParent)) {
            next.delete(childId);
          }
        }
        if (mergedConfig.selectParentsAuto) {
          for (const ancestorId of getAncestorIds(id, termsById)) {
            const descendantIds = getDescendantIds(ancestorId, childrenByParent);
            const remains = descendantIds.some((descId) => next.has(descId));
            if (!remains) {
              next.delete(ancestorId);
            }
          }
        }
      } else {
        if (!mergedConfig.multiSelect) {
          next.clear();
          next.add(id);
        } else {
          next.add(id);
        }
        if (mergedConfig.selectChildrenAuto && mergedConfig.multiSelect) {
          for (const childId of getDescendantIds(id, childrenByParent)) {
            next.add(childId);
          }
        }
        if (mergedConfig.selectParentsAuto && mergedConfig.multiSelect) {
          for (const ancestorId of getAncestorIds(id, termsById)) {
            next.add(ancestorId);
          }
        }
      }
      onSelectionChange([...next]);
    },
    [
      childrenByParent,
      disabled,
      mergedConfig.multiSelect,
      mergedConfig.selectChildrenAuto,
      mergedConfig.selectParentsAuto,
      onSelectionChange,
      selectedSet,
      termsById,
    ],
  );

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setStatusMessage(`${selectedSet.size} selected`);
      if (selectedSet.size === 0) return;
      const hideTimer = setTimeout(() => setStatusMessage(""), 1000);
      return () => clearTimeout(hideTimer);
    }, 0);
    return () => clearTimeout(timer);
  }, [selectedSet.size]);

  const clearAll = React.useCallback(() => {
    if (disabled || !onSelectionChange) {
      return;
    }
    onSelectionChange([]);
  }, [disabled, onSelectionChange]);

  const renderTree = (nodes: TaxonomyTreeNode[], depth = 1, deps: RenderTreeDeps) => {
    const { mergedConfig: cfg, disabled: d, computeIndeterminate: ci } = deps;

    return nodes.map((node) => {
      const { term, children } = node;
      const hasChildren = children.length > 0;
      const selected = selectedSet.has(String(term.id));
      const expanded = effectiveExpandedIds.has(term.id);

      if (hasChildren) {
        const countSelectedInCategory = (nodes_: TaxonomyTreeNode[]): number => {
          let count = 0;
          for (const node_ of nodes_) {
            if (selectedSet.has(String(node_.term.id))) {
              count++;
            }
            if (node_.children.length > 0) {
              count += countSelectedInCategory(node_.children);
            }
          }
          return count;
        };
        const selectedInCategory = countSelectedInCategory(children);
        const totalInCategory = children.length;

        return (
          <Collapsible
            key={term.id}
            open={expanded}
            onOpenChange={debouncedSearch ? undefined : () => handleTogglePanel(term.id)}
            className="mb-2"
          >
            <CollapsibleTrigger
              className={cn(
                "flex w-full items-center gap-3 rounded-lg border px-4 py-4 transition-all",
                "hover:bg-accent/50 hover:border-primary/50",
                "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1",
                expanded ? "border-primary/50 bg-accent/30" : "border-border",
              )}
              data-testid={`panel-${slugify(term.name)}`}
            >
              <ChevronDown
                className={cn(
                  "h-4 w-4 shrink-0 transition-transform duration-200",
                  expanded && "rotate-180",
                )}
              />
              <FolderOpen className="h-4 w-4 shrink-0 text-muted-foreground" />
              <span className="flex-1 truncate text-left font-medium">{term.name}</span>
              {selectedInCategory > 0 && (
                <span className="shrink-0 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                  {selectedInCategory}/{totalInCategory}
                </span>
              )}
              {cfg.showCounts && typeof term.count === "number" && (
                <span className="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground">
                  {term.count}
                </span>
              )}
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-2">
              <div className="space-y-1 pb-1">{renderTree(children, depth + 1, deps)}</div>
            </CollapsibleContent>
          </Collapsible>
        );
      }

      const indeterminate = ci(String(term.id));
      return (
        <div key={term.id} className="group relative mb-1">
          <label
            className={cn(
              "flex items-center gap-2 rounded-md px-3 py-1.5 text-sm cursor-pointer transition-colors",
              "hover:bg-accent/50",
              selected ? "text-foreground" : "text-muted-foreground",
            )}
          >
            <Checkbox
              checked={selected}
              indeterminate={indeterminate}
              onCheckedChange={() => {
                if (!d) {
                  handleSelection(term.id);
                }
              }}
              disabled={d}
              className="h-4 w-4"
            />
            <span className="truncate text-sm leading-tight">{term.name}</span>
            {cfg.showCounts && typeof term.count === "number" && !selected && (
              <span className="ml-auto shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                {term.count}
              </span>
            )}
          </label>
        </div>
      );
    });
  };

  return (
    <div
      className={cn("space-y-4", className)}
      role="region"
      aria-label={mergedConfig.ariaLabel}
      aria-describedby={mergedConfig.ariaDescription ? "taxonomy-selector-description" : undefined}
    >
      {mergedConfig.ariaDescription && (
        <p id="taxonomy-selector-description" className="text-sm text-muted-foreground">
          {mergedConfig.ariaDescription}
        </p>
      )}

      {mergedConfig.enableGlobalSearch && (
        <div className="space-y-2">
          <Label className="text-sm font-medium">Search terms</Label>
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              type="text"
              value={searchQuery}
              onChange={(event: React.ChangeEvent<HTMLInputElement>) =>
                setSearchQuery(event.target.value)
              }
              placeholder="Search terms..."
              disabled={disabled || loading}
              className="pl-9 pr-9"
            />
            {searchQuery && (
              <button
                type="button"
                onClick={() => setSearchQuery("")}
                className="absolute right-2 top-1/2 -translate-y-1/2 rounded-sm p-1 text-muted-foreground hover:text-foreground transition-colors"
                aria-label="Clear search"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
        </div>
      )}

      {mergedConfig.showExpandCollapseAll && !debouncedSearch && (
        <div className="flex items-center justify-between gap-3 rounded-lg border bg-muted/30 px-3 py-2">
          <span className="text-sm text-muted-foreground">{selectedSet.size} selected</span>
          <div className="flex gap-1.5">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                const allIds = rootTrees.map((node) => node.term.id);
                setLocalExpandedIds(new Set<string | number>(allIds));
                if (onUiStateChangeRef.current) {
                  onUiStateChangeRef.current({ expandedIds: allIds });
                }
              }}
              disabled={disabled || loading}
              className="h-7 px-2 text-xs"
            >
              <FolderOpen className="h-3.5 w-3.5 mr-1" />
              Expand all
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                setLocalExpandedIds(new Set());
                if (onUiStateChangeRef.current) {
                  onUiStateChangeRef.current({ expandedIds: [] });
                }
              }}
              disabled={disabled || loading}
              className="h-7 px-2 text-xs"
            >
              <Folder className="h-3.5 w-3.5 mr-1" />
              Collapse all
            </Button>
          </div>
        </div>
      )}

      <div className="relative">
        {loading && (
          <div className="pointer-events-none absolute inset-0 z-10 rounded-xl bg-background/70 backdrop-blur-sm" />
        )}
        <div
          className="w-full overflow-y-auto space-y-0.5 py-1 px-1"
          style={{ maxHeight: mergedConfig.maxHeight }}
        >
          {filteredTrees.length > 0 ? (
            renderTree(filteredTrees, 1, {
              mergedConfig,
              disabled,
              computeIndeterminate,
            })
          ) : (
            <div className="rounded-lg border border-dashed bg-muted/20 p-5 text-center">
              <Tag className="h-8 w-8 mx-auto mb-2 text-muted-foreground/50" />
              <p className="text-sm text-muted-foreground">
                {debouncedSearch ? "No matching terms found" : "No terms available"}
              </p>
            </div>
          )}
        </div>
      </div>

      <div className="flex items-center justify-between gap-4 pt-3 border-t">
        <span className="text-sm font-medium">{selectedSet.size} selected</span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={clearAll}
          disabled={disabled || selectedSet.size === 0 || loading}
          className="h-8 text-xs"
        >
          Clear all
        </Button>
      </div>

      <div role="status" aria-live="polite" className="sr-only">
        {statusMessage}
      </div>
    </div>
  );
}

export { TaxonomySelector };
