import { useState, useEffect, useRef, useCallback, useMemo } from "react";

interface FieldValidationOptions {
  endpoint: string;
  param: string;
  value: string;
  debounce?: number;
  validate?: (value: string) => boolean;
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
  const abortControllerRef = useRef<AbortController | null>(null);

  useEffect(() => {
    valueRef.current = value;
  }, [value]);

  const checkValue = useCallback(
    async (val: string) => {
      if (validate && !validate(val)) {
        setStatus({ checking: false, available: null, message: "" });
        return;
      }

      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }

      abortControllerRef.current = new AbortController();

      setStatus((prev) => ({ ...prev, checking: true }));

      try {
        const response = await fetch(
          `${endpoint}?${param}=${encodeURIComponent(val)}`,
          { signal: abortControllerRef.current.signal },
        );

        if (response.status === 403) {
          setStatus({
            checking: false,
            available: null,
            message: "Session expired. Please refresh the page.",
          });
          return;
        }

        const result = await response.json();
        setStatus({
          checking: false,
          available: result.available,
          message: result.message || "",
        });
      } catch (error: unknown) {
        if ((error as Error).name === "AbortError") {
          return;
        }
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
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
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
