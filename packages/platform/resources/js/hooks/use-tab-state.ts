import { useState, useCallback } from "react";

export function useTabState(defaultTab: string) {
  const [activeTab, setActiveTab] = useState(() => {
    if (typeof window === "undefined") return defaultTab;
    const params = new URLSearchParams(location.search);
    return params.get("tab") || defaultTab;
  });

  const handleTabChange = useCallback((value: string) => {
    setActiveTab(value);
    const url = new URL(location.href);
    url.searchParams.set("tab", value);
    history.replaceState({}, "", url);
  }, []);

  return { activeTab, handleTabChange };
}
