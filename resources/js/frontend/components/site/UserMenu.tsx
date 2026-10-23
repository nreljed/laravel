import { Link, router } from "@inertiajs/react";
import { Menu as MenuPrimitive } from "@base-ui/react/menu";
import { CircleUserRound, LayoutDashboard, LogIn, LogOut, UserPlus } from "lucide-react";
import { useAuth } from "@nreljed/platform/hooks/use-auth";
import { cn } from "cn";
import { SiteAvatar } from "./SiteAvatar";
import { useSiteTheme } from "./site-theme";

// Public-site user control. Signed out it falls back to a bare user icon;
// signed in it shows the nav-user avatar (image, or the user's initials
// monogram) — neither wrapped in a button, the Menu pill is the only button in
// the header. The trigger opens the account menu (Sign in / Create account
// signed out, Dashboard / Account / Log out signed in) where the icons live.
// Built on Base UI's Menu with the site tokens (not the dashboard kit) so the
// popup matches the marketing theme instead of the admin palette.
const triggerClass =
  "inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full text-site-ink";

const itemClass =
  "flex w-full cursor-default items-center gap-2.5 rounded-site-card px-site-sm py-site-xs text-sm text-site-ink outline-hidden select-none data-highlighted:bg-site-alt-1";

const popupClass =
  "min-w-56 origin-(--transform-origin) rounded-site-card border border-site-border bg-site-bg-strong p-1.5 text-site-ink shadow-lg outline-none";

export function UserMenu() {
  const { user, isLoggedIn } = useAuth();
  const theme = useSiteTheme();
  const name = user?.name || "Account";
  const email = user?.email || "";

  const avatarNode = <SiteAvatar name={name} src={user?.avatar ?? null} size="lg" />;

  return (
    <MenuPrimitive.Root>
      <MenuPrimitive.Trigger
        nativeButton={false}
        render={
          <div
            role="button"
            tabIndex={0}
            aria-label={isLoggedIn ? "Account menu" : "Sign in"}
            className={triggerClass}
          />
        }
      >
        {isLoggedIn ? avatarNode : <CircleUserRound className="h-5 w-5" />}
      </MenuPrimitive.Trigger>

      <MenuPrimitive.Portal>
        <MenuPrimitive.Positioner
          data-site-theme={theme}
          className="isolate z-50 outline-none"
          align="end"
          side="bottom"
          sideOffset={8}
        >
          <MenuPrimitive.Popup className={popupClass}>
            {isLoggedIn ? (
              <>
                <div className="flex items-center gap-site-sm px-site-xs py-site-xs">
                  {avatarNode}
                  <div className="grid min-w-0 flex-1 leading-tight">
                    <span className="truncate text-sm font-semibold text-site-ink">{name}</span>
                    {email ? (
                      <span className="truncate text-xs text-site-ink-soft">{email}</span>
                    ) : null}
                  </div>
                </div>
                <MenuPrimitive.Separator className="-mx-1 my-1 h-px bg-site-border" />
                <MenuPrimitive.Item
                  render={<Link href={route("dashboard.index")} />}
                  className={itemClass}
                >
                  <LayoutDashboard className="h-4 w-4 text-site-ink-soft" />
                  <span>Dashboard</span>
                </MenuPrimitive.Item>
                <MenuPrimitive.Item
                  render={<Link href={route("dashboard.account")} />}
                  className={itemClass}
                >
                  <CircleUserRound className="h-4 w-4 text-site-ink-soft" />
                  <span>Account</span>
                </MenuPrimitive.Item>
                <MenuPrimitive.Separator className="-mx-1 my-1 h-px bg-site-border" />
                <MenuPrimitive.Item
                  className={cn(itemClass, "cursor-pointer")}
                  onClick={() => router.post(route("logout"))}
                >
                  <LogOut className="h-4 w-4 text-site-ink-soft" />
                  <span>Log out</span>
                </MenuPrimitive.Item>
              </>
            ) : (
              <>
                <MenuPrimitive.Item render={<Link href={route("login")} />} className={itemClass}>
                  <LogIn className="h-4 w-4 text-site-ink-soft" />
                  <span>Sign in</span>
                </MenuPrimitive.Item>
                <MenuPrimitive.Item
                  render={<Link href={route("register")} />}
                  className={itemClass}
                >
                  <UserPlus className="h-4 w-4 text-site-ink-soft" />
                  <span>Create account</span>
                </MenuPrimitive.Item>
              </>
            )}
          </MenuPrimitive.Popup>
        </MenuPrimitive.Positioner>
      </MenuPrimitive.Portal>
    </MenuPrimitive.Root>
  );
}
