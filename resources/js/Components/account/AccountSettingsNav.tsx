import { Link, usePage } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { User, Shield, Bell, Puzzle, AlertTriangle, Menu, type LucideIcon } from "lucide-react";
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
    items: [{ icon: User, label: "Profile", route: "dashboard.account.page.profile" }],
  },
  {
    label: "Security",
    items: [{ icon: Shield, label: "Password & Email", route: "dashboard.account.page.security" }],
  },
  {
    label: "Preferences",
    items: [{ icon: Bell, label: "Notifications", route: "dashboard.account.page.notifications" }],
  },
  {
    label: "Integrations",
    items: [
      { icon: Puzzle, label: "AI Provider Keys", route: "dashboard.account.page.integrations" },
    ],
  },
  {
    label: "Account",
    items: [{ icon: AlertTriangle, label: "Advanced", route: "dashboard.account.page.advanced" }],
  },
];

function getActiveRoute(url: string): string {
  if (url === "/dashboard/account" || url === "/dashboard/account/" || url.endsWith("/profile")) {
    return "dashboard.account.page.profile";
  }
  if (url.includes("/security")) return "dashboard.account.page.security";
  if (url.includes("/notifications")) return "dashboard.account.page.notifications";
  if (url.includes("/integrations")) return "dashboard.account.page.integrations";
  if (url.includes("/advanced")) return "dashboard.account.page.advanced";
  return "dashboard.account.page.profile";
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

interface AccountSettingsNavProps {
  className?: string;
}

export function AccountSettingsNav({ className }: AccountSettingsNavProps) {
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

export default AccountSettingsNav;
