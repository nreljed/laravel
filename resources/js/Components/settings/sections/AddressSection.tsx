import { useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import { MapPin } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { SettingsAccordionItem } from "@/Components/shared/settings-accordion";

interface Address {
  first_name?: string;
  last_name?: string;
  company?: string;
  address_line1?: string;
  address_line2?: string;
  city?: string;
  state?: string;
  postcode?: string;
  country?: string;
  phone?: string;
}

interface Props {
  billingAddress: Address | null;
  shippingAddress: Address | null;
  asAccordion?: boolean;
}

export function AddressSection({ billingAddress, shippingAddress, asAccordion }: Props) {
  const { data, setData, put, processing, recentlySuccessful } = useForm({
    billing_address: billingAddress ?? {
      first_name: "",
      last_name: "",
      company: "",
      address_line1: "",
      address_line2: "",
      city: "",
      state: "",
      postcode: "",
      country: "",
      phone: "",
    },
    shipping_address: shippingAddress ?? {
      first_name: "",
      last_name: "",
      company: "",
      address_line1: "",
      address_line2: "",
      city: "",
      state: "",
      postcode: "",
      country: "",
      phone: "",
    },
  });

  const setBilling = (field: string, value: string) => {
    setData("billing_address", { ...data.billing_address, [field]: value });
  };

  const setShipping = (field: string, value: string) => {
    setData("shipping_address", { ...data.shipping_address, [field]: value });
  };

  const copyBillingToShipping = () => {
    setData("shipping_address", { ...data.billing_address });
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    put(route("dashboard.account.address"));
  };

  const content = (
    <form id="address-form" onSubmit={submit} className="space-y-6">
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="space-y-4">
          <h4 className="text-sm font-semibold">Billing Address</h4>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="billing_first_name">First Name</Label>
              <Input
                id="billing_first_name"
                value={data.billing_address.first_name}
                onChange={(e) => setBilling("first_name", e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="billing_last_name">Last Name</Label>
              <Input
                id="billing_last_name"
                value={data.billing_address.last_name}
                onChange={(e) => setBilling("last_name", e.target.value)}
              />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="billing_company">Company</Label>
            <Input
              id="billing_company"
              value={data.billing_address.company}
              onChange={(e) => setBilling("company", e.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="billing_address_line1">Address Line 1</Label>
            <Input
              id="billing_address_line1"
              value={data.billing_address.address_line1}
              onChange={(e) => setBilling("address_line1", e.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="billing_address_line2">Address Line 2</Label>
            <Input
              id="billing_address_line2"
              value={data.billing_address.address_line2}
              onChange={(e) => setBilling("address_line2", e.target.value)}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="billing_city">City</Label>
              <Input
                id="billing_city"
                value={data.billing_address.city}
                onChange={(e) => setBilling("city", e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="billing_state">State</Label>
              <Input
                id="billing_state"
                value={data.billing_address.state}
                onChange={(e) => setBilling("state", e.target.value)}
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="billing_postcode">Postcode</Label>
              <Input
                id="billing_postcode"
                value={data.billing_address.postcode}
                onChange={(e) => setBilling("postcode", e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="billing_country">Country</Label>
              <Input
                id="billing_country"
                maxLength={2}
                placeholder="US"
                value={data.billing_address.country}
                onChange={(e) => setBilling("country", e.target.value)}
              />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="billing_phone">Phone</Label>
            <Input
              id="billing_phone"
              value={data.billing_address.phone}
              onChange={(e) => setBilling("phone", e.target.value)}
            />
          </div>
        </div>

        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-semibold">Shipping Address</h4>
            <Button type="button" variant="ghost" size="sm" onClick={copyBillingToShipping}>
              Copy from billing
            </Button>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="shipping_first_name">First Name</Label>
              <Input
                id="shipping_first_name"
                value={data.shipping_address.first_name}
                onChange={(e) => setShipping("first_name", e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="shipping_last_name">Last Name</Label>
              <Input
                id="shipping_last_name"
                value={data.shipping_address.last_name}
                onChange={(e) => setShipping("last_name", e.target.value)}
              />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shipping_company">Company</Label>
            <Input
              id="shipping_company"
              value={data.shipping_address.company}
              onChange={(e) => setShipping("company", e.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shipping_address_line1">Address Line 1</Label>
            <Input
              id="shipping_address_line1"
              value={data.shipping_address.address_line1}
              onChange={(e) => setShipping("address_line1", e.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shipping_address_line2">Address Line 2</Label>
            <Input
              id="shipping_address_line2"
              value={data.shipping_address.address_line2}
              onChange={(e) => setShipping("address_line2", e.target.value)}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="shipping_city">City</Label>
              <Input
                id="shipping_city"
                value={data.shipping_address.city}
                onChange={(e) => setShipping("city", e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="shipping_state">State</Label>
              <Input
                id="shipping_state"
                value={data.shipping_address.state}
                onChange={(e) => setShipping("state", e.target.value)}
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="shipping_postcode">Postcode</Label>
              <Input
                id="shipping_postcode"
                value={data.shipping_address.postcode}
                onChange={(e) => setShipping("postcode", e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="shipping_country">Country</Label>
              <Input
                id="shipping_country"
                maxLength={2}
                placeholder="US"
                value={data.shipping_address.country}
                onChange={(e) => setShipping("country", e.target.value)}
              />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shipping_phone">Phone</Label>
            <Input
              id="shipping_phone"
              value={data.shipping_address.phone}
              onChange={(e) => setShipping("phone", e.target.value)}
            />
          </div>
        </div>
      </div>

      <div className="flex justify-end">
        <Button type="submit" disabled={processing}>
          {recentlySuccessful ? "Saved!" : "Save Address"}
        </Button>
      </div>
    </form>
  );

  if (asAccordion) {
    return (
      <SettingsAccordionItem
        value="address"
        icon={MapPin}
        title="Billing & Shipping Address"
        description="Manage your default billing and shipping addresses."
      >
        {content}
      </SettingsAccordionItem>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <MapPin className="h-4 w-4" />
          Billing & Shipping Address
        </CardTitle>
        <CardDescription>Manage your default billing and shipping addresses.</CardDescription>
      </CardHeader>
      <CardContent>{content}</CardContent>
    </Card>
  );
}
