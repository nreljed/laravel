import { Head, Link, useForm, router } from "@inertiajs/react";
import { MapPin, Plus, Trash2, Star, ArrowLeft } from "lucide-react";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Badge } from "@nreljed/platform/Components/ui/badge";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Label } from "@nreljed/platform/Components/ui/label";
import { NativeSelect, NativeSelectOption } from "@nreljed/platform/Components/ui/native-select";
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from "@nreljed/platform/Components/ui/empty";

interface Address {
  id: number;
  code: string;
  type: string;
  line1: string;
  line2: string | null;
  city: string;
  postal_code: string | null;
  country_code: string;
  is_primary: boolean;
}

interface Props {
  addresses: Address[];
}

const typeLabels: Record<string, string> = {
  billing: "Billing",
  shipping: "Shipping",
};

export default function MyAddressesIndex({ addresses }: Props) {
  const form = useForm({
    type: "shipping",
    line1: "",
    line2: "",
    city: "",
    postal_code: "",
    country_code: "TN",
    is_primary: false,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    form.post(route("dashboard.my.addresses.store"), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    });
  }

  return (
    <AuthenticatedLayout header="My Addresses">
      <Head title="My Addresses" />

      <div className="mb-4">
        <Link href={route("dashboard.my.orders.index")}>
          <Button variant="ghost" size="sm">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to My Orders
          </Button>
        </Link>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="space-y-4">
          {addresses.length === 0 ? (
            <Card>
              <CardContent className="py-16">
                <Empty>
                  <EmptyHeader>
                    <MapPin className="mx-auto h-12 w-12 text-muted-foreground" />
                    <EmptyTitle>No addresses yet</EmptyTitle>
                    <EmptyDescription>Add an address and it will appear here for faster checkout.</EmptyDescription>
                  </EmptyHeader>
                </Empty>
              </CardContent>
            </Card>
          ) : (
            addresses.map((address) => (
              <Card key={address.id}>
                <CardContent className="p-4">
                  <div className="flex items-start justify-between">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <Badge variant={address.type === "billing" ? "default" : "secondary"}>
                          {typeLabels[address.type] || address.type}
                        </Badge>
                        {address.is_primary && (
                          <Badge className="bg-amber-100 text-amber-800">
                            <Star className="mr-1 h-3 w-3" />
                            Primary
                          </Badge>
                        )}
                      </div>
                      <div className="text-sm">
                        <p className="font-medium">{address.line1}</p>
                        {address.line2 && <p className="text-muted-foreground">{address.line2}</p>}
                        <p className="text-muted-foreground">
                          {address.city}
                          {address.postal_code ? ` ${address.postal_code}` : ""} · {address.country_code}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-1">
                      {!address.is_primary && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() =>
                            router.post(route("dashboard.my.addresses.set-primary", address.id), {}, { preserveScroll: true })
                          }
                        >
                          <Star className="mr-1 h-3 w-3" />
                          Set primary
                        </Button>
                      )}
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                          router.delete(route("dashboard.my.addresses.destroy", address.id), {
                            preserveScroll: true,
                          })
                        }
                      >
                        <Trash2 className="h-4 w-4 text-muted-foreground" />
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))
          )}
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Plus className="h-5 w-5" />
              Add address
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="address-type">Type</Label>
                <NativeSelect
                  id="address-type"
                  value={form.data.type}
                  onChange={(e) => form.setData("type", e.target.value)}
                  className="w-full"
                >
                  <NativeSelectOption value="billing">Billing</NativeSelectOption>
                  <NativeSelectOption value="shipping">Shipping</NativeSelectOption>
                </NativeSelect>
              </div>

              <div className="space-y-2">
                <Label htmlFor="address-line1">Address line 1</Label>
                <Input
                  id="address-line1"
                  value={form.data.line1}
                  onChange={(e) => form.setData("line1", e.target.value)}
                  placeholder="Street address"
                  required
                />
                {form.errors.line1 && <p className="text-sm text-red-600">{form.errors.line1}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="address-line2">Address line 2 (optional)</Label>
                <Input
                  id="address-line2"
                  value={form.data.line2}
                  onChange={(e) => form.setData("line2", e.target.value)}
                  placeholder="Apartment, suite, etc."
                />
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="address-city">City</Label>
                  <Input
                    id="address-city"
                    value={form.data.city}
                    onChange={(e) => form.setData("city", e.target.value)}
                    required
                  />
                  {form.errors.city && <p className="text-sm text-red-600">{form.errors.city}</p>}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="address-postal">Postal code</Label>
                  <Input
                    id="address-postal"
                    value={form.data.postal_code}
                    onChange={(e) => form.setData("postal_code", e.target.value)}
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="address-country">Country code</Label>
                <Input
                  id="address-country"
                  value={form.data.country_code}
                  onChange={(e) => form.setData("country_code", e.target.value.toUpperCase())}
                  maxLength={2}
                  placeholder="TN"
                  required
                />
                {form.errors.country_code && <p className="text-sm text-red-600">{form.errors.country_code}</p>}
              </div>

              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.data.is_primary}
                  onChange={(e) => form.setData("is_primary", e.target.checked)}
                />
                Set as primary address
              </label>

              <Button type="submit" disabled={form.processing}>
                {form.processing ? "Saving..." : "Save address"}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}