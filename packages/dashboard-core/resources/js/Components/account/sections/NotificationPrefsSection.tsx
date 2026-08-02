import { useState, useEffect, useMemo } from "react";
import type { LucideIcon } from "lucide-react";
import { router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Button } from "@/Components/ui/button";
import { Checkbox } from "@/Components/ui/checkbox";
import { Separator } from "@/Components/ui/separator";
import { Badge } from "@/Components/ui/badge";
import { SettingsAccordionItem } from "@/Components/shared/settings-accordion";
import { Bell, BellOff, Mail, Monitor, Volume2, VolumeX } from "lucide-react";

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
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

const CHANNEL_ICONS: Record<string, LucideIcon> = {
  in_app: Monitor,
  email: Mail,
};

export function NotificationPrefsSection({
  notificationTypes,
  preferences: initialPrefs,
  asAccordion: _asAccordion,
  accordionDefaultOpen: _accordionDefaultOpen,
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

  const hasChanges = useMemo(() => {
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
  }, [prefs, notificationTypes, initialPrefs]);

  const toggleChannel = (typeKey: string, channel: "in_app" | "email", checked: boolean) => {
    setPrefs((prev) => ({
      ...prev,
      [typeKey]: { ...(prev[typeKey] || { in_app: true, email: false }), [channel]: checked },
    }));
  };

  const toggleAll = (enable: boolean) => {
    const updated: Record<string, PrefValue> = {};
    for (const types of Object.values(notificationTypes)) {
      for (const key of Object.keys(types)) {
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
    router.post(
      route("dashboard.account.notifications"),
      { preferences: flat },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.add({ title: "Notification preferences updated.", type: "success" });
          setSaving(false);
        },
        onError: () => {
          toast.add({ title: "Failed to update notification preferences.", type: "error" });
          setSaving(false);
        },
      },
    );
  };

  const [notificationSound, setNotificationSound] = useState(
    () => localStorage.getItem("notification_sound_enabled") !== "false",
  );

  useEffect(() => {
    localStorage.setItem("notification_sound_enabled", String(notificationSound));
  }, [notificationSound]);

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

        <label className="flex items-center justify-between rounded-lg border p-3 cursor-pointer">
          <div className="flex items-center gap-3">
            {notificationSound ? (
              <Volume2 className="h-4 w-4 text-muted-foreground" />
            ) : (
              <VolumeX className="h-4 w-4 text-muted-foreground" />
            )}
            <div>
              <p className="text-sm font-medium">Notification Sound</p>
              <p className="text-xs text-muted-foreground">
                Play a sound when new notifications arrive
              </p>
            </div>
          </div>
          <Checkbox
            checked={notificationSound}
            onCheckedChange={(checked) => setNotificationSound(!!checked)}
          />
        </label>

        <Separator />

        {Object.entries(notificationTypes).map(([group, types]) => (
          <div key={group} className="space-y-3">
            <h4 className="text-sm font-semibold text-foreground">{group}</h4>
            <div className="space-y-2">
              {Object.entries(types).map(([typeKey, typeConfig]) => {
                const current = prefs[typeKey] || { in_app: true, email: false };
                return (
                  <div
                    key={typeKey}
                    className="flex items-start justify-between rounded-lg border p-3"
                  >
                    <div className="flex-1 min-w-0 mr-4 space-y-1">
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-medium leading-none text-foreground">
                          {typeConfig.label}
                        </p>
                        {!current.in_app && !current.email && (
                          <Badge variant="secondary" className="text-[10px] h-4 px-1">
                            <BellOff className="mr-0.5 h-3 w-3" />
                            Muted
                          </Badge>
                        )}
                      </div>
                      <p className="text-xs text-muted-foreground">{typeConfig.description}</p>
                    </div>
                    <div className="flex items-center gap-4 shrink-0">
                      {(["in_app", "email"] as const).map((channel) => {
                        const Icon = CHANNEL_ICONS[channel]!;
                        return (
                          <div key={channel} className="flex items-center gap-1.5">
                            <Checkbox
                              id={`${typeKey}-${channel}`}
                              checked={current[channel]}
                              onCheckedChange={(checked) => {
                                toggleChannel(typeKey, channel, checked === true);
                              }}
                            />
                            <Icon className="h-3.5 w-3.5 text-muted-foreground" />
                            <label
                              htmlFor={`${typeKey}-${channel}`}
                              className="text-xs cursor-pointer text-muted-foreground"
                            >
                              {channel === "in_app" ? "In-App" : "Email"}
                            </label>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        ))}

        <Separator />

        <div className="flex justify-end">
          <Button onClick={savePrefs} disabled={saving || !hasChanges} size="sm">
            {saving ? "Saving..." : "Save Preferences"}
          </Button>
        </div>
      </div>
    </SettingsAccordionItem>
  );
}

export default NotificationPrefsSection;
