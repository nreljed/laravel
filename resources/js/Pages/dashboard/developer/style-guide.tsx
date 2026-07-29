import { usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { Checkbox } from "@/Components/ui/checkbox";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import { Avatar, AvatarFallback, AvatarImage } from "@/Components/ui/avatar";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
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
import { Globe, ShieldCheck, Bell, Lock, Mail, AlertTriangle, Eye, Info } from "lucide-react";

type BrandProps = { logo?: string; favicon?: string; name?: string };

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
            Component reference, brand assets, and settings section previews.
          </p>
        </div>

        <Tabs defaultValue="brand">
          <TabsList className="w-full justify-start flex-wrap h-auto gap-1 bg-muted/50 p-1">
            <TabsTrigger value="brand">Brand</TabsTrigger>
            <TabsTrigger value="buttons">Buttons</TabsTrigger>
            <TabsTrigger value="badges">Badges</TabsTrigger>
            <TabsTrigger value="forms">Form Elements</TabsTrigger>
            <TabsTrigger value="overlays">Overlays</TabsTrigger>
            <TabsTrigger value="settings">Settings Sections</TabsTrigger>
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
                <CardDescription>All available button variants</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {(["default", "destructive", "outline", "secondary", "ghost", "link"] as const).map(
                  (v) => (
                    <div key={v} className="space-y-2">
                      <h3 className="text-sm font-medium capitalize">{v}</h3>
                      <div className="flex flex-wrap items-center gap-2">
                        <Button variant={v}>{v}</Button>
                        <Button variant={v} disabled>
                          Disabled
                        </Button>
                      </div>
                    </div>
                  ),
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Button Sizes</CardTitle>
                <CardDescription>xs, sm, default, and lg sizes</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {(["xs", "sm", "default", "lg"] as const).map((s) => (
                  <div key={s} className="space-y-2">
                    <h3 className="text-sm font-medium">{s.toUpperCase()}</h3>
                    <div className="flex flex-wrap items-center gap-2">
                      <Button size={s}>{s}</Button>
                      <Button size={s} variant="outline">
                        {s}
                      </Button>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Badges ─────────────────────────────────────── */}
          <TabsContent value="badges" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Badge Variants</CardTitle>
                <CardDescription>
                  All 6 badge variants — plain, with icon, and with custom color overrides
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {(["default", "secondary", "destructive", "outline", "ghost", "link"] as const).map(
                  (v) => (
                    <div key={v} className="space-y-2">
                      <h3 className="text-sm font-medium capitalize">{v}</h3>
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={v}>{v}</Badge>
                        <Badge variant={v}>
                          <Globe className="h-3 w-3" />
                          With icon
                        </Badge>
                        <Badge variant={v} className="px-3">
                          Longer text
                        </Badge>
                      </div>
                    </div>
                  ),
                )}

                <Separator />

                <div className="space-y-2">
                  <h3 className="text-sm font-medium">Status Pattern Examples</h3>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="default">Active</Badge>
                    <Badge variant="secondary">Draft</Badge>
                    <Badge variant="destructive">Error</Badge>
                    <Badge variant="outline">Pending</Badge>
                    <Badge variant="ghost">Inactive</Badge>
                  </div>
                </div>

                <Separator />

                <div className="space-y-2">
                  <h3 className="text-sm font-medium">Custom Color Overrides</h3>
                  <p className="text-xs text-muted-foreground">
                    Using className to apply custom colors on top of variants
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge
                      variant="outline"
                      className="border-green-500/50 text-green-700 dark:text-green-400 bg-green-500/10"
                    >
                      <ShieldCheck className="h-3 w-3" />
                      Verified
                    </Badge>
                    <Badge
                      variant="outline"
                      className="border-amber-500/50 text-amber-700 dark:text-amber-400 bg-amber-500/10"
                    >
                      <AlertTriangle className="h-3 w-3" />
                      Warning
                    </Badge>
                    <Badge
                      variant="outline"
                      className="border-red-500/50 text-red-700 dark:text-red-400 bg-red-500/10"
                    >
                      <AlertTriangle className="h-3 w-3" />
                      Critical
                    </Badge>
                    <Badge
                      variant="outline"
                      className="border-blue-500/50 text-blue-700 dark:text-blue-400 bg-blue-500/10"
                    >
                      <Info className="h-3 w-3" />
                      Info
                    </Badge>
                    <Badge
                      variant="outline"
                      className="border-purple-500/50 text-purple-700 dark:text-purple-400 bg-purple-500/10"
                    >
                      <Bell className="h-3 w-3" />
                      Scheduled
                    </Badge>
                  </div>
                </div>

                <Separator />

                <div className="space-y-2">
                  <h3 className="text-sm font-medium">Badge Sizes</h3>
                  <p className="text-xs text-muted-foreground">Default sizing via px/py classes</p>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary" className="text-[10px] px-1.5 py-0 h-4">
                      Tiny
                    </Badge>
                    <Badge variant="secondary" className="text-xs">
                      Default
                    </Badge>
                    <Badge variant="secondary" className="text-sm px-3 py-1">
                      Large
                    </Badge>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Form Elements ──────────────────────────────── */}
          <TabsContent value="forms" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Inputs &amp; Fields</CardTitle>
                <CardDescription>
                  Text, number, email, password, textarea, and field wrappers
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="grid gap-4 grid-cols-1 md:grid-cols-2">
                  <SectionField label="Text Input" htmlFor="demo-text">
                    <Input id="demo-text" placeholder="Enter text..." />
                  </SectionField>
                  <SectionField label="Password" htmlFor="demo-pass">
                    <Input id="demo-pass" type="password" placeholder="••••••••" />
                  </SectionField>
                  <SectionField label="Email" htmlFor="demo-email">
                    <Input id="demo-email" type="email" placeholder="you@example.com" />
                  </SectionField>
                  <SectionField label="Number" htmlFor="demo-num">
                    <Input id="demo-num" type="number" min={0} max={100} placeholder="0" />
                  </SectionField>
                  <SectionField label="With Error" htmlFor="demo-err">
                    <Input id="demo-err" aria-invalid="true" placeholder="Invalid input" />
                  </SectionField>
                  <SectionField label="Disabled" htmlFor="demo-dis">
                    <Input id="demo-dis" disabled placeholder="Disabled" />
                  </SectionField>
                </div>
                <SectionField label="Textarea" htmlFor="demo-ta">
                  <Textarea id="demo-ta" placeholder="Write something..." />
                </SectionField>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Switches, Checkboxes &amp; Selects</CardTitle>
                <CardDescription>Toggle, checkbox, and dropdown controls</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="grid gap-4 grid-cols-1 md:grid-cols-2">
                  <SectionField label="Switch">
                    <div className="flex items-center gap-2">
                      <Switch id="demo-switch" />
                      <Label htmlFor="demo-switch" className="cursor-pointer font-normal">
                        Toggle me
                      </Label>
                    </div>
                  </SectionField>
                  <SectionField label="Checkbox">
                    <div className="flex items-center gap-2">
                      <Checkbox id="demo-check" />
                      <Label htmlFor="demo-check" className="cursor-pointer font-normal">
                        Check me
                      </Label>
                    </div>
                  </SectionField>
                  <SectionField label="Select">
                    <Select>
                      <SelectTrigger>
                        <SelectValue placeholder="Choose..." />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="a">Option A</SelectItem>
                        <SelectItem value="b">Option B</SelectItem>
                        <SelectItem value="c">Option C</SelectItem>
                      </SelectContent>
                    </Select>
                  </SectionField>
                </div>
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
          </TabsContent>

          {/* ─── Overlays ───────────────────────────────────── */}
          <TabsContent value="overlays" className="space-y-6">
            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Dialog</CardTitle>
                <CardDescription>Modal dialog for user actions</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <Dialog>
                  <DialogTrigger render={<Button variant="outline" />}>Open Dialog</DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>Confirm Action</DialogTitle>
                      <DialogDescription>
                        This action cannot be undone. Please confirm you want to proceed.
                      </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                      <Button variant="outline">Cancel</Button>
                      <Button>Confirm</Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Alert Dialog</CardTitle>
                <CardDescription>
                  Critical confirmation requiring explicit user action
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <AlertDialog>
                  <AlertDialogTrigger render={<Button variant="destructive" />}>
                    Delete Account
                  </AlertDialogTrigger>
                  <AlertDialogContent>
                    <AlertDialogHeader>
                      <AlertDialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5" />
                        Delete Account
                      </AlertDialogTitle>
                      <AlertDialogDescription>
                        This will permanently delete your account and all associated data.
                      </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                      <AlertDialogCancel>Cancel</AlertDialogCancel>
                      <AlertDialogAction>Delete</AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="border-b border-border">
                <CardTitle>Collapsible</CardTitle>
                <CardDescription>Toggle content visibility</CardDescription>
              </CardHeader>
              <CardContent>
                <Collapsible>
                  <CollapsibleTrigger
                    render={<Button variant="outline" className="w-full justify-between" />}
                  >
                    <span>Toggle Content</span>
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <div className="mt-2 p-4 border border-border rounded-lg bg-muted/30">
                      <p className="text-sm text-muted-foreground">This content is now visible!</p>
                    </div>
                  </CollapsibleContent>
                </Collapsible>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ─── Settings Sections ──────────────────────────── */}
          <TabsContent value="settings" className="space-y-6">
            <div>
              <h3 className="text-lg font-semibold text-foreground">Settings Section Variants</h3>
              <p className="text-sm text-muted-foreground">
                Accordion and Card modes with different content lengths. Click headers to
                expand/collapse.
              </p>
            </div>

            <div className="space-y-2">
              <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                Accordion Mode — Single Group (only one open at a time)
              </h4>
              <p className="text-xs text-muted-foreground">
                Short, medium, and long sections. "App Settings" is default open.
              </p>
            </div>

            <SettingsAccordion
              items={[
                {
                  value: "app-settings",
                  icon: Globe,
                  title: "App Settings",
                  description: "Configure general settings for your application.",
                  defaultOpen: true,
                  content: (
                    <SectionField
                      label="Application Name"
                      htmlFor="sg-name"
                      description="The name of your application"
                    >
                      <Input id="sg-name" value="Cost Breaker 360" readOnly />
                    </SectionField>
                  ),
                },
                {
                  value: "registration",
                  icon: ShieldCheck,
                  title: "Registration",
                  description: "Control who can register and their default role.",
                  content: (
                    <div className="space-y-4">
                      <SectionField label="Enable Public Registration">
                        <div className="flex items-center gap-2">
                          <Switch id="sg-reg" defaultChecked />
                          <Label htmlFor="sg-reg" className="cursor-pointer font-normal">
                            Enabled
                          </Label>
                        </div>
                      </SectionField>
                      <SectionField label="Require Email Verification">
                        <div className="flex items-center gap-2">
                          <Switch id="sg-verify" />
                          <Label htmlFor="sg-verify" className="cursor-pointer font-normal">
                            Disabled
                          </Label>
                        </div>
                      </SectionField>
                      <SectionField
                        label="Default Role"
                        htmlFor="sg-role"
                        description="Role assigned to new registrations"
                      >
                        <Select defaultValue="user">
                          <SelectTrigger id="sg-role">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="user">User</SelectItem>
                            <SelectItem value="editor">Editor</SelectItem>
                          </SelectContent>
                        </Select>
                      </SectionField>
                    </div>
                  ),
                },
                {
                  value: "security",
                  icon: Lock,
                  title: "Security",
                  description:
                    "Password policy, session lifetime, and login attempt limits to protect user accounts.",
                  content: (
                    <div className="space-y-4">
                      <SectionField
                        label="Minimum Password Length"
                        htmlFor="sg-pwd"
                        description="Minimum characters required"
                      >
                        <Input id="sg-pwd" type="number" value="8" readOnly />
                      </SectionField>
                      <SectionField
                        label="Session Lifetime (minutes)"
                        htmlFor="sg-sess"
                        description="How long a user stays logged in before re-authentication"
                      >
                        <Input id="sg-sess" type="number" value="120" readOnly />
                      </SectionField>
                      <SectionField
                        label="Max Login Attempts"
                        htmlFor="sg-login"
                        description="Failed attempts before temporary lockout"
                      >
                        <Input id="sg-login" type="number" value="5" readOnly />
                      </SectionField>
                      <SectionField label="Two-Factor Authentication">
                        <div className="flex items-center gap-2">
                          <Switch id="sg-2fa" />
                          <Label htmlFor="sg-2fa" className="cursor-pointer font-normal">
                            Disabled
                          </Label>
                        </div>
                      </SectionField>
                      <SectionField
                        label="IP Whitelist"
                        htmlFor="sg-ip"
                        description="Comma-separated IPs allowed to access admin (leave empty for all)"
                      >
                        <Input id="sg-ip" placeholder="192.168.1.0/24, 10.0.0.1" readOnly />
                      </SectionField>
                    </div>
                  ),
                },
                {
                  value: "notif-settings",
                  icon: Bell,
                  title: "Notifications",
                  description: "Configure notification delivery channels.",
                  content: (
                    <SectionField label="Enable Email Notifications">
                      <div className="flex items-center gap-2">
                        <Switch id="sg-notif" />
                        <Label htmlFor="sg-notif" className="cursor-pointer font-normal">
                          Disabled
                        </Label>
                      </div>
                    </SectionField>
                  ),
                },
              ]}
            />

            <Separator />

            <div className="space-y-2">
              <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                Card Mode — Static
              </h4>
              <p className="text-xs text-muted-foreground">
                Non-collapsible card with header, fields, and submit button.
              </p>
            </div>

            <Section
              title="Email Settings"
              description="SMTP configuration for outgoing mail."
              icon={Mail}
              submitLabel="Save Email Settings"
            >
              <SectionField label="From Email" htmlFor="sg-from" description="Sender email address">
                <Input id="sg-from" type="email" value="noreply@costbreaker360.com" readOnly />
              </SectionField>
              <SectionField
                label="From Name"
                htmlFor="sg-fromname"
                description="Sender display name"
              >
                <Input id="sg-fromname" value="Cost Breaker 360" readOnly />
              </SectionField>
            </Section>

            <Section
              title="Danger Zone"
              description="Irreversible actions that affect your entire application."
              icon={AlertTriangle}
              submitLabel="Delete All Data"
              submitVariant="destructive"
            >
              <SectionField label="Confirm Deletion" htmlFor="sg-confirm">
                <Input id="sg-confirm" placeholder="Type DELETE to confirm" readOnly />
              </SectionField>
            </Section>
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
