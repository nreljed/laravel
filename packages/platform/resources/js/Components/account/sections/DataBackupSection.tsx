import { useRef } from "react";
import { useForm } from "@inertiajs/react";
import { Download, RefreshCw, FileJson, CheckCircle2, Database } from "lucide-react";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "../../ui/accordion";
import { Button } from "../../ui/button";
import { Badge } from "../../ui/badge";
import { Field, FieldError, FieldLabel } from "../../ui/field";
import { Input } from "../../ui/input";
import { Separator } from "../../ui/separator";
import { toast } from "../../ui/toast";

export interface DataModule {
  key: string;
  label: string;
  description: string | null;
  supports_export: boolean;
  supports_restore: boolean;
  actions: Array<"export" | "restore">;
  provider: string;
  summary: number | null;
}

function ModuleRestoreForm({ module }: { module: DataModule }) {
  const fileInputRef = useRef<HTMLInputElement>(null);

  const form = useForm({
    backup: null as File | null,
    confirmation: "",
  });

  const errorMessage = (key: "backup" | "confirmation") => form.errors[key];

  const canSubmit = Boolean(form.data.backup) && form.data.confirmation === "restore my data";

  const submit = () => {
    form.post(route("dashboard.account.data.restore", { module: module.key }), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        if (fileInputRef.current) fileInputRef.current.value = "";
        toast.add({ title: `${module.label} restored successfully.`, type: "success" });
      },
      onError: () => {
        toast.add({
          title: "Restore failed.",
          type: "error",
        });
      },
    });
  };

  return (
    <div className="space-y-4">
      <div className="rounded-md bg-muted/60 p-4 text-sm text-muted-foreground">
        <p>
          Restoring replaces <span className="font-medium text-foreground">only your</span>{" "}
          {module.label.toLowerCase()} data with the contents of the backup file. Changes cannot be
          undone — export a fresh copy first if you want to keep your current data.
        </p>
      </div>

      <Field data-invalid={Boolean(errorMessage("backup"))}>
        <FieldLabel htmlFor={`backup-file-${module.key}`}>Backup file</FieldLabel>
        <input
          ref={fileInputRef}
          id={`backup-file-${module.key}`}
          type="file"
          accept=".json,application/json"
          className="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20"
          onChange={(e) => {
            const file = e.target.files?.[0] ?? null;
            form.setData("backup", file);
          }}
        />
        <p className="text-xs text-muted-foreground mt-1">
          Use a file exported from the same module (JSON, up to 20 MB).
        </p>
        {errorMessage("backup") && <FieldError>{errorMessage("backup")}</FieldError>}
      </Field>

      <Field data-invalid={Boolean(errorMessage("confirmation"))}>
        <FieldLabel htmlFor={`restore-confirm-${module.key}`}>
          Type <span className="font-mono text-destructive">restore my data</span> to confirm
        </FieldLabel>
        <Input
          id={`restore-confirm-${module.key}`}
          type="text"
          className="font-mono"
          placeholder="restore my data"
          autoComplete="off"
          value={form.data.confirmation}
          onChange={(e) => form.setData("confirmation", e.target.value)}
        />
        {errorMessage("confirmation") && (
          <FieldError>{errorMessage("confirmation")}</FieldError>
        )}
      </Field>

      <div className="flex justify-end pt-2 border-t border-border">
        <Button variant="destructive" onClick={submit} disabled={!canSubmit || form.processing}>
          <RefreshCw className={`h-4 w-4 mr-2 ${form.processing ? "animate-spin" : ""}`} />
          {form.processing ? "Restoring..." : "Restore my data"}
        </Button>
      </div>
    </div>
  );
}

interface DataBackupSectionProps {
  modules: DataModule[];
}

export function DataBackupSection({ modules }: DataBackupSectionProps) {
  if (modules.length === 0) {
    return (
      <div className="rounded-xl border bg-card">
        <div className="p-6 text-center text-sm text-muted-foreground">
          <FileJson className="h-8 w-8 mx-auto mb-3 text-muted-foreground/60" />
          No modules support data backup and restore right now.
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {modules.map((module) => (
        <div key={module.key} className="rounded-xl border bg-card overflow-hidden">
          <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <Database className="h-4 w-4 text-muted-foreground shrink-0" />
                <h3 className="font-medium">{module.label}</h3>
                {module.summary !== null && (
                  <Badge variant="secondary" className="shrink-0">
                    {module.summary} {module.summary === 1 ? "record" : "records"}
                  </Badge>
                )}
              </div>
              {module.description && (
                <p className="text-sm text-muted-foreground mt-1">{module.description}</p>
              )}
              <div className="flex flex-wrap items-center gap-2 mt-2 text-xs text-muted-foreground">
                {module.supports_export && (
                  <Badge variant="outline" className="gap-1 font-normal">
                    <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                    Backup available
                  </Badge>
                )}
                {module.supports_restore && (
                  <Badge variant="outline" className="gap-1 font-normal">
                    <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                    Restore available
                  </Badge>
                )}
              </div>
            </div>

            {module.supports_export && (
              <div className="flex shrink-0 items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  render={
                    <a href={route("dashboard.account.data.export", { module: module.key })} />
                  }
                >
                  <Download className="h-4 w-4 mr-2" />
                  Export
                </Button>
              </div>
            )}
          </div>

          {module.supports_restore && (
            <>
              <Separator />
              <div className="px-5 pb-5">
                <Accordion className="w-full">
                  <AccordionItem value="restore">
                    <AccordionTrigger className="text-sm font-medium">
                      <span className="flex items-center gap-2">
                        <RefreshCw className="h-4 w-4" />
                        Restore from a backup file
                      </span>
                    </AccordionTrigger>
                    <AccordionContent>
                      <div className="pt-4">
                        <ModuleRestoreForm module={module} />
                      </div>
                    </AccordionContent>
                  </AccordionItem>
                </Accordion>
              </div>
            </>
          )}
        </div>
      ))}
    </div>
  );
}

export default DataBackupSection;