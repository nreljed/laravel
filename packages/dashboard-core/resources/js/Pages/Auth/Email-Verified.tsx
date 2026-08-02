import { Link, router } from "@inertiajs/react";
import { Button } from "@/Components/ui/button";
import { CheckCircle2 } from "lucide-react";
import { Card, CardDescription, CardFooter, CardHeader, CardTitle } from "@/Components/ui/card";
import GuestLayout from "@/Layouts/GuestLayout";

export default function EmailVerified() {
  return (
    <GuestLayout title="Email Verified">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <div className="mx-auto mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
            <CheckCircle2 className="h-8 w-8 text-green-600 dark:text-green-400" />
          </div>
          <CardTitle className="text-2xl">Email verified!</CardTitle>
          <CardDescription>
            Your email address has been successfully verified. You can now close this tab or
            continue to your dashboard.
          </CardDescription>
        </CardHeader>
        <CardFooter className="flex flex-col gap-3">
          <Button className="w-full" onClick={() => router.visit(route("dashboard"))}>
            Continue to Dashboard
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
      </Card>
    </GuestLayout>
  );
}
