import { useState } from "react";
import { usePage, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Checkbox } from "@/Components/ui/checkbox";
import { Separator } from "@/Components/ui/separator";
import { Avatar, AvatarFallback, AvatarImage } from "@/Components/ui/avatar";
import {
  Field,
  FieldLabel,
  FieldDescription,
  FieldError,
  FieldContent,
  FieldTitle,
} from "@/Components/ui/field";
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxInput,
  ComboboxItem,
  ComboboxLabel,
  ComboboxList,
  ComboboxTrigger,
  ComboboxValue,
} from "@/Components/ui/combobox";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/Components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/Components/ui/alert-dialog";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/Components/ui/collapsible";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Section, SectionField } from "@/Components/shared/section";
import { SettingsAccordion } from "@/Components/shared/settings-accordion";
import { BrandLogo } from "@/Components/frontend/BrandLogo";
import {
  Mail,
  Lock,
  AlertTriangle,
  Eye,
  Info,
  User,
  Building2,
  Loader2,
  ChevronDown,
} from "lucide-react";

type BrandProps = { logo?: string; favicon?: string; name?: string };

const BUTTON_VARIANTS = [
  { name: "default", label: "Default" },
  { name: "secondary", label: "Secondary" },
  { name: "destructive", label: "Destructive" },
  { name: "outline", label: "Outline" },
  { name: "ghost", label: "Ghost" },
  { name: "link", label: "Link" },
] as const;

const BADGE_VARIANTS = [
  { name: "default", label: "Default" },
  { name: "secondary", label: "Secondary" },
  { name: "destructive", label: "Destructive" },
  { name: "outline", label: "Outline" },
  { name: "ghost", label: "Ghost" },
  { name: "link", label: "Link" },
  { name: "success", label: "Success" },
  { name: "warning", label: "Warning" },
  { name: "error", label: "Error" },
  { name: "info", label: "Info" },
] as const;

const FRAMEWORKS = ["React", "Vue", "Svelte", "Angular", "Solid"];

const CITY_GROUPS = [
  { value: "Tunis", items: ["Tunis", "La Marsa", "Carthage", "Le Bardo", "Sidi Bou Said"] },
  {
    value: "Ben Arous",
    items: ["Ben Arous", "El Mourouj", "Hammam Lif", "Hammam Chott", "Bou Mhel"],
  },
  { value: "Sousse", items: ["Sousse", "Hammam Sousse", "Msaken", "Akouda", "Kalaa Kebira"] },
];

const COLOR_TOKENS = [
  { var: "--color-background", label: "background" },
  { var: "--color-foreground", label: "foreground" },
  { var: "--color-card", label: "card" },
  { var: "--color-popover", label: "popover" },
  { var: "--color-primary", label: "primary" },
  { var: "--color-primary-foreground", label: "primary-fg" },
  { var: "--color-secondary", label: "secondary" },
  { var: "--color-secondary-foreground", label: "secondary-fg" },
  { var: "--color-muted", label: "muted" },
  { var: "--color-muted-foreground", label: "muted-fg" },
  { var: "--color-accent", label: "accent" },
  { var: "--color-accent-foreground", label: "accent-fg" },
  { var: "--color-destructive", label: "destructive" },
  { var: "--color-destructive-foreground", label: "destructive-fg" },
  { var: "--color-border", label: "border" },
  { var: "--color-input", label: "input" },
  { var: "--color-ring", label: "ring" },
  { var: "--color-sidebar", label: "sidebar" },
  { var: "--color-sidebar-foreground", label: "sidebar-fg" },
] as const;

function VariantRow({
  label,
  disabled = false,
  loading = false,
}: {
  label: string;
  disabled?: boolean;
  loading?: boolean;
}) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="w-24 shrink-0 text-xs font-medium uppercase tracking-wide text-muted-foreground">
        {label}
      </span>
      {BUTTON_VARIANTS.map((v) => (
        <Button key={v.name} variant={v.name} disabled={disabled || loading}>
          {loading ? <Loader2 className="size-4 animate-spin" /> : null}
          {v.label}
        </Button>
      ))}
    </div>
  );
}

function VariantStates() {
  return (
    <div className="space-y-3 rounded-lg border border-border bg-background p-4">
      <VariantRow label="Normal" />
      <VariantRow label="Disabled" disabled />
      <VariantRow label="Loading" loading />
    </div>
  );
}

function BasicRadioDemo() {
  const [value, setValue] = useState("small");
  return (
    <RadioGroup
      value={value}
      onValueChange={(next) => next !== null && setValue(next)}
      className="w-fit"
    >
      <Field orientation="horizontal" className="w-fit">
        <RadioGroupItem value="small" id="basic-small" />
        <FieldLabel htmlFor="basic-small" className="font-normal">
          Small
        </FieldLabel>
      </Field>
      <Field orientation="horizontal" className="w-fit">
        <RadioGroupItem value="medium" id="basic-medium" />
        <FieldLabel htmlFor="basic-medium" className="font-normal">
          Medium
        </FieldLabel>
      </Field>
      <Field orientation="horizontal" className="w-fit">
        <RadioGroupItem value="large" id="basic-large" />
        <FieldLabel htmlFor="basic-large" className="font-normal">
          Large
        </FieldLabel>
      </Field>
    </RadioGroup>
  );
}

function ChoiceCardRadioDemo() {
  const [plan, setPlan] = useState("plus");
  const plans = [
    { value: "plus", title: "Plus", description: "For individuals and small teams." },
    { value: "pro", title: "Pro", description: "For growing businesses." },
    {
      value: "enterprise",
      title: "Enterprise",
      description: "For large teams and enterprises.",
    },
  ];
  return (
    <RadioGroup
      value={plan}
      onValueChange={(next) => next !== null && setPlan(next)}
      className="max-w-md"
    >
      {plans.map((p) => (
        <FieldLabel key={p.value} htmlFor={`plan-${p.value}`}>
          <Field orientation="horizontal">
            <FieldContent>
              <FieldTitle>{p.title}</FieldTitle>
              <FieldDescription>{p.description}</FieldDescription>
            </FieldContent>
            <RadioGroupItem value={p.value} id={`plan-${p.value}`} />
          </Field>
        </FieldLabel>
      ))}
    </RadioGroup>
  );
}

function DisabledRadioDemo() {
  return (
    <div className="space-y-4">
      <RadioGroup defaultValue="available" className="w-fit">
        <Field orientation="horizontal" className="w-fit">
          <RadioGroupItem value="available" id="disabled-available" />
          <FieldLabel htmlFor="disabled-available" className="font-normal">
            Available
          </FieldLabel>
        </Field>
        <Field orientation="horizontal" data-disabled className="w-fit">
          <RadioGroupItem value="unavailable" id="disabled-unavailable" disabled />
          <FieldLabel htmlFor="disabled-unavailable" className="font-normal">
            Unavailable (disabled item)
          </FieldLabel>
        </Field>
      </RadioGroup>
      <RadioGroup disabled defaultValue="group-a" className="w-fit">
        <Field orientation="horizontal" className="w-fit">
          <RadioGroupItem value="group-a" id="group-disabled-a" />
          <FieldLabel htmlFor="group-disabled-a" className="font-normal">
            Option A
          </FieldLabel>
        </Field>
        <Field orientation="horizontal" className="w-fit">
          <RadioGroupItem value="group-b" id="group-disabled-b" />
          <FieldLabel htmlFor="group-disabled-b" className="font-normal">
            Option B
          </FieldLabel>
        </Field>
      </RadioGroup>
    </div>
  );
}

function RadioGroupDemo() {
  return (
    <div className="grid gap-8 lg:grid-cols-3">
      <div className="space-y-2">
        <h4 className="text-sm font-semibold text-foreground">Basic</h4>
        <p className="text-sm text-muted-foreground">Simple radio list with inline labels</p>
        <BasicRadioDemo />
      </div>

      <div className="space-y-2">
        <h4 className="text-sm font-semibold text-foreground">Choice Cards</h4>
        <p className="text-sm text-muted-foreground">
          Selectable bordered cards — the pattern used by the Publish/Schedule/Save Draft tiles
        </p>
        <ChoiceCardRadioDemo />
      </div>

      <div className="space-y-2">
        <h4 className="text-sm font-semibold text-foreground">Disabled</h4>
        <p className="text-sm text-muted-foreground">Per-item disabled and whole-group disabled</p>
        <DisabledRadioDemo />
      </div>
    </div>
  );
}

function SelectsDemo() {
  const [framework, setFramework] = useState<string | null>(null);
  const [region, setRegion] = useState<string | null>(null);

  return (
    <div className="grid gap-6 md:grid-cols-2">
      <Field>
        <FieldLabel>Basic Select</FieldLabel>
        <Select
          value={framework ?? ""}
          onValueChange={(value) => value !== null && setFramework(value)}
        >
          <SelectTrigger className="w-full">
            <SelectValue placeholder="Pick a framework" />
          </SelectTrigger>
          <SelectContent>
            {FRAMEWORKS.map((f) => (
              <SelectItem key={f} value={f}>
                {f}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <FieldDescription>Single select with keyboard navigation</FieldDescription>
      </Field>

      <Field>
        <FieldLabel>Select with Groups</FieldLabel>
        <Select value={region ?? ""} onValueChange={(value) => value !== null && setRegion(value)}>
          <SelectTrigger className="w-full">
            <SelectValue placeholder="Pick a region" />
          </SelectTrigger>
          <SelectContent>
            {CITY_GROUPS.map((group) => (
              <SelectGroup key={group.value}>
                <SelectLabel>{group.value}</SelectLabel>
                {group.items.map((city) => (
                  <SelectItem key={city} value={city}>
                    {city}
                  </SelectItem>
                ))}
              </SelectGroup>
            ))}
          </SelectContent>
        </Select>
      </Field>

      <Field>
        <FieldLabel>Disabled Select</FieldLabel>
        <Select disabled>
          <SelectTrigger className="w-full">
            <SelectValue placeholder="Disabled" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">None</SelectItem>
          </SelectContent>
        </Select>
      </Field>

      <Field>
        <FieldLabel>Compact Select (sm)</FieldLabel>
        <Select
          value={framework ?? ""}
          onValueChange={(value) => value !== null && setFramework(value)}
        >
          <SelectTrigger size="sm" className="w-full">
            <SelectValue placeholder="Small trigger" />
          </SelectTrigger>
          <SelectContent>
            {FRAMEWORKS.map((f) => (
              <SelectItem key={f} value={f}>
                {f}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </Field>
    </div>
  );
}

function ComboboxDemo() {
  const [city, setCity] = useState<string | null>(null);

  const selectCity = (value: string | null) => {
    if (value !== null) {
      setCity(value);
    }
  };

  return (
    <div className="grid gap-6 md:grid-cols-2">
      <Field>
        <FieldLabel>Searchable Combobox (grouped)</FieldLabel>
        <Combobox
          value={city ?? undefined}
          onValueChange={selectCity}
          items={CITY_GROUPS}
          itemToStringValue={(c) => c}
        >
          <ComboboxTrigger
            render={<Button variant="outline" className="w-full justify-between font-normal" />}
          >
            <ComboboxValue placeholder="Select a city" />
          </ComboboxTrigger>
          <ComboboxContent>
            <ComboboxInput showTrigger={false} placeholder="Search cities..." />
            <ComboboxEmpty>No city found.</ComboboxEmpty>
            <ComboboxList>
              {(group: (typeof CITY_GROUPS)[number]) => (
                <ComboboxGroup key={group.value} items={group.items}>
                  <ComboboxLabel>{group.value}</ComboboxLabel>
                  {group.items.map((c) => (
                    <ComboboxItem key={c} value={c}>
                      {c}
                    </ComboboxItem>
                  ))}
                </ComboboxGroup>
              )}
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
        {city && <FieldDescription>Selected: {city}</FieldDescription>}
      </Field>

      <Field>
        <FieldLabel>Combobox with Icon Trigger</FieldLabel>
        <Combobox
          value={city ?? undefined}
          onValueChange={selectCity}
          items={CITY_GROUPS}
          itemToStringValue={(c) => c}
        >
          <ComboboxTrigger
            render={<Button variant="outline" className="w-full justify-between font-normal" />}
          >
            <span className="flex items-center gap-2">
              <Building2 className="size-4 text-muted-foreground" />
              <ComboboxValue placeholder="Select a city" />
            </span>
          </ComboboxTrigger>
          <ComboboxContent>
            <ComboboxInput showTrigger={false} placeholder="Search cities..." />
            <ComboboxEmpty>No city found.</ComboboxEmpty>
            <ComboboxList>
              {(group: (typeof CITY_GROUPS)[number]) => (
                <ComboboxGroup key={group.value} items={group.items}>
                  <ComboboxLabel>{group.value}</ComboboxLabel>
                  {group.items.map((c) => (
                    <ComboboxItem key={c} value={c}>
                      {c}
                    </ComboboxItem>
                  ))}
                </ComboboxGroup>
              )}
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
        <FieldDescription>Shows the icon before the value</FieldDescription>
      </Field>
    </div>
  );
}

export default function StyleGuide() {
  const pageProps = usePage().props;
  const brand = (pageProps.brand as BrandProps) || {};
  const appName = brand.name || "Cost Breaker 360";
  const logoUrl = brand.logo || null;
  const faviconUrl = brand.favicon || null;

  return (
    <AuthenticatedLayout header="UI Style Guide" maxWidth="max-w-5xl">
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard")}>Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>UI Style Guide</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div>
          <h2 className="text-2xl font-bold tracking-tight text-foreground">UI Style Guide</h2>
          <p className="text-muted-foreground">
            Showcasing all available UI components and variants
          </p>
        </div>

        <Tabs defaultValue="brand">
          <TabsList className="w-full justify-start flex-wrap h-auto gap-1 bg-muted/50 p-1">
            <TabsTrigger value="brand">Brand</TabsTrigger>
            <TabsTrigger value="buttons">Buttons</TabsTrigger>
            <TabsTrigger value="badges">Badges</TabsTrigger>
            <TabsTrigger value="selects">Selects</TabsTrigger>
            <TabsTrigger value="forms">Forms</TabsTrigger>
            <TabsTrigger value="dialogs">Dialogs</TabsTrigger>
            <TabsTrigger value="sections">Sections</TabsTrigger>
          </TabsList>

          {/* ─── Brand ──────────────────────────────────────── */}
          <TabsContent value="brand" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Logo &amp; Favicon</CardTitle>
                <CardDescription>
                  Current brand assets and how they render across the app
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-8">
                <div className="space-y-3">
                  <h3 className="text-sm font-semibold text-foreground">Raw Assets</h3>
                  <div className="flex flex-wrap items-end gap-6">
                    <div className="space-y-1.5">
                      <p className="text-xs text-muted-foreground">Logo</p>
                      {logoUrl ? (
                        <img src={logoUrl} alt="Logo" className="h-12 w-auto object-contain" />
                      ) : (
                        <div className="h-12 w-24 rounded-lg bg-muted flex items-center justify-center text-xs text-muted-foreground">
                          No logo
                        </div>
                      )}
                    </div>
                    <div className="space-y-1.5">
                      <p className="text-xs text-muted-foreground">Favicon</p>
                      {faviconUrl ? (
                        <img
                          src={faviconUrl}
                          alt="Favicon"
                          className="h-12 w-12 object-contain rounded-md"
                        />
                      ) : (
                        <div className="h-12 w-12 rounded-lg bg-muted flex items-center justify-center text-xs text-muted-foreground">
                          —
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                <Separator />

                <div className="space-y-3">
                  <h3 className="text-sm font-semibold text-foreground">BrandLogo — All Sizes</h3>
                  <p className="text-xs text-muted-foreground">With logo (public header)</p>
                  <div className="flex flex-wrap items-center gap-6">
                    {(["sm", "md", "lg", "xl"] as const).map((s) => (
                      <div key={s} className="space-y-1">
                        <p className="text-[10px] text-muted-foreground uppercase">{s}</p>
                        <BrandLogo
                          logo={logoUrl}
                          favicon={faviconUrl}
                          name={appName}
                          size={s}
                          showName
                        />
                      </div>
                    ))}
                  </div>
                  <p className="text-xs text-muted-foreground">Favicon fallback</p>
                  <div className="flex flex-wrap items-center gap-6">
                    {(["sm", "md", "lg", "xl"] as const).map((s) => (
                      <div key={s} className="space-y-1">
                        <p className="text-[10px] text-muted-foreground uppercase">{s}</p>
                        <BrandLogo favicon={faviconUrl} name={appName} size={s} showName />
                      </div>
                    ))}
                  </div>
                  <p className="text-xs text-muted-foreground">Icon fallback</p>
                  <div className="flex flex-wrap items-center gap-6">
                    {(["sm", "md", "lg", "xl"] as const).map((s) => (
                      <div key={s} className="space-y-1">
                        <p className="text-[10px] text-muted-foreground uppercase">{s}</p>
                        <BrandLogo name={appName} size={s} showName />
                      </div>
                    ))}
                  </div>
                </div>

                <Separator />

                <div className="space-y-3">
                  <h3 className="text-sm font-semibold text-foreground">Context: Public Header</h3>
                  <div className="border border-border rounded-lg bg-background px-4 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-6">
                      <BrandLogo
                        logo={logoUrl}
                        favicon={faviconUrl}
                        name={appName}
                        size="sm"
                        showName
                      />
                      <span className="hidden sm:inline text-sm text-muted-foreground">Home</span>
                      <span className="hidden sm:inline text-sm text-muted-foreground">Blog</span>
                      <span className="hidden sm:inline text-sm text-muted-foreground">About</span>
                    </div>
                  </div>
                </div>

                <div className="space-y-3">
                  <h3 className="text-sm font-semibold text-foreground">Context: Auth Pages</h3>
                  <div className="border border-border rounded-lg bg-background px-4 py-6 flex flex-col items-center gap-2 max-w-sm mx-auto">
                    <BrandLogo favicon={faviconUrl} name={appName} size="lg" showName />
                    <p className="text-xs text-muted-foreground mt-1">Favicon + name only</p>
                  </div>
                </div>

                <div className="space-y-3">
                  <h3 className="text-sm font-semibold text-foreground">
                    Context: Dashboard Sidebar
                  </h3>
                  <div className="border border-border rounded-lg bg-sidebar p-3 max-w-xs">
                    <div className="flex items-center gap-3">
                      <Avatar className="h-8 w-8 rounded-lg shrink-0">
                        {logoUrl ? <AvatarImage src={logoUrl} alt={appName} /> : null}
                        <AvatarFallback className="rounded-lg bg-primary text-primary-foreground text-xs font-medium">
                          {appName.charAt(0)}
                        </AvatarFallback>
                      </Avatar>
                      <span className="truncate text-sm font-medium text-sidebar-foreground">
                        {appName}
                      </span>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Buttons ────────────────────────────────────── */}
          <TabsContent value="buttons" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Button Variants</CardTitle>
                <CardDescription>
                  All 6 variants from the official shadcn/ui button docs — default, secondary,
                  destructive, outline, ghost, link — follow the current app theme (toggle
                  dark/light mode in the nav to compare)
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <VariantStates />

                <div className="space-y-2 pt-4 border-t border-border">
                  <h3 className="text-lg font-medium">render: Button + Link (Inertia)</h3>
                  <p className="text-sm text-muted-foreground">
                    Using Button with render to wrap an Inertia Link — gives button styling with SPA
                    navigation
                  </p>
                  <div className="flex flex-wrap gap-2">
                    <Button render={<Link href={route("dashboard")} />}>Dashboard (Default)</Button>
                    <Button variant="outline" render={<Link href={route("dashboard")} />}>
                      Outline
                    </Button>
                    <Button variant="secondary" render={<Link href={route("dashboard")} />}>
                      Secondary
                    </Button>
                    <Button variant="ghost" render={<Link href={route("dashboard")} />}>
                      Ghost
                    </Button>
                    <Button variant="link" render={<Link href={route("dashboard")} />}>
                      Link Style
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Radio Group Styles</CardTitle>
                <CardDescription>
                  Radio group patterns from the official shadcn/ui docs — basic, choice cards, and
                  disabled states
                </CardDescription>
              </CardHeader>
              <CardContent>
                <RadioGroupDemo />
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Button Sizes</CardTitle>
                <CardDescription>
                  Different button sizes available in the Button component
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Default (h-9)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="default">Default</Button>
                    <Button size="default" variant="outline">
                      Default
                    </Button>
                    <Button size="default" variant="secondary">
                      Default
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">XS (h-6)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="xs">XS</Button>
                    <Button size="xs" variant="outline">
                      XS
                    </Button>
                    <Button size="xs" variant="secondary">
                      XS
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">SM (h-8)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="sm">SM</Button>
                    <Button size="sm" variant="outline">
                      SM
                    </Button>
                    <Button size="sm" variant="secondary">
                      SM
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">LG (h-10)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="lg">LG</Button>
                    <Button size="lg" variant="outline">
                      LG
                    </Button>
                    <Button size="lg" variant="secondary">
                      LG
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Icon (size-9)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="icon" variant="outline" aria-label="Outline icon">
                      <ChevronDown />
                    </Button>
                    <Button size="icon" variant="secondary" aria-label="Secondary icon">
                      <ChevronDown />
                    </Button>
                    <Button size="icon" variant="ghost" aria-label="Ghost icon">
                      <ChevronDown />
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Icon XS (size-6)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="icon-xs" variant="outline" aria-label="Outline icon xs">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-xs" variant="secondary" aria-label="Secondary icon xs">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-xs" variant="ghost" aria-label="Ghost icon xs">
                      <ChevronDown />
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Icon SM (size-8)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="icon-sm" variant="outline" aria-label="Outline icon sm">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-sm" variant="secondary" aria-label="Secondary icon sm">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-sm" variant="ghost" aria-label="Ghost icon sm">
                      <ChevronDown />
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Icon LG (size-10)</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="icon-lg" variant="outline" aria-label="Outline icon lg">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-lg" variant="secondary" aria-label="Secondary icon lg">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-lg" variant="ghost" aria-label="Ghost icon lg">
                      <ChevronDown />
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">All Sizes Comparison</h3>
                  <div className="flex flex-wrap items-end gap-2">
                    <Button size="xs">XS</Button>
                    <Button size="sm">SM</Button>
                    <Button size="default">Default</Button>
                    <Button size="lg">LG</Button>
                    <Button size="icon-xs" variant="outline" aria-label="Icon xs">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-sm" variant="outline" aria-label="Icon sm">
                      <ChevronDown />
                    </Button>
                    <Button size="icon" variant="outline" aria-label="Icon">
                      <ChevronDown />
                    </Button>
                    <Button size="icon-lg" variant="outline" aria-label="Icon lg">
                      <ChevronDown />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Badges ─────────────────────────────────────── */}
          <TabsContent value="badges" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Badge Variants</CardTitle>
                <CardDescription>
                  All badge variants — core shadcn/ui (default, secondary, destructive, outline,
                  ghost, link) plus status variants (success, warning, error, info)
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {BADGE_VARIANTS.map((v) => (
                  <div key={v.name} className="space-y-2">
                    <h3 className="text-lg font-medium">{v.label}</h3>
                    <div className="flex flex-wrap gap-2">
                      <Badge variant={v.name}>{v.label}</Badge>
                      <Badge variant={v.name}>
                        <span className="size-3 rounded-full bg-foreground/30" /> With icon
                      </Badge>
                      <Badge variant={v.name} className="px-3">
                        Longer text
                      </Badge>
                    </div>
                  </div>
                ))}

                <div className="space-y-2 pt-4 border-t border-border">
                  <h3 className="text-lg font-medium">All Variants Side by Side</h3>
                  <p className="text-sm text-muted-foreground">
                    Compare all badge variants in a single row
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    {BADGE_VARIANTS.map((v) => (
                      <Badge key={v.name} variant={v.name}>
                        {v.label}
                      </Badge>
                    ))}
                  </div>
                </div>

                <div className="space-y-2 pt-4 border-t border-border">
                  <h3 className="text-lg font-medium">On Sidebar-like Background</h3>
                  <p className="text-sm text-muted-foreground">
                    How badges look on a dark/muted background (simulates sidebar nav)
                  </p>
                  <div className="flex flex-wrap items-center gap-2 rounded-lg bg-sidebar p-4 border border-sidebar-border">
                    {BADGE_VARIANTS.map((v) => (
                      <Badge key={v.name} variant={v.name}>
                        {v.label}
                      </Badge>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Color Tokens</CardTitle>
                <CardDescription>
                  Visual swatches of all CSS custom property color tokens used across the theme
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Core Tokens</h3>
                  <p className="text-sm text-muted-foreground">
                    Surface, text, and accent colors — toggle dark/light mode to see both palettes
                  </p>
                  <div className="flex flex-wrap gap-3">
                    {COLOR_TOKENS.map((token) => (
                      <span key={token.var} className="inline-flex items-center gap-2 text-xs">
                        <span
                          className="size-4 rounded-full border"
                          style={{ backgroundColor: `var(${token.var})` }}
                        />{" "}
                        {token.label}
                      </span>
                    ))}
                  </div>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Status Patterns</h3>
                  <p className="text-sm text-muted-foreground">
                    Common status badge usage across the app
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="success">Active</Badge>
                    <Badge variant="warning">Pending</Badge>
                    <Badge variant="error">Failed</Badge>
                    <Badge variant="info">Processing</Badge>
                    <Badge variant="secondary">Draft</Badge>
                    <Badge variant="outline">Archived</Badge>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Selects ────────────────────────────────────── */}
          <TabsContent value="selects" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Select &amp; Combobox</CardTitle>
                <CardDescription>
                  Base UI Select and Combobox components — the searchable combobox is used across
                  the app (e.g. timezone, country, date format pickers)
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Select (Base UI)</h3>
                  <p className="text-sm text-muted-foreground">
                    Controlled single-select with placeholder, groups, sizes, and disabled state
                  </p>
                  <SelectsDemo />
                </div>

                <div className="space-y-2 pt-4 border-t border-border">
                  <h3 className="text-lg font-medium">Combobox (Base UI)</h3>
                  <p className="text-sm text-muted-foreground">
                    Searchable combobox with trigger, value, grouped options, and inline search
                  </p>
                  <ComboboxDemo />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Forms ──────────────────────────────────────── */}
          <TabsContent value="forms" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Field, Input &amp; Field Patterns</CardTitle>
                <CardDescription>Testing consistency of form components</CardDescription>
              </CardHeader>
              <CardContent className="grid gap-4 grid-cols-1 md:grid-cols-2 pt-6">
                <Field>
                  <FieldLabel>Basic Input</FieldLabel>
                  <Input type="text" placeholder="Standard Input" />
                </Field>

                <Field>
                  <FieldLabel>Field + FieldLabel</FieldLabel>
                  <Input type="text" placeholder="Enter your full name" />
                </Field>

                <Field>
                  <FieldLabel>Field + Description</FieldLabel>
                  <Input type="email" placeholder="john@example.com" />
                  <FieldDescription>We'll never share your email.</FieldDescription>
                </Field>

                <Field>
                  <FieldLabel>Field + Error</FieldLabel>
                  <Input type="text" placeholder="johndoe" aria-invalid="true" />
                  <FieldError>This username is taken.</FieldError>
                </Field>

                <Field>
                  <FieldLabel>Textarea</FieldLabel>
                  <Textarea placeholder="Write something..." />
                </Field>

                <Field>
                  <FieldLabel>Disabled</FieldLabel>
                  <Input type="text" placeholder="Disabled" disabled />
                </Field>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Switches, Checkboxes &amp; Radios</CardTitle>
                <CardDescription>Toggle and selection controls</CardDescription>
              </CardHeader>
              <CardContent className="grid gap-8 md:grid-cols-3">
                <div className="space-y-2">
                  <h4 className="text-sm font-semibold text-foreground">Switch</h4>
                  <div className="flex flex-col gap-4">
                    <Field orientation="horizontal">
                      <FieldLabel htmlFor="switch-default" className="font-normal">
                        Notifications
                      </FieldLabel>
                      <Switch id="switch-default" defaultChecked />
                    </Field>
                    <Field orientation="horizontal">
                      <FieldLabel htmlFor="switch-disabled" className="font-normal">
                        Dark mode (disabled)
                      </FieldLabel>
                      <Switch id="switch-disabled" disabled />
                    </Field>
                  </div>
                </div>

                <div className="space-y-2">
                  <h4 className="text-sm font-semibold text-foreground">Checkbox</h4>
                  <div className="flex flex-col gap-4">
                    <Field orientation="horizontal">
                      <Checkbox id="checkbox-terms" defaultChecked />
                      <FieldLabel htmlFor="checkbox-terms" className="font-normal">
                        Accept terms
                      </FieldLabel>
                    </Field>
                    <Field orientation="horizontal">
                      <Checkbox id="checkbox-news" />
                      <FieldLabel htmlFor="checkbox-news" className="font-normal">
                        Subscribe to newsletter
                      </FieldLabel>
                    </Field>
                  </div>
                </div>

                <div className="space-y-2">
                  <h4 className="text-sm font-semibold text-foreground">Radio</h4>
                  <BasicRadioDemo />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Dialogs ────────────────────────────────────── */}
          <TabsContent value="dialogs" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Dialog Examples</CardTitle>
                <CardDescription>
                  Different dialog patterns from shadcn/ui documentation
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Basic Dialog</h3>
                  <p className="text-sm text-muted-foreground">
                    A simple dialog for user confirmation
                  </p>
                  <Dialog>
                    <DialogTrigger render={<Button variant="outline" />}>
                      Open Basic Dialog
                    </DialogTrigger>
                    <DialogContent>
                      <DialogHeader>
                        <DialogTitle>Are you sure?</DialogTitle>
                        <DialogDescription>
                          This action cannot be undone. This will permanently delete your account
                          and remove your data from our servers.
                        </DialogDescription>
                      </DialogHeader>
                      <DialogFooter>
                        <DialogClose render={<Button variant="outline" />}>Cancel</DialogClose>
                        <Button variant="destructive">Delete</Button>
                      </DialogFooter>
                    </DialogContent>
                  </Dialog>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Sticky Footer</h3>
                  <p className="text-sm text-muted-foreground">
                    Keep actions visible while the content scrolls
                  </p>
                  <Dialog>
                    <DialogTrigger render={<Button variant="outline" />}>
                      Open with Sticky Footer
                    </DialogTrigger>
                    <DialogContent className="max-w-md">
                      <DialogHeader>
                        <DialogTitle>Terms of Service</DialogTitle>
                        <DialogDescription>
                          Please read our terms and conditions carefully
                        </DialogDescription>
                      </DialogHeader>
                      <div className="max-h-[300px] overflow-y-auto space-y-4 py-4">
                        <p className="text-sm text-muted-foreground">
                          Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod
                          tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim
                          veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea
                          commodo consequat.
                        </p>
                        <p className="text-sm text-muted-foreground">
                          Duis aute irure dolor in reprehenderit in voluptate velit esse cillum
                          dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
                          proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                        </p>
                        <p className="text-sm text-muted-foreground">
                          Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium
                          doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo
                          inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.
                        </p>
                        <p className="text-sm text-muted-foreground">
                          Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut
                          fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem
                          sequi nesciunt.
                        </p>
                      </div>
                      <DialogFooter>
                        <DialogClose render={<Button variant="outline" />}>Decline</DialogClose>
                        <Button>Accept</Button>
                      </DialogFooter>
                    </DialogContent>
                  </Dialog>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Delete Account Dialog</h3>
                  <p className="text-sm text-muted-foreground">
                    Complete delete account confirmation example
                  </p>
                  <Dialog>
                    <DialogTrigger render={<Button variant="destructive" />}>
                      Delete Account
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-[500px]">
                      <DialogHeader>
                        <DialogTitle className="text-destructive flex items-center gap-2">
                          <AlertTriangle className="h-5 w-5" />
                          Delete Account
                        </DialogTitle>
                        <DialogDescription>
                          We will immediately delete all of your content. This includes:
                        </DialogDescription>
                      </DialogHeader>
                      <div className="py-4">
                        <ul className="list-disc list-outside ml-4 space-y-1 text-sm text-muted-foreground">
                          <li>Your profile information</li>
                          <li>All your posts and content</li>
                          <li>All associated data</li>
                        </ul>
                        <p className="mt-4 font-medium text-foreground">
                          This action cannot be undone.
                        </p>
                      </div>
                      <DialogFooter>
                        <DialogClose render={<Button variant="outline" />}>Cancel</DialogClose>
                        <Button variant="destructive">Delete my account</Button>
                      </DialogFooter>
                    </DialogContent>
                  </Dialog>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Alert Dialog Examples</CardTitle>
                <CardDescription>
                  For critical confirmations that require user action
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Basic Alert Dialog</h3>
                  <p className="text-sm text-muted-foreground">
                    A modal dialog that interrupts the user with important information
                  </p>
                  <AlertDialog>
                    <AlertDialogTrigger render={<Button variant="outline" />}>
                      Open Alert Dialog
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                          This action cannot be undone. This will permanently delete your account
                          and remove your data from our servers.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction>Delete</AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Delete Account Alert</h3>
                  <p className="text-sm text-muted-foreground">
                    Complete delete account confirmation with list of what will be deleted
                  </p>
                  <AlertDialog>
                    <AlertDialogTrigger render={<Button variant="destructive" />}>
                      Delete Account
                    </AlertDialogTrigger>
                    <AlertDialogContent className="sm:max-w-[500px]">
                      <AlertDialogHeader>
                        <AlertDialogTitle className="text-destructive flex items-center gap-2">
                          <AlertTriangle className="h-5 w-5" />
                          Delete Account
                        </AlertDialogTitle>
                        <AlertDialogDescription className="space-y-4">
                          <p>We will immediately delete all of your content. This includes:</p>
                          <ul className="list-disc list-outside ml-4 space-y-1">
                            <li>Your profile information</li>
                            <li>All your posts and content</li>
                            <li>All associated data</li>
                          </ul>
                          <p className="font-medium">This action cannot be undone.</p>
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction>Delete my account</AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Sections ───────────────────────────────────── */}
          <TabsContent value="sections" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Settings Accordion (Windows 11 Style)</CardTitle>
                <CardDescription>
                  Reusable SettingsAccordion component mimicking Windows 11 settings
                </CardDescription>
              </CardHeader>
              <CardContent>
                <SettingsAccordion
                  items={[
                    {
                      value: "personal-info",
                      icon: User,
                      title: "Personal Information",
                      description: "Your personal information and social media links",
                      content: (
                        <div className="space-y-2">
                          <p className="text-sm text-muted-foreground">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                          </p>
                          <div className="p-3 bg-muted/30 rounded-lg">
                            <p className="text-sm font-medium">Form fields:</p>
                            <p className="text-sm text-muted-foreground">- First Name</p>
                            <p className="text-sm text-muted-foreground">- Last Name</p>
                          </div>
                        </div>
                      ),
                    },
                    {
                      value: "email",
                      icon: Mail,
                      title: "Email Address",
                      description: "Your email address for communication",
                      content: (
                        <p className="text-sm text-muted-foreground">
                          Duis aute irure dolor in reprehenderit in voluptate velit.
                        </p>
                      ),
                    },
                    {
                      value: "password",
                      icon: Lock,
                      title: "Change Password",
                      description: "Update your password to keep your account secure",
                      content: (
                        <p className="text-sm text-muted-foreground">
                          Sed ut perspiciatis unde omnis iste natus error.
                        </p>
                      ),
                    },
                    {
                      value: "delete",
                      icon: AlertTriangle,
                      title: "Delete Account",
                      description: "Once you delete your account, there is no going back",
                      variant: "destructive",
                      content: (
                        <div className="space-y-2">
                          <p className="text-sm text-destructive font-medium">
                            Warning: This action cannot be undone!
                          </p>
                          <p className="text-sm text-muted-foreground">
                            All your data will be permanently deleted.
                          </p>
                        </div>
                      ),
                    },
                  ]}
                />
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Section Component</CardTitle>
                <CardDescription>
                  Section and SectionField in Card and Accordion modes
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <Section
                  title="Card Mode"
                  description="Standard card with header, fields, and submit button"
                  icon={Info}
                  submitLabel="Save"
                >
                  <SectionField label="Field inside Card" htmlFor="sec-card">
                    <Input id="sec-card" placeholder="Card mode field" />
                  </SectionField>
                </Section>
                <Section
                  title="Accordion Mode"
                  description="Renders inside a SettingsAccordion collapsible"
                  icon={Eye}
                  asAccordion
                  accordionDefaultOpen
                >
                  <SectionField label="Field inside Accordion" htmlFor="sec-acc">
                    <Input id="sec-acc" placeholder="Accordion mode field" />
                  </SectionField>
                </Section>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Collapsible Examples</CardTitle>
                <CardDescription>
                  Simple collapsible sections for showing/hiding content
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Basic Collapsible</h3>
                  <p className="text-sm text-muted-foreground">
                    Click the trigger to expand/collapse content
                  </p>
                  <Collapsible>
                    <CollapsibleTrigger
                      render={<Button variant="outline" className="w-full justify-between" />}
                    >
                      <span>Toggle Content</span>
                      <ChevronDown className="h-4 w-4 transition-transform duration-200 [&[data-panel-open]]:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <div className="mt-2 p-4 border border-border rounded-lg bg-muted/30">
                        <p className="text-sm text-muted-foreground">
                          This content is now visible! You can put any content here — forms, text,
                          images, etc.
                        </p>
                      </div>
                    </CollapsibleContent>
                  </Collapsible>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Collapsible with Card</h3>
                  <p className="text-sm text-muted-foreground">A more styled collapsible section</p>
                  <Collapsible>
                    <div className="rounded-lg border border-border bg-card">
                      <CollapsibleTrigger
                        render={
                          <button className="flex items-center justify-between w-full px-4 py-3 text-left hover:bg-muted/50 transition-colors" />
                        }
                      >
                        <div className="flex items-center gap-3 min-w-0 flex-1">
                          <User className="h-5 w-5 text-muted-foreground shrink-0" />
                          <div className="min-w-0 overflow-hidden">
                            <span className="font-medium block">User Settings</span>
                            <p className="text-sm text-muted-foreground">
                              Manage your account preferences
                            </p>
                          </div>
                        </div>
                        <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 [&[data-panel-open]]:rotate-180" />
                      </CollapsibleTrigger>
                      <CollapsibleContent>
                        <div className="px-4 pb-4 space-y-3">
                          <div className="p-3 bg-muted/30 rounded-lg">
                            <p className="text-sm font-medium">Notifications</p>
                            <p className="text-sm text-muted-foreground">Email alerts enabled</p>
                          </div>
                          <div className="p-3 bg-muted/30 rounded-lg">
                            <p className="text-sm font-medium">Privacy</p>
                            <p className="text-sm text-muted-foreground">Profile is public</p>
                          </div>
                        </div>
                      </CollapsibleContent>
                    </div>
                  </Collapsible>
                </div>

                <div className="space-y-2">
                  <h3 className="text-lg font-medium">Multiple Collapsibles</h3>
                  <p className="text-sm text-muted-foreground">
                    Multiple independent collapsible sections
                  </p>
                  <div className="space-y-2">
                    {["Section 1", "Section 2", "Section 3"].map((title) => (
                      <Collapsible key={title}>
                        <div className="rounded-lg border border-border bg-card">
                          <CollapsibleTrigger
                            render={
                              <button className="flex items-center justify-between w-full px-4 py-3 text-left hover:bg-muted/50 transition-colors" />
                            }
                          >
                            <span className="font-medium">{title}</span>
                            <ChevronDown className="h-4 w-4 text-muted-foreground transition-transform duration-200 [&[data-panel-open]]:rotate-180" />
                          </CollapsibleTrigger>
                          <CollapsibleContent>
                            <div className="px-4 pb-4">
                              <p className="text-sm text-muted-foreground">
                                Content for {title.toLowerCase()} goes here.
                              </p>
                            </div>
                          </CollapsibleContent>
                        </div>
                      </Collapsible>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
