import * as React from "react";
import * as RPNInput from "react-phone-number-input";
import { CheckIcon, ChevronsUpDown } from "lucide-react";
import flags from "react-phone-number-input/flags";
import type { CountryCode } from "libphonenumber-js/min";
import { Button } from "@/Components/ui/button";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/Components/ui/command";
import { Input } from "@/Components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { ScrollArea } from "@/Components/ui/scroll-area";
import { cn } from "@/lib/utils";

const toE164 = (val: string | undefined): string | undefined => {
  if (!val || typeof val !== "string") return undefined;
  if (val.startsWith("+")) return val;
  const digits = val.replaceAll(/\D/g, "");
  return digits ? `+${digits}` : undefined;
};

interface PhoneInputProps {
  className?: string;
  value?: string;
  onChange?: (_value: string) => void;
}

const PhoneInput = React.forwardRef<HTMLInputElement, PhoneInputProps>(
  ({ className, onChange, value, ...props }, ref) => {
    const e164Value = React.useMemo(() => toE164(value), [value]);

    return (
      <RPNInput.default
        ref={ref as React.Ref<React.ComponentRef<typeof RPNInput.default>>}
        className={cn("flex", className)}
        flagComponent={FlagComponent}
        countrySelectComponent={CountrySelect}
        inputComponent={InputComponent}
        smartCaret={false}
        value={e164Value}
        onChange={(value) => onChange?.(value || "")}
        {...props}
      />
    );
  },
);
PhoneInput.displayName = "PhoneInput";

const InputWithRef = Input as React.ForwardRefExoticComponent<
  React.ComponentPropsWithoutRef<typeof Input> & React.RefAttributes<HTMLInputElement>
>;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const InputComponent = React.forwardRef<HTMLInputElement, Record<string, any>>(
  ({ className, ...props }, ref) => (
    <InputWithRef className={cn("rounded-e-lg rounded-s-none", className)} {...props} ref={ref} />
  ),
);
InputComponent.displayName = "InputComponent";

interface CountrySelectProps {
  disabled?: boolean;
  value?: string;
  options?: Array<{ value?: string; label: string }>;
  onChange: (_value?: string) => void;
}

const CountrySelect = ({
  disabled,
  value: selectedCountry,
  options: countryList,
  onChange,
}: CountrySelectProps) => {
  const [searchValue, setSearchValue] = React.useState("");
  const [isOpen, setIsOpen] = React.useState(false);

  return (
    <Popover
      open={isOpen}
      onOpenChange={(open) => {
        setIsOpen(open);
        if (open) {
          setSearchValue("");
        }
      }}
    >
      <PopoverTrigger
        render={
          <Button
            type="button"
            variant="outline"
            className="flex gap-1 rounded-e-none rounded-s-lg border-r-0 px-3 focus:z-10"
            disabled={disabled}
          />
        }
      >
        <FlagComponent country={selectedCountry} countryName={selectedCountry} />
        <ChevronsUpDown
          className={cn(
            "-mr-2 size-4 opacity-50 dark:opacity-70",
            disabled ? "hidden" : "opacity-100",
          )}
        />
      </PopoverTrigger>
      <PopoverContent className="w-[300px] p-0">
        <Command>
          <CommandInput
            value={searchValue}
            onValueChange={(value: string) => {
              setSearchValue(value);
              setTimeout(() => {
                const viewportElement = document.querySelector(
                  "[data-radix-scroll-area-viewport]",
                ) as HTMLElement | null;
                viewportElement?.scrollTo(0, 0);
              }, 0);
            }}
            placeholder="Search country..."
          />
          <CommandList>
            <ScrollArea className="h-72">
              <CommandEmpty>No country found.</CommandEmpty>
              <CommandGroup>
                {(countryList ?? []).map(({ value, label }) =>
                  value ? (
                    <CountrySelectOption
                      key={value}
                      country={value}
                      countryName={label}
                      selectedCountry={selectedCountry}
                      onChange={onChange}
                      onSelectComplete={() => setIsOpen(false)}
                    />
                  ) : null,
                )}
              </CommandGroup>
            </ScrollArea>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
};

interface CountrySelectOptionProps {
  country: string;
  countryName: string;
  selectedCountry?: string;
  onChange: (_value?: string) => void;
  onSelectComplete: () => void;
}

const CountrySelectOption = ({
  country,
  countryName,
  selectedCountry,
  onChange,
  onSelectComplete,
}: CountrySelectOptionProps) => {
  const handleSelect = () => {
    onChange(country);
    onSelectComplete();
  };

  return (
    <CommandItem className="gap-2" onSelect={handleSelect}>
      <FlagComponent country={country} countryName={countryName} />
      <span className="flex-1 text-sm">{countryName}</span>
      <span className="text-sm text-foreground/70">{`+${RPNInput.getCountryCallingCode(country as CountryCode)}`}</span>
      <CheckIcon
        className={cn("ml-auto size-4", country === selectedCountry ? "opacity-100" : "opacity-0")}
      />
    </CommandItem>
  );
};

interface FlagComponentProps {
  country?: string;
  countryName?: string;
}

const FlagComponent = ({ country, countryName }: FlagComponentProps) => {
  const Flag = flags[country as CountryCode] as React.ComponentType<{ title?: string }> | undefined;

  return (
    <span className="flex h-4 w-6 items-center justify-center overflow-hidden rounded-sm bg-foreground/20">
      {Flag && <Flag title={countryName} />}
    </span>
  );
};

export { PhoneInput };
