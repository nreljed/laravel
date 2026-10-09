import { Moon, Sun } from "lucide-react";
import { useTheme } from "next-themes";

// Project-owned dark-mode switch for the public site chrome. Uses the fixed
// non-inverting pill palette (bg-[#191c16] text-[#edf0e3]) so it stays legible
// in both themes. `resolvedTheme` is undefined until next-themes hydrates, so
// the server and first client render both fall through to the moon icon — no
// SSR/hydration mismatch without a mount effect.
export function ThemeToggle() {
  const { resolvedTheme, setTheme } = useTheme();
  const isDark = resolvedTheme === "dark";

  return (
    <button
      type="button"
      aria-label="Toggle dark mode"
      onClick={() => setTheme(isDark ? "light" : "dark")}
      className="inline-flex h-[53px] w-[53px] items-center justify-center rounded-site-card bg-[#191c16] text-[#edf0e3] transition-transform duration-200 hover:scale-[1.05]"
    >
      {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
    </button>
  );
}
