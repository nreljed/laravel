import { Head, router, usePage } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "../../../../Components/ui/card";
import { StatusBadge } from "../../../../Components/ui/status-badge";
import { Button } from "../../../../Components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../../../Components/ui/select";
import { Separator } from "../../../../Components/ui/separator";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "../../../../Components/ui/alert-dialog";
import { useState } from "react";
import {
  ArrowLeft,
  Clock,
  Globe,
  Languages,
  Mail,
  Monitor,
  MousePointerClick,
  ShieldCheck,
  Trash2,
  User,
} from "lucide-react";

interface FieldDefinition {
  name: string;
  label?: string;
  type?: string;
}

interface FormDefinition {
  label?: string;
  fields?: FieldDefinition[];
}

interface Submission {
  id: number;
  form_key: string;
  form_label: string;
  name: string;
  email: string;
  summary: string | null;
  payload: Record<string, string | null>;
  status: string;
  ip: string | null;
  user_agent: string | null;
  referrer: string | null;
  utm: {
    source: string | null;
    medium: string | null;
    campaign: string | null;
    term: string | null;
    content: string | null;
  };
  consent_at: string | null;
  read_at: string | null;
  created_at: string;
}

interface PageProps {
  submission: Submission;
  definition: FormDefinition | null;
  statuses: string[];
  pinnedForm: string | null;
}

const statusMeta: Record<string, { label: string; variant: "warning" | "success" | "accentCyan" }> = {
  new: { label: "New", variant: "warning" },
  read: { label: "Read", variant: "success" },
  archived: { label: "Archived", variant: "accentCyan" },
};

export default function AdminSubmissionShow() {
  const { props } = usePage();
  const { submission, definition, statuses = ["new", "read", "archived"], pinnedForm } =
    props as unknown as PageProps;

  const [deleteOpen, setDeleteOpen] = useState(false);

  const meta = statusMeta[submission.status] ?? {
    label: submission.status,
    variant: "info" as const,
  };

  const basePath = pinnedForm
    ? route("dashboard.admin.contact.index")
    : route("dashboard.admin.submissions.index");
  const updatePath = pinnedForm
    ? route("dashboard.admin.contact.update", submission.id)
    : route("dashboard.admin.submissions.update", submission.id);
  const destroyPath = pinnedForm
    ? route("dashboard.admin.contact.destroy", submission.id)
    : route("dashboard.admin.submissions.destroy", submission.id);

  // Render the payload in the form's declared field order. A field the
  // visitor left blank is still shown, so the admin sees what was asked.
  const declaredFields = (definition?.fields ?? []).filter((field) =>
    Boolean(field?.name),
  );
  const declaredNames = new Set(declaredFields.map((field) => field.name));
  const extraKeys = Object.keys(submission.payload ?? {}).filter(
    (key) => !declaredNames.has(key),
  );

  const labelFor = (name: string, fallback?: string) =>
    fallback ?? declaredFields.find((field) => field.name === name)?.label ?? name;

  const updateStatus = (status: string) => {
    router.patch(updatePath, { status }, { preserveScroll: true });
  };

  const destroy = () => {
    router.delete(destroyPath);
  };

  const hasUtm = Object.values(submission.utm ?? {}).some(Boolean);

  return (
    <AdminLayout title="Form Submission">
      <Head
        title={
          submission.name
            ? `${submission.form_label} — ${submission.name}`
            : submission.form_label
        }
      />

      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button variant="ghost" size="sm" onClick={() => router.get(basePath)}>
          <ArrowLeft className="size-4" aria-hidden="true" />
          Back to submissions
        </Button>

        <div className="flex items-center gap-2">
          <StatusBadge variant={meta.variant}>{meta.label}</StatusBadge>

          <Select value={submission.status} onValueChange={updateStatus}>
            <SelectTrigger className="w-[140px]" aria-label="Status">
              <SelectValue>{meta.label}</SelectValue>
            </SelectTrigger>
            <SelectContent>
              {statuses.map((value) => (
                <SelectItem key={value} value={value}>
                  {statusMeta[value]?.label ?? value}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Button variant="destructive" size="sm" onClick={() => setDeleteOpen(true)}>
            <Trash2 className="size-4" aria-hidden="true" />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Mail className="size-4" aria-hidden="true" />
                {submission.summary || "No subject"}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <dl className="space-y-4">
                {declaredFields.map((field) => {
                  const value = submission.payload?.[field.name];
                  const isLong = (value ?? "").length > 120 || field.type === "textarea";

                  return (
                    <div key={field.name}>
                      <dt className="text-xs text-muted-foreground">
                        {labelFor(field.name, field.label)}
                      </dt>
                      {isLong ? (
                        <dd className="text-sm leading-relaxed whitespace-pre-wrap">
                          {value || "—"}
                        </dd>
                      ) : (
                        <dd className="text-sm font-medium break-words">{value || "—"}</dd>
                      )}
                    </div>
                  );
                })}

                {extraKeys.map((key) => (
                  <div key={key}>
                    <dt className="text-xs text-muted-foreground">{labelFor(key)}</dt>
                    <dd className="text-sm break-words">
                      {String(submission.payload?.[key] ?? "—")}
                    </dd>
                  </div>
                ))}
              </dl>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <User className="size-4" aria-hidden="true" />
                Sender
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div>
                <p className="text-xs text-muted-foreground">Name</p>
                <p className="font-medium">{submission.name || "—"}</p>
              </div>
              <Separator />
              <div>
                <p className="text-xs text-muted-foreground">Email</p>
                {submission.email ? (
                  <a
                    href={`mailto:${submission.email}`}
                    className="font-medium text-primary hover:underline"
                  >
                    {submission.email}
                  </a>
                ) : (
                  <p>—</p>
                )}
              </div>
              <Separator />
              <div>
                <p className="text-xs text-muted-foreground">Form</p>
                <p>{submission.form_label}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Clock className="size-4" aria-hidden="true" />
                Details
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div>
                <p className="text-xs text-muted-foreground">Received</p>
                <p>{new Date(submission.created_at).toLocaleString()}</p>
              </div>
              {submission.read_at && (
                <>
                  <Separator />
                  <div>
                    <p className="text-xs text-muted-foreground">First read</p>
                    <p>{new Date(submission.read_at).toLocaleString()}</p>
                  </div>
                </>
              )}
              <Separator />
              <div>
                <p className="text-xs text-muted-foreground">IP address</p>
                <p className="flex items-center gap-1.5 font-mono text-xs">
                  <Globe className="size-3.5" aria-hidden="true" />
                  {submission.ip ?? "—"}
                </p>
              </div>
              {submission.user_agent && (
                <>
                  <Separator />
                  <div>
                    <p className="text-xs text-muted-foreground">Browser</p>
                    <p className="flex items-start gap-1.5 break-words text-xs text-muted-foreground">
                      <Monitor className="size-3.5 shrink-0 mt-0.5" aria-hidden="true" />
                      {submission.user_agent}
                    </p>
                  </div>
                </>
              )}
              {submission.referrer && (
                <>
                  <Separator />
                  <div>
                    <p className="text-xs text-muted-foreground">Referrer</p>
                    <p className="flex items-start gap-1.5 break-all text-xs text-muted-foreground">
                      <MousePointerClick className="size-3.5 shrink-0 mt-0.5" aria-hidden="true" />
                      {submission.referrer}
                    </p>
                  </div>
                </>
              )}
            </CardContent>
          </Card>

          {(hasUtm || submission.consent_at) && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Languages className="size-4" aria-hidden="true" />
                  Attribution
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                {hasUtm &&
                  Object.entries(submission.utm ?? {})
                    .filter(([, value]) => Boolean(value))
                    .map(([key, value]) => (
                      <div key={key}>
                        <p className="text-xs text-muted-foreground">UTM {key}</p>
                        <p className="break-all">{value}</p>
                      </div>
                    ))}
                {submission.consent_at && (
                  <>
                    {hasUtm && <Separator />}
                    <div>
                      <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <ShieldCheck className="size-3.5" aria-hidden="true" />
                        Consent given
                      </p>
                      <p>{new Date(submission.consent_at).toLocaleString()}</p>
                    </div>
                  </>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this submission?</AlertDialogTitle>
            <AlertDialogDescription>
              The submission is removed permanently. This cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={() => {
                setDeleteOpen(false);
                destroy();
              }}
            >
              Delete permanently
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
