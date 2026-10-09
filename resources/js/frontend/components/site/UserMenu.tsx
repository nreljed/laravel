import { Link } from "@inertiajs/react";
import { CircleUserRound, LogIn } from "lucide-react";
import { useAuth } from "@nreljed/platform/hooks/use-auth";

// Public-site user control. Routes to the dashboard when authenticated
// (showing the user's avatar when present) and to the login page otherwise.
// Styled with the fixed non-inverting pill palette to match ThemeToggle.
export function UserMenu() {
  const { user, isLoggedIn } = useAuth();
  const name = (user?.name as string) || "";
  const avatar = (user?.avatar as string) || null;

  return (
    <Link
      href={route(isLoggedIn ? "dashboard.index" : "login")}
      aria-label={isLoggedIn ? "Dashboard" : "Sign in"}
      className="inline-flex h-[53px] w-[53px] items-center justify-center rounded-site-card bg-[#191c16] text-[#edf0e3] transition-transform duration-200 hover:scale-[1.05]"
    >
      {isLoggedIn ? (
        avatar ? (
          <img src={avatar} alt={name} className="h-8 w-8 rounded-full object-cover" />
        ) : (
          <CircleUserRound className="h-5 w-5" />
        )
      ) : (
        <LogIn className="h-5 w-5" />
      )}
    </Link>
  );
}
