import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Button } from "@/Components/ui/button";
import { Switch } from "@/Components/ui/switch";
import { Separator } from "@/Components/ui/separator";
import { Badge } from "@/Components/ui/badge";
import { SettingsAccordionItem } from "@/Components/ui/settings-accordion";
import { Bell, BellOff, Mail, Smartphone } from "lucide-react";

interface NotificationTypeConfig {
  label: string;
  description: string;
  default_channels: string[];
  default_enabled: boolean;
  group: string;
  email_template?: string;
}

type PrefValue = { in_app: boolean; email: boolean };

interface NotificationPrefsSectionProps {
  notificationTypes: Record<string, Record<string, NotificationTypeConfig>>;
  preferences: Record<string, { in_app?: boolean; email?: boolean }>;
}

export function NotificationPrefsSection({
  notificationTypes,
  preferences: initialPrefs,
}: NotificationPrefsSectionProps) {
  const [prefs, setPrefs] = useState<Record<string, PrefValue>>(() => {
    const result: Record<string, PrefValue> = {};
    for (const types of Object.values(notificationTypes)) {
      for (const [key, config] of Object.entries(types)) {
        const saved = initialPrefs[key] || {};
        result[key] = {
          in_app: saved.in_app ?? config.default_enabled ?? true,
          email: saved.email ?? config.default_enabled ?? true,
        };
      }
    }
    return result;
  });
  const [saving, setSaving] = useState(false);

  const hasChanges = () => {
    for (const types of Object.values(notificationTypes)) {
      for (const [key, config] of Object.entries(types)) {
        const p = prefs[key];
        if (!p) return true;
        const saved = initialPrefs[key] || {};
        if (p.in_app !== (saved.in_app ?? config.default_enabled ?? true)) return true;
        if (p.email !== (saved.email ?? config.default_enabled ?? true)) return true;
      }
    }
    return false;
  };

  const toggleChannel = (typeKey: string, channel: "in_app" | "email", checked: boolean) => {
    setPrefs((prev) => ({
      ...prev,
      [typeKey]: { ...(prev[typeKey] || { in_app: true, email: false }), [channel]: checked },
    }));
  };

  const toggleAll = (enable: boolean) => {
    const updated: Record<string, PrefValue> = {};
    for (const types of Object.values(notificationTypes)) {
      for (const [key] of Object.entries(types)) {
        updated[key] = { in_app: enable, email: enable };
      }
    }
    setPrefs(updated);
  };

  const savePrefs = () => {
    if (saving) return;
    setSaving(true);
    const flat: { type: string; channel: string; enabled: boolean }[] = [];
    for (const [type, channels] of Object.entries(prefs)) {
      for (const [channel, enabled] of Object.entries(channels)) {
        flat.push({ type, channel, enabled: enabled ?? false });
      }
    }
    router.post(route("account.notification-preferences"), { preferences: flat }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Notification preferences updated.");
        setSaving(false);
      },
      onError: () => {
        toast.error("Failed to update notification preferences.");
        setSaving(false);
      },
    });
  };

  return (
    <SettingsAccordionItem
      value="notifications"
      icon={Bell}
      title="Notification Preferences"
      description="Choose which notifications you receive"
    >
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <p className="text-muted-foreground text-sm">
            Control your notification channels for each event type.
          </p>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => toggleAll(true)}>
              Enable All
            </Button>
            <Button variant="outline" size="sm" onClick={() => toggleAll(false)}>
              Disable All
            </Button>
          </div>
        </div>

        <Separator />

        {Object.entries(notificationTypes).map(([group, types]) => (
          <div key={group}>
            <h4 className="mb-3 text-sm font-medium text-muted-foreground">{group}</h4>
            <div className="space-y-3">
              {Object.entries(types).map(([typeKey, typeConfig]) => {
                const current = prefs[typeKey] || { in_app: true, email: false };
                return (
                  <div key={typeKey} className="rounded-lg border p-3">
                    <div className="mb-2">
                      <p className="text-sm font-medium">{typeConfig.label}</p>
                      <p className="text-xs text-muted-foreground">{typeConfig.description}</p>
                    </div>
                    <div className="flex items-center gap-4">
                      {typeConfig.default_channels?.includes("in_app") && (
                        <label className="flex items-center gap-2 cursor-pointer">
                          <Switch
                            checked={current.in_app}
                            onCheckedChange={(checked) => toggleChannel(typeKey, "in_app", checked)}
                          />
                          <Smartphone className="h-3.5 w-3.5 text-muted-foreground" />
                          <span className="text-xs">In-app</span>
                        </label>
                      )}
                      {(typeConfig.default_channels?.includes("email") || typeConfig.email_template) && (
                        <label className="flex items-center gap-2 cursor-pointer">
                          <Switch
                            checked={current.email}
                            onCheckedChange={(checked) => toggleChannel(typeKey, "email", checked)}
                          />
                          <Mail className="h-3.5 w-3.5 text-muted-foreground" />
                          <span className="text-xs">Email</span>
                        </label>
                      )}
                      {!current.in_app && !current.email && (
                        <Badge variant="secondary" className="text-[10px] h-4 px-1">
                          <BellOff className="mr-0.5 h-3 w-3" />
                          Muted
                        </Badge>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        ))}

        <Separator />

        <div className="flex justify-end">
          <Button onClick={savePrefs} disabled={saving || !hasChanges()} size="sm">
            {saving ? "Saving..." : "Save Preferences"}
          </Button>
        </div>
      </div>
    </SettingsAccordionItem>
  );
}

export default NotificationPrefsSection;
