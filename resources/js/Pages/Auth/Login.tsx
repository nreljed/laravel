import * as React from "react";
import { Link, useForm, usePage } from "@inertiajs/react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { PasswordInput } from "@/Components/shared/password-input";
import { Label } from "@/Components/ui/label";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Checkbox } from "@/Components/ui/checkbox";
import { Field, FieldError } from "@/Components/ui/field";
import { ErrorMessage } from "@/Components/shared/error-message";
import { CheckCircle } from "lucide-react";
import { Spinner } from "@/Components/ui/spinner";
import { loginSchema } from "@/lib/schemas";
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

interface LoginData {
  username: string;
  password: string;
  remember: boolean;
}

export default function Login({
  status,
  canResetPassword,
  errors: pageErrors,
}: {
  status?: string;
  canResetPassword: boolean;
  errors?: Record<string, string>;
}) {
  const { props } = usePage();
  const registration = (props.registration as { enabled?: boolean }) || {};
  const registrationEnabled = registration.enabled !== false;
  const {
    data,
    setData,
    post,
    processing,
    errors: formErrors,
    setError,
  } = useForm<LoginData>({
    username: "",
    password: "",
    remember: false,
  });

  const errors = { ...formErrors, ...pageErrors } as Record<string, string>;

  const showPasswordChangedMessage = React.useMemo(() => {
    if (!status) return false;
    const s = status.toLowerCase();
    return s.includes("password") && (s.includes("changed") || s.includes("reset"));
  }, [status]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const result = loginSchema.safeParse(data);
    if (!result.success) {
      const mapped = zodErrorsToMap(result.error?.issues);
      for (const [key, msg] of Object.entries(mapped)) {
        setError(key as keyof LoginData, msg);
      }
      return;
    }

    post(route("login"), { onFinish: () => setError("password", "") });
  };

  return (
    <GuestLayout title="Log in">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <CardTitle className="text-2xl">Sign in to your account</CardTitle>
          <CardDescription>
            Enter your username or email and password to access your dashboard
          </CardDescription>
        </CardHeader>
        <form onSubmit={handleSubmit}>
          <CardContent className="grid gap-4">
            {showPasswordChangedMessage && (
              <Alert className="border-green-500 bg-green-50 dark:bg-green-950 dark:border-green-800">
                <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                <AlertDescription className="text-green-700 dark:text-green-300">
                  Your password has been changed successfully. Please sign in with your new
                  password.
                </AlertDescription>
              </Alert>
            )}

            {errors.login && (
              <Alert variant="destructive">
                <ErrorMessage error={errors.login} variant="alert" />
              </Alert>
            )}

            <Field data-invalid={!!errors.username}>
              <Label htmlFor="username">
                Username or Email <span className="text-destructive">*</span>
              </Label>
              <Input
                id="username"
                type="text"
                placeholder="Enter your username or email"
                value={data.username}
                onChange={(e) => setData("username", e.target.value)}
                required
                autoFocus
                aria-invalid={!!errors.username}
                aria-describedby={errors.username ? "username-error" : undefined}
              />
              {errors.username && (
                <FieldError id="username-error">
                  <ErrorMessage error={errors.username} />
                </FieldError>
              )}
            </Field>

            <PasswordInput
              variant="login"
              newPasswordLabel="Password"
              required
              newPasswordValue={data.password}
              onNewPasswordChange={(e) => setData("password", e.target.value)}
              newPasswordError={errors.password}
              showForgotPasswordLink={canResetPassword}
              forgotPasswordUrl={canResetPassword ? route("password.request") : undefined}
            />

            <div className="flex items-center space-x-2 mb-4">
              <Checkbox
                id="remember"
                checked={data.remember}
                onCheckedChange={(c) => setData("remember", !!c)}
              />
              <Label htmlFor="remember" className="text-sm font-normal">
                Remember me
              </Label>
            </div>
          </CardContent>
          <CardFooter className="flex flex-col space-y-4">
            <Button type="submit" className="w-full" disabled={processing}>
              {processing && <Spinner className="mr-2" />}
              Sign in
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              {registrationEnabled ? (
                <>
                  Don&apos;t have an account?{" "}
                  <Link href={route("register")} className="text-primary hover:underline">
                    Sign up
                  </Link>
                </>
              ) : (
                "Contact your administrator to get access."
              )}
            </p>
          </CardFooter>
        </form>
      </Card>
    </GuestLayout>
  );
}
