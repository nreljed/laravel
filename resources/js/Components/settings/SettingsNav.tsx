import * as React from "react";
import {
  Globe,
  ShieldCheck,
  Bell,
  Lock,
  Mail,
  Wrench,
  Languages,
} from "lucide-react";

interface SettingsNavItem {
  value: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
}

interface SettingsNavProps {
  activeTab: string;
  onTabChange: (_tab: string) => void;
}

const CORE_ITEMS: SettingsNavItem[] = [
  { value: "general", label: "General", icon: Globe },
  { value: "registration", label: "Registration", icon: ShieldCheck },
  { value: "notifications", label: "Notifications", icon: Bell },
  { value: "security", label: "Security", icon: Lock },
  { value: "email", label: "Email", icon: Mail },
  { value: "maintenance", label: "Maintenance", icon: Wrench },
  { value: "localization", label: "Localization", icon: Languages },
];

export function SettingsNav({ activeTab, onTabChange }: SettingsNavProps) {
  return (
    <nav className="flex items-center gap-1 p-1 bg-muted rounded-lg flex-wrap">
      {CORE_ITEMS.map((item) => {
        const Icon = item.icon;
        const active = activeTab === item.value;

        return (
          <button
            key={item.value}
            type="button"
            onClick={() => onTabChange(item.value)}
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
