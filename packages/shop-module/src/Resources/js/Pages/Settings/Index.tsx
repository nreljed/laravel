import { Head, useForm, usePage } from "@inertiajs/react";
import type { PageProps, FormDataKeys, FormDataValues } from "@inertiajs/core";
import type { ChangeEvent, FormEvent } from "react";
import { Store, Globe2, Coins, Percent, Boxes, ShoppingCart, Ruler, Sparkles, Settings2 } from "lucide-react";
import AdminLayout from "@nreljed/platform/Layouts/AdminLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Switch } from "@nreljed/platform/Components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@nreljed/platform/Components/ui/select";
import { SettingsAccordion } from "@nreljed/platform/Components/shared/settings-accordion";
import { toast } from "@nreljed/platform/Components/ui/toast";
import { cn } from "@nreljed/platform/lib/utils";

interface Country {
    code: string;
    name: string;
}

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface SettingsPage {
    settings: Record<string, string | number | boolean | string[]>;
    countries: Country[];
    currencies: Currency[];
}

interface SettingsForm {
    store_name: string;
    store_email: string;
    store_address_line1: string;
    store_address_line2: string;
    store_city: string;
    store_postcode: string;
    base_country: string;
    selling_locations_mode: string;
    selling_locations: string[];
    default_product_status: string;
    low_stock_threshold: number;
    hold_stock_enabled: boolean;
    hold_stock_minutes: number;
    backorder_default: string;
    inventory_enabled: boolean;
    show_out_of_stock: boolean;
    currency: string;
    currency_symbol: string;
    currency_position: string;
    thousands_separator: string;
    decimal_separator: string;
    price_decimals: number;
    prices_include_tax: boolean;
    tax_based_on: string;
    tax_shipping: boolean;
    tax_display: string;
    weight_unit: string;
    dimension_unit: string;
    enable_reviews: boolean;
    enable_wishlist: boolean;
    auto_complete_orders: boolean;
    abandoned_cart_enabled: boolean;
    abandoned_cart_hours_until_abandoned: number;
    abandoned_cart_sequence_hours: string;
    sale_scheduling_enabled: boolean;
    sale_scheduling_normalize_to_day_boundaries: boolean;
    enable_guest_checkout: boolean;
    require_company_field: boolean;
    require_terms: boolean;
    checkout_default_country: string;
}

type ToggleKey =
    | "hold_stock_enabled"
    | "inventory_enabled"
    | "show_out_of_stock"
    | "prices_include_tax"
    | "tax_shipping"
    | "enable_reviews"
    | "enable_wishlist"
    | "auto_complete_orders"
    | "abandoned_cart_enabled"
    | "sale_scheduling_enabled"
    | "sale_scheduling_normalize_to_day_boundaries"
    | "enable_guest_checkout"
    | "require_company_field"
    | "require_terms";

const POSITION_OPTIONS = [
    { value: "before", label: "1,234.56 €" },
    { value: "after", label: "1,234.56 €" },
    { value: "before_space", label: "€ 1,234.56" },
    { value: "after_space", label: "1,234.56 €" },
];

export default function ShopSettingsPage() {
    const { settings: initial, countries, currencies } = usePage<PageProps & SettingsPage>().props;

    const { data, setData, put, processing, errors } = useForm<SettingsForm>({
        store_name: String(initial.store_name ?? ""),
        store_email: String(initial.store_email ?? ""),
        store_address_line1: String(initial.store_address_line1 ?? ""),
        store_address_line2: String(initial.store_address_line2 ?? ""),
        store_city: String(initial.store_city ?? ""),
        store_postcode: String(initial.store_postcode ?? ""),
        base_country: String(initial.base_country ?? "TN"),
        selling_locations_mode: String(initial.selling_locations_mode ?? "all"),
        selling_locations: (initial.selling_locations ?? []) as string[],
        default_product_status: String(initial.default_product_status ?? "draft"),
        low_stock_threshold: Number(initial.low_stock_threshold ?? 5),
        hold_stock_enabled: Boolean(initial.hold_stock_enabled),
        hold_stock_minutes: Number(initial.hold_stock_minutes ?? 60),
        backorder_default: String(initial.backorder_default ?? "do_not_allow"),
        inventory_enabled: Boolean(initial.inventory_enabled),
        show_out_of_stock: Boolean(initial.show_out_of_stock),
        currency: String(initial.currency ?? "EUR"),
        currency_symbol: String(initial.currency_symbol ?? "€"),
        currency_position: String(initial.currency_position ?? "before"),
        thousands_separator: String(initial.thousands_separator ?? ","),
        decimal_separator: String(initial.decimal_separator ?? "."),
        price_decimals: Number(initial.price_decimals ?? 2),
        prices_include_tax: Boolean(initial.prices_include_tax),
        tax_based_on: String(initial.tax_based_on ?? "billing"),
        tax_shipping: Boolean(initial.tax_shipping),
        tax_display: String(initial.tax_display ?? "exc"),
        weight_unit: String(initial.weight_unit ?? "kg"),
        dimension_unit: String(initial.dimension_unit ?? "cm"),
        enable_reviews: Boolean(initial.enable_reviews),
        enable_wishlist: Boolean(initial.enable_wishlist),
        auto_complete_orders: Boolean(initial.auto_complete_orders),
        abandoned_cart_enabled: Boolean(initial.abandoned_cart_enabled),
        abandoned_cart_hours_until_abandoned: Number(initial.abandoned_cart_hours_until_abandoned ?? 1),
        abandoned_cart_sequence_hours: String(initial.abandoned_cart_sequence_hours ?? "1,24,72"),
        sale_scheduling_enabled: Boolean(initial.sale_scheduling_enabled),
        sale_scheduling_normalize_to_day_boundaries: Boolean(initial.sale_scheduling_normalize_to_day_boundaries),
        enable_guest_checkout: Boolean(initial.enable_guest_checkout),
        require_company_field: Boolean(initial.require_company_field),
        require_terms: Boolean(initial.require_terms),
        checkout_default_country: String(initial.checkout_default_country ?? ""),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route("admin.shop.settings.update"), {
            preserveScroll: true,
            onSuccess: () => toast.add({ title: "Shop settings saved.", type: "success" }),
            onError: () => toast.add({ title: "Failed to save settings. Check the form for errors.", type: "error" }),
        });
    };

    /* Inertia types setData against the form-data shape of a field
     * (`FormDataValues`), which for nested/array fields is not the raw field
     * type. Callers here always hold plain top-level keys and raw values, so
     * the widening is confined to this one adapter instead of leaking a cast
     * into every call site. */
    const set = <K extends keyof SettingsForm>(key: K, value: SettingsForm[K]) =>
        setData(key as FormDataKeys<SettingsForm>, value as FormDataValues<SettingsForm, FormDataKeys<SettingsForm>>);

    const toggleSellingLocation = (code: string) => {
        const current = data.selling_locations as string[];
        set("selling_locations", current.includes(code) ? current.filter((c) => c !== code) : [...current, code]);
    };

    const field = <K extends keyof SettingsForm>(key: K) => ({
        value: data[key] as string | number,
        onChange: (e: ChangeEvent<HTMLInputElement>) =>
            set(key, (typeof data[key] === "number" ? Number(e.target.value) : e.target.value) as SettingsForm[K]),
    });

    const accordionItems = [
        {
            value: "store",
            icon: Store,
            title: "Store Profile",
            description: "Your store name, contact email and registered address.",
            content: (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="store_name">Store Name</Label>
                        <Input id="store_name" {...field("store_name")} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="store_email">Contact Email</Label>
                        <Input id="store_email" type="email" {...field("store_email")} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="store_address_line1">Address Line 1</Label>
                        <Input id="store_address_line1" {...field("store_address_line1")} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="store_address_line2">Address Line 2</Label>
                        <Input id="store_address_line2" {...field("store_address_line2")} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="store_city">City</Label>
                        <Input id="store_city" {...field("store_city")} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="store_postcode">Postcode</Label>
                        <Input id="store_postcode" {...field("store_postcode")} />
                    </div>
                </div>
            ),
        },
        {
            value: "locations",
            icon: Globe2,
            title: "Selling Locations",
            description: "Where your store sells from and which countries you ship to.",
            content: (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="base_country">Store Base Country</Label>
                            <Select value={data.base_country as string} onValueChange={(v) => set("base_country", v)}>
                                <SelectTrigger id="base_country">
                                    <SelectValue placeholder="Select country" />
                                </SelectTrigger>
                                <SelectContent className="max-h-[300px]">
                                    {countries.map((c) => (
                                        <SelectItem key={c.code} value={c.code}>{c.name} ({c.code})</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Selling Location</Label>
                            <Select value={data.selling_locations_mode as string} onValueChange={(v) => set("selling_locations_mode", v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Sell to all countries</SelectItem>
                                    <SelectItem value="specific">Sell to specific countries</SelectItem>
                                    <SelectItem value="except">Sell to all countries except selected</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    {data.selling_locations_mode !== "all" && (
                        <div className="space-y-2">
                            <Label>
                                {data.selling_locations_mode === "specific" ? "Countries to sell to" : "Countries to exclude"}
                            </Label>
                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-64 overflow-y-auto border rounded-md p-3">
                                {countries.map((c) => {
                                    const selected = (data.selling_locations as string[]).includes(c.code);
                                    return (
                                        <button
                                            key={c.code}
                                            type="button"
                                            onClick={() => toggleSellingLocation(c.code)}
                                            className={cn(
                                                "text-left text-sm px-3 py-1.5 rounded-md border transition-colors",
                                                selected
                                                    ? "bg-primary text-primary-foreground border-primary"
                                                    : "bg-card hover:bg-muted",
                                            )}
                                        >
                                            {c.name}
                                        </button>
                                    );
                                })}
                            </div>
                            {errors.selling_locations && (
                                <p className="text-sm text-destructive">{errors.selling_locations}</p>
                            )}
                        </div>
                    )}
                </div>
            ),
        },
        {
            value: "currency",
            icon: Coins,
            title: "Currency & Pricing",
            description: "Store currency, symbol placement and number formatting.",
            content: (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="currency">Store Currency</Label>
                        <Select value={data.currency as string} onValueChange={(v) => set("currency", v)}>
                            <SelectTrigger id="currency">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {currencies.map((c) => (
                                    <SelectItem key={c.code} value={c.code}>{c.name} ({c.code})</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="currency_symbol">Currency Symbol</Label>
                        <Input id="currency_symbol" {...field("currency_symbol")} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="currency_position">Symbol Position</Label>
                        <Select value={data.currency_position as string} onValueChange={(v) => set("currency_position", v)}>
                            <SelectTrigger id="currency_position">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {POSITION_OPTIONS.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label} ({o.value.replace("_", " ")})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="thousands_separator">Thousands Separator</Label>
                            <Input id="thousands_separator" maxLength={2} {...field("thousands_separator")} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="decimal_separator">Decimal Separator</Label>
                            <Input id="decimal_separator" maxLength={2} {...field("decimal_separator")} />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="price_decimals">Decimal Places</Label>
                        <Input id="price_decimals" type="number" min={0} max={4} {...field("price_decimals")} />
                    </div>
                </div>
            ),
        },
        {
            value: "tax",
            icon: Percent,
            title: "Tax Options",
            description: "How taxes are calculated and displayed at checkout.",
            content: (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="tax_based_on">Calculate Tax Based On</Label>
                            <Select value={data.tax_based_on as string} onValueChange={(v) => set("tax_based_on", v)}>
                                <SelectTrigger id="tax_based_on">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="billing">Customer billing address</SelectItem>
                                    <SelectItem value="shipping">Customer shipping address</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="tax_display">Display Prices</Label>
                            <Select value={data.tax_display as string} onValueChange={(v) => set("tax_display", v)}>
                                <SelectTrigger id="tax_display">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="inc">Including tax</SelectItem>
                                    <SelectItem value="exc">Excluding tax</SelectItem>
                                    <SelectItem value="both">Including and excluding tax</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <ToggleRow toggle={{ key: "prices_include_tax", label: "Prices include tax", description: "Product prices already include applicable taxes." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "tax_shipping", label: "Tax shipping", description: "Apply tax to shipping costs." }} data={data} set={set} />
                </div>
            ),
        },
        {
            value: "inventory",
            icon: Boxes,
            title: "Inventory",
            description: "Default product status, stock thresholds and hold stock behaviour.",
            content: (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="default_product_status">Default Product Status</Label>
                            <Select value={data.default_product_status as string} onValueChange={(v) => set("default_product_status", v)}>
                                <SelectTrigger id="default_product_status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">Draft</SelectItem>
                                    <SelectItem value="published">Published</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="backorder_default">Default Backorder Policy</Label>
                            <Select value={data.backorder_default as string} onValueChange={(v) => set("backorder_default", v)}>
                                <SelectTrigger id="backorder_default">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="do_not_allow">Do not allow</SelectItem>
                                    <SelectItem value="allow_notify">Allow, but notify customer</SelectItem>
                                    <SelectItem value="allow">Allow</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="low_stock_threshold">Low Stock Threshold</Label>
                            <Input id="low_stock_threshold" type="number" min={0} {...field("low_stock_threshold")} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="hold_stock_minutes">Hold Stock Minutes</Label>
                            <Input id="hold_stock_minutes" type="number" min={1} {...field("hold_stock_minutes")} />
                            <p className="text-xs text-muted-foreground">Release unpaid orders after this many minutes.</p>
                        </div>
                    </div>
                    <ToggleRow toggle={{ key: "hold_stock_enabled", label: "Hold stock for unpaid orders", description: "Reserve stock when an order is placed, released if payment is not completed." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "inventory_enabled", label: "Track inventory", description: "Enable stock management for products." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "show_out_of_stock", label: "Show out of stock products", description: "Display products that are out of stock in the storefront." }} data={data} set={set} />
                </div>
            ),
        },
        {
            value: "checkout",
            icon: ShoppingCart,
            title: "Checkout",
            description: "Guest checkout, B2B fields and the default country for new checkouts.",
            content: (
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="checkout_default_country">Default Checkout Country</Label>
                        <Select value={data.checkout_default_country || "none"} onValueChange={(v) => set("checkout_default_country", v === "none" ? "" : v)}>
                            <SelectTrigger id="checkout_default_country">
                                <SelectValue placeholder="None (customer picks)" />
                            </SelectTrigger>
                            <SelectContent className="max-h-[300px]">
                                <SelectItem value="none">None (customer picks)</SelectItem>
                                {countries.map((c) => (
                                    <SelectItem key={c.code} value={c.code}>{c.name} ({c.code})</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <ToggleRow toggle={{ key: "enable_guest_checkout", label: "Guest checkout", description: "Allow guests to place orders without an account." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "require_company_field", label: "Require company field", description: "Make the company field mandatory at checkout (B2B)." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "require_terms", label: "Require terms agreement", description: "Customers must accept the terms before placing an order." }} data={data} set={set} />
                </div>
            ),
        },
        {
            value: "units",
            icon: Ruler,
            title: "Units of Measure",
            description: "Weight and dimension units used across the store.",
            content: (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="weight_unit">Weight Unit</Label>
                        <Select value={data.weight_unit as string} onValueChange={(v) => set("weight_unit", v)}>
                            <SelectTrigger id="weight_unit">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="kg">Kilograms (kg)</SelectItem>
                                <SelectItem value="g">Grams (g)</SelectItem>
                                <SelectItem value="lb">Pounds (lb)</SelectItem>
                                <SelectItem value="oz">Ounces (oz)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="dimension_unit">Dimension Unit</Label>
                        <Select value={data.dimension_unit as string} onValueChange={(v) => set("dimension_unit", v)}>
                            <SelectTrigger id="dimension_unit">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cm">Centimeters (cm)</SelectItem>
                                <SelectItem value="m">Meters (m)</SelectItem>
                                <SelectItem value="in">Inches (in)</SelectItem>
                                <SelectItem value="ft">Feet (ft)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            ),
        },
        {
            value: "features",
            icon: Sparkles,
            title: "Features & Automation",
            description: "Reviews, wishlist, abandoned carts and sale scheduling.",
            content: (
                <div className="space-y-3">
                    <ToggleRow toggle={{ key: "enable_reviews", label: "Enable reviews", description: "Allow customers to rate and review products." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "enable_wishlist", label: "Enable wishlist", description: "Let customers save products to a wishlist." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "auto_complete_orders", label: "Auto-complete paid orders", description: "Mark paid orders as completed automatically." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "abandoned_cart_enabled", label: "Abandoned cart recovery", description: "Send recovery emails for abandoned carts." }} data={data} set={set} />
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="abandoned_cart_hours_until_abandoned">Abandon After (hours)</Label>
                            <Input id="abandoned_cart_hours_until_abandoned" type="number" min={1} {...field("abandoned_cart_hours_until_abandoned")} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="abandoned_cart_sequence_hours">Recovery Sequence (hours)</Label>
                            <Input id="abandoned_cart_sequence_hours" {...field("abandoned_cart_sequence_hours")} />
                            <p className="text-xs text-muted-foreground">Comma-separated delays, e.g. 1,24,72</p>
                        </div>
                    </div>
                    <ToggleRow toggle={{ key: "sale_scheduling_enabled", label: "Sale scheduling", description: "Schedule sale prices in advance." }} data={data} set={set} />
                    <ToggleRow toggle={{ key: "sale_scheduling_normalize_to_day_boundaries", label: "Normalize sales to day boundaries", description: "Start/end sales at midnight instead of the exact timestamp." }} data={data} set={set} />
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Shop Settings" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <Settings2 className="h-6 w-6" />
                            Shop Settings
                        </h1>
                        <p className="text-muted-foreground text-sm mt-1">
                            Configure store profile, locations, currency, taxes and checkout behaviour.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <SettingsAccordion items={accordionItems} />
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? "Saving..." : "Save Settings"}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

function ToggleRow({
    toggle,
    data,
    set,
}: {
    toggle: { key: ToggleKey; label: string; description: string };
    data: SettingsForm;
    set: (key: ToggleKey, value: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between rounded-lg border p-3">
            <div className="space-y-0.5">
                <Label className="font-medium">{toggle.label}</Label>
                <p className="text-xs text-muted-foreground">{toggle.description}</p>
            </div>
            <Switch
                checked={Boolean(data[toggle.key])}
                onCheckedChange={(v) => set(toggle.key, v === true)}
            />
        </div>
    );
}
