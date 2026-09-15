import { Head, useForm, usePage } from "@inertiajs/react";
import { FormEventHandler, useState } from "react";
import { ShoppingBag, CreditCard, Truck, Loader2, Check, ChevronLeft, ChevronRight, MapPin, User, Eye } from "lucide-react";
import { loadStripe } from "@stripe/stripe-js";
import { Elements, CardElement, useStripe, useElements } from "@stripe/react-stripe-js";
import StoreLayout from "../../Layouts/StoreLayout";
import { useCurrency } from "../../lib/currency";
import { useAuth } from "@nreljed/platform/hooks/use-auth";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@nreljed/platform/Components/ui/select";
import { RadioGroup, RadioGroupItem } from "@nreljed/platform/Components/ui/radio-group";
import { Checkbox } from "@nreljed/platform/Components/ui/checkbox";
import {
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle,
} from "@nreljed/platform/Components/ui/dialog";

const COUNTRIES = [
  { code: "AF", name: "Afghanistan" }, { code: "AL", name: "Albania" },
  { code: "DZ", name: "Algeria" }, { code: "AR", name: "Argentina" },
  { code: "AU", name: "Australia" }, { code: "AT", name: "Austria" },
  { code: "BH", name: "Bahrain" }, { code: "BD", name: "Bangladesh" },
  { code: "BE", name: "Belgium" }, { code: "BR", name: "Brazil" },
  { code: "BG", name: "Bulgaria" }, { code: "CA", name: "Canada" },
  { code: "CL", name: "Chile" }, { code: "CN", name: "China" },
  { code: "CO", name: "Colombia" }, { code: "HR", name: "Croatia" },
  { code: "CY", name: "Cyprus" }, { code: "CZ", name: "Czech Republic" },
  { code: "DK", name: "Denmark" }, { code: "EG", name: "Egypt" },
  { code: "FI", name: "Finland" }, { code: "FR", name: "France" },
  { code: "DE", name: "Germany" }, { code: "GR", name: "Greece" },
  { code: "HK", name: "Hong Kong" }, { code: "HU", name: "Hungary" },
  { code: "IS", name: "Iceland" }, { code: "IN", name: "India" },
  { code: "ID", name: "Indonesia" }, { code: "IE", name: "Ireland" },
  { code: "IL", name: "Israel" }, { code: "IT", name: "Italy" },
  { code: "JP", name: "Japan" }, { code: "KE", name: "Kenya" },
  { code: "KW", name: "Kuwait" }, { code: "LV", name: "Latvia" },
  { code: "LT", name: "Lithuania" }, { code: "LU", name: "Luxembourg" },
  { code: "MY", name: "Malaysia" }, { code: "MT", name: "Malta" },
  { code: "MX", name: "Mexico" }, { code: "MA", name: "Morocco" },
  { code: "NL", name: "Netherlands" }, { code: "NZ", name: "New Zealand" },
  { code: "NG", name: "Nigeria" }, { code: "NO", name: "Norway" },
  { code: "OM", name: "Oman" }, { code: "PK", name: "Pakistan" },
  { code: "PE", name: "Peru" }, { code: "PH", name: "Philippines" },
  { code: "PL", name: "Poland" }, { code: "PT", name: "Portugal" },
  { code: "QA", name: "Qatar" }, { code: "RO", name: "Romania" },
  { code: "RU", name: "Russia" }, { code: "SA", name: "Saudi Arabia" },
  { code: "RS", name: "Serbia" }, { code: "SG", name: "Singapore" },
  { code: "SK", name: "Slovakia" }, { code: "SI", name: "Slovenia" },
  { code: "ZA", name: "South Africa" }, { code: "KR", name: "South Korea" },
  { code: "ES", name: "Spain" }, { code: "SE", name: "Sweden" },
  { code: "CH", name: "Switzerland" }, { code: "TW", name: "Taiwan" },
  { code: "TN", name: "Tunisia" }, { code: "TR", name: "Turkey" },
  { code: "UA", name: "Ukraine" }, { code: "AE", name: "United Arab Emirates" },
  { code: "GB", name: "United Kingdom" }, { code: "US", name: "United States" },
  { code: "VN", name: "Vietnam" },
];

interface CartItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: string;
  variant_label: string | null;
  product: { id: number; name: string; price: string; thumbnail_url: string | null };
}

interface ShippingMethod {
  id: number;
  name: string;
  description: string | null;
  price: string;
  free_above: string | null;
  estimated_days_min: number | null;
  estimated_days_max: number | null;
}

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

interface TaxBreakdown {
  name: string;
  rate: number;
  type: string;
  amount: number;
}

interface TaxResult {
  tax_total: number;
  breakdown: TaxBreakdown[];
}

interface Props {
  cart: { id: number; items: CartItem[]; subtotal: number; discount: number; total: number };
  shippingMethods: ShippingMethod[];
  userBillingAddress: Address | null;
  userShippingAddress: Address | null;
  stripeKey: string;
  estimatedTax?: TaxResult;
  freeShippingThreshold?: number | null;
  freeShippingApplied?: boolean;
}

interface ShopSettings {
  require_company_field?: boolean;
  require_terms?: boolean;
  checkout_default_country?: string;
  base_country?: string;
}

const STEPS = [
  { key: "details", label: "Details", icon: User },
  { key: "shipping", label: "Shipping", icon: MapPin },
  { key: "review", label: "Review", icon: Eye },
];

function StripePaymentForm({ clientSecret, onSuccess, onError }: { clientSecret: string; onSuccess: (id: string) => void; onError: (msg: string) => void }) {
  const stripe = useStripe();
  const elements = useElements();
  const [processing, setProcessing] = useState(false);

  const handleSubmit = async () => {
    if (!stripe || !elements) return;
    setProcessing(true);

    const card = elements.getElement(CardElement);
    if (!card) { setProcessing(false); return; }

    const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
      payment_method: { card },
    });

    if (error) {
      onError(error.message ?? "Payment failed.");
      setProcessing(false);
      return;
    }

    if (paymentIntent?.status === "succeeded") {
      onSuccess(paymentIntent.id);
    }
  };

  return (
    <div className="space-y-4">
      <div className="rounded-md border p-3">
        <CardElement
          options={{
            style: {
              base: { fontSize: "16px", color: "#424770", "::placeholder": { color: "#aab7c4" } },
              invalid: { color: "#9e2146" },
            },
          }}
        />
      </div>
      <Button onClick={handleSubmit} disabled={!stripe || processing} className="w-full">
        {processing ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : null}
        {processing ? "Processing..." : "Pay Now"}
      </Button>
    </div>
  );
}

function formatAddress(a: Address): string {
  const parts = [a.first_name, a.last_name].filter(Boolean).join(" ");
  const line1 = a.address_line1 || "";
  const line2 = a.address_line2 || "";
  const cityState = [a.city, a.state].filter(Boolean).join(", ");
  const postcode = a.postcode || "";
  const country = a.country || "";
  return [parts, line1, line2, [cityState, postcode].filter(Boolean).join(" "), country].filter(Boolean).join("\n");
}

export default function Checkout({ cart, shippingMethods, userBillingAddress, userShippingAddress, stripeKey, estimatedTax, freeShippingThreshold, freeShippingApplied = false }: Props) {
  const { isLoggedIn, user } = useAuth();
  const { money, active: activeCurrency, currencies } = useCurrency();
  const shopSettings = (usePage().props as { shopSettings?: ShopSettings }).shopSettings ?? {};
  const [step, setStep] = useState(0);
  const [stripeDialogOpen, setStripeDialogOpen] = useState(false);
  const [clientSecret, setClientSecret] = useState("");
  const [paymentIntentId, setPaymentIntentId] = useState("");

  const defaultCountry = shopSettings.checkout_default_country || shopSettings.base_country || "";
  const requiresCompany = shopSettings.require_company_field;
  const requiresTerms = shopSettings.require_terms;

  const { data, setData, post, processing, errors } = useForm({
    payment_method: "manual",
    shipping_method_id: shippingMethods[0]?.id?.toString() ?? "",
    customer_email: user?.email ?? "",
    customer_name: user?.name ?? "",
    customer_phone: "",
    password: "",
    password_confirmation: "",
    accepted_terms: false,
    billing_address: {
      first_name: userBillingAddress?.first_name ?? "",
      last_name: userBillingAddress?.last_name ?? "",
      company: userBillingAddress?.company ?? "",
      address_line1: userBillingAddress?.address_line1 ?? "",
      address_line2: userBillingAddress?.address_line2 ?? "",
      city: userBillingAddress?.city ?? "",
      state: userBillingAddress?.state ?? "",
      postcode: userBillingAddress?.postcode ?? "",
      country: userBillingAddress?.country ?? defaultCountry,
      phone: userBillingAddress?.phone ?? "",
    },
    shipping_address: {
      first_name: userShippingAddress?.first_name ?? "",
      last_name: userShippingAddress?.last_name ?? "",
      company: userShippingAddress?.company ?? "",
      address_line1: userShippingAddress?.address_line1 ?? "",
      address_line2: userShippingAddress?.address_line2 ?? "",
      city: userShippingAddress?.city ?? "",
      state: userShippingAddress?.state ?? "",
      postcode: userShippingAddress?.postcode ?? "",
      country: userShippingAddress?.country ?? defaultCountry,
      phone: userShippingAddress?.phone ?? "",
    },
    stripe_payment_intent_id: "",
  });

  const fieldError = (key: string) => {
    const msg = (errors as Record<string, string>)[key];
    return msg ? <p className="text-sm text-destructive">{msg}</p> : null;
  };

  const generalError = (errors as Record<string, string>).cart
    || (errors as Record<string, string>).email
    || (errors as Record<string, string>).stock
    || null;

  const copyBillingToShipping = () => {
    setData("shipping_address", { ...data.billing_address });
  };

  const selectedShipping = shippingMethods.find((m) => m.id.toString() === data.shipping_method_id);
  const freeAbove = selectedShipping?.free_above;
  const shippingPrice = freeShippingApplied || !selectedShipping
    ? 0
    : freeAbove != null && freeAbove !== "" && Number(freeAbove) > 0 && cart.subtotal >= Number(freeAbove)
      ? 0
      : Number(selectedShipping.price);
  const taxTotal = estimatedTax?.tax_total ?? 0;
  const discountTotal = Number(cart.discount || 0);
  const orderTotal = cart.total + shippingPrice + taxTotal;

  const handleStripeSuccess = (id: string) => {
    setPaymentIntentId(id);
    setStripeDialogOpen(false);
    setData("stripe_payment_intent_id", id);
    post(route("store.checkout.store"), {
      data: { ...data, stripe_payment_intent_id: id },
      preserveScroll: false,
      onError: () => {},
    });
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    if (data.payment_method === "stripe") {
      fetch(route("store.checkout.payment-intent"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "" },
        body: JSON.stringify({}),
      })
        .then((r) => r.json())
        .then((result) => {
          if (result.clientSecret) {
            setClientSecret(result.clientSecret);
            setStripeDialogOpen(true);
          } else {
            alert("Failed to initialize payment. Please try again.");
          }
        })
        .catch(() => alert("Payment initialization failed."));
      return;
    }

    post(route("store.checkout.store"), { preserveScroll: false });
  };

  const progressBar = (
    <div className="mb-8">
      <div className="flex items-center justify-center gap-0">
        {STEPS.map((s, i) => {
          const Icon = s.icon;
          const active = i === step;
          const done = i < step;
          return (
              <div key={s.key} className="flex items-center">
              <button
                type="button"
                onClick={() => done && setStep(i)}
                className={`flex items-center gap-1.5 sm:gap-2 px-2 sm:px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                  active ? "bg-primary text-primary-foreground" :
                  done ? "bg-primary/10 text-primary cursor-pointer hover:bg-primary/20" :
                  "text-muted-foreground"
                }`}
              >
                <span className={`flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold ${
                  active ? "bg-primary-foreground text-primary" :
                  done ? "bg-primary text-primary-foreground" :
                  "bg-muted text-muted-foreground"
                }`}>
                  {done ? <Check className="h-3 w-3" /> : i + 1}
                </span>
                <span className="hidden sm:inline">{s.label}</span>
              </button>
              {i < STEPS.length - 1 && (
                <div className={`w-4 sm:w-8 h-px mx-0.5 sm:mx-1 ${i < step ? "bg-primary" : "bg-border"}`} />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );

  const contactCard = (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          Contact Information
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="customer_email">Email Address</Label>
            <Input id="customer_email" type="email" value={data.customer_email} onChange={(e) => setData("customer_email", e.target.value)} required />
            {errors.customer_email && <p className="text-sm text-destructive">{errors.customer_email}</p>}
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="customer_name">Full Name</Label>
              <Input id="customer_name" value={data.customer_name} onChange={(e) => setData("customer_name", e.target.value)} required />
              {errors.customer_name && <p className="text-sm text-destructive">{errors.customer_name}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="customer_phone">Phone</Label>
              <Input id="customer_phone" value={data.customer_phone} onChange={(e) => setData("customer_phone", e.target.value)} />
            </div>
          </div>
          <div className="border-t pt-3">
            <p className="text-xs text-muted-foreground mb-3">Create an account (optional) to track orders and manage your profile.</p>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="password">Password</Label>
                <Input id="password" type="password" value={data.password} onChange={(e) => setData("password", e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="password_confirmation">Confirm Password</Label>
                <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData("password_confirmation", e.target.value)} />
              </div>
            </div>
            {errors.password && <p className="text-sm text-destructive mt-1">{errors.password}</p>}
          </div>
        </div>
      </CardContent>
    </Card>
  );

  const billingCard = (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <CreditCard className="h-4 w-4" />
          Billing Address
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1.5">
            <Label htmlFor="billing_first_name">First Name</Label>
            <Input id="billing_first_name" value={data.billing_address.first_name} onChange={(e) => setData("billing_address", { ...data.billing_address, first_name: e.target.value })} />
            {fieldError("billing_address.first_name")}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="billing_last_name">Last Name</Label>
            <Input id="billing_last_name" value={data.billing_address.last_name} onChange={(e) => setData("billing_address", { ...data.billing_address, last_name: e.target.value })} />
            {fieldError("billing_address.last_name")}
          </div>
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="billing_company">Company{requiresCompany ? " *" : ""}</Label>
          <Input id="billing_company" value={data.billing_address.company} onChange={(e) => setData("billing_address", { ...data.billing_address, company: e.target.value })} required={requiresCompany} />
          {fieldError("billing_address.company")}
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="billing_address_line1">Address Line 1</Label>
          <Input id="billing_address_line1" value={data.billing_address.address_line1} onChange={(e) => setData("billing_address", { ...data.billing_address, address_line1: e.target.value })} />
          {fieldError("billing_address.address_line1")}
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="billing_address_line2">Address Line 2</Label>
          <Input id="billing_address_line2" value={data.billing_address.address_line2} onChange={(e) => setData("billing_address", { ...data.billing_address, address_line2: e.target.value })} />
          {fieldError("billing_address.address_line2")}
        </div>
        <div className="grid grid-cols-2 gap-3 mt-3">
          <div className="space-y-1.5">
            <Label htmlFor="billing_city">City</Label>
            <Input id="billing_city" value={data.billing_address.city} onChange={(e) => setData("billing_address", { ...data.billing_address, city: e.target.value })} />
            {fieldError("billing_address.city")}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="billing_state">State</Label>
            <Input id="billing_state" value={data.billing_address.state} onChange={(e) => setData("billing_address", { ...data.billing_address, state: e.target.value })} />
            {fieldError("billing_address.state")}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3 mt-3">
          <div className="space-y-1.5">
            <Label htmlFor="billing_postcode">Postcode</Label>
            <Input id="billing_postcode" value={data.billing_address.postcode} onChange={(e) => setData("billing_address", { ...data.billing_address, postcode: e.target.value })} />
            {fieldError("billing_address.postcode")}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="billing_country">Country</Label>
            <Select value={data.billing_address.country} onValueChange={(v) => setData("billing_address", { ...data.billing_address, country: v })}>
              <SelectTrigger id="billing_country">
                <SelectValue placeholder="Select country" />
              </SelectTrigger>
              <SelectContent className="max-h-[300px]">
                {COUNTRIES.map((c) => (
                  <SelectItem key={c.code} value={c.code}>{c.name} ({c.code})</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {fieldError("billing_address.country")}
          </div>
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="billing_phone">Phone</Label>
          <Input id="billing_phone" value={data.billing_address.phone} onChange={(e) => setData("billing_address", { ...data.billing_address, phone: e.target.value })} />
          {fieldError("billing_address.phone")}
        </div>
      </CardContent>
    </Card>
  );

  const shippingCard = (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Truck className="h-4 w-4" />
            Shipping Address
          </CardTitle>
          <Button type="button" variant="ghost" size="sm" onClick={copyBillingToShipping}>
            Copy from billing
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1.5">
            <Label htmlFor="shipping_first_name">First Name</Label>
            <Input id="shipping_first_name" value={data.shipping_address.first_name} onChange={(e) => setData("shipping_address", { ...data.shipping_address, first_name: e.target.value })} />
            {fieldError("shipping_address.first_name")}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shipping_last_name">Last Name</Label>
            <Input id="shipping_last_name" value={data.shipping_address.last_name} onChange={(e) => setData("shipping_address", { ...data.shipping_address, last_name: e.target.value })} />
            {fieldError("shipping_address.last_name")}
          </div>
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="shipping_company">Company</Label>
          <Input id="shipping_company" value={data.shipping_address.company} onChange={(e) => setData("shipping_address", { ...data.shipping_address, company: e.target.value })} />
          {fieldError("shipping_address.company")}
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="shipping_address_line1">Address Line 1</Label>
          <Input id="shipping_address_line1" value={data.shipping_address.address_line1} onChange={(e) => setData("shipping_address", { ...data.shipping_address, address_line1: e.target.value })} />
          {fieldError("shipping_address.address_line1")}
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="shipping_address_line2">Address Line 2</Label>
          <Input id="shipping_address_line2" value={data.shipping_address.address_line2} onChange={(e) => setData("shipping_address", { ...data.shipping_address, address_line2: e.target.value })} />
          {fieldError("shipping_address.address_line2")}
        </div>
        <div className="grid grid-cols-2 gap-3 mt-3">
          <div className="space-y-1.5">
            <Label htmlFor="shipping_city">City</Label>
            <Input id="shipping_city" value={data.shipping_address.city} onChange={(e) => setData("shipping_address", { ...data.shipping_address, city: e.target.value })} />
            {fieldError("shipping_address.city")}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shipping_state">State</Label>
            <Input id="shipping_state" value={data.shipping_address.state} onChange={(e) => setData("shipping_address", { ...data.shipping_address, state: e.target.value })} />
            {fieldError("shipping_address.state")}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3 mt-3">
          <div className="space-y-1.5">
            <Label htmlFor="shipping_postcode">Postcode</Label>
            <Input id="shipping_postcode" value={data.shipping_address.postcode} onChange={(e) => setData("shipping_address", { ...data.shipping_address, postcode: e.target.value })} />
            {fieldError("shipping_address.postcode")}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="shipping_country">Country</Label>
            <Select value={data.shipping_address.country} onValueChange={(v) => setData("shipping_address", { ...data.shipping_address, country: v })}>
              <SelectTrigger id="shipping_country">
                <SelectValue placeholder="Select country" />
              </SelectTrigger>
              <SelectContent className="max-h-[300px]">
                {COUNTRIES.map((c) => (
                  <SelectItem key={c.code} value={c.code}>{c.name} ({c.code})</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {fieldError("shipping_address.country")}
          </div>
        </div>
        <div className="space-y-1.5 mt-3">
          <Label htmlFor="shipping_phone">Phone</Label>
          <Input id="shipping_phone" value={data.shipping_address.phone} onChange={(e) => setData("shipping_address", { ...data.shipping_address, phone: e.target.value })} />
          {fieldError("shipping_address.phone")}
        </div>
      </CardContent>
    </Card>
  );

  const reviewCard = (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Eye className="h-4 w-4" />
          Review Your Order
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <h4 className="text-sm font-semibold mb-2">Contact</h4>
          <p className="text-sm text-muted-foreground whitespace-pre-line">
            {data.customer_name}{data.customer_email ? `\n${data.customer_email}` : ""}{data.customer_phone ? `\n${data.customer_phone}` : ""}
          </p>
        </div>
        <Separator />
        <div>
          <h4 className="text-sm font-semibold mb-2">Billing Address</h4>
          <p className="text-sm text-muted-foreground whitespace-pre-line">{formatAddress(data.billing_address)}</p>
        </div>
        <Separator />
        <div>
          <h4 className="text-sm font-semibold mb-2">Shipping Address</h4>
          <p className="text-sm text-muted-foreground whitespace-pre-line">{formatAddress(data.shipping_address)}</p>
        </div>
        <Separator />
        <div>
          <h4 className="text-sm font-semibold mb-2">Shipping Method</h4>
          <p className="text-sm text-muted-foreground">{selectedShipping?.name || "Not selected"}</p>
        </div>
        <Separator />
        <div>
          <h4 className="text-sm font-semibold mb-2">Payment Method</h4>
          <p className="text-sm text-muted-foreground">{data.payment_method === "stripe" ? "Credit Card (Stripe)" : "Offline / Manual Payment"}</p>
        </div>
        <Separator />
        <div>
          <h4 className="text-sm font-semibold mb-2">Items</h4>
          {cart.items.map((item) => (
            <div key={item.id} className="flex justify-between text-sm py-1">
              <span>{item.product.name} × {item.quantity}{item.variant_label ? ` (${item.variant_label})` : ""}</span>
              <span className="tabular-nums">{money(Number(item.unit_price) * item.quantity)}</span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );

  const content = (
    <form onSubmit={submit}>
      {progressBar}

      {generalError && (
        <div className="mb-4 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {generalError}
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-6">
          {step === 0 && (
            <>
              {!isLoggedIn && contactCard}
              {billingCard}
            </>
          )}

          {step === 1 && (
            <>
              {shippingCard}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <ShoppingBag className="h-4 w-4" />
                    Shipping & Payment
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <Label>Shipping Method</Label>
                    <RadioGroup
                      value={data.shipping_method_id}
                      onValueChange={(v) => setData("shipping_method_id", v)}
                      className="mt-2"
                    >
                      {shippingMethods.map((method) => (
                        <div key={method.id} className="flex items-start gap-3 rounded-md border p-3">
                          <RadioGroupItem value={method.id.toString()} id={`ship-${method.id}`} />
                          <div className="flex-1">
                            <Label htmlFor={`ship-${method.id}`} className="font-medium cursor-pointer">
                              {method.name}
                            </Label>
                            {method.description && (
                              <p className="text-xs text-muted-foreground">{method.description}</p>
                            )}
                            {method.estimated_days_min && (
                              <p className="text-xs text-muted-foreground">
                                Estimated {method.estimated_days_min}–{method.estimated_days_max} days
                              </p>
                            )}
                          </div>
                          <span className="tabular-nums text-sm font-medium">
                            {Number(method.price) === 0 ? "Free" : money(Number(method.price))}
                          </span>
                        </div>
                      ))}
                    </RadioGroup>
                  </div>
                  <Separator />
                  <div>
                    <Label>Payment Method</Label>
                    <Select value={data.payment_method} onValueChange={(v) => setData("payment_method", v)}>
                      <SelectTrigger className="mt-2">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="manual">Offline / Manual Payment</SelectItem>
                        <SelectItem value="stripe">Credit Card (Stripe)</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </CardContent>
              </Card>
            </>
          )}

          {step === 2 && (
            <>
              {reviewCard}
              {requiresTerms && (
                <Card>
                  <CardContent className="pt-6">
                    <div className="flex items-start gap-3">
                      <Checkbox
                        id="accepted_terms"
                        checked={data.accepted_terms}
                        onCheckedChange={(v) => setData("accepted_terms", v === true)}
                      />
                      <div className="space-y-1">
                        <Label htmlFor="accepted_terms" className="cursor-pointer text-sm leading-relaxed">
                          I agree to the Terms &amp; Conditions and Privacy Policy.
                        </Label>
                        {errors.accepted_terms && (
                          <p className="text-sm text-destructive">{errors.accepted_terms}</p>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              )}
            </>
          )}
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ShoppingBag className="h-4 w-4" />
                Order Summary
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {cart.items.map((item) => (
                <div key={item.id} className="flex justify-between text-sm">
                  <span className="truncate pr-2">{item.product.name} × {item.quantity}</span>
                  <span className="tabular-nums shrink-0">{money(Number(item.unit_price) * item.quantity)}</span>
                </div>
              ))}
              <Separator />
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Subtotal</span>
                <span className="tabular-nums">{money(cart.subtotal)}</span>
              </div>
              {discountTotal > 0 && (
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">Discount</span>
                  <span className="tabular-nums text-emerald-600">-{money(discountTotal)}</span>
                </div>
              )}
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Shipping</span>
                <span className="tabular-nums">{shippingPrice === 0 ? "Free" : money(shippingPrice)}</span>
              </div>
              {freeShippingThreshold !== null && freeShippingThreshold !== undefined && cart.subtotal < Number(freeShippingThreshold) && (
                <p className="text-xs text-muted-foreground">
                  Free shipping on orders over {money(Number(freeShippingThreshold))}
                </p>
              )}
              {taxTotal > 0 && (
                <div className="space-y-1">
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Tax</span>
                    <span className="tabular-nums">{money(taxTotal)}</span>
                  </div>
                  {estimatedTax?.breakdown?.map((b, i) => (
                    <div key={i} className="flex justify-between text-xs text-muted-foreground pl-2">
                      <span>{b.name} ({b.type === 'percentage' ? `${b.rate}%` : b.rate})</span>
                          <span>{money(b.amount)}</span>
                    </div>
                  ))}
                </div>
              )}
              <Separator />
              <div className="flex justify-between font-medium text-base">
                <span>Total</span>
                <span className="tabular-nums">{money(orderTotal)}</span>
              </div>
              {(() => {
                const defaultCurrency = currencies.find((c) => c.is_default);
                if (defaultCurrency && activeCurrency && defaultCurrency.code !== activeCurrency.code) {
                  return (
                    <p className="text-xs text-muted-foreground mt-2">
                      Amounts are shown in {activeCurrency.code}. Your payment will be charged in{" "}
                      {defaultCurrency.code}.
                    </p>
                  );
                }
                return null;
              })()}
            </CardContent>
          </Card>

          <div className="flex gap-2">
            {step > 0 && (
              <Button type="button" variant="outline" className="flex-1" onClick={() => setStep(step - 1)}>
                <ChevronLeft className="h-4 w-4 mr-1" /> Back
              </Button>
            )}
            {step < STEPS.length - 1 ? (
              <Button type="button" className="flex-1" onClick={() => setStep(step + 1)}>
                Continue <ChevronRight className="h-4 w-4 ml-1" />
              </Button>
            ) : (
              <Button type="submit" className="flex-1" disabled={processing}>
                {processing ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : null}
                {processing ? "Placing Order..." : "Place Order"}
              </Button>
            )}
          </div>
        </div>
      </div>
    </form>
  );

  const stripePromise = stripeKey ? loadStripe(stripeKey) : null;

  return (
    <>
      {stripeKey && stripePromise && (
        <Dialog open={stripeDialogOpen} onOpenChange={setStripeDialogOpen}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Pay with Card</DialogTitle>
              <DialogDescription>Enter your card details to complete the payment.</DialogDescription>
            </DialogHeader>
            <Elements stripe={stripePromise}>
              <StripePaymentForm
                clientSecret={clientSecret}
                onSuccess={handleStripeSuccess}
                onError={(msg) => alert(msg)}
              />
            </Elements>
          </DialogContent>
        </Dialog>
      )}

      <StoreLayout>
        <Head title="Checkout">
          <meta name="robots" content="noindex, nofollow" />
        </Head>
        <div className="min-h-screen bg-background p-4 md:p-8">
          <div className="mx-auto max-w-6xl">
            {content}
          </div>
        </div>
      </StoreLayout>
    </>
  );
}
