import { useTheme } from "next-themes"
import { useMemo } from "react"

export function useDarkMode(): boolean {
  const { theme } = useTheme()

  return useMemo(() => {
    if (theme === "dark") return true
    if (theme === "light") return false
    if (typeof window === "undefined") return false
    return window.matchMedia("(prefers-color-scheme: dark)").matches
  }, [theme])
}
