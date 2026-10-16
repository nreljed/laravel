import { Moon, Sun } from "lucide-react";
import { useTheme } from "next-themes";
import type { KeyboardEvent } from "react";

// Project-owned theme switch for the public site chrome: a bare icon — no
// button wrapper, the Menu pill is the only button in the header — flipping
// between light and dark, with the glyph matching the resolved mode (Sun /
// Moon). next-themes resolves `resolvedTheme` only after hydration, so the
// first paint falls back to the light glyph before it settles.
export function ThemeToggle() {
  const { resolvedTheme, setTheme } = useTheme();

  const isDark = resolvedTheme === "dark";
  const label = isDark ? "Dark" : "Light";

  const advance = () => setTheme(isDark ? "light" : "dark");

  const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    event.preventDefault();
    advance();
  };

  return (
    <div
      role="button"
      tabIndex={0}
      title={label}
      aria-label={`Theme mode: ${label}. Activate to switch.`}
      onClick={advance}
      onKeyDown={handleKeyDown}
      className="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full text-site-ink"
    >
      {isDark ? <Moon className="h-5 w-5" /> : <Sun className="h-5 w-5" />}
    </div>
  );
}
