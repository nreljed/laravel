import { Head, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Shield, ShieldAlert, ShieldCheck, ShieldX } from "lucide-react";
import { useState, useCallback, useMemo } from "react";
import { toast } from "@/Components/ui/toast";

interface RoleData {
  slug: string;
  name: string;
  system: boolean;
  capabilities: string[];
}

interface PermissionDef {
  slug: string;
  name: string;
  description: string;
}

interface PageProps {
  roles: RoleData[];
  groups: Record<string, PermissionDef[]>;
}

export default function PermissionsManager() {
  const { roles: initialRoles, groups } = usePage().props as unknown as PageProps;
  const [saving, setSaving] = useState<Record<string, boolean>>({});
  const [localOverrides, setLocalOverrides] = useState<Record<string, Record<string, boolean>>>({});
  const [selectedRole, setSelectedRole] = useState(
    () => initialRoles.find((r) => !r.system)?.slug ?? initialRoles[0]?.slug ?? "",
  );

  const roles = useMemo(() => {
    return initialRoles.map((role) => {
      const overrides = localOverrides[role.slug];
      if (!overrides) return role;
      const capsSet = new Set(role.capabilities);
      for (const [cap, granted] of Object.entries(overrides)) {
        if (granted) capsSet.add(cap);
        else capsSet.delete(cap);
      }
      return { ...role, capabilities: [...capsSet] };
    });
  }, [initialRoles, localOverrides]);

  const currentRole = roles.find((r) => r.slug === selectedRole) ?? roles[0]!;
  const isLocked = currentRole.system;

  const handleToggle = useCallback(
    async (roleSlug: string, capability: string) => {
      const role = roles.find((r) => r.slug === roleSlug) ?? roles[0]!;
      const currentValue = role.capabilities.includes(capability);
      const newValue = !currentValue;
      const key = `${roleSlug}:${capability}`;

      setSaving((prev) => ({ ...prev, [key]: true }));
      setLocalOverrides((prev) => ({
        ...prev,
        [roleSlug]: { ...prev[roleSlug], [capability]: newValue },
      }));

      try {
        const response = await fetch(route("dashboard.admin.permissions.toggle"), {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ role: roleSlug, capability, grant: newValue }),
        });
        const data = await response.json();

        if (data.success) {
          toast.add({ title: data.message, type: "success" });
        } else {
          setLocalOverrides((prev) => {
            const next = { ...prev };
            if (Object.hasOwn(next, roleSlug)) {
              const override = { ...next[roleSlug] };
              delete override[capability];
              next[roleSlug] = override;
            }
            return next;
          });
          toast.add({ title: data.error ?? "Failed to update permission", type: "error" });
        }
      } catch {
        setLocalOverrides((prev) => {
          const next = { ...prev };
          if (Object.hasOwn(next, roleSlug)) {
            const override = { ...next[roleSlug] };
            delete override[capability];
            next[roleSlug] = override;
          }
          return next;
        });
        toast.add({ title: "Network error. Please try again.", type: "error" });
      } finally {
        setSaving((prev) => {
          const next = { ...prev };
          delete next[key];
          return next;
        });
      }
    },
    [roles],
  );

  const groupEntries = Object.entries(groups);

  return (
    <AdminLayout title="Permissions Manager">
      <Head title="Permissions Manager" />

      <div className="mb-6 space-y-2">
        <h1 className="text-2xl font-bold text-foreground">Permissions Manager</h1>
        <p className="text-sm text-muted-foreground">
          Select a role and toggle individual permissions to grant or revoke them.
        </p>
      </div>

      {isLocked && (
        <Alert className="mb-6 border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
          <ShieldAlert className="h-5 w-5 text-amber-600 dark:text-amber-400" />
          <AlertDescription className="text-amber-700 dark:text-amber-300 ml-2">
            The <strong>{currentRole.name}</strong> role is locked and cannot be modified from this
            interface to prevent accidental self-lockout.
          </AlertDescription>
        </Alert>
      )}

      {/* Role selector */}
      <div className="mb-6 flex items-center gap-3">
        <label className="text-sm font-medium text-foreground whitespace-nowrap">Role</label>
        <Select
          value={selectedRole}
          onValueChange={(value) => {
            if (value) setSelectedRole(value);
          }}
        >
          <SelectTrigger className="w-full sm:w-64" aria-label="Select role">
            <SelectValue>{currentRole.name}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            {roles.map((role) => (
              <SelectItem key={role.slug} value={role.slug}>
                {role.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Permission groups */}
      <div className="space-y-6">
        {groupEntries.map(([groupName, permissions]) => (
          <Card key={groupName}>
            <CardHeader className="pb-2">
              <div className="flex items-center gap-2">
                <Shield className="h-4 w-4 text-muted-foreground" />
                <CardTitle className="text-sm font-semibold text-foreground">{groupName}</CardTitle>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              {permissions.map((perm) => {
                const granted = currentRole.capabilities.includes(perm.slug);
                const key = `${currentRole.slug}:${perm.slug}`;
                const isLoading = saving[key];

                return (
                  <div
                    key={perm.slug}
                    className="flex items-center justify-between py-2 px-4 border-b border-border/50 last:border-0 hover:bg-muted/20 transition-colors"
                  >
                    <div className="flex items-center gap-2 min-w-0 mr-4">
                      <span className="text-sm text-foreground truncate">{perm.name}</span>
                      <code className="text-xs text-muted-foreground shrink-0">{perm.slug}</code>
                    </div>
                    {isLocked ? (
                      granted ? (
                        <ShieldCheck className="h-4 w-4 text-muted-foreground/60 shrink-0" />
                      ) : (
                        <ShieldX className="h-4 w-4 text-destructive/60 shrink-0" />
                      )
                    ) : (
                      <Switch
                        checked={granted}
                        disabled={isLoading}
                        onCheckedChange={() => handleToggle(currentRole.slug, perm.slug)}
                      />
                    )}
                  </div>
                );
              })}
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Info card */}
      <Card className="mt-6 bg-muted/30">
        <CardContent className="py-4">
          <div className="flex items-start gap-3">
            <Shield className="h-5 w-5 text-muted-foreground shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium text-foreground">About Permissions</p>
              <ul className="text-sm text-muted-foreground mt-1 space-y-0.5 list-disc list-inside">
                <li>Changes take effect immediately — users do not need to log out.</li>
                <li>
                  <strong>System roles</strong> (User, Author, Editor, Admin) are fully locked —
                  they are managed by the config catalog to prevent self-lockout.
                </li>
                <li>
                  Use <strong>Roles</strong> to assign a set of permissions to multiple users at
                  once.
                </li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
