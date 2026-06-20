import { useState, useMemo } from "react";
import { Head, useForm, Link } from "@inertiajs/react";
import { format, addDays } from "date-fns";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Separator } from "@/Components/ui/separator";
import { Calendar as CalendarIcon, Calculator, ArrowLeft } from "lucide-react";
import { STATUS_BADGE, STATUS_LABELS } from "../lib/booking-status";

interface Property {
  id: number;
  name: string;
  price: number | null;
  currency: string | null;
  surface: number | null;
  surface_unit: string | null;
  cleaning_fee: number | null;
  damage_deposit: number | null;
}

interface Booking {
  id: number;
  property_id: number;
  start_date: string;
  end_date: string;
  status: string;
  total_amount: number | null;
  currency: string | null;
  guest_count: number;
  notes: string | null;
  guest_name: string | null;
  guest_email: string | null;
  guest_phone: string | null;
  adults: number | null;
  children: number | null;
  pets: number | null;
  source: string | null;
  nightly_rate: number | null;
  cleaning_fee: number | null;
  service_fee: number | null;
  damage_deposit: number | null;
}

interface PricePreview {
  base_nightly_rate?: number;
  nights?: number;
  subtotal?: number;
  cleaning_fee?: number;
  service_fee?: number;
  total?: number;
}

interface BookingsFormProps {
  booking: Booking | null;
  properties: Property[];
}

export default function BookingsForm({ booking, properties }: BookingsFormProps) {
  const isEditing = !!booking;

  const form = useForm({
    property_id: booking?.property_id || (properties[0]?.id ?? ""),
    start_date: booking?.start_date || "",
    end_date: booking?.end_date || "",
    guest_name: booking?.guest_name || "",
    guest_email: booking?.guest_email || "",
    guest_phone: booking?.guest_phone || "",
    adults: booking?.adults?.toString() || "1",
    children: booking?.children?.toString() || "0",
    pets: booking?.pets?.toString() || "0",
    notes: booking?.notes || "",
    nightly_rate: booking?.nightly_rate?.toString() || "",
    cleaning_fee: booking?.cleaning_fee?.toString() || "",
    service_fee: booking?.service_fee?.toString() || "",
    damage_deposit: booking?.damage_deposit?.toString() || "",
    total_amount: booking?.total_amount?.toString() || "",
    currency: booking?.currency || "USD",
    source: booking?.source || "manual",
  });

  const selectedProperty = properties.find((p) => p.id === Number(form.data.property_id));

  const [showPricePreview, setShowPricePreview] = useState(false);

  const totalNights = useMemo(() => {
    if (!form.data.start_date || !form.data.end_date) return 0;
    return Math.round(
      (new Date(form.data.end_date).getTime() - new Date(form.data.start_date).getTime()) / (1000 * 60 * 60 * 24),
    );
  }, [form.data.start_date, form.data.end_date]);

  const nightlyRate = Number(form.data.nightly_rate) || 0;

  const computedTotal = useMemo(() => {
    const cleaning = Number(form.data.cleaning_fee) || 0;
    const service = Number(form.data.service_fee) || 0;
    const deposit = Number(form.data.damage_deposit) || 0;
    return nightlyRate * totalNights + cleaning + service + deposit;
  }, [nightlyRate, form.data.cleaning_fee, form.data.service_fee, form.data.damage_deposit, totalNights]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: () => toast.success(isEditing ? "Booking updated." : "Booking created."),
      onError: () => toast.error("Failed to save booking."),
    };
    if (isEditing) {
      form.put(route("dashboard.bookings.update", booking!.id), options);
    } else {
      form.post(route("dashboard.bookings.store"), options);
    }
  };

  return (
    <AuthenticatedLayout header={isEditing ? "Edit Booking" : "Add Booking"}>
      <Head title={isEditing ? "Edit Booking" : "Add Booking"} />

      <div className="flex items-center gap-3 mb-6">
        <Link href={route("dashboard.bookings.index")}>
          <Button variant="ghost" size="icon"><ArrowLeft className="h-4 w-4" /></Button>
        </Link>
        <div>
          <h2 className="text-2xl font-bold tracking-tight text-foreground">{isEditing ? "Edit Booking" : "New Booking"}</h2>
          {isEditing && booking && (
            <p className="text-sm text-muted-foreground">
              Booking #{booking.id}
              {booking.status && (
                <Badge className={`ml-2 ${STATUS_BADGE[booking.status] || ""}`}>
                  {STATUS_LABELS[booking.status] || booking.status}
                </Badge>
              )}
            </p>
          )}
        </div>
      </div>

      <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>Property &amp; Dates</CardTitle>
              <CardDescription>Select the property and stay dates</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="property_id">Property</Label>
                  <Select
                    value={String(form.data.property_id)}
                    disabled={isEditing}
                    onValueChange={(value) => {
                      form.setData("property_id", Number(value));
                      const prop = properties.find((p) => p.id === Number(value));
                      if (prop && !isEditing) {
                        if (prop.price) form.setData("nightly_rate", String(prop.price));
                        if (prop.cleaning_fee) form.setData("cleaning_fee", String(prop.cleaning_fee));
                        if (prop.damage_deposit) form.setData("damage_deposit", String(prop.damage_deposit));
                      }
                    }}
                  >
                    <SelectTrigger id="property_id"><SelectValue placeholder="Select a property" /></SelectTrigger>
                    <SelectContent>
                      {properties.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          {p.name}{p.price ? ` (${p.currency || "$"}${p.price}/night)` : ""}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {form.errors.property_id && <p className="text-sm text-destructive">{form.errors.property_id}</p>}
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="source">Source</Label>
                  <Select value={form.data.source} disabled={isEditing} onValueChange={(value) => form.setData("source", value)}>
                    <SelectTrigger id="source"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="manual">Manual</SelectItem>
                      <SelectItem value="website">Website</SelectItem>
                      <SelectItem value="airbnb">Airbnb</SelectItem>
                      <SelectItem value="booking">Booking.com</SelectItem>
                      <SelectItem value="vrbo">VRBO</SelectItem>
                      <SelectItem value="direct">Direct</SelectItem>
                      <SelectItem value="other">Other</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="start_date">Check In</Label>
                  <Input id="start_date" type="date" value={form.data.start_date}
                    disabled={isEditing}
                    onChange={(e) => {
                      form.setData("start_date", e.target.value);
                      if (!isEditing && e.target.value && !form.data.end_date) {
                        form.setData("end_date", format(addDays(new Date(e.target.value + "T12:00:00"), 1), "yyyy-MM-dd"));
                      }
                    }}
                  />
                  {form.errors.start_date && <p className="text-sm text-destructive">{form.errors.start_date}</p>}
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="end_date">Check Out</Label>
                  <Input id="end_date" type="date" value={form.data.end_date}
                    disabled={isEditing}
                    onChange={(e) => form.setData("end_date", e.target.value)}
                  />
                  {form.errors.end_date && <p className="text-sm text-destructive">{form.errors.end_date}</p>}
                </div>
              </div>

              {totalNights > 0 && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <CalendarIcon className="h-4 w-4" />
                  <span>{totalNights} night{totalNights !== 1 ? "s" : ""}</span>
                  {selectedProperty?.price && (
                    <span className="text-foreground font-medium">
                      {selectedProperty.currency || "$"}{Number(selectedProperty.price).toFixed(2)}/night
                    </span>
                  )}
                </div>
              )}

              {!isEditing && totalNights > 0 && (
                <Button type="button" variant="outline" size="sm" onClick={() => setShowPricePreview(!showPricePreview)}>
                  <Calculator className="mr-1.5 h-4 w-4" />
                  {showPricePreview ? "Hide Price Preview" : "Show Price Preview"}
                </Button>
              )}

              {showPricePreview && totalNights > 0 && nightlyRate > 0 && (
                <div className="rounded-lg border border-border p-4 space-y-2 bg-muted/30">
                  <p className="text-sm font-medium text-foreground">Price Preview</p>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">{form.data.currency || "$"}{nightlyRate.toFixed(2)} x {totalNights} night{totalNights !== 1 ? "s" : ""}</span>
                    <span className="text-foreground">{(nightlyRate * totalNights).toFixed(2)}</span>
                  </div>
                  {Number(form.data.cleaning_fee) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Cleaning Fee</span>
                      <span className="text-foreground">{form.data.currency || "$"}{Number(form.data.cleaning_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(form.data.service_fee) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Service Fee</span>
                      <span className="text-foreground">{form.data.currency || "$"}{Number(form.data.service_fee).toFixed(2)}</span>
                    </div>
                  )}
                  <Separator />
                  <div className="flex justify-between text-sm font-semibold text-foreground">
                    <span>Total</span>
                    <span>{form.data.currency || "$"}{computedTotal.toFixed(2)}</span>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Guest Information</CardTitle>
              <CardDescription>Contact and occupancy details</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="guest_name">Guest Name</Label>
                  <Input id="guest_name" value={form.data.guest_name} onChange={(e) => form.setData("guest_name", e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="guest_email">Email</Label>
                  <Input id="guest_email" type="email" value={form.data.guest_email} onChange={(e) => form.setData("guest_email", e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="guest_phone">Phone</Label>
                  <Input id="guest_phone" value={form.data.guest_phone} onChange={(e) => form.setData("guest_phone", e.target.value)} />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="adults">Adults</Label>
                  <Input id="adults" type="number" min="1" value={form.data.adults} onChange={(e) => form.setData("adults", e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="children">Children</Label>
                  <Input id="children" type="number" min="0" value={form.data.children} onChange={(e) => form.setData("children", e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="pets">Pets</Label>
                  <Input id="pets" type="number" min="0" value={form.data.pets} onChange={(e) => form.setData("pets", e.target.value)} />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Notes</CardTitle>
            </CardHeader>
            <CardContent>
              <Textarea id="notes" rows={3} value={form.data.notes} onChange={(e) => form.setData("notes", e.target.value)} placeholder="Any special requests or notes..." />
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>{isEditing ? "Price Breakdown" : "Pricing"}</CardTitle>
              <CardDescription>{isEditing ? "Read-only pricing summary" : "Set the pricing details"}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {isEditing ? (
                <>
                  {nightlyRate > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">{form.data.currency || "$"}{nightlyRate.toFixed(2)} x {totalNights} night{totalNights !== 1 ? "s" : ""}</span>
                      <span className="text-foreground">{(nightlyRate * totalNights).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(form.data.cleaning_fee) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Cleaning Fee</span>
                      <span className="text-foreground">{form.data.currency || "$"}{Number(form.data.cleaning_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(form.data.service_fee) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Service Fee</span>
                      <span className="text-foreground">{form.data.currency || "$"}{Number(form.data.service_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(form.data.damage_deposit) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Damage Deposit</span>
                      <span className="text-foreground">{form.data.currency || "$"}{Number(form.data.damage_deposit).toFixed(2)}</span>
                    </div>
                  )}
                  <Separator />
                  <div className="flex justify-between text-sm font-semibold text-foreground">
                    <span>Total</span>
                    <span>{form.data.currency || "$"} {Number(form.data.total_amount || computedTotal || 0).toFixed(2)}</span>
                  </div>
                </>
              ) : (
                <>
                  <div className="grid gap-2">
                    <Label htmlFor="nightly_rate">Nightly Rate</Label>
                    <Input id="nightly_rate" type="number" step="0.01" min="0" value={form.data.nightly_rate}
                      onChange={(e) => form.setData("nightly_rate", e.target.value)}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="cleaning_fee">Cleaning Fee</Label>
                    <Input id="cleaning_fee" type="number" step="0.01" min="0" value={form.data.cleaning_fee}
                      onChange={(e) => form.setData("cleaning_fee", e.target.value)}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="service_fee">Service Fee</Label>
                    <Input id="service_fee" type="number" step="0.01" min="0" value={form.data.service_fee}
                      onChange={(e) => form.setData("service_fee", e.target.value)}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="damage_deposit">Damage Deposit</Label>
                    <Input id="damage_deposit" type="number" step="0.01" min="0" value={form.data.damage_deposit}
                      onChange={(e) => form.setData("damage_deposit", e.target.value)}
                    />
                  </div>
                  <Separator />
                  <div className="grid gap-2">
                    <Label htmlFor="total_amount">Total Amount</Label>
                    <Input id="total_amount" type="number" step="0.01" min="0" value={form.data.total_amount}
                      onChange={(e) => form.setData("total_amount", e.target.value)}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="currency">Currency</Label>
                    <Input id="currency" maxLength={3} value={form.data.currency}
                      onChange={(e) => form.setData("currency", e.target.value.toUpperCase())}
                    />
                  </div>

                  {totalNights > 0 && nightlyRate > 0 && (
                    <div className="rounded-lg bg-muted/30 p-3 space-y-1 text-sm">
                      <div className="flex justify-between"><span className="text-muted-foreground">Nightly Rate</span><span>{form.data.currency || "$"} {nightlyRate.toFixed(2)}</span></div>
                      <div className="flex justify-between"><span className="text-muted-foreground">Nights</span><span>{totalNights}</span></div>
                      <div className="flex justify-between"><span className="text-muted-foreground">Lodging</span><span>{form.data.currency || "$"} {(nightlyRate * totalNights).toFixed(2)}</span></div>
                      <Separator />
                      <div className="flex justify-between font-semibold"><span>Computed Total</span><span>{form.data.currency || "$"} {computedTotal.toFixed(2)}</span></div>
                    </div>
                  )}
                </>
              )}

              {!isEditing && (
                <Button type="submit" disabled={form.processing} className="w-full">
                  {form.processing ? "Saving..." : "Create Booking"}
                </Button>
              )}
            </CardContent>
          </Card>

          {isEditing && (
            <Card>
              <CardHeader>
                <CardTitle>Actions</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <Button type="submit" disabled={form.processing} className="w-full">
                  {form.processing ? "Saving..." : "Update Booking"}
                </Button>
              </CardContent>
            </Card>
          )}
        </div>
      </form>
    </AuthenticatedLayout>
  );
}
