import { useEffect, useRef, useState } from "react";

export function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedValue(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);

  return debouncedValue;
}

export function useFieldAvailability(field: "username" | "email") {
  const [value, setValue] = useState("");
  const [status, setStatus] = useState<"idle" | "checking" | "taken" | "available">("idle");
  const debouncedValue = useDebounce(value, 400);
  const lastChecked = useRef("");
  const genRef = useRef(0);

  useEffect(() => {
    if (!debouncedValue || debouncedValue.length < 3) {
      const id = setTimeout(() => setStatus("idle"), 0);
      return () => clearTimeout(id);
    }

    if (debouncedValue === lastChecked.current) return;

    const gen = ++genRef.current;
    setStatus("checking");

    const params = new URLSearchParams({ field, value: debouncedValue });

    (async () => {
      try {
        const res = await fetch(`${route("register.availability")}?${params}`, {
          headers: { Accept: "application/json" },
        });

        if (gen !== genRef.current) return;

        if (!res.ok) {
          setStatus("idle");
          return;
        }

        const data: { available: boolean } = await res.json();
        lastChecked.current = debouncedValue;
        setStatus(data.available ? "available" : "taken");
      } catch {
        if (gen !== genRef.current) return;
        setStatus("idle");
      }
    })();
  }, [debouncedValue, field]);

  return { status, setValue };
}
