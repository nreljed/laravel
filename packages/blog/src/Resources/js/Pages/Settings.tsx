import { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { route } from "ziggy-js";
import AdminLayout from "@nreljed/platform/Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@nreljed/platform/Components/ui/breadcrumb";
import { AdminSettingsNav } from "@nreljed/platform/Components/settings/AdminSettingsNav";
import { Section, SectionField } from "@nreljed/platform/Components/shared/section";
import { Switch } from "@nreljed/platform/Components/ui/switch";
import { Button } from "@nreljed/platform/Components/ui/button";
import { FileText, Settings } from "lucide-react";
import { toast } from "@nreljed/platform/Components/ui/toast";

interface PostsSettingsProps {
  settings?: { show_word_count?: string };
}

export default function PostsSettings({ settings = {} }: PostsSettingsProps) {
  const [data, setData] = useState({
    show_word_count: (settings.show_word_count ?? "1") === "1",
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    setErrors({});
    router.put(route("dashboard.posts.settings.update"), data, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onSuccess: () => {
        toast.add({ title: "Posts settings saved.", type: "success" });
        setLoading(false);
      },
      onError: (errs) => {
        setErrors(errs as Record<string, string>);
        toast.add({ title: "Failed to save.", type: "error" });
        setLoading(false);
      },
    });
  };

  return (
    <AdminLayout title="Posts Settings">
      <Head title="Posts Settings" />
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <span className="text-muted-foreground">Admin</span>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <span className="text-muted-foreground">Settings</span>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage className="flex items-center gap-1.5">
                <Settings className="h-3.5 w-3.5" />
                Posts
              </BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-col gap-6 md:flex-row">
          <AdminSettingsNav />

          <div className="min-w-0 flex-1">
            <form onSubmit={handleSubmit}>
              <Section
                title="Editor Display"
                description="Configure the editor behavior"
                icon={FileText}
                asAccordion
              >
                <SectionField
                  label="Show Word Counter"
                  htmlFor="show_word_count"
                  error={errors.show_word_count}
                  description="Display word and character count in the editor footer"
                >
                  <Switch
                    id="show_word_count"
                    checked={data.show_word_count}
                    onCheckedChange={(checked) => setData((d) => ({ ...d, show_word_count: checked }))}
                    disabled={loading}
                  />
                </SectionField>
              </Section>

              <div className="mt-6 flex justify-end border-t border-border pt-6">
                <Button type="submit" disabled={loading} size="lg">
                  {loading ? "Saving..." : "Save Posts Settings"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}