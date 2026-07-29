import { Link } from "@inertiajs/react";
import { Button } from "@/Components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import { UserX } from "lucide-react";
import GuestLayout from "@/Layouts/GuestLayout";

export default function RegisterClosed() {
  return (
    <GuestLayout title="Registration Closed">
      <Card>
        <CardHeader className="space-y-1 text-center">
          <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
            <UserX className="h-6 w-6 text-muted-foreground" />
          </div>
          <CardTitle className="text-2xl">Registration is closed</CardTitle>
          <CardDescription>
            New account registration is currently disabled. Please contact the site administrator to
            request access.
          </CardDescription>
        </CardHeader>
        <CardContent className="text-center text-sm text-muted-foreground">
          If you already have an account, you can sign in below.
        </CardContent>
        <CardFooter className="flex justify-center">
          <Link href={route("login")}>
            <Button>Sign in to your account</Button>
          </Link>
        </CardFooter>
      </Card>
    </GuestLayout>
  );
}
