import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler, useState, useCallback } from "react";
import { Plus, Trash2, Search, X } from "lucide-react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import { Separator } from "@/Components/ui/separator";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";

interface Product {
  id: number;
  name: string;
  sku: string | null;
  price: number;
  stock: number | null;
  thumbnail_url: string | null;
}

interface Customer {
  id: number;
  name: string;
  email: string;
}

interface OrderItem {
  product_id: number;
  product_name: string;
  product_sku: string | null;
  unit_price: number;
  quantity: number;
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  user_id: number | null;
  customer_name: string | null;
  customer_email: string | null;
  customer_phone: string | null;
  notes: string | null;
  billing_address: Record<string, string> | null;
  shipping_address: Record<string, string> | null;
  items: (OrderItem & { id: number })[];
}

interface Props {
  products: { data: Product[]; meta: { current_page: number; last_page: number } };
  customers: Customer[];
  order: Order | null;
}

export default function Form({ products: initialProducts, customers: initialCustomers, order }: Props) {
  const isEditing = !!order;

  const { data, setData, post, put, processing, errors } = useForm({
    is_draft: false,
    customer_type: order?.user_id ? "existing" : "guest",
    user_id: order?.user_id?.toString() ?? "",
    customer_name: order?.customer_name ?? "",
    customer_email: order?.customer_email ?? "",
    customer_phone: order?.customer_phone ?? "",
    notes: order?.notes ?? "",
    billing_address: {
      first_name: order?.billing_address?.first_name ?? "",
      last_name: order?.billing_address?.last_name ?? "",
      address_line1: order?.billing_address?.address_line1 ?? "",
      address_line2: order?.billing_address?.address_line2 ?? "",
      city: order?.billing_address?.city ?? "",
      state: order?.billing_address?.state ?? "",
      postcode: order?.billing_address?.postcode ?? "",
      country: order?.billing_address?.country ?? "",
    },
    shipping_address: {
      first_name: order?.shipping_address?.first_name ?? "",
      last_name: order?.shipping_address?.last_name ?? "",
      address_line1: order?.shipping_address?.address_line1 ?? "",
      address_line2: order?.shipping_address?.address_line2 ?? "",
      city: order?.shipping_address?.city ?? "",
      state: order?.shipping_address?.state ?? "",
      postcode: order?.shipping_address?.postcode ?? "",
      country: order?.shipping_address?.country ?? "",
    },
    items: (order?.items ?? []) as OrderItem[],
  });

  const [searchQuery, setSearchQuery] = useState("");
  const [searchResults, setSearchResults] = useState<Product[]>([]);
  const [showSearch, setShowSearch] = useState(false);

  const [customerSearchQuery, setCustomerSearchQuery] = useState("");
  const [customerResults, setCustomerResults] = useState<Customer[]>([]);
  const [showCustomerSearch, setShowCustomerSearch] = useState(false);

  const debounce = (fn: (...args: unknown[]) => void, ms: number) => {
    let timer: ReturnType<typeof setTimeout>;
    return (...args: unknown[]) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), ms);
    };
  };

  const searchProducts = useCallback(
    debounce((q: string) => {
      if (q.length < 2) { setSearchResults([]); return; }
      fetch(route("dashboard.shop.orders.search-products") + "?q=" + encodeURIComponent(q))
        .then((r) => r.json())
        .then((data) => setSearchResults(data))
        .catch(() => {});
    }, 300),
    [],
  );

  const searchCustomers = useCallback(
    debounce((q: string) => {
      if (q.length < 2) { setCustomerResults([]); return; }
      fetch(route("dashboard.shop.orders.search-customers") + "?q=" + encodeURIComponent(q))
        .then((r) => r.json())
        .then((data) => setCustomerResults(data))
        .catch(() => {});
    }, 300),
    [],
  );

  const addItem = (product: Product) => {
    const existing = data.items.find((i) => i.product_id === product.id);
    if (existing) {
      setData("items", data.items.map((i) =>
        i.product_id === product.id ? { ...i, quantity: i.quantity + 1 } : i,
      ));
    } else {
      setData("items", [...data.items, {
        product_id: product.id,
        product_name: product.name,
        product_sku: product.sku,
        unit_price: product.price,
        quantity: 1,
      }]);
    }
    setShowSearch(false);
    setSearchQuery("");
    setSearchResults([]);
  };

  const removeItem = (productId: number) => {
    setData("items", data.items.filter((i) => i.product_id !== productId));
  };

  const updateItemQty = (productId: number, quantity: number) => {
    if (quantity < 1) return;
    setData("items", data.items.map((i) =>
      i.product_id === productId ? { ...i, quantity } : i,
    ));
  };

  const updateItemPrice = (productId: number, unit_price: number) => {
    setData("items", data.items.map((i) =>
      i.product_id === productId ? { ...i, unit_price } : i,
    ));
  };

  const selectCustomer = (customer: Customer) => {
    setData("customer_type", "existing");
    setData("user_id", customer.id.toString());
    setData("customer_name", customer.name);
    setData("customer_email", customer.email);
    setShowCustomerSearch(false);
    setCustomerSearchQuery("");
    setCustomerResults([]);
  };

  const subtotal = data.items.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (isEditing) {
      put(route("dashboard.shop.orders.update", order!.id), {
        onSuccess: () => toast.success("Order updated."),
        onError: () => toast.error("Failed to update order."),
      });
    } else {
      post(route("dashboard.shop.orders.store"), {
        onSuccess: () => toast.success("Order created."),
        onError: () => toast.error("Failed to create order."),
      });
    }
  };

  const submitDraft: FormEventHandler = (e) => {
    e.preventDefault();
    setData("is_draft", true);
    setTimeout(() => {
      post(route("dashboard.shop.orders.store"), {
        onSuccess: () => toast.success("Draft order saved."),
        onError: () => toast.error("Failed to save draft."),
      });
    }, 50);
  };

  return (
    <AuthenticatedLayout header={isEditing ? `Edit Order ${order!.order_number}` : "Create Order"}>
      <Head title={isEditing ? `Edit Order ${order!.order_number}` : "Create Order"} />

      <form onSubmit={submit}>
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Order Items</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                {showSearch ? (
                  <div className="space-y-2">
                    <div className="flex items-center gap-2">
                      <Input
                        placeholder="Search products by name or SKU..."
                        value={searchQuery}
                        onChange={(e) => {
                          setSearchQuery(e.target.value);
                          searchProducts(e.target.value);
                        }}
                        autoFocus
                      />
                      <Button type="button" variant="ghost" size="icon" onClick={() => { setShowSearch(false); setSearchResults([]); setSearchQuery(""); }}>
                        <X className="h-4 w-4" />
                      </Button>
                    </div>
                    {searchResults.length > 0 && (
                      <div className="border rounded-md max-h-60 overflow-y-auto">
                        {searchResults.map((p) => (
                          <button
                            key={p.id}
                            type="button"
                            className="w-full flex items-center gap-3 px-3 py-2 hover:bg-muted text-left text-sm"
                            onClick={() => addItem(p)}
                          >
                            {p.thumbnail_url && (
                              <img src={p.thumbnail_url} alt="" className="w-8 h-8 rounded object-cover" />
                            )}
                            <div className="flex-1 min-w-0">
                              <p className="truncate font-medium">{p.name}</p>
                              <p className="text-xs text-muted-foreground">{p.sku || "—"}</p>
                            </div>
                            <div className="text-right shrink-0">
                              <p className="tabular-nums">{new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(p.price)}</p>
                              {p.stock !== null && <p className="text-xs text-muted-foreground">{p.stock} in stock</p>}
                            </div>
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                ) : (
                  <Button type="button" variant="outline" size="sm" onClick={() => setShowSearch(true)}>
                    <Search className="mr-2 h-4 w-4" />
                    Add Product
                  </Button>
                )}

                {data.items.length > 0 && (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Product</TableHead>
                        <TableHead className="w-20 text-center">Qty</TableHead>
                        <TableHead className="w-28 text-right">Price</TableHead>
                        <TableHead className="w-28 text-right">Total</TableHead>
                        <TableHead className="w-12"></TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.items.map((item) => (
                        <TableRow key={item.product_id}>
                          <TableCell>
                            <p className="text-sm font-medium">{item.product_name}</p>
                            {item.product_sku && <p className="text-xs text-muted-foreground font-mono">{item.product_sku}</p>}
                          </TableCell>
                          <TableCell>
                            <Input
                              type="number"
                              min={1}
                              className="w-16 h-8 text-center mx-auto"
                              value={item.quantity}
                              onChange={(e) => updateItemQty(item.product_id, parseInt(e.target.value) || 1)}
                            />
                          </TableCell>
                          <TableCell>
                            <Input
                              type="number"
                              step="0.01"
                              min="0"
                              className="w-24 h-8 text-right ml-auto"
                              value={item.unit_price}
                              onChange={(e) => updateItemPrice(item.product_id, parseFloat(e.target.value) || 0)}
                            />
                          </TableCell>
                          <TableCell className="text-right font-medium tabular-nums">
                            {new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(item.unit_price * item.quantity)}
                          </TableCell>
                          <TableCell>
                            <Button type="button" variant="ghost" size="icon" onClick={() => removeItem(item.product_id)}>
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Billing Address</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="billing_first_name">First Name</Label>
                    <Input id="billing_first_name" value={data.billing_address.first_name} onChange={(e) => setData("billing_address", { ...data.billing_address, first_name: e.target.value })} />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="billing_last_name">Last Name</Label>
                    <Input id="billing_last_name" value={data.billing_address.last_name} onChange={(e) => setData("billing_address", { ...data.billing_address, last_name: e.target.value })} />
                  </div>
                </div>
                <div className="space-y-1.5 mt-3">
                  <Label htmlFor="billing_address_line1">Address Line 1</Label>
                  <Input id="billing_address_line1" value={data.billing_address.address_line1} onChange={(e) => setData("billing_address", { ...data.billing_address, address_line1: e.target.value })} />
                </div>
                <div className="space-y-1.5 mt-3">
                  <Label htmlFor="billing_address_line2">Address Line 2</Label>
                  <Input id="billing_address_line2" value={data.billing_address.address_line2} onChange={(e) => setData("billing_address", { ...data.billing_address, address_line2: e.target.value })} />
                </div>
                <div className="grid grid-cols-2 gap-3 mt-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="billing_city">City</Label>
                    <Input id="billing_city" value={data.billing_address.city} onChange={(e) => setData("billing_address", { ...data.billing_address, city: e.target.value })} />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="billing_state">State</Label>
                    <Input id="billing_state" value={data.billing_address.state} onChange={(e) => setData("billing_address", { ...data.billing_address, state: e.target.value })} />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3 mt-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="billing_postcode">Postcode</Label>
                    <Input id="billing_postcode" value={data.billing_address.postcode} onChange={(e) => setData("billing_address", { ...data.billing_address, postcode: e.target.value })} />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="billing_country">Country</Label>
                    <Input id="billing_country" maxLength={2} placeholder="US" value={data.billing_address.country} onChange={(e) => setData("billing_address", { ...data.billing_address, country: e.target.value })} />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle>Shipping Address</CardTitle>
                  <Button type="button" variant="ghost" size="sm" onClick={() => setData("shipping_address", { ...data.billing_address })}>
                    Copy from billing
                  </Button>
                </div>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="shipping_first_name">First Name</Label>
                    <Input id="shipping_first_name" value={data.shipping_address.first_name} onChange={(e) => setData("shipping_address", { ...data.shipping_address, first_name: e.target.value })} />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="shipping_last_name">Last Name</Label>
                    <Input id="shipping_last_name" value={data.shipping_address.last_name} onChange={(e) => setData("shipping_address", { ...data.shipping_address, last_name: e.target.value })} />
                  </div>
                </div>
                <div className="space-y-1.5 mt-3">
                  <Label htmlFor="shipping_address_line1">Address Line 1</Label>
                  <Input id="shipping_address_line1" value={data.shipping_address.address_line1} onChange={(e) => setData("shipping_address", { ...data.shipping_address, address_line1: e.target.value })} />
                </div>
                <div className="space-y-1.5 mt-3">
                  <Label htmlFor="shipping_address_line2">Address Line 2</Label>
                  <Input id="shipping_address_line2" value={data.shipping_address.address_line2} onChange={(e) => setData("shipping_address", { ...data.shipping_address, address_line2: e.target.value })} />
                </div>
                <div className="grid grid-cols-2 gap-3 mt-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="shipping_city">City</Label>
                    <Input id="shipping_city" value={data.shipping_address.city} onChange={(e) => setData("shipping_address", { ...data.shipping_address, city: e.target.value })} />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="shipping_state">State</Label>
                    <Input id="shipping_state" value={data.shipping_address.state} onChange={(e) => setData("shipping_address", { ...data.shipping_address, state: e.target.value })} />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3 mt-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="shipping_postcode">Postcode</Label>
                    <Input id="shipping_postcode" value={data.shipping_address.postcode} onChange={(e) => setData("shipping_address", { ...data.shipping_address, postcode: e.target.value })} />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="shipping_country">Country</Label>
                    <Input id="shipping_country" maxLength={2} placeholder="US" value={data.shipping_address.country} onChange={(e) => setData("shipping_address", { ...data.shipping_address, country: e.target.value })} />
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Customer</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {data.customer_type === "existing" ? (
                  <div className="space-y-2">
                    {showCustomerSearch ? (
                      <div className="space-y-2">
                        <div className="flex items-center gap-2">
                          <Input
                            placeholder="Search customers..."
                            value={customerSearchQuery}
                            onChange={(e) => {
                              setCustomerSearchQuery(e.target.value);
                              searchCustomers(e.target.value);
                            }}
                            autoFocus
                          />
                          <Button type="button" variant="ghost" size="icon" onClick={() => { setShowCustomerSearch(false); setCustomerResults([]); }}>
                            <X className="h-4 w-4" />
                          </Button>
                        </div>
                        {customerResults.length > 0 && (
                          <div className="border rounded-md max-h-48 overflow-y-auto">
                            {customerResults.map((c) => (
                              <button
                                key={c.id}
                                type="button"
                                className="w-full px-3 py-2 hover:bg-muted text-left text-sm"
                                onClick={() => selectCustomer(c)}
                              >
                                <p className="font-medium">{c.name}</p>
                                <p className="text-xs text-muted-foreground">{c.email}</p>
                              </button>
                            ))}
                          </div>
                        )}
                      </div>
                    ) : (
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-sm font-medium">{data.customer_name || "No customer selected"}</p>
                          {data.customer_email && <p className="text-xs text-muted-foreground">{data.customer_email}</p>}
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={() => setShowCustomerSearch(true)}>
                          Change
                        </Button>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="space-y-2">
                    <Label>Customer Type</Label>
                    <Select
                      value={data.customer_type}
                      onValueChange={(v) => setData("customer_type", v)}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="existing">Existing Customer</SelectItem>
                        <SelectItem value="guest">Guest</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                )}

                {data.customer_type === "guest" && (
                  <>
                    <div className="space-y-1.5">
                      <Label htmlFor="customer_name">Name</Label>
                      <Input id="customer_name" value={data.customer_name} onChange={(e) => setData("customer_name", e.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor="customer_email">Email</Label>
                      <Input id="customer_email" type="email" value={data.customer_email} onChange={(e) => setData("customer_email", e.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor="customer_phone">Phone</Label>
                      <Input id="customer_phone" value={data.customer_phone} onChange={(e) => setData("customer_phone", e.target.value)} />
                    </div>
                  </>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Order Summary</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Items</span>
                  <span>{data.items.length}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Subtotal</span>
                  <span className="tabular-nums font-medium">{new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(subtotal)}</span>
                </div>
                <Separator />
                <div className="flex justify-between text-base font-semibold">
                  <span>Total</span>
                  <span className="tabular-nums">{new Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(subtotal)}</span>
                </div>
                <Button type="submit" className="w-full mt-4" disabled={processing || data.items.length === 0}>
                  {processing ? "Saving..." : isEditing ? "Update Order" : "Create Order"}
                </Button>
                {!isEditing && (
                  <Button type="button" variant="outline" className="w-full mt-2" disabled={processing || data.items.length === 0} onClick={submitDraft}>
                    {processing ? "Saving..." : "Save as Draft"}
                  </Button>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Notes</CardTitle>
              </CardHeader>
              <CardContent>
                <Textarea
                  rows={4}
                  placeholder="Internal notes..."
                  value={data.notes}
                  onChange={(e) => setData("notes", e.target.value)}
                />
              </CardContent>
            </Card>
          </div>
        </div>
      </form>
    </AuthenticatedLayout>
  );
}
