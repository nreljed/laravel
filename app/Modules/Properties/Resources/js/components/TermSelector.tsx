import { cn } from "@/lib/utils";
import { Check } from "lucide-react";

interface TermItem {
  id: number;
  name: string;
  group?: string | null;
  icon?: string | null;
  children?: TermItem[];
}

interface TermSelectorProps {
  label: string;
  items: TermItem[];
  selectedIds: number[];
  onChange: (ids: number[]) => void;
  variant?: "checkbox" | "chip" | "tree";
  grouped?: boolean;
}

function CheckboxItem({
  item,
  checked,
  onToggle,
}: {
  item: TermItem;
  checked: boolean;
  onToggle: (id: number) => void;
}) {
  return (
    <label
      className={cn(
        "flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors hover:bg-accent",
        checked && "bg-accent font-medium",
      )}
    >
      <input
        type="checkbox"
        checked={checked}
        onChange={() => onToggle(item.id)}
        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
      />
      {item.name}
    </label>
  );
}

function ChipItem({
  item,
  selected,
  onToggle,
}: {
  item: TermItem;
  selected: boolean;
  onToggle: (id: number) => void;
}) {
  return (
    <button
      type="button"
      onClick={() => onToggle(item.id)}
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors",
        selected
          ? "border-primary bg-primary/10 text-primary"
          : "border-input bg-background text-muted-foreground hover:bg-accent",
      )}
    >
      {selected && <Check className="h-3 w-3" />}
      {item.name}
    </button>
  );
}

function TreeItem({
  item,
  selectedIds,
  onToggle,
  depth = 0,
}: {
  item: TermItem;
  selectedIds: number[];
  onToggle: (id: number) => void;
  depth?: number;
}) {
  const checked = selectedIds.includes(item.id);

  return (
    <div>
      <label
        className={cn(
          "flex cursor-pointer items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent",
          checked && "bg-accent font-medium",
        )}
        style={{ paddingLeft: `${12 + depth * 20}px` }}
      >
        <input
          type="checkbox"
          checked={checked}
          onChange={() => onToggle(item.id)}
          className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
        />
        {item.name}
      </label>
      {item.children?.map((child) => (
        <TreeItem
          key={child.id}
          item={child}
          selectedIds={selectedIds}
          onToggle={onToggle}
          depth={depth + 1}
        />
      ))}
    </div>
  );
}

export function TermSelector({
  label,
  items,
  selectedIds,
  onChange,
  variant = "checkbox",
  grouped = false,
}: TermSelectorProps) {
  const handleToggle = (id: number) => {
    if (selectedIds.includes(id)) {
      onChange(selectedIds.filter((i) => i !== id));
    } else {
      onChange([...selectedIds, id]);
    }
  };

  const groups = grouped
    ? items.reduce<Record<string, TermItem[]>>(
        (acc, item) => {
          const group = item.group || "Other";
          if (!acc[group]) acc[group] = [];
          acc[group].push(item);
          return acc;
        },
        {},
      )
    : null;

  const renderItems = (itemsList: TermItem[]) => {
    if (variant === "tree") {
      return itemsList.map((item) => (
        <TreeItem key={item.id} item={item} selectedIds={selectedIds} onToggle={handleToggle} />
      ));
    }

    if (variant === "chip") {
      return (
        <div className="flex flex-wrap gap-1.5">
          {itemsList.map((item) => (
            <ChipItem key={item.id} item={item} selected={selectedIds.includes(item.id)} onToggle={handleToggle} />
          ))}
        </div>
      );
    }

    return itemsList.map((item) => (
      <CheckboxItem key={item.id} item={item} checked={selectedIds.includes(item.id)} onToggle={handleToggle} />
    ));
  };

  return (
    <div className="space-y-1">
      <p className="text-sm font-medium">{label}</p>
      <div className="rounded-md border p-1">
        {groups
          ? Object.entries(groups).map(([groupName, groupItems]) => (
              <div key={groupName} className="py-1">
                <p className="px-3 pb-1 text-xs font-medium text-muted-foreground">{groupName}</p>
                {variant === "chip" ? (
                  <div className="flex flex-wrap gap-1.5 px-3">{renderItems(groupItems)}</div>
                ) : (
                  renderItems(groupItems)
                )}
              </div>
            ))
          : renderItems(items)}
      </div>
    </div>
  );
}
