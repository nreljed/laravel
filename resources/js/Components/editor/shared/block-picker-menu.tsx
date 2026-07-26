import { cn } from "@/lib/utils";
import type { BlockOption } from "../shared/block-options";

interface BlockPickerMenuProps {
  options: BlockOption[];
  highlightedIndex: number;
  onHighlight: (_index: number) => void;
  onSelect: (_option: BlockOption) => void;
  maxItems?: number;
}

export function BlockPickerMenu({
  options,
  highlightedIndex,
  onHighlight,
  onSelect,
  maxItems = 30,
}: BlockPickerMenuProps) {
  const visible = options.slice(0, maxItems);

  if (visible.length === 0) {
    return <div className="px-2 py-1.5 text-sm text-muted-foreground">No results</div>;
  }

  return (
    <ul role="listbox">
      {visible.map((option, i) => {
        const Icon = option.IconComponent;
        return (
          <li
            key={option.key}
            tabIndex={-1}
            className={cn(
              "relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none",
              highlightedIndex === i && "bg-accent text-accent-foreground",
            )}
            ref={option.setRefElement}
            role="option"
            aria-selected={highlightedIndex === i}
            id={`block-picker-item-${i}`}
            onMouseEnter={() => onHighlight(i)}
            onClick={() => onSelect(option)}
          >
            <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
            <span>{option.title}</span>
          </li>
        );
      })}
    </ul>
  );
}
