import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Section, SectionField } from "@/Components/shared/section";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { Network, Plus, Trash2 } from "lucide-react";

interface IpRule {
  id: number;
  ip_address: string;
  type: "allow" | "block";
  description: string | null;
  is_active: boolean;
}

interface IpAccessSectionProps {
  ipRules: IpRule[];
  asAccordion?: boolean;
}

export function IpAccessSection({ ipRules, asAccordion }: IpAccessSectionProps) {
  const [rules, setRules] = useState(ipRules);
  const [newIp, setNewIp] = useState("");
  const [newType, setNewType] = useState<"allow" | "block">("block");
  const [newDescription, setNewDescription] = useState("");
  const [loading, setLoading] = useState(false);

  const handleAdd = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading || !newIp.trim()) return;
    setLoading(true);

    router.post(
      route("dashboard.admin.ip-rules.store"),
      {
        ip_address: newIp.trim(),
        type: newType,
        description: newDescription.trim() || null,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
          const props = page.props as { ipRules?: IpRule[] };
          if (props.ipRules) setRules(props.ipRules);
          setNewIp("");
          setNewDescription("");
          toast.add({ title: "IP rule added.", type: "success" });
          setLoading(false);
        },
        onError: (errs) => {
          const msg = Object.values(errs).flat().join(", ");
          toast.add({ title: msg || "Failed to add rule.", type: "error" });
          setLoading(false);
        },
      },
    );
  };

  const toggleActive = (rule: IpRule) => {
    router.put(
      route("dashboard.admin.ip-rules.update", rule.id),
      { is_active: !rule.is_active },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
          const props = page.props as { ipRules?: IpRule[] };
          if (props.ipRules) setRules(props.ipRules);
        },
      },
    );
  };

  const deleteRule = (rule: IpRule) => {
    if (!confirm(`Delete ${rule.ip_address}?`)) return;

    router.delete(route("dashboard.admin.ip-rules.destroy", rule.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (page) => {
        const props = page.props as { ipRules?: IpRule[] };
        if (props.ipRules) setRules(props.ipRules);
        toast.add({ title: "IP rule deleted.", type: "success" });
      },
    });
  };

  return (
    <Section
      title="IP Access Control"
      description="Manage IP-based access rules. Admins are always allowed."
      icon={Network}
      asAccordion={asAccordion}
    >
      <div className="rounded-md bg-muted p-3 text-sm text-muted-foreground">
        Block rules deny access first. If allow rules exist, only those IPs can access the site
        (plus admins). Wildcards supported (e.g., <code>192.168.1.*</code>).
      </div>

      <form onSubmit={handleAdd} className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <SectionField label="Add IP Rule" htmlFor="new_ip" className="flex-1">
          <Input
            id="new_ip"
            placeholder="192.168.1.100 or 10.0.0.*"
            value={newIp}
            onChange={(e) => setNewIp(e.target.value)}
          />
        </SectionField>
        <div className="flex gap-2">
          <div className="flex items-center gap-2">
            <Switch
              id="new_type"
              checked={newType === "allow"}
              onCheckedChange={(c) => setNewType(c ? "allow" : "block")}
            />
            <Label
              htmlFor="new_type"
              className="cursor-pointer font-normal text-sm whitespace-nowrap"
            >
              {newType === "allow" ? "Allow" : "Block"}
            </Label>
          </div>
          <Input
            placeholder="Description (optional)"
            value={newDescription}
            onChange={(e) => setNewDescription(e.target.value)}
            className="w-40"
          />
          <Button type="submit" size="sm" disabled={loading || !newIp.trim()}>
            <Plus className="h-4 w-4 mr-1" />
            Add
          </Button>
        </div>
      </form>

      {rules.length > 0 && (
        <div className="rounded-md border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/50">
                <th className="px-3 py-2 text-left font-medium">IP Address</th>
                <th className="px-3 py-2 text-left font-medium">Type</th>
                <th className="px-3 py-2 text-left font-medium">Description</th>
                <th className="px-3 py-2 text-left font-medium">Active</th>
                <th className="px-3 py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rules.map((rule) => (
                <tr key={rule.id} className="border-b last:border-b-0">
                  <td className="px-3 py-2 font-mono text-xs">{rule.ip_address}</td>
                  <td className="px-3 py-2">
                    <span
                      className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        rule.type === "block"
                          ? "bg-destructive/10 text-destructive"
                          : "bg-green-500/10 text-green-700 dark:bg-green-500/15 dark:text-green-400"
                      }`}
                    >
                      {rule.type}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-muted-foreground">{rule.description || "—"}</td>
                  <td className="px-3 py-2">
                    <Switch checked={rule.is_active} onCheckedChange={() => toggleActive(rule)} />
                  </td>
                  <td className="px-3 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => deleteRule(rule)}
                      className="inline-flex items-center justify-center rounded-md text-sm font-medium h-8 w-8 text-destructive hover:bg-destructive/10 transition-colors"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Section>
  );
}
