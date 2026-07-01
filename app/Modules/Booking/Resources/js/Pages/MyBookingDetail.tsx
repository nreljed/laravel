import { useState } from "react";
import { usePage, Link } from "@inertiajs/react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import { Avatar, AvatarFallback } from "@/Components/ui/avatar";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { PricingBreakdown, type PricingData } from "../components/guest/PricingBreakdown";
import { BookingTimeline } from "../components/guest/BookingTimeline";
import { BOOKING_STATUS, STATUS_LABELS } from "../lib/booking-status";
import {
  AlertCircle,
  CheckCircle2,
  Users,
  AlertTriangle,
  Pencil,
  Phone,
  Mail,
  User,
  MessageSquare,
  Loader2,
  CalendarDays,
  ExternalLink,
  ArrowLeft,
  Globe,
} from "lucide-react";

interface HostPhoneEntry {
  label: string;
  phone: string;
}

interface HostSocialEntry {
  social_title: string;
  social_value: string;
}

interface MyBookingDetailData {
  id: number;
  status: string;
  status_label?: string;
  status_badge_class?: string;
  property_id: number;
  property_title: string;
  property_thumbnail?: string | null;
  property_url?: string | null;
  guest_name: string;
  guest_email: string;
  guest_phone?: string;
  check_in_date: string;
  check_out_date: string;
  check_in_time: string;
  check_out_time: string;
  timezone: string;
  guests: number;
  has_pets: boolean;
  special_requests?: string;
  total_price: number;
  nightly_rate: number;
  cleaning_fee: number;
  service_fee: number;
  cancellation_policy: string;
  created_at: string;
  unit_id?: string;
  unit_name?: string;
  refund_amount: number;
  refund_percent?: number | null;
  refund_policy_applied?: string;
  host_name?: string;
  host_phone?: string;
  host_phone_numbers?: HostPhoneEntry[];
  host_email?: string;
  host_avatar?: string;
  host_bio?: string;
  host_social_media?: HostSocialEntry[];
  pricing?: PricingData | null;
  refund_preview?: {
    policy_applied: string;
    refund_percent: number;
    refund_amount: number;
    reason?: string;
  } | null;
}

const MyBookingDetail = () => {
  const pageProps = usePage<{ booking: MyBookingDetailData | null }>().props;
  const { booking } = pageProps;
  const [cancelling, setCancelling] = useState(false);
  const [showCancelConfirm, setShowCancelConfirm] = useState(false);
  const [modifying, setModifying] = useState(false);
  const [showModifyForm, setShowModifyForm] = useState(false);
  const [modError, setModError] = useState("");
  const [modSuccess, setModSuccess] = useState("");
  const [modCheckIn, setModCheckIn] = useState(booking?.check_in_date || "");
  const [modCheckOut, setModCheckOut] = useState(booking?.check_out_date || "");
  const [modGuests, setModGuests] = useState(booking?.guests || 1);
  const [modNotes, setModNotes] = useState("");

  if (!booking) {
    return (
      <AuthenticatedLayout header="Booking Not Found">
        <Card className="w-full max-w-md mx-auto mt-8">
          <CardHeader>
            <div className="flex items-center gap-2 text-destructive">
              <AlertCircle className="h-5 w-5" />
              <CardTitle className="text-lg">Booking Not Found</CardTitle>
            </div>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground text-sm">This booking could not be found.</p>
          </CardContent>
        </Card>
      </AuthenticatedLayout>
    );
  }

  const checkOut = new Date(booking.check_out_date);
  const checkIn = new Date(booking.check_in_date);
  const nights = Math.max(1, (checkOut.getTime() - checkIn.getTime()) / (1000 * 60 * 60 * 24));

  const globalProps = pageProps as Record<string, unknown>;
  const allowedCancelStatuses = (
    globalProps.booking_global_settings as Record<string, unknown> | undefined
  )?.allow_guest_cancellation_statuses as string[] | undefined;
  const canManage = allowedCancelStatuses
    ? allowedCancelStatuses.includes(booking.status)
    : [BOOKING_STATUS.Pending, BOOKING_STATUS.Confirmed].includes(booking.status);
  const allowedModifyStatuses = (
    globalProps.booking_global_settings as Record<string, unknown> | undefined
  )?.allow_guest_modification_statuses as string[] | undefined;
  const canModify = allowedModifyStatuses
    ? allowedModifyStatuses.includes(booking.status)
    : [BOOKING_STATUS.Pending, BOOKING_STATUS.Confirmed, BOOKING_STATUS.CheckedIn].includes(booking.status);

  const handleCancel = async () => {
    setShowCancelConfirm(false);
    setCancelling(true);
    try {
      const params = new URLSearchParams({ cancelled_by: "guest" });
      const res = await fetch(route("dashboard.my-bookings.cancel", booking.id), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params,
      });
      if (res.ok) {
        toast.success("Booking cancelled successfully.");
        setTimeout(() => location.reload(), 1000);
      } else {
        toast.error("Failed to cancel booking.");
      }
    } catch {
      toast.error("Network error. Please try again.");
    } finally {
      setCancelling(false);
    }
  };

  const handleModify = async () => {
    if (modifying) return;
    if (!modCheckIn || !modCheckOut) { toast.error("Please provide both check-in and check-out dates."); return; }
    if (new Date(modCheckOut) <= new Date(modCheckIn)) { toast.error("Check-out must be after check-in."); return; }
    setModError("");
    setModifying(true);
    try {
      const formData = new FormData();
      formData.append("check_in_date", modCheckIn);
      formData.append("check_out_date", modCheckOut);
      formData.append("guests", String(modGuests));
      formData.append("notes", modNotes);
      const res = await fetch(route("dashboard.my-bookings.modify", booking.id), { method: "POST", body: formData });
      const data = await res.json();
      if (data.success) {
        setModSuccess(data.message);
        setTimeout(() => { if (data.redirect_url) location.assign(data.redirect_url); else { setModSuccess(""); setShowModifyForm(false); } }, 2000);
      } else { setModError(data.error || "Failed to submit modification request."); }
    } catch { setModError("Network error. Please try again."); }
    finally { setModifying(false); }
  };

  return (
    <AuthenticatedLayout header={`Booking #${booking.id}`}>
      <div className="mx-auto max-w-2xl space-y-5">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem><BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbLink href={route("dashboard.my-bookings.index")}>My Bookings</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>Booking #{booking.id}</BreadcrumbPage></BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <Card>
          <CardHeader className="border-b pb-3">
            <div className="flex items-start justify-between">
              <div className="min-w-0">
                <p className="text-muted-foreground text-xs">Booking #{booking.id}</p>
                <CardTitle className="mt-0.5 text-xl leading-tight flex items-center gap-2">
                  {booking.property_thumbnail ? (
                    <img src={booking.property_thumbnail} alt="" className="h-8 w-8 rounded object-cover shrink-0" />
                  ) : <CalendarDays className="h-5 w-5 text-foreground shrink-0" />}
                  {booking.property_url ? (
                    <a href={booking.property_url} target="_blank" rel="noopener noreferrer" className="hover:underline">{booking.property_title}</a>
                  ) : booking.property_title}
                </CardTitle>
              </div>
              <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium shrink-0 ml-3 ${booking.status_badge_class || ""}`}>
                {booking.status_label || STATUS_LABELS[booking.status] || booking.status}
              </span>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <h4 className="text-sm font-semibold text-foreground">Booking Information</h4>

            <BookingTimeline
              checkInDate={booking.check_in_date}
              checkOutDate={booking.check_out_date}
              checkInTime={booking.check_in_time}
              checkOutTime={booking.check_out_time}
              timezone={booking.timezone}
              nights={nights}
            />

            <div className="flex items-center gap-2.5">
              <Users className="text-muted-foreground h-4 w-4 shrink-0" />
              <span className="text-muted-foreground text-sm">Guests:</span>
              <span className="text-sm font-medium">{booking.guests}</span>
              {booking.has_pets && <><span className="text-muted-foreground text-xs">|</span><span className="text-xs text-muted-foreground">Pets: Yes</span></>}
              {booking.unit_name && <><span className="text-muted-foreground text-xs">|</span><span className="text-xs text-muted-foreground">Unit: {booking.unit_name}</span></>}
            </div>

            <PricingBreakdown price={booking.pricing} />
            <div className="space-y-3">
              <h4 className="text-sm font-semibold text-foreground">Guest Information</h4>
              <div className="space-y-2">
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-center gap-2 text-muted-foreground text-sm shrink-0"><User className="h-4 w-4" /><span>Name:</span></div>
                  <span className="text-sm font-medium text-right">{booking.guest_name}</span>
                </div>
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-center gap-2 text-muted-foreground text-sm shrink-0"><Mail className="h-4 w-4" /><span>Email:</span></div>
                  <span className="text-sm font-medium text-right">{booking.guest_email}</span>
                </div>
                {booking.guest_phone && (
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2 text-muted-foreground text-sm shrink-0"><Phone className="h-4 w-4" /><span>Phone:</span></div>
                    <span className="text-sm font-medium text-right">{booking.guest_phone}</span>
                  </div>
                )}
              </div>
              {booking.special_requests && (
                <div className="flex items-start gap-2.5 pt-1">
                  <MessageSquare className="text-muted-foreground h-4 w-4 shrink-0 mt-0.5" />
                  <div><p className="text-muted-foreground text-xs font-medium mb-0.5">Special Requests</p><p className="text-sm text-foreground">{booking.special_requests}</p></div>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {booking.host_name && (
          <Card>
            <CardHeader className="border-b pb-3">
              <div className="flex items-center gap-2"><User className="h-4 w-4 text-primary" /><CardTitle className="text-base">Contact Host</CardTitle></div>
              <CardDescription>Need help? Reach out to your host directly.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center gap-3">
                <Avatar className="h-10 w-10">
                  {booking.host_avatar ? (
                    <img src={booking.host_avatar} alt="" className="h-10 w-10 rounded-full object-cover" />
                  ) : null}
                  <AvatarFallback className="bg-muted text-xs">
                    {booking.host_name!.split(" ").map((n) => n[0]).join("").toUpperCase().slice(0, 2)}
                  </AvatarFallback>
                </Avatar>
                <div>
                  <p className="font-medium text-foreground">{booking.host_name}</p>
                  {booking.host_bio && <p className="text-xs text-muted-foreground line-clamp-2">{booking.host_bio}</p>}
                </div>
              </div>
              <div className="space-y-1.5">
                {(booking.host_phone_numbers && booking.host_phone_numbers.length > 0
                  ? booking.host_phone_numbers
                  : booking.host_phone ? [{ label: "Phone", phone: booking.host_phone }] : []
                ).map((entry: HostPhoneEntry, idx: number) => (
                  <div key={idx} className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2 text-muted-foreground text-sm shrink-0"><Phone className="h-4 w-4" /><span>{entry.label}:</span></div>
                    <a href={`tel:${entry.phone}`} className="text-sm font-medium text-primary hover:underline text-right">{entry.phone}</a>
                  </div>
                ))}
                {(booking.host_email || (booking as any).host_email) && (
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2 text-muted-foreground text-sm shrink-0"><Mail className="h-4 w-4" /><span>Email:</span></div>
                    <a href={`mailto:${booking.host_email}`} className="text-sm font-medium text-primary hover:underline text-right">{booking.host_email}</a>
                  </div>
                )}
                {(booking.host_social_media ?? []).length > 0 && (
                  <div className="flex flex-wrap justify-end gap-2 pt-1">
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

        {booking.property_url && (
          <Card>
            <CardHeader className="border-b pb-3">
              <div className="flex items-center gap-2"><ExternalLink className="h-4 w-4 text-primary" /><CardTitle className="text-base">Property</CardTitle></div>
              <CardDescription>View this property on the site</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {booking.property_thumbnail && (
                <a href={booking.property_url} target="_blank" rel="noopener noreferrer">
                  <img src={booking.property_thumbnail} alt={booking.property_title} className="w-full h-40 object-cover rounded-lg border" />
                </a>
              )}
              <Button variant="outline" className="w-full" asChild>
                <a href={booking.property_url} target="_blank" rel="noopener noreferrer"><ExternalLink className="mr-2 h-4 w-4" /> View Property</a>
              </Button>
            </CardContent>
          </Card>
        )}

        {canModify && (
          <Card>
            <CardHeader className="border-b pb-3">
              <div className="flex items-center gap-2"><Pencil className="h-4 w-4 text-primary" /><CardTitle className="text-base">Request Modification</CardTitle></div>
              <CardDescription>Request changes to dates or number of guests.</CardDescription>
            </CardHeader>
            <CardContent>
              {!showModifyForm ? (
                <Button variant="outline" size="sm" onClick={() => { setShowModifyForm(true); setModSuccess(""); }}>Request Modification</Button>
              ) : null}
              {showModifyForm && !modSuccess && (
                <div className="space-y-3 pt-1">
                  <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1"><Label className="text-xs">Check-in</Label><Input type="date" value={modCheckIn} disabled={modifying} onChange={(e) => setModCheckIn(e.target.value)} /></div>
                    <div className="space-y-1"><Label className="text-xs">Check-out</Label><Input type="date" value={modCheckOut} disabled={modifying} onChange={(e) => setModCheckOut(e.target.value)} /></div>
                  </div>
                  <div className="space-y-1"><Label className="text-xs">Guests</Label><Input type="number" min={1} value={modGuests} disabled={modifying} onChange={(e) => setModGuests(Number(e.target.value))} /></div>
                  <div className="space-y-1"><Label className="text-xs">Notes</Label><Textarea placeholder="Describe the changes..." value={modNotes} disabled={modifying} onChange={(e) => setModNotes(e.target.value)} rows={3} /></div>
                  {modError && <p className="text-xs font-medium text-destructive">{modError}</p>}
                  <div className="flex gap-2">
                    <Button size="sm" disabled={modifying} onClick={handleModify}>{modifying ? <><Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> Submitting...</> : "Submit Request"}</Button>
                    <Button variant="ghost" size="sm" disabled={modifying} onClick={() => setShowModifyForm(false)}>Cancel</Button>
                  </div>
                </div>
              )}
              {modSuccess && (
                <div className="flex items-start gap-2.5 rounded-md border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950/50">
                  <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-600 dark:text-green-400" />
                  <div><p className="text-sm font-medium text-green-800 dark:text-green-300">{modSuccess}</p><p className="mt-0.5 text-xs text-green-600 dark:text-green-400">Reloading your booking details...</p></div>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {canManage && (
          <Card className="border-destructive/30">
            <CardHeader className="border-b pb-3">
              <div className="flex items-center gap-2"><AlertTriangle className="h-4 w-4 text-destructive" /><CardTitle className="text-base">Cancel Booking</CardTitle></div>
              <CardDescription>
                {booking.cancellation_policy === "flexible"
                  ? "Free cancellation up to 24 hours before check-in."
                  : booking.cancellation_policy === "moderate"
                    ? "50% refund up to 5 days before check-in."
                    : booking.cancellation_policy === "limited"
                      ? "Full refund up to 14 days before check-in. 50% refund 7–14 days before."
                      : booking.cancellation_policy === "firm"
                        ? "Full refund up to 30 days before check-in. 50% refund 7–30 days before."
                        : booking.cancellation_policy === "long_term"
                          ? "Long-term stay — first 30 nights non-refundable."
                          : "Please review the cancellation policy before cancelling."}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Button variant="destructive" className="w-full" disabled={cancelling} onClick={() => setShowCancelConfirm(true)}>
                {cancelling ? <><Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> Cancelling...</> : "Cancel Booking"}
              </Button>
              <AlertDialog open={showCancelConfirm} onOpenChange={setShowCancelConfirm}>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>Cancel this booking?</AlertDialogTitle>
                    <AlertDialogDescription>
                      {booking.refund_preview ? (
                        <div className="space-y-2">
                          <p>This action cannot be undone. Your booking will be cancelled.</p>
                          <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-sm space-y-1">
                            <p className="font-medium">Refund Preview</p>
                            <p>Policy: <span className="font-medium capitalize">{booking.refund_preview.policy_applied.replaceAll("_", " ")}</span></p>
                            <p>
                              Refund: <span className="font-medium">{booking.refund_preview.refund_percent}%</span>
                              {booking.refund_preview.refund_amount > 0 && (
                                <> — <span className="font-medium text-green-600 dark:text-green-400">${booking.refund_preview.refund_amount.toFixed(2)}</span></>
                              )}
                            </p>
                            {booking.refund_preview.reason && <p className="text-xs text-muted-foreground">{booking.refund_preview.reason}</p>}
                          </div>
                        </div>
                      ) : (
                        <p>This action cannot be undone. Your booking will be cancelled.</p>
                      )}
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>Keep Booking</AlertDialogCancel>
                    <AlertDialogAction onClick={handleCancel} className="bg-destructive hover:bg-destructive/90">Yes, Cancel Booking</AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </CardContent>
          </Card>
        )}
      </div>
    </AuthenticatedLayout>
  );
};

export default MyBookingDetail;
