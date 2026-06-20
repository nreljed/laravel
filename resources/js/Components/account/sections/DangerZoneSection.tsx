import { useState, useEffect, useRef, useMemo } from "react";
import { router, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Button } from "@/Components/ui/button";
import { SettingsAccordionItem } from "@/Components/ui/settings-accordion";
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { AlertTriangle, Building2, Calendar, FileText, ShoppingCart, User, Bell, ClipboardList } from "lucide-react";

interface DangerZoneSectionProps {
  asAccordion?: boolean;
}

interface DeleteItem {
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  items: string[];
}

const CORE_DELETED: DeleteItem[] = [
  {
    label: "Profile & Account",
    icon: User,
    items: [
      "Your profile information (name, email, avatar, bio, phone, social links)",
      "Account credentials and preferences",
      "Notification preferences",
    ],
  },
  {
    label: "Notifications & Activity",
    icon: Bell,
    items: [
      "All in-app notifications",
      "Audit logs and activity history",
    ],
  },
];

const MODULE_DELETED: Record<string, DeleteItem> = {
  properties: {
    label: "Properties",
    icon: Building2,
    items: [
      "All property listings you created",
      "Property images and galleries",
      "Property types, categories, and features you created",
    ],
  },
  bookings: {
    label: "Bookings",
    icon: Calendar,
    items: [
      "All booking records you created",
      "Calendar blocks and availability settings",
      "iCal feeds you imported",
      "Guest portal data and magic link tokens",
    ],
  },
  posts: {
    label: "Posts",
    icon: FileText,
    items: [
      "All blog posts and articles you wrote",
      "Post featured images and galleries",
    ],
  },
  ecommerce: {
    label: "E-Commerce",
    icon: ShoppingCart,
    items: [
      "Your shopping cart and cart items",
      "Orders you placed (kept as anonymous records)",
    ],
  },
};

export function DangerZoneSection({ asAccordion }: DangerZoneSectionProps) {
  const [deleteConfirm, setDeleteConfirm] = useState("");
  const [showDialog, setShowDialog] = useState(false);
  const [loading, setLoading] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const pageProps = usePage().props;
  const enabledModules = (pageProps.modules as { enabled: string[] } | undefined)?.enabled ?? [];

  const deletableData = useMemo(() => {
    const items: DeleteItem[] = [];
    for (const module of enabledModules) {
      const info = MODULE_DELETED[module];
      if (info) items.push(info);
    }
    return items;
  }, [enabledModules]);

  useEffect(() => {
    return () => {
      if (countdownRef.current) clearInterval(countdownRef.current);
    };
  }, []);

  const handleDelete = () => {
    if (deleteConfirm !== "delete my account") {
      toast.error('Please type "delete my account" to confirm');
      return;
    }
    setCountdown(10);
    countdownRef.current = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(countdownRef.current!);
          countdownRef.current = null;
          setLoading(true);
          router.post(route("account.destroy"), { confirmation: "delete my account" }, {
            preserveScroll: true,
            onSuccess: () => {
              setLoading(false);
              setShowDialog(false);
            },
            onError: () => {
              toast.error("Failed to delete account.");
              setLoading(false);
              setShowDialog(false);
            },
          });
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
  };

  const dialogContent = (
    <AlertDialog open={showDialog} onOpenChange={setShowDialog}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-5 w-5" />
            Delete Account?
          </AlertDialogTitle>
          <AlertDialogDescription className="space-y-4 text-left">
            <p className="text-destructive font-medium">
              This will permanently delete your account and remove all of your data. This action is irreversible.
            </p>
            <div className="space-y-3">
              <p className="text-sm font-medium text-muted-foreground">This includes:</p>

              {[...CORE_DELETED, ...deletableData].map((group) => (
                <div key={group.label}>
                  <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-1 flex items-center gap-1.5">
                    {group.icon && <group.icon className="size-3.5" />}
                    {group.label}
                  </p>
                  <ul className="list-disc list-outside ml-5 space-y-0.5 text-sm text-muted-foreground">
                    {group.items.map((item) => (
                      <li key={item}>{item}</li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
            <div>
              <Label htmlFor="delete_confirm">Type "delete my account" to confirm</Label>
              <Input
                id="delete_confirm"
                type="text"
                value={deleteConfirm}
                onChange={(e) => setDeleteConfirm(e.target.value)}
                placeholder="delete my account"
                className="mt-2"
                disabled={loading || countdown > 0}
              />
            </div>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={countdown > 0}>Cancel</AlertDialogCancel>
          <Button
            variant="destructive"
            onClick={handleDelete}
            disabled={deleteConfirm !== "delete my account" || countdown > 0}
          >
            {countdown > 0 ? `Delete (${countdown})` : loading ? "Deleting..." : "Delete Account"}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );

  if (asAccordion) {
    return (
      <SettingsAccordionItem
        value="danger-zone"
        icon={AlertTriangle}
        title="Delete Account"
        description="Once you delete your account, there is no going back. Please be certain."
        variant="destructive"
      >
        <p className="text-sm text-muted-foreground mb-4">
          This will permanently delete your account and remove all of your data from our servers. This action is irreversible.
        </p>
        <div className="flex justify-end">{dialogContent}</div>
      </SettingsAccordionItem>
    );
  }

  return dialogContent;
}

export default DangerZoneSection;
