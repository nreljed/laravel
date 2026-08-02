import React, { useMemo, useEffect } from "react";
import { useForm, router } from "@inertiajs/react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Field, FieldError } from "@/Components/ui/field";
import { ErrorMessage } from "@/Components/shared/error-message";
import { Spinner } from "@/Components/ui/spinner";
import { forgotPasswordSchema } from "@/lib/schemas";
import { zodErrorsToMap } from "@/lib/utils";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import GuestLayout from "@/Layouts/GuestLayout";
import { ArrowLeft, CheckCircle } from "lucide-react";

const ALLOWED_REDIRECT_PREFIXES = ["/", "/dashboard"];

function isValidRedirectPath(url: string): boolean {
  try {
    const parsed = new URL(url, location.origin);
    if (parsed.origin !== location.origin) return false;
    return ALLOWED_REDIRECT_PREFIXES.some(
      (prefix) => parsed.pathname.startsWith(prefix) || parsed.pathname === prefix,
    );
  } catch {
    return false;
  }
}

function sanitizeRedirect(redirectTo: string | null): string | null {
  if (!redirectTo) return null;
  if (!isValidRedirectPath(redirectTo)) return null;
  return redirectTo;
}

function getBackButtonLabel(redirectTo: string | null) {
  if (!redirectTo) return "Back to login";

  const redirectUrl = new URL(redirectTo, location.origin);
  const path = sanitizeRedirect(redirectTo) ? redirectUrl.pathname : "/login";
  const segments = path.split("/").filter(Boolean);

  if (segments.length === 0) return "Go Back";

  const targetPage = segments.at(-1) || "";
  if (!targetPage) return "Go Back";

  return `Back to ${targetPage.charAt(0).toUpperCase() + targetPage.slice(1)}`;
}

export default function ForgotPassword({
  status,
  errors: pageErrors,
}: {
  status?: string;
  errors?: Record<string, string>;
}) {
  const redirectTo = useMemo(() => {
    const urlParams =
      typeof window !== "undefined" ? new URLSearchParams(location.search) : new URLSearchParams();
    return sanitizeRedirect(urlParams.get("redirect_to"));
  }, []);

  const {
    data,
    setData,
    post,
    processing,
    errors: formErrors,
    setError,
  } = useForm({
    email: "",
  });

  const errors = { ...formErrors, ...pageErrors } as Record<string, string>;

  useEffect(() => {
    if (!status || !redirectTo) return;
    const timer = setTimeout(() => {
      router.visit(redirectTo, { method: "get", replace: true });
    }, 2000);
    return () => clearTimeout(timer);
  }, [status, redirectTo]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const result = forgotPasswordSchema.safeParse(data);
    if (!result.success) {
      const mapped = zodErrorsToMap(result.error?.issues);
      for (const [key, msg] of Object.entries(mapped)) {
        setError(key as keyof typeof data, msg);
      }
      return;
    }

    post(route("password.email"));
  };

  return (
    <GuestLayout title="Forgot Password">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <CardTitle className="text-2xl">Forgot your password?</CardTitle>
          <CardDescription>
            No problem. Enter your email and we&apos;ll send you a reset link.
          </CardDescription>
        </CardHeader>

        <form onSubmit={handleSubmit}>
          {redirectTo && <input type="hidden" name="redirect_to" value={redirectTo} />}
          <CardContent className="grid gap-4">
            {status && (
              <Alert className="bg-green-500/10 border-green-500/20 dark:bg-green-500/20">
                <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 shrink-0" />
                <AlertDescription className="text-green-700 dark:text-green-300">
                  {status}
                </AlertDescription>
              </Alert>
            )}

            <Field data-invalid={!!errors.email}>
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                placeholder="Enter your email"
                value={data.email}
                onChange={(e) => setData("email", e.target.value)}
                required
                autoFocus
                aria-invalid={!!errors.email}
                aria-describedby={errors.email ? "email-error" : undefined}
              />
              {errors.email && (
                <FieldError id="email-error">
                  <ErrorMessage error={errors.email} />
                </FieldError>
              )}
            </Field>

            <Button type="submit" className="w-full" disabled={processing}>
              {processing && <Spinner className="mr-2" />}
              Send Reset Link
            </Button>
          </CardContent>
        </form>

        <CardFooter className="flex flex-col space-y-4">
          <Button
            variant="outline"
            onClick={() => router.visit(route("login"), { replace: true })}
            className="w-full"
          >
            <ArrowLeft className="h-4 w-4" />
            {getBackButtonLabel(redirectTo)}
          </Button>
        </CardFooter>
      </Card>
    </GuestLayout>
  );
}
