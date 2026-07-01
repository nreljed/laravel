import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Field, FieldLabel, FieldDescription, FieldError } from "@/Components/ui/field";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { SettingsAccordion } from "@/Components/ui/settings-accordion";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/Components/ui/collapsible";
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
import { User, Mail, Lock, AlertTriangle } from "lucide-react";

export default function TestPage() {
  return (
    <AuthenticatedLayout header="Development" maxWidth="max-w-4xl">
      <Head title="Test Components" />

      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard")}>Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Development</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div>
          <h2 className="text-2xl font-bold tracking-tight text-foreground">
            Dialog & Accordion Test
          </h2>
          <p className="text-muted-foreground">Testing shadcn/ui Dialog and Accordion components</p>
        </div>

        <Card>
          <CardHeader className="border-b border-border">
            <CardTitle>Button Variants</CardTitle>
            <CardDescription>
              Different button variants available in the Button component
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="space-y-2">
              <h3 className="text-lg font-medium">Default</h3>
              <div className="flex flex-wrap gap-2">
                <Button variant="default">Default</Button>
                <Button variant="default" disabled>
                  Disabled
                </Button>
                <Button variant="default" disabled>
                  Loading (disabled)
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-medium">Destructive</h3>
              <div className="flex flex-wrap gap-2">
                <Button variant="destructive">Destructive</Button>
                <Button variant="destructive" disabled>
                  Disabled
                </Button>
                <Button variant="destructive" disabled>
                  Loading (disabled)
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-medium">Outline</h3>
              <div className="flex flex-wrap gap-2">
                <Button variant="outline">Outline</Button>
                <Button variant="outline" disabled>
                  Disabled
                </Button>
                <Button variant="outline" disabled>
                  Loading (disabled)
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-medium">Secondary</h3>
              <div className="flex flex-wrap gap-2">
                <Button variant="secondary">Secondary</Button>
                <Button variant="secondary" disabled>
                  Disabled
                </Button>
                <Button variant="secondary" disabled>
                  Loading (disabled)
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-medium">Ghost</h3>
              <div className="flex flex-wrap gap-2">
                <Button variant="ghost">Ghost</Button>
                <Button variant="ghost" disabled>
                  Disabled
                </Button>
                <Button variant="ghost" disabled>
                  Loading (disabled)
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-medium">Link</h3>
              <div className="flex flex-wrap gap-2">
                <Button variant="link">Link</Button>
                <Button variant="link" disabled>
                  Disabled
                </Button>
              </div>
            </div>
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
              </div>
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-medium">XS (h-6)</h3>
              <div className="flex flex-wrap items-center gap-2">
                <Button size="xs">XS</Button>
                <Button size="xs" variant="outline">
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
              </div>
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-medium">LG (h-10)</h3>
              <div className="flex flex-wrap items-center gap-2">
                <Button size="lg">LG</Button>
                <Button size="lg" variant="outline">
                  LG
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

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
              <Dialog>
                <DialogTrigger asChild>
                  <Button variant="outline">Open Basic Dialog</Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Are you sure?</DialogTitle>
                    <DialogDescription>This action cannot be undone.</DialogDescription>
                  </DialogHeader>
                  <DialogFooter>
                    <Button variant="outline">Cancel</Button>
                    <Button variant="destructive">Delete</Button>
                  </DialogFooter>
                </DialogContent>
              </Dialog>
            </div>

            <div className="space-y-2">
              <h3 className="text-lg font-medium">Delete Account Dialog</h3>
              <Dialog>
                <DialogTrigger asChild>
                  <Button variant="destructive">Delete Account</Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle className="text-destructive flex items-center gap-2">
                      <AlertTriangle className="h-5 w-5" />
                      Delete Account
                    </DialogTitle>
                    <DialogDescription>
                      We will immediately delete all of your content.
                    </DialogDescription>
                  </DialogHeader>
                  <div className="py-4">
                    <ul className="list-disc list-outside ml-4 space-y-1 text-sm text-muted-foreground">
                      <li>Your profile information</li>
                      <li>All your posts and property listings</li>
                      <li>All associated data</li>
                    </ul>
                    <p className="mt-4 font-medium text-foreground">
                      This action cannot be undone.
                    </p>
                  </div>
                  <DialogFooter>
                    <Button variant="outline">Cancel</Button>
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
            <CardDescription>For critical confirmations that require user action</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="outline">Open Alert Dialog</Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                  <AlertDialogDescription>
                    This action cannot be undone. This will permanently delete your account.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction>Delete</AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>

            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="destructive">Delete Account</Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle className="text-destructive flex items-center gap-2">
                    <AlertTriangle className="h-5 w-5" />
                    Delete Account
                  </AlertDialogTitle>
                  <AlertDialogDescription>
                    We will immediately delete all of your content.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction>Delete my account</AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="border-b border-border">
            <CardTitle>Field, Input & InputGroup Test</CardTitle>
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
          </CardContent>
        </Card>

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
                  title: "Personal Informations",
                  description: "Your personal information and social media links",
                  content: (
                    <div className="space-y-2">
                      <p className="text-sm text-muted-foreground">Lorem ipsum dolor sit amet.</p>
                    </div>
                  ),
                },
                {
                  value: "email",
                  icon: Mail,
                  title: "Email Address",
                  description: "Your email address for communication",
                  content: <p className="text-sm text-muted-foreground">Email content here.</p>,
                },
                {
                  value: "password",
                  icon: Lock,
                  title: "Change Password",
                  description: "Update your password to keep your account secure",
                  content: <p className="text-sm text-muted-foreground">Password content here.</p>,
                },
                {
                  value: "delete",
                  icon: AlertTriangle,
                  title: "Delete Account",
                  description: "Once you delete your account, there is no going back",
                  variant: "destructive",
                  content: (
                    <p className="text-sm text-destructive font-medium">
                      Warning: This action cannot be undone!
                    </p>
                  ),
                },
              ]}
            />
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
            <Collapsible>
              <CollapsibleTrigger asChild>
                <Button variant="outline" className="w-full justify-between">
                  <span>Toggle Content</span>
                </Button>
              </CollapsibleTrigger>
              <CollapsibleContent>
                <div className="mt-2 p-4 border border-border rounded-lg bg-muted/30">
                  <p className="text-sm text-muted-foreground">This content is now visible!</p>
                </div>
              </CollapsibleContent>
            </Collapsible>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
