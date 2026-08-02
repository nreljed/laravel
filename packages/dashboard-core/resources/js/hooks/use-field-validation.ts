import { useState, useEffect, useRef, useCallback, useMemo } from "react";
import { router } from "@inertiajs/react";

interface FieldValidationOptions {
  endpoint: string;
  param: string;
  value: string;
  debounce?: number;
  validate?: (_value: string) => boolean;
}

interface FieldValidationStatus {
  checking: boolean;
  available: boolean | null;
  message: string;
}

export function useFieldValidation(options: FieldValidationOptions): FieldValidationStatus {
  const { endpoint, param, value, debounce = 800, validate } = options;

  const [status, setStatus] = useState<FieldValidationStatus>({
    checking: false,
    available: null,
    message: "",
  });

  const valueRef = useRef(value);
  const genRef = useRef(0);

  useEffect(() => {
    valueRef.current = value;
  }, [value]);

  const checkValue = useCallback(
    async (val: string) => {
      if (validate && !validate(val)) {
        setStatus({ checking: false, available: null, message: "" });
        return;
      }

      const gen = ++genRef.current;
      setStatus((prev) => ({ ...prev, checking: true }));

      try {
        const result = await new Promise<{ available: boolean; message?: string }>(
          (resolve, reject) => {
            router.get(route(endpoint), { [param]: val } as Record<string, string>, {
              preserveState: true,
              preserveScroll: true,
              onSuccess: ((e: unknown) => {
                const p = (
                  e as { detail?: { page?: { props?: { available?: boolean; message?: string } } } }
                )?.detail?.page?.props;
                resolve({ available: p?.available ?? false, message: p?.message });
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
              }) as any,
              onError: (errors) => reject(errors),
            });
          },
        );

        if (gen !== genRef.current) return;
        setStatus({
          checking: false,
          available: result.available,
          message: result.message || "",
        });
      } catch {
        setStatus({ checking: false, available: null, message: "" });
      }
    },
    [endpoint, param, validate],
  );

  useEffect(() => {
    if (!value) {
      queueMicrotask(() => {
        setStatus({ checking: false, available: null, message: "" });
      });
      return;
    }

    const timer = setTimeout(() => {
      checkValue(valueRef.current);
    }, debounce);

    return () => {
      clearTimeout(timer);
      genRef.current += 1;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, debounce]);

  const computedStatus = useMemo(() => {
    if (!value && !status.checking && status.available === null && !status.message) {
      return { checking: false, available: null, message: "" };
    }
    return status;
  }, [value, status]);

  return computedStatus;
}

export default useFieldValidation;
