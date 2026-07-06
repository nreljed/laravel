import { Head, Link, useForm } from "@inertiajs/react";
import { Package, ArrowLeft } from "lucide-react";
import StoreLayout from "@/Layouts/StoreLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";

interface OrderData {
  id: number;
  order_number: string;
  customer_email: string;
  customer_name: string;
  total: number;
  status: string;
}

interface Props {
  order: OrderData;
  email: string;
}

export default function RegisterFromOrder({ order, email }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    name: order.customer_name || "",
    password: "",
    password_confirmation: "",
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route("store.checkout.store-register", order.id), {
      preserveScroll: true,
    });
  };

  return (
    <StoreLayout>
      <Head title="Create Account" />
      <div className="mx-auto max-w-lg px-4 py-12 sm:px-6">
        <Link href={route("store.checkout.confirmation", order.id)} className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground mb-6">
          <ArrowLeft className="mr-1 h-4 w-4" />
          Back to order confirmation
        </Link>

        <Card>
          <CardHeader className="text-center">
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
              <Package className="h-6 w-6 text-primary" />
            </div>
            <CardTitle className="text-xl">Create your account</CardTitle>
            <CardDescription>
              Link order <span className="font-medium">#{order.order_number}</span> to a new account to track it from your dashboard.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input id="email" value={email} disabled className="bg-muted" />
                <p className="text-xs text-muted-foreground">Your order confirmation was sent to this email.</p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="name">Full Name</Label>
                <Input
                  id="name"
                  value={data.name}
                  onChange={(e) => setData("name", e.target.value)}
                  required
                />
                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  type="password"
                  value={data.password}
                  onChange={(e) => setData("password", e.target.value)}
                  required
                  minLength={8}
                />
                {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Confirm Password</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  value={data.password_confirmation}
                  onChange={(e) => setData("password_confirmation", e.target.value)}
                  required
                />
                {errors.password_confirmation && (
                  <p className="text-sm text-destructive">{errors.password_confirmation}</p>
                )}
              </div>

              <Button type="submit" className="w-full" disabled={processing}>
                Create Account & Sign In
              </Button>

              <p className="text-center text-xs text-muted-foreground">
                Already have an account?{" "}
                <Link href={route("login")} className="text-primary hover:underline">
                  Sign in
                </Link>
              </p>
            </form>
          </CardContent>
        </Card>
      </div>
    </StoreLayout>
  );
}
