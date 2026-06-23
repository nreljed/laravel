import { Section, SectionField } from "@/Components/section"
import { Input } from "@/Components/ui/input"
import { Switch } from "@/Components/ui/switch"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select"
import { router, usePage } from "@inertiajs/react"
import { toast } from "sonner"
import { useState } from "react"
import { ShoppingCart } from "lucide-react"

const CURRENCY_OPTIONS = [
  { value: "USD", label: "US Dollar ($)" },
  { value: "EUR", label: "Euro (€)" },
  { value: "GBP", label: "British Pound (£)" },
  { value: "TND", label: "Tunisian Dinar (د.ت)" },
]

const WEIGHT_UNITS = [
  { value: "kg", label: "kg" },
  { value: "g", label: "g" },
  { value: "lb", label: "lb" },
  { value: "oz", label: "oz" },
]

const DIMENSION_UNITS = [
  { value: "cm", label: "cm" },
  { value: "m", label: "m" },
  { value: "in", label: "in" },
  { value: "ft", label: "ft" },
]

export function ECommerceSection({ asAccordion = false }: { asAccordion?: boolean }) {
  const flash = usePage().props.flash as Record<string, string> | undefined
  const [saving, setSaving] = useState(false)
  const [settings, setSettings] = useState({
    default_product_status: "draft",
    low_stock_threshold: "5",
    show_out_of_stock: false,
    currency: "EUR",
    currency_position: "before",
    weight_unit: "kg",
    dimension_unit: "cm",
    enable_reviews: true,
    enable_wishlist: true,
    auto_complete_orders: false,
  })

  if (flash?.status) toast.success(flash.status)

  const handleChange = (field: string, value: string | boolean) => {
    setSettings((prev) => ({ ...prev, [field]: value }))
  }

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault()
    if (saving) return
    setSaving(true)
    router.put(route("dashboard.admin.ecommerce.settings.update"), settings, {
      preserveScroll: true,
      onSuccess: () => { toast.success("E-Commerce settings saved."); setSaving(false) },
      onError: () => { toast.error("Failed to save."); setSaving(false) },
    })
  }

  return (
    <Section
      title="E-Commerce Settings"
      description="Configure store defaults, currency, and product behavior."
      icon={ShoppingCart}
      asAccordion={asAccordion}
      onSubmit={handleSave}
      loading={saving}
      submitLabel="Save E-Commerce Settings"
    >
      <div className="grid grid-cols-2 gap-4">
        <SectionField label="Default Product Status" htmlFor="default_product_status">
          <Select
            value={settings.default_product_status}
            onValueChange={(v) => handleChange("default_product_status", v)}
          >
            <SelectTrigger id="default_product_status">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="published">Published</SelectItem>
            </SelectContent>
          </Select>
        </SectionField>
        <SectionField label="Low Stock Threshold" htmlFor="low_stock_threshold">
          <Input
            id="low_stock_threshold"
            type="number"
            min="0"
            max="999"
            value={settings.low_stock_threshold}
            onChange={(e) => handleChange("low_stock_threshold", e.target.value)}
          />
        </SectionField>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <SectionField label="Currency" htmlFor="currency">
          <Select
            value={settings.currency}
            onValueChange={(v) => handleChange("currency", v)}
          >
            <SelectTrigger id="currency">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {CURRENCY_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </SectionField>
        <SectionField label="Currency Position" htmlFor="currency_position">
          <Select
            value={settings.currency_position}
            onValueChange={(v) => handleChange("currency_position", v)}
          >
            <SelectTrigger id="currency_position">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="before">Before (€10)</SelectItem>
              <SelectItem value="after">After (10€)</SelectItem>
            </SelectContent>
          </Select>
        </SectionField>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <SectionField label="Weight Unit" htmlFor="weight_unit">
          <Select
            value={settings.weight_unit}
            onValueChange={(v) => handleChange("weight_unit", v)}
          >
            <SelectTrigger id="weight_unit">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {WEIGHT_UNITS.map((u) => (
                <SelectItem key={u.value} value={u.value}>{u.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </SectionField>
        <SectionField label="Dimension Unit" htmlFor="dimension_unit">
          <Select
            value={settings.dimension_unit}
            onValueChange={(v) => handleChange("dimension_unit", v)}
          >
            <SelectTrigger id="dimension_unit">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {DIMENSION_UNITS.map((u) => (
                <SelectItem key={u.value} value={u.value}>{u.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </SectionField>
      </div>

      <div className="flex flex-wrap items-center gap-6 pt-2">
        {[
          { key: "show_out_of_stock", label: "Show Out of Stock" },
          { key: "enable_reviews", label: "Enable Reviews" },
          { key: "enable_wishlist", label: "Enable Wishlist" },
          { key: "auto_complete_orders", label: "Auto-Complete Orders" },
        ].map(({ key, label }) => (
          <label key={key} className="flex items-center gap-2 cursor-pointer">
            <Switch
              checked={(settings as Record<string, boolean | string>)[key] as boolean}
              onCheckedChange={(v) => handleChange(key, v)}
            />
            <span className="text-sm">{label}</span>
          </label>
        ))}
      </div>
    </Section>
  )
}
