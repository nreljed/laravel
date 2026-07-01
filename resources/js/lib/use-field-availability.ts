import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";

export function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value);

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
      setStatus("idle");
      return;
    }

    if (debouncedValue === lastChecked.current) return;

    const gen = ++genRef.current;
    setStatus("checking");

    router.get(route("register.availability"), { field, value: debouncedValue } as any, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page: any) => {
        if (gen !== genRef.current) return;
        const data = page.props as any;
        lastChecked.current = debouncedValue;
        setStatus(data.available ? "available" : "taken");
      },
      onError: () => {},
    });
  }, [debouncedValue, field]);

  return { status, setValue };
}
