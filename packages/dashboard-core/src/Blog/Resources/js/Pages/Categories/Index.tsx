import { useCallback, useEffect, useMemo, useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import {
  Plus,
  Pencil,
  Trash2,
  FolderTree,
  GripVertical,
  ChevronRight,
  Search,
  X,
} from "lucide-react";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragOverlay,
  type UniqueIdentifier,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { toast } from "@/Components/ui/toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Checkbox } from "@/Components/ui/checkbox";
import { Badge } from "@/Components/ui/badge";
import { ScrollArea } from "@/Components/ui/scroll-area";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/Components/ui/sheet";
import {
  Empty,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyMedia,
} from "@/Components/ui/empty";
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from "@/Components/ui/tooltip";

interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  parent_id: number | null;
  depth: number;
  order: number;
  meta_title: string | null;
  meta_description: string | null;
  posts_count: number;
}

interface TreeNode extends Category {
  children: TreeNode[];
}

interface Props {
  categories: Category[];
}

function buildTree(categories: Category[]): TreeNode[] {
  const map = new Map<number, TreeNode>();
  const roots: TreeNode[] = [];

  for (const cat of categories) {
    map.set(cat.id, { ...cat, children: [] });
  }

  for (const cat of categories) {
    const node = map.get(cat.id)!;
    if (cat.parent_id && map.has(cat.parent_id)) {
      map.get(cat.parent_id)!.children.push(node);
    } else {
      roots.push(node);
    }
  }

  return roots;
}

function filterTree(nodes: TreeNode[], query: string): TreeNode[] {
  const lower = query.toLowerCase();
  return nodes
    .map((node) => {
      const matches = node.name.toLowerCase().includes(lower);
      const filteredChildren = filterTree(node.children, query);
      if (matches || filteredChildren.length > 0) {
        return { ...node, children: filteredChildren };
      }
      return null;
    })
    .filter(Boolean) as TreeNode[];
}

const depthColors = [
  "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
  "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
  "bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400",
];

function CategoryRow({
  node,
  selectedIds,
  expandedIds,
  onToggleSelect,
  onToggleExpand,
  onSelect,
  selectedId,
  depth = 0,
}: {
  node: TreeNode;
  selectedIds: Set<number>;
  expandedIds: Set<number>;
  onToggleSelect: (id: number) => void;
  onToggleExpand: (id: number) => void;
  onSelect: (cat: Category) => void;
  selectedId: number | null;
  depth?: number;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: String(node.id),
  });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.4 : 1,
    zIndex: isDragging ? 50 : ("auto" as never),
    position: "relative" as const,
  };

  const hasChildren = node.children.length > 0;
  const isExpanded = expandedIds.has(node.id);
  const isSelected = selectedId === node.id;

  return (
    <div ref={setNodeRef} style={style}>
      <div
        className={`group flex items-center gap-2 rounded-md px-2 py-1.5 text-sm cursor-pointer transition-colors ${
          isSelected ? "bg-primary/10 text-primary" : "hover:bg-muted/50 text-foreground"
        } ${isDragging ? "shadow-lg ring-2 ring-primary rounded-md" : ""}`}
        style={{ paddingLeft: `${depth * 24 + 8}px` }}
        onClick={() => onSelect({ ...node, children: undefined } as Category)}
      >
        <button
          type="button"
          {...attributes}
          {...listeners}
          className="shrink-0 text-muted-foreground cursor-grab hover:text-foreground active:cursor-grabbing touch-none p-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity"
          aria-label="Drag to reorder"
        >
          <GripVertical className="h-4 w-4" />
        </button>

        <Checkbox
          checked={selectedIds.has(node.id)}
          onCheckedChange={() => onToggleSelect(node.id)}
          onClick={(e) => e.stopPropagation()}
          className="shrink-0"
        />

        {hasChildren ? (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onToggleExpand(node.id);
            }}
            className="shrink-0 p-0.5 rounded hover:bg-muted transition-colors"
          >
            <ChevronRight
              className={`h-3.5 w-3.5 text-muted-foreground transition-transform ${
                isExpanded ? "rotate-90" : ""
              }`}
            />
          </button>
        ) : (
          <span className="w-[18px] shrink-0" />
        )}

        <FolderTree className="h-4 w-4 shrink-0 text-muted-foreground" />

        <span className="flex-1 truncate font-medium">{node.name}</span>

        {node.depth > 0 && (
          <Badge
            variant="outline"
            className={`shrink-0 text-[10px] px-1.5 py-0 ${depthColors[node.depth] || depthColors[0]}`}
          >
            L{node.depth}
          </Badge>
        )}

        <Badge variant="secondary" className="shrink-0 text-[10px] px-1.5 py-0">
          {node.posts_count}
        </Badge>
      </div>

      {hasChildren && isExpanded && (
        <div>
          {node.children.map((child) => (
            <CategoryRow
              key={child.id}
              node={child}
              selectedIds={selectedIds}
              expandedIds={expandedIds}
              onToggleSelect={onToggleSelect}
              onToggleExpand={onToggleExpand}
              onSelect={onSelect}
              selectedId={selectedId}
              depth={depth + 1}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function CategoryDragOverlay({ node, depth = 0 }: { node: TreeNode; depth?: number }) {
  return (
    <div
      className="flex items-center gap-2 rounded-md border bg-popover px-2 py-1.5 text-sm shadow-lg opacity-90"
      style={{ paddingLeft: `${depth * 24 + 8}px` }}
    >
      <GripVertical className="h-4 w-4 shrink-0 text-muted-foreground" />
      <span className="w-[18px] shrink-0" />
      <FolderTree className="h-4 w-4 shrink-0 text-muted-foreground" />
      <span className="flex-1 truncate font-medium">{node.name}</span>
      {node.depth > 0 && (
        <Badge variant="outline" className="shrink-0 text-[10px] px-1.5 py-0">
          L{node.depth}
        </Badge>
      )}
    </div>
  );
}

export default function PostCategoriesIndex({ categories }: Props) {
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [expandedIds, setExpandedIds] = useState<Set<number>>(() => {
    const ids = new Set<number>();
    for (const cat of categories) {
      if (cat.depth === 0) {
        ids.add(cat.id);
      }
    }
    return ids;
  });
  const [selectedCategory, setSelectedCategory] = useState<Category | null>(null);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);
  const [newOpen, setNewOpen] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const check = () => setIsMobile(window.innerWidth < 768);
    check();
    const observer = new ResizeObserver(check);
    observer.observe(document.body);
    return () => observer.disconnect();
  }, []);

  const tree = useMemo(() => {
    const fullTree = buildTree(categories);
    return search ? filterTree(fullTree, search) : fullTree;
  }, [categories, search]);

  const allVisibleIds = useMemo(() => {
    const ids: string[] = [];
    const collect = (nodes: TreeNode[]) => {
      for (const node of nodes) {
        ids.push(String(node.id));
        if (expandedIds.has(node.id)) {
          collect(node.children);
        }
      }
    };
    collect(tree);
    return ids;
  }, [tree, expandedIds]);

  const nodeMap = useMemo(() => {
    const map = new Map<number, TreeNode>();
    const index = (nodes: TreeNode[]) => {
      for (const node of nodes) {
        map.set(node.id, node);
        index(node.children);
      }
    };
    index(buildTree(categories));
    return map;
  }, [categories]);

  const activeNode = activeId ? (nodeMap.get(Number(activeId)) ?? null) : null;

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const toggleSelect = useCallback((id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const toggleExpand = useCallback((id: number) => {
    setExpandedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const selectAll = useCallback(() => {
    const allIds = new Set(categories.map((c) => c.id));
    setSelectedIds(allIds);
  }, [categories]);

  const deselectAll = useCallback(() => {
    setSelectedIds(new Set());
  }, []);

  const handleDragEnd = useCallback(
    (event: { active: { id: UniqueIdentifier }; over: { id: UniqueIdentifier } | null }) => {
      const { active, over } = event;
      setActiveId(null);

      if (!over || active.id === over.id) return;

      const activeNum = Number(active.id);
      const overNum = Number(over.id);

      const activeNode = nodeMap.get(activeNum);
      const overNode = nodeMap.get(overNum);
      if (!activeNode || !overNode) return;

      if (activeNode.parent_id !== overNode.parent_id) return;

      const parentId = activeNode.parent_id;
      const siblings = parentId ? (nodeMap.get(parentId)?.children ?? []) : tree;

      const oldIndex = siblings.findIndex((s) => s.id === activeNum);
      const newIndex = siblings.findIndex((s) => s.id === overNum);
      if (oldIndex === -1 || newIndex === -1) return;

      const reorderedIds = arrayMove(
        siblings.map((s) => s.id),
        oldIndex,
        newIndex,
      );

      router.post(
        route("dashboard.posts.categories.reorder"),
        { ids: reorderedIds },
        { preserveScroll: true, only: [] },
      );
    },
    [nodeMap, tree],
  );

  const handleBulkDelete = useCallback(() => {
    if (selectedIds.size === 0) return;
    if (!confirm(`Delete ${selectedIds.size} categor${selectedIds.size === 1 ? "y" : "ies"}?`))
      return;

    router.post(
      route("dashboard.posts.categories.bulk"),
      { action: "delete", ids: [...selectedIds] },
      {
        preserveScroll: true,
        only: [],
        onSuccess: () => {
          setSelectedIds(new Set());
          setSelectedCategory(null);
          setSheetOpen(false);
          toast.add({ title: "Categories deleted.", type: "success" });
        },
      },
    );
  }, [selectedIds]);

  const handleDelete = useCallback((cat: Category) => {
    if (!confirm(`Delete "${cat.name}"?`)) return;
    router.delete(route("dashboard.posts.categories.destroy", cat.id), {
      preserveScroll: true,
      only: [],
      onSuccess: () => {
        setSelectedCategory(null);
        setSheetOpen(false);
        toast.add({ title: "Category deleted.", type: "success" });
      },
    });
  }, []);

  const openNewCategory = useCallback(() => {
    setSelectedCategory(null);
    setNewOpen(true);
  }, []);

  return (
    <AuthenticatedLayout header="Post Categories">
      <Head title="Post Categories" />

      <div className="flex h-[calc(100vh-8rem)] gap-0 overflow-hidden rounded-lg border">
        {/* Left Panel — Tree */}
        <div className="flex w-full min-w-0 flex-col border-r md:w-[380px]">
          <div className="flex items-center justify-between border-b px-4 py-3">
            <h2 className="text-sm font-semibold">Categories</h2>
            <div className="flex items-center gap-1">
              {selectedIds.size > 0 && (
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger
                      render={
                        <Button variant="destructive" size="sm" onClick={handleBulkDelete}>
                          <Trash2 className="mr-1 h-3.5 w-3.5" />
                          Delete ({selectedIds.size})
                        </Button>
                      }
                    />
                    <TooltipContent>Delete selected categories</TooltipContent>
                  </Tooltip>
                </TooltipProvider>
              )}
              <Button size="sm" onClick={openNewCategory}>
                <Plus className="mr-1 h-3.5 w-3.5" />
                Add
              </Button>
            </div>
          </div>

          <div className="border-b px-4 py-2">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search categories..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="h-8 pl-8 text-sm"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch("")}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
            <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
              <button
                type="button"
                onClick={selectedIds.size === categories.length ? deselectAll : selectAll}
                className="hover:text-foreground transition-colors"
              >
                {selectedIds.size === categories.length ? "Deselect all" : "Select all"}
              </button>
              <span>{selectedIds.size > 0 && ` · ${selectedIds.size} selected`}</span>
            </div>
          </div>

          <ScrollArea className="flex-1">
            {categories.length === 0 ? (
              <Empty className="py-12">
                <EmptyHeader>
                  <EmptyMedia variant="icon">
                    <FolderTree />
                  </EmptyMedia>
                  <EmptyTitle>No categories yet</EmptyTitle>
                  <EmptyDescription>Create categories to organize your posts.</EmptyDescription>
                </EmptyHeader>
              </Empty>
            ) : tree.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <Search className="mb-2 h-8 w-8 text-muted-foreground/50" />
                <p className="text-sm text-muted-foreground">No categories match "{search}"</p>
              </div>
            ) : (
              <div className="p-2">
                <DndContext
                  sensors={sensors}
                  collisionDetection={closestCenter}
                  onDragStart={() => setActiveId(null)}
                  onDragEnd={handleDragEnd}
                >
                  <SortableContext items={allVisibleIds} strategy={verticalListSortingStrategy}>
                    <div className="space-y-0.5">
                      {tree.map((node) => (
                        <CategoryRow
                          key={node.id}
                          node={node}
                          selectedIds={selectedIds}
                          expandedIds={expandedIds}
                          onToggleSelect={toggleSelect}
                          onToggleExpand={toggleExpand}
                          onSelect={(cat) => {
                            setSelectedCategory(cat);
                            if (isMobile) setSheetOpen(true);
                          }}
                          selectedId={selectedCategory?.id ?? null}
                        />
                      ))}
                    </div>
                  </SortableContext>
                  <DragOverlay>
                    {activeNode ? <CategoryDragOverlay node={activeNode} /> : null}
                  </DragOverlay>
                </DndContext>
              </div>
            )}
          </ScrollArea>

          <div className="border-t px-4 py-2 text-xs text-muted-foreground">
            {categories.length} categories · Drag to reorder
          </div>
        </div>

        {/* Right Panel — Detail */}
        <div className="hidden flex-1 md:flex md:flex-col">
          {selectedCategory ? (
            <CategoryDetailPanel
              category={selectedCategory}
              allCategories={categories}
              onClose={() => {
                setSelectedCategory(null);
                setSheetOpen(false);
              }}
              onDelete={handleDelete}
            />
          ) : (
            <div className="flex flex-1 flex-col items-center justify-center text-center">
              <FolderTree className="mb-3 h-12 w-12 text-muted-foreground/30" />
              <p className="text-sm font-medium text-muted-foreground">
                Select a category to view details
              </p>
              <p className="mt-1 text-xs text-muted-foreground/70">
                Click any category in the tree
              </p>
            </div>
          )}
        </div>
      </div>

      {/* New Category Sheet (mobile fallback) */}
      <Sheet open={newOpen} onOpenChange={setNewOpen}>
        <SheetContent side="right" className="w-full sm:max-w-md">
          <SheetHeader>
            <SheetTitle>Add Category</SheetTitle>
            <SheetDescription>Create a new post category.</SheetDescription>
          </SheetHeader>
          <NewCategoryForm allCategories={categories} onSuccess={() => setNewOpen(false)} />
        </SheetContent>
      </Sheet>

      {/* Edit/Delete Sheet (mobile) */}
      <Sheet
        open={sheetOpen && !!selectedCategory}
        onOpenChange={(open) => {
          setSheetOpen(open);
          if (!open) setSelectedCategory(null);
        }}
      >
        <SheetContent side="right" className="w-full sm:max-w-md md:hidden">
          {selectedCategory && (
            <>
              <SheetHeader>
                <SheetTitle>{selectedCategory.name}</SheetTitle>
                <SheetDescription>Category details</SheetDescription>
              </SheetHeader>
              <CategoryDetailPanel
                category={selectedCategory}
                allCategories={categories}
                onClose={() => {
                  setSelectedCategory(null);
                  setSheetOpen(false);
                }}
                onDelete={(cat) => {
                  handleDelete(cat);
                  setSheetOpen(false);
                }}
                embedded
              />
            </>
          )}
        </SheetContent>
      </Sheet>
    </AuthenticatedLayout>
  );
}

function CategoryDetailPanel({
  category,
  allCategories,
  onClose,
  onDelete,
  embedded = false,
}: {
  category: Category;
  allCategories: Category[];
  onClose: () => void;
  onDelete: (cat: Category) => void;
  embedded?: boolean;
}) {
  const [editing, setEditing] = useState(false);
  const { data, setData, put, processing, errors } = useForm({
    name: category.name,
    description: category.description || "",
    parent_id: category.parent_id ? String(category.parent_id) : "",
    order: String(category.order),
    meta_title: category.meta_title || "",
    meta_description: category.meta_description || "",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route("dashboard.posts.categories.update", category.id), {
      preserveScroll: true,
      only: [],
      onSuccess: () => {
        setEditing(false);
        toast.add({ title: "Category updated.", type: "success" });
      },
    });
  };

  const availableParents = allCategories.filter((c) => c.id !== category.id && c.depth < 2);

  const content = (
    <div className="space-y-4 p-4">
      {editing ? (
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid gap-2">
            <Label htmlFor="edit-name">Name</Label>
            <Input
              id="edit-name"
              value={data.name}
              onChange={(e) => setData("name", e.target.value)}
              required
            />
            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
          </div>
          <div className="grid gap-2">
            <Label htmlFor="edit-desc">Description</Label>
            <Textarea
              id="edit-desc"
              value={data.description}
              onChange={(e) => setData("description", e.target.value)}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="edit-parent">Parent Category</Label>
            <select
              id="edit-parent"
              value={data.parent_id}
              onChange={(e) => setData("parent_id", e.target.value)}
              className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
            >
              <option value="">None (top level)</option>
              {availableParents.map((cat) => (
                <option key={cat.id} value={String(cat.id)}>
                  {"\u{A0}\u{A0}".repeat(cat.depth)}
                  {cat.depth > 0 ? "\u{251C}\u{2500} " : ""}
                  {cat.name}
                </option>
              ))}
            </select>
          </div>
          <div className="grid gap-2">
            <Label htmlFor="edit-order">Order</Label>
            <Input
              id="edit-order"
              type="number"
              min="0"
              value={data.order}
              onChange={(e) => setData("order", e.target.value)}
            />
          </div>
          <div className="space-y-3 rounded-md border p-3">
            <p className="text-sm font-medium text-muted-foreground">SEO</p>
            <div className="grid gap-2">
              <Label htmlFor="edit-meta-title">Meta Title</Label>
              <Input
                id="edit-meta-title"
                maxLength={70}
                placeholder="Optional — defaults to category name"
                value={data.meta_title}
                onChange={(e) => setData("meta_title", e.target.value)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-meta-desc">Meta Description</Label>
              <Textarea
                id="edit-meta-desc"
                maxLength={160}
                rows={2}
                placeholder="Optional — defaults to category description"
                value={data.meta_description}
                onChange={(e) => setData("meta_description", e.target.value)}
              />
            </div>
          </div>
          <div className="flex gap-2">
            <Button type="submit" disabled={processing}>
              {processing ? "Saving..." : "Save"}
            </Button>
            <Button type="button" variant="ghost" onClick={() => setEditing(false)}>
              Cancel
            </Button>
          </div>
        </form>
      ) : (
        <div className="space-y-4">
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <FolderTree className="h-5 w-5 text-muted-foreground" />
              <h3 className="text-lg font-semibold">{category.name}</h3>
              {category.depth > 0 && (
                <Badge variant="outline" className="text-xs">
                  L{category.depth}
                </Badge>
              )}
            </div>
            {category.description && (
              <p className="text-sm text-muted-foreground">{category.description}</p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3 text-sm">
            <div className="space-y-1">
              <p className="text-muted-foreground">Slug</p>
              <p className="font-mono text-xs">{category.slug}</p>
            </div>
            <div className="space-y-1">
              <p className="text-muted-foreground">Posts</p>
              <p className="font-medium">{category.posts_count}</p>
            </div>
            <div className="space-y-1">
              <p className="text-muted-foreground">Parent</p>
              <p>
                {category.parent_id
                  ? (allCategories.find((c) => c.id === category.parent_id)?.name ?? "—")
                  : "None (root)"}
              </p>
            </div>
            <div className="space-y-1">
              <p className="text-muted-foreground">Order</p>
              <p>{category.order}</p>
            </div>
          </div>

          {(category.meta_title || category.meta_description) && (
            <div className="space-y-2 rounded-md border p-3">
              <p className="text-sm font-medium text-muted-foreground">SEO</p>
              {category.meta_title && (
                <div className="space-y-1">
                  <p className="text-xs text-muted-foreground">Meta Title</p>
                  <p className="text-sm">{category.meta_title}</p>
                </div>
              )}
              {category.meta_description && (
                <div className="space-y-1">
                  <p className="text-xs text-muted-foreground">Meta Description</p>
                  <p className="text-sm">{category.meta_description}</p>
                </div>
              )}
            </div>
          )}

          <div className="flex gap-2">
            <Button size="sm" onClick={() => setEditing(true)}>
              <Pencil className="mr-1 h-3.5 w-3.5" />
              Edit
            </Button>
            <Button size="sm" variant="destructive" onClick={() => onDelete(category)}>
              <Trash2 className="mr-1 h-3.5 w-3.5" />
              Delete
            </Button>
          </div>
        </div>
      )}
    </div>
  );

  if (embedded) {
    return content;
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between border-b px-4 py-3">
        <h3 className="text-sm font-semibold">Category Details</h3>
        <Button variant="ghost" size="icon-sm" onClick={onClose}>
          <X className="h-4 w-4" />
        </Button>
      </div>
      <ScrollArea className="flex-1">{content}</ScrollArea>
    </div>
  );
}

function NewCategoryForm({
  allCategories,
  onSuccess,
}: {
  allCategories: Category[];
  onSuccess: () => void;
}) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: "",
    description: "",
    parent_id: "",
    order: "0",
    meta_title: "",
    meta_description: "",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route("dashboard.posts.categories.store"), {
      preserveScroll: true,
      only: [],
      onSuccess: () => {
        reset();
        onSuccess();
        toast.add({ title: "Category created.", type: "success" });
      },
    });
  };

  const availableParents = allCategories.filter((c) => c.depth < 2);

  return (
    <form onSubmit={handleSubmit} className="space-y-4 p-4">
      <div className="grid gap-2">
        <Label htmlFor="new-name">Name</Label>
        <Input
          id="new-name"
          value={data.name}
          onChange={(e) => setData("name", e.target.value)}
          required
        />
        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="new-desc">Description</Label>
        <Textarea
          id="new-desc"
          value={data.description}
          onChange={(e) => setData("description", e.target.value)}
        />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="new-parent">Parent Category</Label>
        <select
          id="new-parent"
          value={data.parent_id}
          onChange={(e) => setData("parent_id", e.target.value)}
          className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
        >
          <option value="">None (top level)</option>
          {availableParents.map((cat) => (
            <option key={cat.id} value={String(cat.id)}>
              {"\u{A0}\u{A0}".repeat(cat.depth)}
              {cat.depth > 0 ? "\u{251C}\u{2500} " : ""}
              {cat.name}
            </option>
          ))}
        </select>
      </div>
      <div className="grid gap-2">
        <Label htmlFor="new-order">Order</Label>
        <Input
          id="new-order"
          type="number"
          min="0"
          value={data.order}
          onChange={(e) => setData("order", e.target.value)}
        />
      </div>
      <div className="space-y-3 rounded-md border p-3">
        <p className="text-sm font-medium text-muted-foreground">SEO</p>
        <div className="grid gap-2">
          <Label htmlFor="new-meta-title">Meta Title</Label>
          <Input
            id="new-meta-title"
            maxLength={70}
            placeholder="Optional — defaults to category name"
            value={data.meta_title}
            onChange={(e) => setData("meta_title", e.target.value)}
          />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="new-meta-desc">Meta Description</Label>
          <Textarea
            id="new-meta-desc"
            maxLength={160}
            rows={2}
            placeholder="Optional — defaults to category description"
            value={data.meta_description}
            onChange={(e) => setData("meta_description", e.target.value)}
          />
        </div>
      </div>
      <Button type="submit" className="w-full" disabled={processing}>
        {processing ? "Creating..." : "Create Category"}
      </Button>
    </form>
  );
}
