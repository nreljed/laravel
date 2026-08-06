import { Link, usePage } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import {
  Globe,
  ShieldCheck,
  Lock,
  Network,
  Bell,
  Mail,
  Wrench,
  Menu,
  TerminalSquare,
  type LucideIcon,
} from "lucide-react";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/Components/ui/sheet";
import { Button } from "@/Components/ui/button";

interface NavItem {
  icon: LucideIcon;
  label: string;
  route: string;
}

interface NavCategory {
  label: string | null;
  items: NavItem[];
}

const NAV_ITEMS: NavCategory[] = [
  {
    label: null,
    items: [{ icon: Globe, label: "General", route: "dashboard.admin.settings.general" }],
  },
  {
    label: "Security",
    items: [
      { icon: ShieldCheck, label: "Registration", route: "dashboard.admin.settings.registration" },
      { icon: Lock, label: "Security", route: "dashboard.admin.settings.security" },
      { icon: Network, label: "IP Access", route: "dashboard.admin.settings.ip-access" },
    ],
  },
  {
    label: "Communication",
    items: [
      { icon: Bell, label: "Notifications", route: "dashboard.admin.settings.notifications" },
      { icon: Mail, label: "Email", route: "dashboard.admin.settings.email" },
    ],
  },
  {
    label: "Maintenance",
    items: [{ icon: Wrench, label: "Maintenance", route: "dashboard.admin.settings.maintenance" }],
  },
  {
    label: "Developer",
    items: [{ icon: TerminalSquare, label: "Developer", route: "dashboard.admin.settings.dev" }],
  },
];

function getActiveRoute(url: string): string {
  if (url.includes("/settings/registration")) return "dashboard.admin.settings.registration";
  if (url.includes("/settings/notifications")) return "dashboard.admin.settings.notifications";
  if (url.includes("/settings/security")) return "dashboard.admin.settings.security";
  if (url.includes("/settings/email")) return "dashboard.admin.settings.email";
  if (url.includes("/settings/maintenance")) return "dashboard.admin.settings.maintenance";
  if (url.includes("/settings/ip-access")) return "dashboard.admin.settings.ip-access";
  if (url.includes("/settings/dev")) return "dashboard.admin.settings.dev";
  return "dashboard.admin.settings.general";
}

function NavItems({ onNavigate }: { onNavigate?: () => void }) {
  const { url } = usePage();
  const activeRoute = getActiveRoute(url);

  return (
    <ul className="space-y-6">
      {NAV_ITEMS.map((category) => (
        <li key={category.label ?? "__top"}>
          {category.label && (
            <p className="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              {category.label}
            </p>
          )}
          <ul className="space-y-0.5">
            {category.items.map((item) => {
              const Icon = item.icon;
              const isActive = activeRoute === item.route;
              return (
                <li key={item.route}>
                  <Link
                    href={route(item.route)}
                    onClick={onNavigate}
                    className={cn(
                      "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                      isActive
                        ? "bg-accent text-accent-foreground shadow-sm"
                        : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
                    )}
                  >
                    <Icon className="h-4 w-4 shrink-0" />
                    <span>{item.label}</span>
                  </Link>
                </li>
              );
            })}
          </ul>
        </li>
      ))}
    </ul>
  );
}

interface AdminSettingsNavProps {
  className?: string;
}

export function AdminSettingsNav({ className }: AdminSettingsNavProps) {
  return (
    <>
      <nav className={cn("hidden md:block w-56 shrink-0", className)}>
        <NavItems />
      </nav>

      <div className={cn("md:hidden", className)}>
        <Sheet>
          <SheetTrigger
            render={
              <Button variant="outline" size="sm">
                <Menu className="h-4 w-4 mr-2" />
                Menu
              </Button>
            }
          />
          <SheetContent side="left" showCloseButton>
            <SheetHeader>
              <SheetTitle>Settings</SheetTitle>
            </SheetHeader>
            <div className="flex-1 overflow-y-auto px-4 pb-4">
              <NavItems onNavigate={() => {}} />
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </>
  );
}

export { NAV_ITEMS };

export default AdminSettingsNav;
