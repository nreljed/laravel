import { useTheme } from "next-themes";

export function useDarkMode(): boolean {
  const { resolvedTheme } = useTheme();
  return resolvedTheme === "dark";
}
