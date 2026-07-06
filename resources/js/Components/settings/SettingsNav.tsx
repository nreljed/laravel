import * as React from "react";
import {
  Globe,
  ShieldCheck,
  Bell,
  Lock,
  Mail,
  Wrench,
  Languages,
  Puzzle,
  Building2,
  Calendar,
  ShoppingCart,
} from "lucide-react";

const MODULE_COLORS: Record<string, string> = {
  properties: "bg-blue-500",
  booking: "bg-amber-500",
  shop: "bg-pink-500",
};

interface SettingsNavItem {
  value: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  module?: string;
}

interface SettingsNavProps {
  activeTab: string;
  onTabChange: (_tab: string) => void;
  enabledModules?: string[];
}

const CORE_ITEMS: SettingsNavItem[] = [
  { value: "general", label: "General", icon: Globe },
  { value: "registration", label: "Registration", icon: ShieldCheck },
  { value: "notifications", label: "Notifications", icon: Bell },
  { value: "security", label: "Security", icon: Lock },
  { value: "email", label: "Email", icon: Mail },
  { value: "maintenance", label: "Maintenance", icon: Wrench },
  { value: "localization", label: "Localization", icon: Languages },
  { value: "modules", label: "Modules", icon: Puzzle },
];

const MODULE_ITEMS: SettingsNavItem[] = [
  { value: "properties", label: "Properties", icon: Building2, module: "properties" },
  { value: "booking", label: "Booking", icon: Calendar, module: "booking" },
  { value: "shop", label: "Shop", icon: ShoppingCart, module: "shop" },
];

export function SettingsNav({ activeTab, onTabChange, enabledModules = [] }: SettingsNavProps) {
  const allItems = React.useMemo(() => {
    const mods = Array.isArray(enabledModules) ? enabledModules : [];
    const items: SettingsNavItem[] = [...CORE_ITEMS];

    const hasModules = MODULE_ITEMS.some((m) => mods.includes(m.module ?? ""));
    if (hasModules) {
      items.push({ value: "", label: "", icon: Globe, module: "separator" });
    }

    for (const item of MODULE_ITEMS) {
      if (mods.includes(item.module ?? "")) {
        items.push(item);
      }
    }

    return items;
  }, [enabledModules]);

  return (
    <nav className="flex items-center gap-1 p-1 bg-muted rounded-lg flex-wrap">
      {allItems.map((item) => {
        if (item.module === "separator") {
          return <div key="sep" className="w-px h-6 bg-border/50 mx-0.5" role="separator" />;
        }

        const Icon = item.icon;
        const active = activeTab === item.value;
        const dotColor = item.module ? MODULE_COLORS[item.module] : null;

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
            {dotColor && <span className={`h-1.5 w-1.5 rounded-full ${dotColor} shrink-0`} />}
            <Icon className="h-4 w-4" />
            <span className="hidden sm:inline">{item.label}</span>
          </button>
        );
      })}
    </nav>
  );
}

export default SettingsNav;
