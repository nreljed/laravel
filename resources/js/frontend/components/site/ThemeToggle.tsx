import { Monitor, Moon, Sun } from "lucide-react";
import { useTheme } from "next-themes";
import type { KeyboardEvent } from "react";

// Project-owned theme switch for the public site chrome: a bare icon — no
// button wrapper, the Menu pill is the only button in the header — cycling
// light → dark → automatic, with the glyph changing form per mode (Sun /
// Moon / Monitor). next-themes resolves `theme` only after hydration, so the
// server and first client render both fall back to the automatic glyph; no
// SSR/hydration mismatch without a mount effect.
const modeLabels = {
  light: "Light",
  dark: "Dark",
  system: "Automatic",
} as const;

type ThemeMode = keyof typeof modeLabels;

export function ThemeToggle() {
  const { theme, setTheme } = useTheme();

  const current: ThemeMode = theme === "light" || theme === "dark" ? theme : "system";
  const next: ThemeMode = current === "light" ? "dark" : current === "dark" ? "system" : "light";

  const advance = () => setTheme(next);

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== "Enter" && event.key !== " ") {
      return;
    }
    event.preventDefault();
    advance();
  };

  return (
    <div
      role="button"
      tabIndex={0}
      title={modeLabels[current]}
      aria-label={`Theme mode: ${modeLabels[current]}. Activate to switch.`}
      onClick={advance}
      onKeyDown={handleKeyDown}
      className="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full text-site-ink"
    >
      {current === "system" ? (
        <Monitor className="h-5 w-5" />
      ) : current === "dark" ? (
        <Moon className="h-5 w-5" />
      ) : (
        <Sun className="h-5 w-5" />
      )}
    </div>
  );
}
