import { Link, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { PasswordInput } from "@/Components/shared/password-input";
import { Label } from "@/Components/ui/label";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { Field, FieldDescription, FieldError } from "@/Components/ui/field";
import { ErrorMessage } from "@/Components/shared/error-message";
import { Spinner } from "@/Components/ui/spinner";
import { registrationSchema } from "@/lib/schemas";
import { zodErrorsToMap } from "@/lib/utils";
import { useFieldAvailability } from "@/lib/use-field-availability";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import GuestLayout from "@/Layouts/GuestLayout";
import { Loader2, Check, X } from "lucide-react";

export default function Register({ errors: pageErrors }: { errors?: Record<string, string> }) {
  const {
    data,
    setData,
    post,
    processing,
    errors: formErrors,
    setError,
  } = useForm({
    username: "",
    email: "",
    password: "",
    password_confirmation: "",
  });

  const usernameAvail = useFieldAvailability("username");
  const emailAvail = useFieldAvailability("email");

  const errors = { ...formErrors, ...pageErrors } as Record<string, string>;

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    const result = registrationSchema.safeParse(data);
    if (!result.success) {
      const mapped = zodErrorsToMap(result.error?.issues);
      for (const [key, msg] of Object.entries(mapped)) {
        setError(key as keyof typeof data, msg);
      }
      return;
    }

    post(route("register"), {
      onFinish: () => setError("password", ""),
    });
  };

  return (
    <GuestLayout title="Register">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <CardTitle className="text-2xl">Create an account</CardTitle>
          <CardDescription>Enter your details to get started</CardDescription>
        </CardHeader>

        {errors.register && (
          <CardContent>
            <Alert variant="destructive">
              <AlertDescription>{errors.register}</AlertDescription>
            </Alert>
          </CardContent>
        )}

        <form onSubmit={submit}>
          <CardContent className="grid gap-4">
            <Field data-invalid={!!errors.username}>
              <Label htmlFor="username">
                Username <span className="text-destructive">*</span>
              </Label>
              <div className="relative">
                <Input
                  id="username"
                  type="text"
                  placeholder="johndoe"
                  value={data.username}
                  onChange={(e) => {
                    const val = e.target.value.replaceAll(/[^a-zA-Z0-9_.-]/g, "");
                    setData("username", val);
                    usernameAvail.setValue(val);
                  }}
                  required
                  autoFocus
                  aria-invalid={!!errors.username}
                  aria-describedby={errors.username ? "username-error" : undefined}
                />
                <span className="absolute right-3 top-1/2 -translate-y-1/2">
                  {usernameAvail.status === "checking" && (
                    <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                  )}
                  {usernameAvail.status === "taken" && <X className="h-4 w-4 text-destructive" />}
                  {usernameAvail.status === "available" && (
                    <Check className="h-4 w-4 text-green-500 dark:text-green-400" />
                  )}
                </span>
              </div>
              <FieldDescription>
                Your unique login handle. Letters, numbers, underscores, hyphens and periods only.
              </FieldDescription>
              {usernameAvail.status === "taken" && (
                <FieldError>
                  <ErrorMessage error="This username is already taken." />
                </FieldError>
              )}
              {errors.username && (
                <FieldError id="username-error">
                  <ErrorMessage error={errors.username} />
                </FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.email}>
              <Label htmlFor="email">
                Email <span className="text-destructive">*</span>
              </Label>
              <div className="relative">
                <Input
                  id="email"
                  type="email"
                  placeholder="name@example.com"
                  value={data.email}
                  onChange={(e) => {
                    setData("email", e.target.value);
                    emailAvail.setValue(e.target.value);
                  }}
                  required
                  aria-invalid={!!errors.email}
                  aria-describedby={errors.email ? "email-error" : undefined}
                />
                <span className="absolute right-3 top-1/2 -translate-y-1/2">
                  {emailAvail.status === "checking" && (
                    <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                  )}
                  {emailAvail.status === "taken" && <X className="h-4 w-4 text-destructive" />}
                  {emailAvail.status === "available" && (
                    <Check className="h-4 w-4 text-green-500 dark:text-green-400" />
                  )}
                </span>
              </div>
              {emailAvail.status === "taken" && (
                <FieldError>
                  <ErrorMessage error="This email is already registered." />
                </FieldError>
              )}
              {errors.email && (
                <FieldError id="email-error">
                  <ErrorMessage error={errors.email} />
                </FieldError>
              )}
            </Field>

            <PasswordInput
              variant="register"
              newPasswordLabel="Password"
              confirmLabel="Confirm Password"
              showStrengthBar={true}
              newPasswordValue={data.password}
              onNewPasswordChange={(e) => setData("password", e.target.value)}
              confirmValue={data.password_confirmation}
              onConfirmChange={(e) => setData("password_confirmation", e.target.value)}
              newPasswordError={errors.password}
              confirmError={errors.password_confirmation}
            />

            <Button type="submit" className="w-full" disabled={processing}>
              {processing && <Spinner className="mr-2" />}
              Create account
            </Button>
          </CardContent>
        </form>

        <CardFooter className="flex flex-col space-y-4">
          <p className="text-center text-sm text-muted-foreground">
            Already have an account?{" "}
            <Link href={route("login")} className="text-primary hover:underline">
              Sign in
            </Link>
          </p>
        </CardFooter>
      </Card>
    </GuestLayout>
  );
}
