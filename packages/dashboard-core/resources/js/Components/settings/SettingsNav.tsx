import * as React from "react";
import { Globe, ShieldCheck, Bell, Lock, Mail, Wrench, Network, Boxes } from "lucide-react";

interface SettingsNavItem {
  value: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
}

interface SettingsNavProps {
  activeTab: string;
  onTabChange: (_tab: string) => void;
  items?: SettingsNavItem[];
  enabledModules?: string[];
}

const CORE_ITEMS: SettingsNavItem[] = [
  { value: "general", label: "General", icon: Globe },
  { value: "registration", label: "Registration", icon: ShieldCheck },
  { value: "notifications", label: "Notifications", icon: Bell },
  { value: "security", label: "Security", icon: Lock },
  { value: "email", label: "Email", icon: Mail },
  { value: "maintenance", label: "Maintenance", icon: Wrench },
  { value: "ip-access", label: "IP Access", icon: Network },
];

function humanizeModuleName(name: string): string {
  return name.replaceAll(/[-_]+/g, " ").replaceAll(/\b\w/g, (char) => char.toUpperCase());
}

export function SettingsNav({ activeTab, onTabChange, items, enabledModules }: SettingsNavProps) {
  const resolvedItems =
    items ??
    (enabledModules && enabledModules.length > 0
      ? enabledModules.map((name) => ({
          value: name,
          label: humanizeModuleName(name),
          icon: Boxes,
        }))
      : CORE_ITEMS);

  return (
    <nav className="flex items-center gap-1 p-1 bg-muted rounded-lg flex-wrap">
      {resolvedItems.map((item) => {
        const Icon = item.icon;
        const active = activeTab === item.value;

        return (
          <button
            key={item.value}
            type="button"
            onClick={() => onTabChange(item.value)}
            title={item.label}
            className={`inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 ${
              active
                ? "bg-background text-foreground shadow-sm"
                : "text-muted-foreground hover:text-foreground hover:bg-background/50"
            }`}
          >
            <Icon className="h-4 w-4" />
            <span className="hidden sm:inline">{item.label}</span>
          </button>
        );
      })}
    </nav>
  );
}

export default SettingsNav;
