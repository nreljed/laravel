import React, { useEffect } from "react";
import { useForm, router } from "@inertiajs/react";
import { Button } from "@/Components/ui/button";
import { PasswordInput } from "@/Components/shared/password-input";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Spinner } from "@/Components/ui/spinner";
import { resetPasswordSchema } from "@/lib/schemas";
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

function SuccessRedirect({ status }: { status: string }) {
  useEffect(() => {
    const timer = setTimeout(() => {
      router.visit(route("login"), { method: "get", replace: true });
    }, 3000);
    return () => clearTimeout(timer);
  }, []);

  return (
    <GuestLayout title="Password Reset">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <CardTitle className="text-2xl">Password Reset Successful!</CardTitle>
          <CardDescription>
            Your password has been reset successfully. You will be redirected to the login page
            shortly.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Alert className="bg-green-500/10 border-green-500/20 dark:bg-green-500/20">
            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 shrink-0" />
            <AlertDescription className="text-green-700 dark:text-green-300">
              {status}
            </AlertDescription>
          </Alert>
        </CardContent>
        <CardFooter className="flex flex-col pt-6">
          <Button
            variant="outline"
            onClick={() => router.visit(route("login"), { replace: true })}
            className="w-full"
          >
            <ArrowLeft className="h-4 w-4" />
            Go to Login
          </Button>
        </CardFooter>
      </Card>
    </GuestLayout>
  );
}

export default function ResetPassword({
  token,
  email,
  status,
  errors: pageErrors,
}: {
  token: string;
  email: string;
  status?: string;
  errors?: Record<string, string>;
}) {
  const {
    data,
    setData,
    post,
    processing,
    errors: formErrors,
    reset,
    setError,
  } = useForm({
    token: token,
    email: email,
    password: "",
    password_confirmation: "",
  });

  const errors = { ...formErrors, ...pageErrors } as Record<string, string>;
  const success = !!status && status.toLowerCase().includes("success");

  if (success) {
    return <SuccessRedirect status={status!} />;
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const result = resetPasswordSchema.safeParse({
      password: data.password,
      password_confirmation: data.password_confirmation,
    });
    if (!result.success) {
      const mapped = zodErrorsToMap(result.error?.issues);
      for (const [key, msg] of Object.entries(mapped)) {
        setError(key as keyof typeof data, msg);
      }
      return;
    }

    post(route("password.store"), {
      onFinish: () => reset("password", "password_confirmation"),
    });
  };

  return (
    <GuestLayout title="Reset Password">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <CardTitle className="text-2xl">Reset your password</CardTitle>
          <CardDescription>Enter your new password below.</CardDescription>
        </CardHeader>

        <form onSubmit={handleSubmit}>
          <input type="hidden" name="token" value={data.token} />
          <input type="hidden" name="email" value={data.email} />
          <CardContent className="grid gap-4">
            {status && (
              <Alert className="bg-green-500/10 border-green-500/20 dark:bg-green-500/20">
                <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 shrink-0" />
                <AlertDescription className="text-green-700 dark:text-green-300">
                  {status}
                </AlertDescription>
              </Alert>
            )}

            <PasswordInput
              variant="register"
              newPasswordLabel="New Password"
              confirmLabel="Confirm New Password"
              showStrengthBar={true}
              newPasswordValue={data.password}
              onNewPasswordChange={(e) => setData("password", e.target.value)}
              confirmValue={data.password_confirmation}
              onConfirmChange={(e) => setData("password_confirmation", e.target.value)}
              newPasswordError={errors.password}
              confirmError={errors.password_confirmation}
              autoFocus
            />

            <Button
              type="submit"
              className="w-full"
              disabled={
                processing ||
                !data.password ||
                !data.password_confirmation ||
                data.password !== data.password_confirmation
              }
            >
              {processing && <Spinner className="mr-2" />}
              Reset Password
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
            Back to Login
          </Button>
        </CardFooter>
      </Card>
    </GuestLayout>
  );
}
