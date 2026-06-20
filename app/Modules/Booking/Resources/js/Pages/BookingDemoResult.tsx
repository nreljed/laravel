import { useState, useEffect } from "react";
import { Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import {
  ArrowLeft,
  Building2,
  User,
  CalendarDays,
  DollarSign,
  ExternalLink,
  CheckCircle2,
  XCircle,
  Home,
  Mail,
  Phone,
  PawPrint,
  MessageSquare,
  Beaker,
  Key,
  Loader2,
  Copy,
  RotateCcw,
  Ban,
  RefreshCw,
  Activity,
} from "lucide-react";
import { BOOKING_STATUS } from "../lib/booking-status";

interface EmailStatusItem {
  key: string;
  label: string;
  sent: boolean;
  sent_at: string | null;
}

interface BookingData {
  id: number;
  property_id?: number;
  property_title?: string;
  guest_name?: string;
  guest_email?: string;
  guest_phone?: string;
  guests?: number;
  has_pets?: boolean;
  has_unit?: boolean;
  unit_name?: string;
  check_in?: string;
  check_out?: string;
  nights?: number;
  created_at?: string;
  status?: string;
  status_label?: string;
  status_badge_class?: string;
  special_requests?: string;
  title?: string;
  price_breakdown?: Record<string, unknown>;
  magic_link?: string;
  raw_token?: string;
  token_table_exists?: boolean;
  email_status?: EmailStatusItem[];
  allowed_transitions?: string[];
}

interface PropertyData {
  thumbnail_url?: string;
  title?: string;
  city?: string;
  booking_settings?: { enabled?: boolean };
}

interface BookingDemoResultProps {
  booking: BookingData;
  property: PropertyData;
  admin_url: string;
}

export default function BookingDemoResult({
  booking,
  property,
  admin_url,
}: BookingDemoResultProps) {
  const price = (booking.price_breakdown || {}) as Record<string, unknown>;
  const [magicLink, setMagicLink] = useState(booking.magic_link || "");
  const [rawToken, setRawToken] = useState(booking.raw_token || "");
  const [tokenStatus, setTokenStatus] = useState<Array<Record<string, unknown>> | null>(null);
  const [emailStatus, setEmailStatus] = useState<EmailStatusItem[] | null>(
    booking.email_status || null,
  );
  const [verification, setVerification] = useState<null | {
    checks?: Array<{ key: string; label: string; passed: boolean; detail: string }>;
    summary?: string;
    all_passed?: boolean;
  }>(null);
  const [verifLoading, setVerifLoading] = useState(false);
  const [tokenLoading, setTokenLoading] = useState(false);
  const [verifyLoading, setVerifyLoading] = useState(false);
  const [pipelineTrace, setPipelineTrace] = useState<Array<{
    at: string;
    stage: string;
    message: string;
    context?: Record<string, unknown>;
  }> | null>(null);
  const [traceLoading, setTraceLoading] = useState(false);

  const fetchTokenStatus = async () => {
    setTokenLoading(true);
    try {
      const r = await fetch("/dashboard/bookings/demo/token-status/" + booking.id);
      if (r.ok) {
        const data = await r.json();
        setTokenStatus(data.tokens || []);
      }
    } catch {
      /* ignore */
    }
    setTokenLoading(false);
  };

  const fetchEmailStatus = async () => {
    try {
      const r = await fetch("/dashboard/bookings/demo/email-status/" + booking.id);
      if (r.ok) {
        const data = await r.json();
        setEmailStatus(data.emails || []);
      }
    } catch {
      /* ignore */
    }
  };

  const fetchVerification = async () => {
    setVerifyLoading(true);
    try {
      const r = await fetch("/dashboard/bookings/demo/verify/" + booking.id);
      if (r.ok) setVerification(await r.json());
    } catch {
      /* ignore */
    }
    setVerifyLoading(false);
  };

  const fetchPipelineTrace = async () => {
    setTraceLoading(true);
    try {
      const r = await fetch("/dashboard/bookings/demo/pipeline-trace/" + booking.id);
      if (r.ok) {
        const data = await r.json();
        setPipelineTrace(data.trace || []);
      }
    } catch {
      /* ignore */
    }
    setTraceLoading(false);
  };

  useEffect(() => {
    if (!tokenStatus) fetchTokenStatus();
    if (!emailStatus) fetchEmailStatus();
    if (!verification) fetchVerification();
    if (!pipelineTrace) fetchPipelineTrace();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const regenerateMagicLink = async () => {
    setVerifLoading(true);
    try {
      const r = await fetch("/dashboard/bookings/demo/generate-magic-link/" + booking.id, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ _wpnonce: "" }),
      });
      if (r.ok) {
        const data = await r.json();
        setMagicLink(data.magic_link || "");
        setRawToken(data.raw_token || "");
      }
    } catch {
      /* ignore */
    }
    setVerifLoading(false);
  };

  return (
    <AuthenticatedLayout header="Demo Result">
      <div className="space-y-6 max-w-5xl">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Link href="/dashboard/bookings/demo">
              <Button variant="ghost" size="icon">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <Beaker className="h-6 w-6 text-primary" />
            <div>
              <h1 className="text-2xl font-bold text-foreground">Demo Result</h1>
              <p className="text-sm text-muted-foreground">
                Booking #{booking.id} — Full integration report
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button asChild variant="default">
              <Link href={admin_url}>
                <ExternalLink className="h-4 w-4 mr-1" /> Open in Admin
              </Link>
            </Button>
            <Link href="/dashboard/bookings/demo">
              <Button variant="outline">
                <Beaker className="h-4 w-4 mr-1" /> New Demo
              </Button>
            </Link>
          </div>
        </div>

        <Tabs defaultValue="host" className="w-full">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="host">
              <Building2 className="h-4 w-4 mr-2" />
              Host / Admin
            </TabsTrigger>
            <TabsTrigger value="guest">
              <User className="h-4 w-4 mr-2" />
              Guest / Client
            </TabsTrigger>
            <TabsTrigger value="magic">
              <Key className="h-4 w-4 mr-2" />
              Magic Link & Flow
            </TabsTrigger>
          </TabsList>

          <TabsContent value="host" className="space-y-6 mt-6">
            <Card className="border-primary/20">
              <CardHeader className="pb-3">
                <div className="flex items-center gap-2">
                  <Building2 className="h-5 w-5 text-primary" />
                  <CardTitle>Admin: What the Host Sees</CardTitle>
                </div>
                <CardDescription>
                  This is how the booking appears in the admin dashboard at{" "}
                  <code className="text-xs bg-muted px-1 rounded">{admin_url}</code>
                </CardDescription>
              </CardHeader>
            </Card>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm flex items-center gap-2">
                    <Home className="h-4 w-4" /> Property
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="font-medium">{booking.property_title || "N/A"}</p>
                  <p className="text-sm text-muted-foreground">ID: {booking.property_id}</p>
                  {property?.booking_settings?.enabled && (
                    <Badge
                      variant="outline"
                      className="mt-2 text-[10px] text-green-600 dark:text-green-400"
                    >
                      Booking enabled
                    </Badge>
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm flex items-center gap-2">
                    <User className="h-4 w-4" /> Guest
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="font-medium">{booking.guest_name || "N/A"}</p>
                  <p className="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                    <Mail className="h-3 w-3" /> {booking.guest_email}
                  </p>
                  {booking.guest_phone && (
                    <p className="text-xs text-muted-foreground flex items-center gap-1">
                      <Phone className="h-3 w-3" /> {booking.guest_phone}
                    </p>
                  )}
                  <p className="text-xs text-muted-foreground mt-1">
                    {booking.guests} guest(s)
                    {booking.has_pets ? ", with pets" : ""}
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm flex items-center gap-2">
                    <CalendarDays className="h-4 w-4" /> Stay Details
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="font-medium">
                    {booking.check_in} → {booking.check_out}
                  </p>
                  <p className="text-sm text-muted-foreground">{booking.nights} night(s)</p>
                  <p className="text-xs text-muted-foreground mt-1">
                    Created: {booking.created_at}
                  </p>
                </CardContent>
              </Card>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <DollarSign className="h-4 w-4" /> Price Breakdown
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between text-sm">
                    <span>
                      ${Number(price.base_nightly_rate || 0).toFixed(2)} × {price.nights || 0}{" "}
                      nights
                    </span>
                    <span>${Number(price.subtotal || 0).toFixed(2)}</span>
                  </div>
                  {Number(price.length_of_stay_discount_amount || 0) > 0 && (
                    <div className="flex justify-between text-sm text-green-600 dark:text-green-400">
                      <span>LOS discount ({String(price.length_of_stay_discount_percent)}%)</span>
                      <span>-${Number(price.length_of_stay_discount_amount).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(price.promo_discount_amount || 0) > 0 && (
                    <div className="flex justify-between text-sm text-purple-600 dark:text-purple-400">
                      <span>{price.promo_discount_label || "Promo discount"}</span>
                      <span>-${Number(price.promo_discount_amount).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(price.extra_guest_charge || 0) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span>Extra guest charge</span>
                      <span>${Number(price.extra_guest_charge).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(price.cleaning_fee || 0) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span>Cleaning fee</span>
                      <span>${Number(price.cleaning_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(price.service_fee || 0) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span>Service fee</span>
                      <span>${Number(price.service_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(price.pet_fee || 0) > 0 && (
                    <div className="flex justify-between text-sm">
                      <span>Pet fee</span>
                      <span>${Number(price.pet_fee).toFixed(2)}</span>
                    </div>
                  )}
                  {Number(price.damage_deposit || 0) > 0 && (
                    <div className="flex justify-between text-sm text-amber-600 dark:text-amber-400">
                      <span>Damage deposit</span>
                      <span>${Number(price.damage_deposit).toFixed(2)}</span>
                    </div>
                  )}
                  <hr />
                  <div className="flex justify-between font-semibold text-base">
                    <span>Total</span>
                    <span>${Number(price.total || 0).toFixed(2)}</span>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CalendarDays className="h-4 w-4" /> Status & Actions
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center gap-3">
                    <span className="text-sm text-muted-foreground">Current status:</span>
                    <span
                      className={`inline-flex px-3 py-1 rounded-full text-sm font-medium ${booking.status_badge_class || ""}`}
                    >
                      {booking.status_label}
                    </span>
                  </div>

                  {booking.special_requests && (
                    <div>
                      <p className="text-sm font-medium flex items-center gap-1 mb-1">
                        <MessageSquare className="h-3 w-3" /> Special Requests
                      </p>
                      <p className="text-sm text-muted-foreground whitespace-pre-wrap bg-muted/50 rounded p-2">
                        {booking.special_requests}
                      </p>
                    </div>
                  )}

                  <ul className="text-xs text-muted-foreground space-y-1 list-disc list-inside">
                    <li>
                      Host can transition status: pending → confirmed → checked_in → checked_out
                    </li>
                    <li>Host can cancel booking at any time</li>
                    <li>
                      Booking appears in{" "}
                      <Link
                        href="/dashboard/manage-bookings/calendar"
                        className="text-primary underline"
                      >
                        Calendar view
                      </Link>
                    </li>
                    <li>Booking exportable via CSV</li>
                  </ul>
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="guest" className="space-y-6 mt-6">
            <Card className="border-primary/20">
              <CardHeader className="pb-3">
                <div className="flex items-center gap-2">
                  <User className="h-5 w-5 text-primary" />
                  <CardTitle>Guest / Client: What the Customer Sees</CardTitle>
                </div>
                <CardDescription>
                  This simulates how the booking confirmation appears to a guest after booking via
                  the frontend widget.
                </CardDescription>
              </CardHeader>
            </Card>

            <div className="max-w-lg mx-auto">
              <Card className="border-green-500/30">
                <CardHeader className="text-center pb-3">
                  <CheckCircle2 className="h-12 w-12 mx-auto mb-2 text-green-500 dark:text-green-400" />
                  <CardTitle className="text-xl">Booking Confirmed!</CardTitle>
                  <CardDescription>Booking #{booking.id}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {property && (
                    <div className="flex items-center gap-4 p-3 bg-muted rounded-lg">
                      {property.thumbnail_url ? (
                        <img
                          src={property.thumbnail_url}
                          alt={property.title}
                          loading="lazy"
                          className="w-16 h-16 rounded-lg object-cover"
                        />
                      ) : (
                        <div className="w-16 h-16 rounded-lg bg-muted-foreground/10 flex items-center justify-center">
                          <Building2 className="h-8 w-8 text-muted-foreground/50" />
                        </div>
                      )}
                      <div>
                        <p className="font-semibold">{property.title}</p>
                        {property.city && (
                          <p className="text-sm text-muted-foreground">{property.city}</p>
                        )}
                      </div>
                    </div>
                  )}

                  <div className="space-y-2 text-sm">
                    <div className="flex items-center gap-2">
                      <User className="h-4 w-4 text-muted-foreground" />
                      <span>{booking.guest_name}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <CalendarDays className="h-4 w-4 text-muted-foreground" />
                      <span>
                        {booking.check_in} → {booking.check_out}
                      </span>
                      <Badge variant="secondary" className="text-[10px]">
                        {booking.nights} night(s)
                      </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                      <svg
                        className="h-4 w-4 text-muted-foreground"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                      >
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                      </svg>
                      <span>{booking.guests} guest(s)</span>
                      {booking.has_pets && (
                        <Badge variant="outline" className="text-[10px] flex items-center gap-1">
                          <PawPrint className="h-3 w-3" /> Pets
                        </Badge>
                      )}
                    </div>
                  </div>

                  {booking.special_requests && (
                    <div className="text-sm p-3 bg-muted rounded-lg">
                      <p className="font-medium text-xs text-muted-foreground mb-1">
                        Special requests:
                      </p>
                      <p className="text-sm">{booking.special_requests}</p>
                    </div>
                  )}

                  <div className="border-t pt-3">
                    <div className="flex justify-between font-semibold text-lg">
                      <span>Total</span>
                      <span>${Number(price.total || 0).toFixed(2)}</span>
                    </div>
                    {booking.status === BOOKING_STATUS.Pending && (
                      <p className="text-xs text-muted-foreground mt-2 text-center">
                        Your booking is pending host confirmation. You will be notified once
                        confirmed.
                      </p>
                    )}
                  </div>

                  <div className="text-center">
                    <Button asChild variant="outline" size="sm">
                      <a href={admin_url}>View Booking Details</a>
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>
          <TabsContent value="magic" className="space-y-6 mt-6">
            <Card className="border-primary/20">
              <CardHeader className="pb-3">
                <div className="flex items-center gap-2">
                  <Key className="h-5 w-5 text-primary" />
                  <CardTitle>Guest Flow Simulation</CardTitle>
                </div>
                <CardDescription>
                  End-to-end testing of the guest experience: magic link, cancellation, email
                  delivery
                </CardDescription>
              </CardHeader>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm flex items-center gap-2">
                  <ExternalLink className="h-4 w-4" /> Magic Link URL
                </CardTitle>
                <CardDescription>
                  Sent to guest via email. Click to open the guest management page.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {magicLink ? (
                  <>
                    <div className="flex gap-2">
                      <code className="flex-1 text-xs bg-muted p-2 rounded border truncate">
                        {magicLink}
                      </code>
                      <Button
                        variant="outline"
                        size="sm"
                        className="shrink-0"
                        onClick={() => {
                          navigator.clipboard.writeText(magicLink);
                        }}
                      >
                        <Copy className="h-3 w-3" />
                      </Button>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                      <div className="bg-muted/50 rounded p-2">
                        <span className="text-muted-foreground block">Token (raw)</span>
                        <span className="font-mono font-medium text-[10px] break-all">
                          {rawToken ? rawToken.slice(0, 24) + "…" : "—"}
                        </span>
                      </div>
                      <div className="bg-muted/50 rounded p-2">
                        <span className="text-muted-foreground block">Guest email</span>
                        <span className="font-mono font-medium">{booking.guest_email || "—"}</span>
                      </div>
                      <div className="bg-muted/50 rounded p-2">
                        <span className="text-muted-foreground block">Token table</span>
                        <span>{booking.token_table_exists ? "✅ exists" : "❌ missing"}</span>
                      </div>
                      <div className="bg-muted/50 rounded p-2">
                        <span className="text-muted-foreground block">Expiry</span>
                        <span>30 days</span>
                      </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button size="sm" onClick={() => window.open(magicLink, "_blank")}>
                        <ExternalLink className="h-3 w-3 mr-1" /> Open Guest View
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={regenerateMagicLink}
                        disabled={verifLoading}
                      >
                        <RotateCcw className="h-3 w-3 mr-1" /> Regenerate Link
                      </Button>
                      <Button variant="outline" size="sm" disabled>
                        <Ban className="h-3 w-3 mr-1" /> Test Cancel Flow
                      </Button>
                      <Button variant="outline" size="sm" disabled>
                        <Key className="h-3 w-3 mr-1" /> Test Registration
                      </Button>
                    </div>
                  </>
                ) : (
                  <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span>Generating magic link...</span>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={regenerateMagicLink}
                      className="ml-2"
                    >
                      Generate Now
                    </Button>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm flex items-center gap-2">
                  <Key className="h-4 w-4" /> Token Table Inspection
                </CardTitle>
                <CardDescription>
                  Raw entries from the booking tokens table
                </CardDescription>
              </CardHeader>
              <CardContent>
                {tokenLoading ? (
                  <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" /> Loading tokens...
                  </div>
                ) : tokenStatus && tokenStatus.length > 0 ? (
                  <div className="space-y-2">
                    {tokenStatus.map((t: Record<string, unknown>, i: number) => (
                      <div
                        key={i}
                        className="bg-muted/30 rounded p-3 font-mono text-xs space-y-1 border"
                      >
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Token #{String(t.id)}</span>
                          <Badge
                            variant={t.is_expired ? "destructive" : "default"}
                            className="text-[10px]"
                          >
                            {t.is_expired ? "Expired" : "Active"}
                          </Badge>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Hash (truncated):</span>
                          <span>{(t.token_hash_short as string) || "—"}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Email:</span>
                          <span>{(t.guest_email as string) || "—"}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Created:</span>
                          <span>{(t.created_at as string) || "—"}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Expires:</span>
                          <span>{(t.expires_at as string) || "—"}</span>
                        </div>
                        {!t.is_expired && typeof t.hours_remaining === "number" && (
                          <div className="flex justify-between text-blue-500">
                            <span>Remaining:</span>
                            <span>{t.hours_remaining}h</span>
                          </div>
                        )}
                      </div>
                    ))}
                    <Button variant="outline" size="sm" onClick={fetchTokenStatus}>
                      <RefreshCw className="h-3 w-3 mr-1" /> Refresh
                    </Button>
                  </div>
                ) : (
                  <div className="py-4 text-center text-sm text-muted-foreground">
                    No tokens found for this booking.
                    <Button variant="link" size="sm" onClick={regenerateMagicLink}>
                      Generate one
                    </Button>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm flex items-center gap-2">
                  <Mail className="h-4 w-4" /> Email Delivery Status
                </CardTitle>
                <CardDescription>Transactional emails for this booking lifecycle</CardDescription>
              </CardHeader>
              <CardContent>
                {emailStatus ? (
                  <div className="space-y-2">
                    <div className="flex items-center justify-between p-3 bg-muted/50 rounded-lg border-l-4 border-green-500">
                      <div className="flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-green-500" />
                        <span className="text-sm font-medium">Booking Received (magic link)</span>
                      </div>
                      <Badge variant="outline" className="text-[10px] text-green-600">
                        Sent on creation
                      </Badge>
                    </div>
                    {emailStatus.map((e: EmailStatusItem) => (
                      <div
                        key={e.key}
                        className={`flex items-center justify-between p-3 rounded-lg border-l-4 ${
                          e.sent
                            ? "border-green-500 bg-green-500/5"
                            : "border-muted-foreground/20 bg-muted/20"
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          {e.sent ? (
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                          ) : (
                            <div className="h-4 w-4 rounded-full border-2 border-muted-foreground/30" />
                          )}
                          <div>
                            <span
                              className={`text-sm font-medium ${e.sent ? "" : "text-muted-foreground"}`}
                            >
                              {e.label}
                            </span>
                            {e.sent_at && (
                              <p className="text-[10px] text-muted-foreground font-mono">
                                Sent: {e.sent_at}
                              </p>
                            )}
                          </div>
                        </div>
                        <Badge variant={e.sent ? "default" : "outline"} className="text-[10px]">
                          {e.sent ? "Sent" : "Pending"}
                        </Badge>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span>Loading email status...</span>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm flex items-center gap-2">
                  <RefreshCw className="h-4 w-4" /> Allowed Status Transitions
                </CardTitle>
                <CardDescription>
                  From current status{" "}
                  <Badge variant="secondary" className="text-[10px]">
                    {booking.status_label}
                  </Badge>
                </CardDescription>
              </CardHeader>
              <CardContent>
                {booking.allowed_transitions && booking.allowed_transitions.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {booking.allowed_transitions.map((t: string) => (
                      <Badge key={t} variant="outline" className="text-xs">
                        → {t}
                      </Badge>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">
                    No transitions allowed from this status (terminal: checked_out or cancelled)
                  </p>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <CardTitle className="text-sm flex items-center gap-2">
                    <CheckCircle2 className="h-4 w-4" /> Integration Verification
                  </CardTitle>
                  {verification && (
                    <Badge
                      variant={verification.all_passed ? "default" : "destructive"}
                      className="text-xs"
                    >
                      {verification.summary}
                    </Badge>
                  )}
                </div>
                <CardDescription>Automated checks based on actual booking data</CardDescription>
              </CardHeader>
              <CardContent>
                {verifyLoading ? (
                  <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" /> Running checks...
                  </div>
                ) : verification?.checks ? (
                  <div className="space-y-1">
                    {verification.checks.map(
                      (c: { key: string; label: string; passed: boolean; detail: string }) => (
                        <div
                          key={c.key}
                          className={`flex items-start gap-3 p-2 rounded text-sm ${
                            c.passed ? "bg-green-500/5" : "bg-red-500/5"
                          }`}
                        >
                          {c.passed ? (
                            <CheckCircle2 className="h-4 w-4 text-green-500 shrink-0 mt-0.5" />
                          ) : (
                            <XCircle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                          )}
                          <div className="flex-1 min-w-0">
                            <span
                              className={`font-medium ${c.passed ? "text-green-700 dark:text-green-300" : "text-red-700 dark:text-red-300"}`}
                            >
                              {c.label}
                            </span>
                            {c.detail && (
                              <p className="text-[11px] text-muted-foreground font-mono truncate">
                                {c.detail}
                              </p>
                            )}
                          </div>
                        </div>
                      ),
                    )}
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={fetchVerification}
                      className="mt-2"
                    >
                      <RefreshCw className="h-3 w-3 mr-1" /> Re-run Checks
                    </Button>
                  </div>
                ) : (
                  <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" /> Loading verification...
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <CardTitle className="text-sm flex items-center gap-2">
                    <Activity className="h-4 w-4" /> Pipeline Trace
                  </CardTitle>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={fetchPipelineTrace}
                    disabled={traceLoading}
                  >
                    <RefreshCw className="h-3 w-3 mr-1" /> Refresh
                  </Button>
                </div>
                <CardDescription>
                  Persistent trace of all pipeline stages (status transitions, emails,
                  notifications)
                </CardDescription>
              </CardHeader>
              <CardContent>
                {traceLoading ? (
                  <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" /> Loading trace...
                  </div>
                ) : pipelineTrace && pipelineTrace.length > 0 ? (
                  <div className="space-y-1">
                    {pipelineTrace.map((entry, i) => (
                      <div
                        key={i}
                        className="flex items-start gap-3 p-2 rounded text-sm bg-muted/30 border-l-4 border-blue-400"
                      >
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2">
                            <span className="text-[10px] font-mono text-muted-foreground">
                              {entry.at}
                            </span>
                            <Badge variant="secondary" className="text-[10px] font-mono">
                              {entry.stage}
                            </Badge>
                          </div>
                          <p className="text-xs mt-0.5">{entry.message}</p>
                          {entry.context && Object.keys(entry.context).length > 0 && (
                            <details className="mt-1">
                              <summary className="text-[10px] text-muted-foreground cursor-pointer hover:text-foreground">
                                Context ({Object.keys(entry.context).length} keys)
                              </summary>
                              <pre className="text-[10px] bg-muted/50 p-2 rounded mt-1 overflow-x-auto">
                                {JSON.stringify(entry.context, null, 2)}
                              </pre>
                            </details>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
                    <Activity className="h-4 w-4" /> No pipeline trace entries found. Trigger a
                    status transition to populate the trace.
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  );
}
