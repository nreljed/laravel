import { useState, useCallback } from "react";
import { router } from "@inertiajs/react";

interface UsePasswordVerificationReturn {
  verifyPassword: (password: string) => Promise<boolean>;
  isVerifying: boolean;
  isVerified: boolean;
  error: string | null;
  reset: () => void;
  needsVerification: boolean;
}

export function usePasswordVerification(): UsePasswordVerificationReturn {
  const [isVerifying, setIsVerifying] = useState(false);
  const [isVerified, setIsVerified] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const verifyPassword = useCallback(async (password: string): Promise<boolean> => {
    if (!password) {
      setError("Please enter your password");
      return false;
    }

    setIsVerifying(true);
    setError(null);

    try {
      const result = await new Promise<{ verified: boolean; message?: string }>((resolve, reject) => {
        router.post(route("dashboard.account.verify-delete-password"), { password } as any, {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (page: any) => resolve(page.props as any),
          onError: (errors) => reject(errors),
        });
      });

      if (result.verified) {
        setIsVerified(true);
        return true;
      }

      setError(result.message || "Incorrect password");
      return false;
    } catch {
      setError("Verification failed. Please try again.");
      return false;
    } finally {
      setIsVerifying(false);
    }
  }, []);

  const reset = useCallback(() => {
    setIsVerifying(false);
    setIsVerified(false);
    setError(null);
  }, []);

  return {
    verifyPassword,
    isVerifying,
    isVerified,
    error,
    reset,
    needsVerification: !isVerified,
  };
}
