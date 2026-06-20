import { useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import { Button } from "@/Components/ui/button";
import { PasswordInput } from "@/Components/ui/password-input";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { ErrorMessage } from "@/Components/ui/error-message";
import { Spinner } from "@/Components/ui/spinner";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import GuestLayout from "@/Layouts/GuestLayout";

export default function ConfirmPassword({ errors: pageErrors }: { errors?: Record<string, string> }) {
  const { data, setData, post, processing, errors: formErrors, reset } = useForm({
    password: "",
  });

  const errors = { ...formErrors, ...pageErrors } as Record<string, string>;

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(route("password.confirm"), {
      onFinish: () => reset("password"),
    });
  };

  return (
    <GuestLayout title="Confirm Password">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <CardTitle className="text-2xl">Confirm password</CardTitle>
          <CardDescription>
            This is a secure area. Please confirm your password before continuing.
          </CardDescription>
        </CardHeader>
        <form onSubmit={submit}>
          <CardContent className="grid gap-4">
            {errors.password && (
              <Alert variant="destructive">
                <ErrorMessage error={errors.password} variant="alert" />
              </Alert>
            )}

            <PasswordInput
              variant="login"
              newPasswordLabel="Password"
              required
              newPasswordValue={data.password}
              onNewPasswordChange={(e) => setData("password", e.target.value)}
              newPasswordError={errors.password}
              showForgotPasswordLink={false}
              autoFocus
            />

            <Button type="submit" className="w-full" disabled={processing}>
              {processing && <Spinner className="mr-2" />}
              Confirm
            </Button>
          </CardContent>
        </form>
      </Card>
    </GuestLayout>
  );
}
