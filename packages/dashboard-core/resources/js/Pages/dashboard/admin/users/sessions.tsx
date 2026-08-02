import { Head, Link, router, usePage } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
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
import { ArrowLeft, Smartphone, Monitor, Trash2 } from "lucide-react";
import { useState } from "react";

interface Session {
  id: string;
  ip_address: string;
  user_agent: string;
  last_activity: number;
  is_current: boolean;
}

interface UserBrief {
  id: number;
  name: string;
  email: string;
}

interface PageProps {
  user: UserBrief;
  sessions: Session[];
}

function parseUserAgent(ua: string): { browser: string; os: string; device: string } {
  const isMobile = /Mobile|Android|iPhone|iPad|iPod/i.test(ua);
  let browser = "Unknown";
  let os = "Unknown";

  if (ua.includes("Chrome")) browser = "Chrome";
  else if (ua.includes("Firefox")) browser = "Firefox";
  else if (ua.includes("Safari")) browser = "Safari";
  else if (ua.includes("Edge")) browser = "Edge";

  if (ua.includes("Windows")) os = "Windows";
  else if (ua.includes("Mac")) os = "macOS";
  else if (ua.includes("Linux")) os = "Linux";
  else if (ua.includes("Android")) os = "Android";
  else if (ua.includes("iPhone") || ua.includes("iPad")) os = "iOS";

  return { browser, os, device: isMobile ? "Mobile" : "Desktop" };
}

export default function AdminUserSessions() {
  const { props } = usePage();
  const { user, sessions } = props as unknown as PageProps;
  const [revokeSessionId, setRevokeSessionId] = useState<string | null>(null);

  const handleRevoke = () => {
    if (!revokeSessionId) return;
    router.delete(
      route("dashboard.admin.users.sessions.destroy", { user: user.id, session: revokeSessionId }),
    );
    setRevokeSessionId(null);
  };

  return (
    <AdminLayout title={`Sessions: ${user.name}`}>
      <Head title={`Sessions: ${user.name}`} />

      <div className="mb-4">
        <Link href={route("dashboard.admin.users.edit", { user: user.id })}>
          <Button variant="ghost" size="sm">
            <ArrowLeft className="mr-1 size-4" /> Back to {user.name}
          </Button>
        </Link>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Active Sessions ({sessions.length})</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Device</TableHead>
                <TableHead>Browser / OS</TableHead>
                <TableHead>IP Address</TableHead>
                <TableHead>Last Activity</TableHead>
                <TableHead className="w-24">Action</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sessions.map((session) => {
                const info = parseUserAgent(session.user_agent);
                return (
                  <TableRow key={session.id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        {info.device === "Mobile" ? (
                          <Smartphone className="size-4 text-muted-foreground" />
                        ) : (
                          <Monitor className="size-4 text-muted-foreground" />
                        )}
                        <span className="text-sm">{info.device}</span>
                        {session.is_current && (
                          <Badge variant="success" className="text-[10px] px-1.5">
                            Current
                          </Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {info.browser} / {info.os}
                    </TableCell>
                    <TableCell className="font-mono text-xs text-muted-foreground">
                      {session.ip_address}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {new Date(session.last_activity * 1000).toLocaleString()}
                    </TableCell>
                    <TableCell>
                      {!session.is_current && (
                        <Button
                          variant="ghost"
                          size="icon-xs"
                          className="text-destructive"
                          onClick={() => setRevokeSessionId(session.id)}
                        >
                          <Trash2 className="size-3.5" />
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                );
              })}
              {sessions.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                    No active sessions.
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      <AlertDialog
        open={!!revokeSessionId}
        onOpenChange={(open) => {
          if (!open) setRevokeSessionId(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Revoke Session</AlertDialogTitle>
            <AlertDialogDescription>
              This session will be terminated immediately. The user will be logged out and will need
              to sign in again.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleRevoke}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Revoke Session
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
