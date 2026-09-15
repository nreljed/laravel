import { useState, useMemo } from "react";
import { Link, router } from "@inertiajs/react";
import { toast } from "@nreljed/platform/Components/ui/toast";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Avatar, AvatarFallback } from "@nreljed/platform/Components/ui/avatar";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Textarea } from "@nreljed/platform/Components/ui/textarea";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@nreljed/platform/Components/ui/alert-dialog";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@nreljed/platform/Components/ui/breadcrumb";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import {
  BOOKING_STATUS,
  STATUS_BADGE,
  STATUS_DOT,
  getStatusLabel,
  PAYMENT_STATUS_BADGE,
} from "../lib/booking-status";
import { ArrowLeft, CalendarDays, User, Mail, Phone, DollarSign, Users, Building2, Clock, History, ArrowRight, Globe } from "lucide-react";

interface Property {
  id: number;
  name: string;
  price: number | null;
  currency: string | null;
}

interface User {
  id: number;
  name: string;
  email: string;
}

interface HostPhoneEntry {
  label: string;
  phone: string;
}

interface HostSocialEntry {
  social_title: string;
  social_value: string;
}

interface Booking {
  id: number;
  property: Property | null;
  user: User | null;
  host_name?: string;
  host_phone?: string;
  host_phone_numbers?: HostPhoneEntry[];
  host_email?: string;
  host_avatar?: string;
  host_bio?: string;
  host_social_media?: HostSocialEntry[];
  start_date: string;
  end_date: string;
  check_in?: string;
  check_out?: string;
  check_in_time?: string;
  check_out_time?: string;
  status: string;
  status_label?: string;
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
  cancellation_reason: string | null;
  confirmed_at: string | null;
  cancelled_at: string | null;
  checked_in_at: string | null;
  checked_out_at: string | null;
  nightly_rate: number | null;
  cleaning_fee: number | null;
  service_fee: number | null;
  damage_deposit: number | null;
  payment_status: string | null;
  allowed_transitions?: string[];
  created_at: string;
  updated_at: string;
}

interface ShowProps {
  booking: Booking;
  timezone?: string;
}

function TimelineNode({ color, label, value, isLast = false, icon }: {
  color: string; label: string; value: string; isLast?: boolean; icon?: React.ReactNode;
}) {
  return (
    <div className="flex items-start gap-3 relative">
      {!isLast && <div className="absolute left-[9px] top-[22px] bottom-0 w-0.5 bg-border" />}
      <div className={`size-[20px] rounded-full flex items-center justify-center shrink-0 mt-0.5 z-10 ${color}`}>
        {icon || <div className="size-2 rounded-full bg-current" />}
      </div>
      <div className="pb-4 min-w-0">
        <p className="text-sm font-medium text-foreground">{label}</p>
        <p className="text-xs text-muted-foreground">{value}</p>
      </div>
    </div>
  );
}

function formatTimestamp(ts?: string | null): string {
  if (!ts) return "";
  return new Date(ts + (ts.includes("T") ? "" : "T12:00:00")).toLocaleDateString("en-US", {
    month: "short", day: "numeric", hour: "numeric", minute: "2-digit",
  });
}

function formatTimeStr(time?: string): string {
  if (!time) return "";
  const [h, m] = time.split(":");
  if (!h || !m) return time;
  const hour = Number(h);
  const ampm = hour >= 12 ? "PM" : "AM";
  const hour12 = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
  return `${hour12}:${m} ${ampm}`;
}

function formatDateStr(dateStr?: string): string {
  if (!dateStr) return "";
  return new Date(dateStr + "T12:00:00").toLocaleDateString("en-US", {
    weekday: "short", month: "short", day: "numeric", year: "numeric",
  });
}

export default function BookingShow({ booking, timezone }: ShowProps) {
  const [confirmState, setConfirmState] = useState<{
    open: boolean; title: string; description: string; buttonLabel: string; destructive?: boolean; action: () => void;
  }>({ open: false, title: "", description: "", buttonLabel: "", action: () => {} });
  const [processing, setProcessing] = useState(false);
  const [reason, setReason] = useState("");

  const totalNights = useMemo(() => {
    if (!booking.start_date || !booking.end_date) return 0;
    return Math.round(
      (new Date(booking.end_date).getTime() - new Date(booking.start_date).getTime()) / (1000 * 60 * 60 * 24),
    );
  }, [booking.start_date, booking.end_date]);

  const statusEvents = useMemo(() => {
    const events: { status: string; timestamp: string | null; label: string }[] = [
      { status: BOOKING_STATUS.Pending, timestamp: booking.created_at, label: "Booking Created" },
    ];
    if (booking.confirmed_at) events.push({ status: BOOKING_STATUS.Confirmed, timestamp: booking.confirmed_at, label: "Confirmed" });
    if (booking.checked_in_at) events.push({ status: BOOKING_STATUS.CheckedIn, timestamp: booking.checked_in_at, label: "Checked In" });
    if (booking.checked_out_at) events.push({ status: BOOKING_STATUS.CheckedOut, timestamp: booking.checked_out_at, label: "Checked Out" });
    if (booking.cancelled_at) events.push({ status: BOOKING_STATUS.Cancelled, timestamp: booking.cancelled_at, label: "Cancelled" });
    return events;
  }, [booking]);

  const handleTransition = (newStatus: string) => {
    const isDestructive = newStatus === BOOKING_STATUS.Cancelled;
    setConfirmState({
      open: true,
      title: `Mark as "${getStatusLabel(newStatus)}"?`,
      description: `Are you sure you want to change booking #${booking.id} to "${getStatusLabel(newStatus)}"?`,
      buttonLabel: `Mark ${getStatusLabel(newStatus)}`,
      destructive: isDestructive,
      action: () => {
        setProcessing(true);
        router.post(
          route("dashboard.bookings.transition", booking.id),
          { status: newStatus, reason },
          {
            preserveScroll: true,
            onSuccess: () => {
              toast.add({ title: `Status changed to ${getStatusLabel(newStatus)}`, type: "success" });
              setReason("");
            },
            onError: (err) => toast.add({ title: typeof err === "object" ? "Failed to update status" : String(err), type: "error" }),
            onFinish: () => setProcessing(false),
          },
        );
      },
    });
  };

  const guestName = booking.guest_name || booking.user?.name || "N/A";
  const guestInitials = guestName.split(" ").map((n) => n[0]).join("").toUpperCase().slice(0, 2);
  const propertyName = booking.property?.name || "Unknown Property";

  return (
    <AuthenticatedLayout header={`Booking #${booking.id}`}>
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem><BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbLink href={route("dashboard.bookings.index")}>Bookings</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>#{booking.id}</BreadcrumbPage></BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Link href={route("dashboard.bookings.index")}>
              <Button variant="ghost" size="icon"><ArrowLeft className="h-4 w-4" /></Button>
            </Link>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-2xl font-bold tracking-tight text-foreground">{propertyName}</h2>
                <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[booking.status] || ""}`}>
                  {booking.status_label || getStatusLabel(booking.status)}
                </span>
              </div>
              <p className="text-sm text-muted-foreground">Booking #{booking.id} &middot; {formatDateStr(booking.start_date)} &ndash; {formatDateStr(booking.end_date)}</p>
            </div>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          <div className="space-y-6 lg:col-span-2">
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center gap-2">
                  <CalendarDays className="h-4 w-4 text-muted-foreground" />
                  Stay Timeline
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex items-stretch gap-4">
                  <div className="flex-1 bg-muted/30 rounded-lg p-4">
                    <p className="text-xs text-muted-foreground mb-1">Check In</p>
                    <p className="text-lg font-semibold text-foreground">{formatDateStr(booking.check_in || booking.start_date)}</p>
                    {booking.check_in_time && <p className="text-xs text-muted-foreground">After {formatTimeStr(booking.check_in_time)}</p>}
                  </div>
                  <div className="flex items-center">
                    <ArrowRight className="h-5 w-5 text-muted-foreground" />
                  </div>
                  <div className="flex-1 bg-muted/30 rounded-lg p-4">
                    <p className="text-xs text-muted-foreground mb-1">Check Out</p>
                    <p className="text-lg font-semibold text-foreground">{formatDateStr(booking.check_out || booking.end_date)}</p>
                    {booking.check_out_time && <p className="text-xs text-muted-foreground">Before {formatTimeStr(booking.check_out_time)}</p>}
                    {timezone && <p className="text-xs text-muted-foreground/60 mt-1">{timezone}</p>}
                  </div>
                </div>
                <div className="flex gap-4 mt-3 text-sm text-muted-foreground">
                  <span className="flex items-center gap-1"><Clock className="h-3.5 w-3.5" /> {totalNights} night{totalNights !== 1 ? "s" : ""}</span>
                  <span className="flex items-center gap-1"><Users className="h-3.5 w-3.5" /> {booking.guest_count || booking.adults || 0} guest{(booking.guest_count || booking.adults || 0) !== 1 ? "s" : ""}</span>
                  {booking.source && <span className="flex items-center gap-1"><Building2 className="h-3.5 w-3.5" /> {booking.source}</span>}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center gap-2">
                  <User className="h-4 w-4 text-muted-foreground" />
                  Guest Information
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center gap-3">
                  <Avatar className="h-10 w-10">
                    <AvatarFallback className="bg-muted text-xs">{guestInitials}</AvatarFallback>
                  </Avatar>
                  <div>
                    <p className="font-medium text-foreground">{guestName}</p>
                    {booking.user?.email && <p className="text-xs text-muted-foreground">Registered user</p>}
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {booking.guest_email && (
                    <div className="flex items-center gap-2 text-sm">
                      <Mail className="h-4 w-4 text-muted-foreground shrink-0" />
                      <a href={`mailto:${booking.guest_email}`} className="hover:underline text-foreground">{booking.guest_email}</a>
                    </div>
                  )}
                  {booking.guest_phone && (
                    <div className="flex items-center gap-2 text-sm">
                      <Phone className="h-4 w-4 text-muted-foreground shrink-0" />
                      <a href={`tel:${booking.guest_phone}`} className="hover:underline text-foreground">{booking.guest_phone}</a>
                    </div>
                  )}
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Users className="h-4 w-4 shrink-0" />
                    {booking.adults || 0} adult{booking.adults !== 1 ? "s" : ""}
                    {booking.children ? `, ${booking.children} child${booking.children !== 1 ? "ren" : ""}` : ""}
                    {booking.pets ? `, ${booking.pets} pet${booking.pets !== 1 ? "s" : ""}` : ""}
                  </div>
                </div>
              </CardContent>
            </Card>

            {(booking.host_name || booking.user) && (
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-base flex items-center gap-2">
                    <User className="h-4 w-4 text-muted-foreground" />
                    Host / Manager
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex items-center gap-3">
                    <Avatar className="h-10 w-10">
                      {booking.host_avatar ? (
                        <img src={booking.host_avatar} alt="" className="h-10 w-10 rounded-full object-cover" />
                      ) : null}
                      <AvatarFallback className="bg-muted text-xs">
                        {(booking.host_name || booking.user?.name || "H").split(" ").map((n: string) => n[0]).join("").toUpperCase().slice(0, 2)}
                      </AvatarFallback>
                    </Avatar>
                    <div>
                      <p className="font-medium text-foreground">{booking.host_name || booking.user?.name}</p>
                      {booking.host_bio && <p className="text-xs text-muted-foreground line-clamp-2">{booking.host_bio}</p>}
                    </div>
                  </div>
                  <div className="space-y-1.5">
                    {(booking.host_phone_numbers && booking.host_phone_numbers.length > 0
                      ? booking.host_phone_numbers
                      : booking.host_phone ? [{ label: "Phone", phone: booking.host_phone }] : []
                    ).map((entry: HostPhoneEntry, idx: number) => (
                      <div key={idx} className="flex items-center gap-2 text-sm">
                        <Phone className="h-4 w-4 text-muted-foreground shrink-0" />
                        <span className="text-xs text-muted-foreground min-w-[60px]">{entry.label}:</span>
                        <a href={`tel:${entry.phone}`} className="hover:underline text-foreground">{entry.phone}</a>
                      </div>
                    ))}
                    {(booking.host_email || booking.user?.email) && (
                      <div className="flex items-center gap-2 text-sm">
                        <Mail className="h-4 w-4 text-muted-foreground shrink-0" />
                        <a href={`mailto:${booking.host_email || booking.user?.email}`} className="hover:underline text-foreground">{(booking.host_email || booking.user?.email)}</a>
                      </div>
                    )}
                    {(booking.host_social_media ?? []).length > 0 && (
                      <div className="flex flex-wrap gap-2 pt-1">
                        {(booking.host_social_media ?? []).map((s: HostSocialEntry, idx: number) => (
                          <a
                            key={idx}
                            href={s.social_value}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                          >
                            <Globe className="h-3 w-3" />
                            {s.social_title}
                          </a>
                        ))}
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            )}

            {booking.notes && (
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-base">Notes</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-muted-foreground whitespace-pre-wrap">{booking.notes}</p>
                </CardContent>
              </Card>
            )}
          </div>

          <div className="space-y-6">
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center gap-2">
                  <History className="h-4 w-4 text-muted-foreground" />
                  Status Timeline
                </CardTitle>
              </CardHeader>
              <CardContent>
                {statusEvents.map((event, idx) => {
                  const isLast = idx === statusEvents.length - 1;
                  const dotColor = STATUS_DOT[event.status] || "bg-gray-300 dark:bg-gray-600";
                  return (
                    <TimelineNode
                      key={event.label}
                      color={dotColor}
                      label={event.label}
                      value={formatTimestamp(event.timestamp)}
                      isLast={isLast}
                    />
                  );
                })}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center gap-2">
                  <ArrowRight className="h-4 w-4 text-muted-foreground" />
                  Actions
                </CardTitle>
                <CardDescription>Change booking status</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {(booking.allowed_transitions ?? []).length > 0 ? (
                  (booking.allowed_transitions ?? []).map((transition) => {
                    const isDestructive = transition === BOOKING_STATUS.Cancelled;
                    return (
                      <Button
                        key={transition}
                        className="w-full"
                        variant={isDestructive ? "destructive" : "default"}
                        onClick={() => handleTransition(transition)}
                        disabled={processing}
                      >
                        {processing ? "Processing..." : `Mark ${getStatusLabel(transition)}`}
                      </Button>
                    );
                  })
                ) : (
                  <>
                    {booking.status === BOOKING_STATUS.Pending && (
                      <>
                        <Button className="w-full" onClick={() => handleTransition(BOOKING_STATUS.Confirmed)}>Confirm Booking</Button>
                        <div className="space-y-2">
                          <Label htmlFor="reason">Cancellation Reason</Label>
                          <Textarea id="reason" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Optional reason..." />
                        </div>
                        <Button variant="destructive" className="w-full" onClick={() => handleTransition(BOOKING_STATUS.Cancelled)}>Cancel Booking</Button>
                      </>
                    )}
                    {booking.status === BOOKING_STATUS.Confirmed && (
                      <>
                        <Button className="w-full" onClick={() => handleTransition(BOOKING_STATUS.CheckedIn)}>Check In</Button>
                        <div className="space-y-2">
                          <Label htmlFor="reason">Cancellation Reason</Label>
                          <Textarea id="reason" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Optional reason..." />
                        </div>
                        <Button variant="destructive" className="w-full" onClick={() => handleTransition(BOOKING_STATUS.Cancelled)}>Cancel Booking</Button>
                      </>
                    )}
                    {booking.status === BOOKING_STATUS.CheckedIn && (
                      <Button className="w-full" onClick={() => handleTransition(BOOKING_STATUS.CheckedOut)}>Check Out</Button>
                    )}
                    {[BOOKING_STATUS.CheckedOut, BOOKING_STATUS.Cancelled].includes(booking.status as any) && (
                      <p className="text-sm text-muted-foreground text-center">This booking is in a terminal state.</p>
                    )}
                  </>
                )}

                {booking.cancellation_reason && (
                  <div className="border-t pt-3">
                    <Label className="text-xs text-muted-foreground">Cancellation Reason</Label>
                    <p className="text-sm mt-1 text-foreground">{booking.cancellation_reason}</p>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center gap-2">
                  <DollarSign className="h-4 w-4 text-muted-foreground" />
                  Pricing
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {booking.payment_status && (
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">Payment</span>
                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${PAYMENT_STATUS_BADGE[booking.payment_status] || ""}`}>
                      {booking.payment_status.charAt(0).toUpperCase() + booking.payment_status.slice(1)}
                    </span>
                  </div>
                )}
                <Separator />
                <div className="space-y-1 text-sm">
                  {booking.nightly_rate && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">{booking.currency || "$"} {Number(booking.nightly_rate).toFixed(2)} x {totalNights} night{totalNights !== 1 ? "s" : ""}</span>
                      <span>{booking.currency || "$"} {(Number(booking.nightly_rate) * totalNights).toFixed(2)}</span>
                    </div>
                  )}
                  {booking.cleaning_fee && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Cleaning Fee</span>
                      <span>{booking.currency || "$"} {Number(booking.cleaning_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {booking.service_fee && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Service Fee</span>
                      <span>{booking.currency || "$"} {Number(booking.service_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {booking.damage_deposit && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Damage Deposit</span>
                      <span>{booking.currency || "$"} {Number(booking.damage_deposit).toFixed(2)}</span>
                    </div>
                  )}
                  <Separator />
                  <div className="flex justify-between font-medium text-foreground">
                    <span>Total</span>
                    <span>{booking.currency || "$"} {Number(booking.total_amount || 0).toFixed(2)}</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <AlertDialog open={confirmState.open} onOpenChange={(open) => setConfirmState((s) => ({ ...s, open }))}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{confirmState.title}</AlertDialogTitle>
            <AlertDialogDescription>{confirmState.description}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => setReason("")}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmState.action}
              className={confirmState.destructive ? "bg-destructive text-destructive-foreground hover:bg-destructive/90" : ""}
            >
              {confirmState.buttonLabel}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AuthenticatedLayout>
  );
}
