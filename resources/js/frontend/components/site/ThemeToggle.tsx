import { Moon, Sun } from "lucide-react";
import { useTheme } from "next-themes";

// Project-owned dark-mode switch for the public site chrome. Sits on the fixed
// non-inverting "inverted" surface tokens (site-invert-bg / site-invert-ink) so
// it stays dark and legible in both themes. `resolvedTheme` is undefined until
// next-themes hydrates, so the server and first client render both fall through
// to the moon icon — no SSR/hydration mismatch without a mount effect.
export function ThemeToggle() {
  const { resolvedTheme, setTheme } = useTheme();
  const isDark = resolvedTheme === "dark";

  return (
    <button
      type="button"
      aria-label="Toggle dark mode"
      onClick={() => setTheme(isDark ? "light" : "dark")}
      className="inline-flex h-[53px] w-[53px] items-center justify-center rounded-site-card bg-site-invert-bg text-site-invert-ink transition-transform duration-200 hover:scale-[1.05]"
    >
      {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
    </button>
  );
}
