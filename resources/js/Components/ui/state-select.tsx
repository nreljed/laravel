import * as React from "react";
import { Check, ChevronsUpDown } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/Components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Input } from "@/Components/ui/input";

interface StateSelectProps {
  value?: string;
  onChange: (_value: string) => void;
  states?: Record<string, Record<string, string>>;
  countryCode?: string;
  disabled?: boolean;
  placeholder?: string;
  textPlaceholder?: string;
  id?: string;
  [key: string]: unknown;
}

function StateSelect({
  value,
  onChange,
  states,
  countryCode,
  disabled,
  placeholder = "Select state...",
  textPlaceholder = "Enter state...",
  id,
  ...props
}: StateSelectProps) {
  const [open, setOpen] = React.useState(false);

  const countryStates = React.useMemo(
    () => (states && countryCode ? states[countryCode] : null),
    [states, countryCode],
  );

  const hasStates = React.useMemo(
    () => countryStates && Object.keys(countryStates).length > 0,
    [countryStates],
  );

  const items = React.useMemo(
    () =>
      hasStates && countryStates
        ? Object.entries(countryStates).map(([code, name]) => ({
            value: code,
            label: name,
          }))
        : [],
    [hasStates, countryStates],
  );

  const selected = React.useMemo(
    () => items.find((item) => item.value === value) || null,
    [items, value],
  );

  if (hasStates) {
    return (
      <Popover open={open && !disabled} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            id={id}
            variant="outline"
            role="combobox"
            disabled={disabled}
            className="w-full justify-between font-normal"
            {...props}
          >
            {selected ? selected.label : placeholder}
            <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[--radix-popover-trigger-width] p-0">
          <Command>
            <CommandInput placeholder="Search state..." />
            <CommandList>
              <CommandEmpty>No state found.</CommandEmpty>
              <CommandGroup>
                {items.map((item) => (
                  <CommandItem
                    key={item.value}
                    value={`${item.label} ${item.value}`}
                    onSelect={() => {
                      onChange(item.value === value ? "" : item.value);
                      setOpen(false);
                    }}
                  >
                    {item.label}
                    <Check
                      className={cn(
                        "ml-auto size-4",
                        value === item.value ? "opacity-100" : "opacity-0",
                      )}
                    />
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    );
  }

  return (
    <Input
      id={id}
      value={value || ""}
      onChange={(e) => onChange(e.target.value)}
      placeholder={textPlaceholder}
      disabled={disabled}
      {...props}
    />
  );
}

export { StateSelect };
