import { Link } from "@inertiajs/react";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent } from "@/Components/ui/card";
import { Database, Mail, Bell, BellRing } from "lucide-react";

interface SystemStatusCardsProps {
  notificationTableExists: boolean;
  emailConfigured: boolean;
  fromEmail: string;
}

export function SystemStatusCards({
  notificationTableExists,
  emailConfigured,
  fromEmail,
}: SystemStatusCardsProps) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <Card
        size="sm"
        className={`border-l-4 ${notificationTableExists ? "border-l-green-500" : "border-l-amber-500"}`}
      >
        <CardContent className="space-y-1">
          <div className="flex items-center gap-2">
            <Database className="h-4 w-4 text-muted-foreground" />
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              DB Table
            </span>
          </div>
          <Badge
            variant={notificationTableExists ? "default" : "secondary"}
            className="text-[10px] px-1.5 py-0"
          >
            {notificationTableExists ? "Ready" : "Not Created"}
          </Badge>
        </CardContent>
      </Card>
      <Card
        size="sm"
        className={`border-l-4 ${emailConfigured ? "border-l-green-500" : "border-l-amber-500"}`}
      >
        <CardContent className="space-y-1">
          <div className="flex items-center gap-2">
            <Mail className="h-4 w-4 text-muted-foreground" />
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              Email
            </span>
          </div>
          <Badge
            variant={emailConfigured ? "default" : "secondary"}
            className="text-[10px] px-1.5 py-0"
          >
            {emailConfigured ? "Configured" : "Not Set Up"}
          </Badge>
          {emailConfigured && (
            <p className="text-[10px] text-muted-foreground truncate">{fromEmail}</p>
          )}
        </CardContent>
      </Card>
      <Card size="sm" className="border-l-4 border-l-blue-500">
        <CardContent className="space-y-1">
          <div className="flex items-center gap-2">
            <Bell className="h-4 w-4 text-muted-foreground" />
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              Notifications
            </span>
          </div>
          <p className="text-lg font-semibold text-foreground">
            <Link href={route("dashboard.notifications")} className="hover:underline">
              View &rarr;
            </Link>
          </p>
        </CardContent>
      </Card>
      <Card size="sm" className="border-l-4 border-l-slate-500">
        <CardContent className="space-y-1">
          <div className="flex items-center gap-2">
            <BellRing className="h-4 w-4 text-muted-foreground" />
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              Prefs
            </span>
          </div>
          <p className="text-lg font-semibold text-foreground">
            <Link href={route("dashboard.account")} className="hover:underline">
              Configure &rarr;
            </Link>
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
