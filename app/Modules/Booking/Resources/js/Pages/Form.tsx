import { Head, useForm, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { ArrowLeft, Loader2, Building2, Calendar as CalendarIcon, User, Mail, Phone, Users } from "lucide-react";

interface Property {
  id: number;
  name: string;
}

interface Booking {
  id: number;
  property_id: number;
  guest_name: string;
  guest_email: string;
  guest_phone: string | null;
  start_date: string;
  end_date: string;
  adults: number;
  children: number;
  pets: number;
  notes: string | null;
}

interface BookingFormProps {
  booking: Booking | null;
  properties: Property[];
}

export default function BookingForm({ booking, properties }: BookingFormProps) {
  const isEditing = !!booking;

  const { data, setData, post, put, processing, errors } = useForm({
    property_id: booking?.property_id?.toString() ?? "",
    guest_name: booking?.guest_name ?? "",
    guest_email: booking?.guest_email ?? "",
    guest_phone: booking?.guest_phone ?? "",
    start_date: booking?.start_date ?? "",
    end_date: booking?.end_date ?? "",
    adults: booking?.adults?.toString() ?? "1",
    children: booking?.children?.toString() ?? "0",
    pets: booking?.pets?.toString() ?? "0",
    notes: booking?.notes ?? "",
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();

    const payload = {
      ...data,
      adults: parseInt(data.adults),
      children: parseInt(data.children),
      pets: parseInt(data.pets),
    };

    if (isEditing) {
      put(route("dashboard.bookings.update", booking.id), {
        data: payload,
        preserveScroll: true,
      });
    } else {
      post(route("dashboard.bookings.store"), {
        data: payload,
        preserveScroll: true,
      });
    }
  };

  return (
    <AuthenticatedLayout
      header={
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href={route("dashboard.bookings.index")}>Bookings</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>{isEditing ? "Edit Booking" : "New Booking"}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      }
    >
      <Head title={isEditing ? "Edit Booking" : "New Booking"} />

      <div className="flex flex-col gap-6 p-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">
              {isEditing ? "Edit Booking" : "New Booking"}
            </h1>
            <p className="text-muted-foreground">
              {isEditing ? "Update booking details" : "Create a new booking"}
            </p>
          </div>
          <Button variant="outline" onClick={() => router.visit(route("dashboard.bookings.index"))}>
            <ArrowLeft className="mr-2 h-4 w-4" /> Back
          </Button>
        </div>

        <form onSubmit={submit} className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Building2 className="h-5 w-5 text-muted-foreground" />
                Property
              </CardTitle>
              <CardDescription>Select the property for this booking</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="property_id">Property *</Label>
                  <Select
                    value={data.property_id}
                    onValueChange={(val) => setData("property_id", val)}
                    disabled={isEditing}
                  >
                    <SelectTrigger id="property_id">
                      <SelectValue placeholder="Select a property" />
                    </SelectTrigger>
                    <SelectContent>
                      {properties.map((p) => (
                        <SelectItem key={p.id} value={p.id.toString()}>
                          {p.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.property_id && (
                    <p className="text-sm text-destructive">{errors.property_id}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <CalendarIcon className="h-5 w-5 text-muted-foreground" />
                Dates
              </CardTitle>
              <CardDescription>Set check-in and check-out dates</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="start_date">Check-In *</Label>
                  <Input
                    id="start_date"
                    type="date"
                    value={data.start_date}
                    onChange={(e) => setData("start_date", e.target.value)}
                  />
                  {errors.start_date && (
                    <p className="text-sm text-destructive">{errors.start_date}</p>
                  )}
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="end_date">Check-Out *</Label>
                  <Input
                    id="end_date"
                    type="date"
                    value={data.end_date}
                    onChange={(e) => setData("end_date", e.target.value)}
                  />
                  {errors.end_date && (
                    <p className="text-sm text-destructive">{errors.end_date}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <User className="h-5 w-5 text-muted-foreground" />
                Guest Information
              </CardTitle>
              <CardDescription>Guest contact details</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="guest_name">Guest Name *</Label>
                <div className="relative">
                  <User className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="guest_name"
                    className="pl-9"
                    placeholder="Full name"
                    value={data.guest_name}
                    onChange={(e) => setData("guest_name", e.target.value)}
                  />
                </div>
                {errors.guest_name && (
                  <p className="text-sm text-destructive">{errors.guest_name}</p>
                )}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="guest_email">Email *</Label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="guest_email"
                      type="email"
                      className="pl-9"
                      placeholder="guest@example.com"
                      value={data.guest_email}
                      onChange={(e) => setData("guest_email", e.target.value)}
                    />
                  </div>
                  {errors.guest_email && (
                    <p className="text-sm text-destructive">{errors.guest_email}</p>
                  )}
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="guest_phone">Phone</Label>
                  <div className="relative">
                    <Phone className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                      id="guest_phone"
                      className="pl-9"
                      placeholder="+1 (555) 000-0000"
                      value={data.guest_phone}
                      onChange={(e) => setData("guest_phone", e.target.value)}
                    />
                  </div>
                  {errors.guest_phone && (
                    <p className="text-sm text-destructive">{errors.guest_phone}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Users className="h-5 w-5 text-muted-foreground" />
                Guests
              </CardTitle>
              <CardDescription>Number of guests</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="adults">Adults</Label>
                  <Input
                    id="adults"
                    type="number"
                    min="1"
                    value={data.adults}
                    onChange={(e) => setData("adults", e.target.value)}
                  />
                  {errors.adults && (
                    <p className="text-sm text-destructive">{errors.adults}</p>
                  )}
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="children">Children</Label>
                  <Input
                    id="children"
                    type="number"
                    min="0"
                    value={data.children}
                    onChange={(e) => setData("children", e.target.value)}
                  />
                  {errors.children && (
                    <p className="text-sm text-destructive">{errors.children}</p>
                  )}
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="pets">Pets</Label>
                  <Input
                    id="pets"
                    type="number"
                    min="0"
                    value={data.pets}
                    onChange={(e) => setData("pets", e.target.value)}
                  />
                  {errors.pets && (
                    <p className="text-sm text-destructive">{errors.pets}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                Notes
              </CardTitle>
              <CardDescription>Additional notes or special requests</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-2">
                <Textarea
                  id="notes"
                  placeholder="Any special requests or notes..."
                  value={data.notes}
                  onChange={(e) => setData("notes", e.target.value)}
                  rows={4}
                />
                {errors.notes && (
                  <p className="text-sm text-destructive">{errors.notes}</p>
                )}
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end gap-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.visit(route("dashboard.bookings.index"))}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {isEditing ? "Update Booking" : "Create Booking"}
            </Button>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}
