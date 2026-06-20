import { useState, useCallback } from "react";

const DEFAULT_CONFIG = {
  logErrors: import.meta.env.DEV,
  defaultMessage: "An error occurred. Please try again.",
} as const;

interface ParsedError {
  message: string;
  errors: Record<string, unknown> | null;
  fieldErrors: Record<string, string>;
  code: string;
  status: number | null;
  parsed: boolean;
}

interface ErrorHandlerConfig {
  logErrors?: boolean;
  defaultMessage?: string;
}

interface HandleErrorOptions {
  onFieldError?: (fieldErrors: Record<string, string>) => void;
  onFormError?: (message: string) => void;
  onComplete?: (parsed: ParsedError) => void;
  clearFirst?: boolean;
  showError?: boolean;
}

interface UseErrorHandlerReturn {
  error: string | null;
  fieldErrors: Record<string, string>;
  isErrorVisible: boolean;
  hasErrors: boolean;
  setError: (message: string) => void;
  setFieldError: (field: string, message: string) => void;
  clearErrors: () => void;
  clearFieldError: (field: string) => void;
  hideError: () => void;
  showError: () => void;
  handleError: (err: unknown, options?: HandleErrorOptions) => ParsedError;
  parseErrors: (err: unknown) => ParsedError;
  getFieldError: (field: string) => string | null;
  hasFieldError: (field: string) => boolean;
}

function parseErrorResponse(error: unknown): ParsedError {
  if (error && typeof error === "object" && (error as Record<string, unknown>).parsed) {
    return error as ParsedError;
  }

  if (typeof error === "string") {
    return {
      message: error,
      errors: null,
      fieldErrors: {},
      code: "STRING_ERROR",
      status: null,
      parsed: true,
    };
  }

  if (error === null || error === undefined) {
    return {
      message: "An unknown error occurred",
      errors: null,
      fieldErrors: {},
      code: "UNKNOWN_ERROR",
      status: null,
      parsed: true,
    };
  }

  if (typeof error === "object") {
    const err = error as Record<string, unknown>;
    const fieldErrors: Record<string, string> = {};
    let message = (err.message as string) || (err.error as string) || DEFAULT_CONFIG.defaultMessage;

    Object.keys(err).forEach((key) => {
      const value = err[key];

      if (["message", "error", "code", "status"].includes(key)) {
        return;
      }

      if (Array.isArray(value)) {
        fieldErrors[key] = String(value[0]);
      } else if (typeof value === "string") {
        fieldErrors[key] = value;
      } else if (value && typeof value === "object") {
        const nestedErr = value as Record<string, unknown>;
        Object.keys(nestedErr).forEach((nestedKey) => {
          const nestedValue = nestedErr[nestedKey];
          fieldErrors[nestedKey] = Array.isArray(nestedValue)
            ? String(nestedValue[0])
            : (nestedValue as Record<string, string>)?.message ||
              (nestedValue as Record<string, string>)?.error ||
              "Invalid";
        });
      }
    });

    return {
      message,
      errors: (err.errors as Record<string, unknown>) || null,
      fieldErrors,
      code:
        (err.code as string) ||
        (Object.keys(fieldErrors).length > 0 ? "VALIDATION_ERROR" : "ERROR"),
      status: (err.status as number) || null,
      parsed: true,
    };
  }

  return {
    message: DEFAULT_CONFIG.defaultMessage,
    errors: null,
    fieldErrors: {},
    code: "UNKNOWN_ERROR",
    status: null,
    parsed: true,
  };
}

export function useErrorHandler(config?: ErrorHandlerConfig): UseErrorHandlerReturn {
  const finalConfig = { ...DEFAULT_CONFIG, ...config };

  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [isErrorVisible, setIsErrorVisible] = useState(false);

  const handleError = useCallback(
    (err: unknown, options?: HandleErrorOptions): ParsedError => {
      const {
        onFieldError,
        onFormError,
        onComplete,
        clearFirst = true,
        showError = true,
      } = options ?? {};

      const parsed = parseErrorResponse(err);

      if (finalConfig.logErrors) {
        console.error("[ErrorHandler]", {
          original: err,
          parsed,
        });
      }

      if (clearFirst) {
        setFieldErrors({});
        setError(null);
      }

      if (Object.keys(parsed.fieldErrors).length > 0) {
        setFieldErrors(parsed.fieldErrors);
        onFieldError?.(parsed.fieldErrors);
      }

      if (parsed.message) {
        setError(parsed.message);
        onFormError?.(parsed.message);
      }

      if (showError) {
        setIsErrorVisible(true);
      }

      onComplete?.(parsed);

      return parsed;
    },
    [finalConfig.logErrors],
  );

  const setFieldError = useCallback((field: string, message: string) => {
    setFieldErrors((prev) => ({ ...prev, [field]: message }));
  }, []);

  const setFormError = useCallback((message: string) => {
    setError(message);
    setIsErrorVisible(true);
  }, []);

  const clearErrors = useCallback(() => {
    setError(null);
    setFieldErrors({});
    setIsErrorVisible(false);
  }, []);

  const clearFieldError = useCallback((field: string) => {
    setFieldErrors((prev) => {
      const newErrors = { ...prev };
      delete newErrors[field];
      return newErrors;
    });
  }, []);

  const hideError = useCallback(() => {
    setIsErrorVisible(false);
  }, []);

  const showErrorDisplay = useCallback(() => {
    if (error || Object.keys(fieldErrors).length > 0) {
      setIsErrorVisible(true);
    }
  }, [error, fieldErrors]);

  const parseErrors = useCallback((err: unknown): ParsedError => {
    return parseErrorResponse(err);
  }, []);

  const getFieldError = useCallback(
    (field: string): string | null => {
      return fieldErrors[field] || null;
    },
    [fieldErrors],
  );

  const hasFieldErrorFn = useCallback(
    (field: string): boolean => {
      return !!fieldErrors[field];
    },
    [fieldErrors],
  );

  const hasErrors = error !== null || Object.keys(fieldErrors).length > 0;

  return {
    error,
    fieldErrors,
    isErrorVisible,
    hasErrors,
    setError: setFormError,
    setFieldError,
    clearErrors,
    clearFieldError,
    hideError,
    showError: showErrorDisplay,
    handleError,
    parseErrors,
    getFieldError,
    hasFieldError: hasFieldErrorFn,
  };
}

export default useErrorHandler;
