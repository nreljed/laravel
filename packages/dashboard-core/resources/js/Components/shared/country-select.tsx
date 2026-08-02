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
import { COUNTRY_NAMES } from "@/lib/country-names";

interface CountrySelectProps {
  value?: string;
  onChange: (_value: string) => void;
  countries?: Record<string, string>;
  disabled?: boolean;
  placeholder?: string;
  id?: string;
}

function CountrySelect({
  value,
  onChange,
  countries,
  disabled,
  placeholder = "Select country...",
  id,
}: CountrySelectProps) {
  const [open, setOpen] = React.useState(false);

  const items = React.useMemo(
    () =>
      Object.entries(countries || COUNTRY_NAMES).map(([code, name]) => ({
        value: code,
        label: name,
      })),
    [countries],
  );

  const selected = React.useMemo(() => items.find((item) => item.value === value), [items, value]);

  return (
    <Popover open={open && !disabled} onOpenChange={setOpen}>
      <PopoverTrigger
        render={
          <Button
            id={id}
            variant="outline"
            role="combobox"
            disabled={disabled}
            className="w-full justify-between font-normal"
          />
        }
      >
        {selected ? selected.label : placeholder}
        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
      </PopoverTrigger>
      <PopoverContent className="w-[--anchor-width] p-0">
        <Command>
          <CommandInput placeholder="Search country..." />
          <CommandList>
            <CommandEmpty>No country found.</CommandEmpty>
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

export { CountrySelect };
