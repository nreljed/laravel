import { Link, useForm, usePage } from "@inertiajs/react";
import { FormEventHandler } from "react";
import { Button } from "@/Components/ui/button";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { ErrorMessage } from "@/Components/shared/error-message";
import { CheckCircle } from "lucide-react";
import { Spinner } from "@/Components/ui/spinner";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import GuestLayout from "@/Layouts/GuestLayout";

export default function VerifyEmail({
  status,
  errors: pageErrors,
}: {
  status?: string;
  errors?: Record<string, string>;
}) {
  const { props } = usePage();
  const errorsFromPage = pageErrors || (props.errors as Record<string, string>) || {};
  const { post, processing, errors: formErrors } = useForm({});
  const errors = { ...formErrors, ...errorsFromPage } as Record<string, string>;

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(route("verification.send"));
  };

  return (
    <GuestLayout title="Email Verification">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <CardTitle className="text-2xl">Verify your email</CardTitle>
          <CardDescription>
            Thanks for signing up! Before getting started, please verify your email address by
            clicking the link we sent you.
          </CardDescription>
        </CardHeader>
        <form onSubmit={submit}>
          <CardContent className="grid gap-4">
            {status === "verification-link-sent" && (
              <Alert className="bg-green-500/10 border-green-500/20 dark:bg-green-500/20">
                <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 shrink-0" />
                <AlertDescription className="text-green-700 dark:text-green-300">
                  A new verification link has been sent to your email address.
                </AlertDescription>
              </Alert>
            )}

            {errors.email && (
              <Alert variant="destructive">
                <ErrorMessage error={errors.email} variant="alert" />
              </Alert>
            )}
          </CardContent>
          <CardFooter className="flex flex-col gap-4">
            <Button type="submit" disabled={processing} className="w-full">
              {processing && <Spinner className="mr-2" />}
              Resend Verification Email
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              <Link
                href={route("logout")}
                method="post"
                as="button"
                className="underline underline-offset-4 hover:text-primary"
              >
                Log Out
              </Link>
            </p>
          </CardFooter>
        </form>
      </Card>
    </GuestLayout>
  );
}
