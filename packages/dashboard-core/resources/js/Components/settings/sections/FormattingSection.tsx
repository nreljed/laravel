import * as React from "react";
import { Section, SectionField } from "@/Components/shared/section";
import { Input } from "@/Components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { DollarSign, Ruler, Hash } from "lucide-react";

const CURRENCY_OPTIONS = [
  { value: "USD", label: "US Dollar ($)" },
  { value: "EUR", label: "Euro (€)" },
  { value: "GBP", label: "British Pound (£)" },
  { value: "TND", label: "Tunisian Dinar (د.ت)" },
  { value: "MAD", label: "Moroccan Dirham (MAD)" },
  { value: "DZD", label: "Algerian Dinar (DA)" },
  { value: "EGP", label: "Egyptian Pound (E£)" },
  { value: "SAR", label: "Saudi Riyal (ر.س)" },
  { value: "AED", label: "UAE Dirham (د.إ)" },
  { value: "QAR", label: "Qatari Riyal (ر.ق)" },
  { value: "KWD", label: "Kuwaiti Dinar (د.ك)" },
  { value: "BHD", label: "Bahraini Dinar (.د.ب)" },
  { value: "OMR", label: "Omani Rial (ر.ع)" },
  { value: "JOD", label: "Jordanian Dinar (د.أ)" },
  { value: "LYD", label: "Libyan Dinar (ل.د)" },
  { value: "SDG", label: "Sudanese Pound (ج.س.)" },
  { value: "SYP", label: "Syrian Pound (ل.س)" },
  { value: "IQD", label: "Iraqi Dinar (ع.د)" },
  { value: "IRR", label: "Iranian Rial (﷼)" },
  { value: "TRY", label: "Turkish Lira (₺)" },
];

const CURRENCY_POSITION_OPTIONS = [
  { value: "before", label: "Before ($100)" },
  { value: "after", label: "After (100$)" },
];

const COUNTRY_OPTIONS = [
  { value: "TN", label: "Tunisia" },
  { value: "US", label: "United States" },
  { value: "GB", label: "United Kingdom" },
  { value: "FR", label: "France" },
  { value: "DE", label: "Germany" },
  { value: "IT", label: "Italy" },
  { value: "ES", label: "Spain" },
  { value: "MA", label: "Morocco" },
  { value: "DZ", label: "Algeria" },
  { value: "LY", label: "Libya" },
  { value: "EG", label: "Egypt" },
  { value: "SA", label: "Saudi Arabia" },
  { value: "AE", label: "United Arab Emirates" },
  { value: "QA", label: "Qatar" },
  { value: "KW", label: "Kuwait" },
  { value: "BH", label: "Bahrain" },
  { value: "OM", label: "Oman" },
  { value: "JO", label: "Jordan" },
  { value: "CA", label: "Canada" },
  { value: "AU", label: "Australia" },
];

const SURFACE_UNIT_OPTIONS = [
  { value: "sq_ft", label: "Square Feet (ft²)" },
  { value: "sq_m", label: "Square Meters (m²)" },
  { value: "sq_yd", label: "Square Yards (yd²)" },
  { value: "acres", label: "Acres (ac)" },
  { value: "hectares", label: "Hectares (ha)" },
];

const THOUSANDS_SEPARATOR_OPTIONS = [
  { value: ",", label: "Comma (1,000)" },
  { value: " ", label: "Space (1 000)" },
  { value: ".", label: "Period (1.000)" },
];

const DECIMAL_SEPARATOR_OPTIONS = [
  { value: ".", label: "Period (100.50)" },
  { value: ",", label: "Comma (100,50)" },
];

interface FormattingSectionProps {
  data: Record<string, string | undefined>;
  onChange: (_field: string, _value: string) => void;
  errors: Record<string, unknown>;
  loading?: boolean;
  onSubmit?: (_e: React.FormEvent) => void;
}

function FormattingSection({ data, onChange, errors, loading, onSubmit }: FormattingSectionProps) {
  return (
    <div className="space-y-6">
      <Section
        title="Pricing & Currency"
        description="Global currency and price formatting defaults across the system."
        icon={DollarSign}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <SectionField
          label="Default Currency"
          htmlFor="default_currency"
          error={errors?.default_currency as string}
          description="The default currency for prices across the system."
        >
          <Select
            value={data?.default_currency || "USD"}
            onValueChange={(value) => onChange("default_currency", value ?? "")}
            disabled={loading}
          >
            <SelectTrigger id="default_currency">
              <SelectValue placeholder="Select currency" />
            </SelectTrigger>
            <SelectContent>
              {CURRENCY_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </SectionField>

        <SectionField
          label="Currency Position"
          htmlFor="currency_position"
          error={errors?.currency_position as string}
          description="Where to display the currency symbol relative to the amount."
        >
          <Select
            value={data?.currency_position || "before"}
            onValueChange={(value) => onChange("currency_position", value ?? "")}
            disabled={loading}
          >
            <SelectTrigger id="currency_position">
              <SelectValue placeholder="Select position" />
            </SelectTrigger>
            <SelectContent>
              {CURRENCY_POSITION_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </SectionField>

        <SectionField
          label="Empty Price Text"
          htmlFor="price_empty_text"
          error={errors?.price_empty_text as string}
          description="Text to display when price is not set (e.g., 'Contact us')."
        >
          <Input
            id="price_empty_text"
            value={data?.price_empty_text || ""}
            onChange={(e) => onChange("price_empty_text", e.target.value)}
            placeholder="Contact us"
            disabled={loading}
          />
        </SectionField>
      </Section>

      <Section
        title="Units & Measurement"
        description="Global defaults for location and surface area."
        icon={Ruler}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <SectionField
          label="Default Country"
          htmlFor="default_country"
          error={errors?.default_country as string}
          description="The default country."
        >
          <Select
            value={data?.default_country || "TN"}
            onValueChange={(value) => onChange("default_country", value ?? "")}
            disabled={loading}
          >
            <SelectTrigger id="default_country">
              <SelectValue placeholder="Select country" />
            </SelectTrigger>
            <SelectContent>
              {COUNTRY_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </SectionField>

        <SectionField
          label="Surface Unit"
          htmlFor="surface_unit"
          error={errors?.surface_unit as string}
          description="The default unit for surface area."
        >
          <Select
            value={data?.surface_unit || "sq_ft"}
            onValueChange={(value) => onChange("surface_unit", value ?? "")}
            disabled={loading}
          >
            <SelectTrigger id="surface_unit">
              <SelectValue placeholder="Select unit" />
            </SelectTrigger>
            <SelectContent>
              {SURFACE_UNIT_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </SectionField>
      </Section>

      <Section
        title="Number Formatting"
        description="How numbers are formatted throughout the system"
        icon={Hash}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <SectionField
            label="Thousands Separator"
            htmlFor="thousands_separator"
            error={errors?.thousands_separator as string}
            description="Character used to separate thousands in numbers."
          >
            <Select
              value={data?.thousands_separator || ","}
              onValueChange={(value) => onChange("thousands_separator", value ?? "")}
              disabled={loading}
            >
              <SelectTrigger id="thousands_separator">
                <SelectValue placeholder="Select separator" />
              </SelectTrigger>
              <SelectContent>
                {THOUSANDS_SEPARATOR_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>

          <SectionField
            label="Decimal Separator"
            htmlFor="decimal_separator"
            error={errors?.decimal_separator as string}
            description="Character used to separate decimal places."
          >
            <Select
              value={data?.decimal_separator || "."}
              onValueChange={(value) => onChange("decimal_separator", value ?? "")}
              disabled={loading}
            >
              <SelectTrigger id="decimal_separator">
                <SelectValue placeholder="Select separator" />
              </SelectTrigger>
              <SelectContent>
                {DECIMAL_SEPARATOR_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
        </div>
      </Section>
    </div>
  );
}

export { FormattingSection };
