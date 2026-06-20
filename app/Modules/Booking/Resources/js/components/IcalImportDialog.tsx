import { useState, useEffect, useCallback } from "react";
import { toast } from "sonner";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogTrigger,
} from "@/Components/ui/dialog";
import { Loader2, Plus, Trash2, RefreshCw, CalendarClock } from "lucide-react";

interface IcalFeed {
  id: string;
  url: string;
  name: string;
  last_sync: string;
  last_sync_status: string;
  last_error: string | null;
  event_count: number;
  created_at: string;
}

interface IcalImportDialogProps {
  propertyId: number | string;
}

export const IcalImportDialog = ({ propertyId }: IcalImportDialogProps) => {
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState("");
  const [name, setName] = useState("");
  const [importing, setImporting] = useState(false);
  const [feeds, setFeeds] = useState<IcalFeed[]>([]);
  const [loadingFeeds, setLoadingFeeds] = useState(false);
  const [syncingId, setSyncingId] = useState<string | null>(null);

  const pid = typeof propertyId === "string" ? Number(propertyId) : propertyId;

  const fetchFeeds = useCallback(async () => {
    if (!pid) return;
    setLoadingFeeds(true);
    try {
      const res = await fetch(route("dashboard.bookings.ical.feeds", { property_id: pid }));
      const data = await res.json();
      setFeeds(data.feeds ?? []);
    } catch {
      toast.error("Failed to load iCal feeds");
    } finally {
      setLoadingFeeds(false);
    }
  }, [pid]);

  useEffect(() => {
    if (open && pid) {
      fetchFeeds();
    }
  }, [open, pid, fetchFeeds]);

  const handleImport = async () => {
    if (!url.trim()) return;
    setImporting(true);
    try {
      const res = await fetch(route("dashboard.bookings.ical.import"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ url: url.trim(), name: name.trim() || undefined, property_id: pid }),
      });
      const data = await res.json();
      if (!res.ok) {
        toast.error(data.error || "Import failed");
        return;
      }
      toast.success(`Imported ${data.event_count} events`);
      setUrl("");
      setName("");
      await fetchFeeds();
    } catch {
      toast.error("Failed to import iCal feed");
    } finally {
      setImporting(false);
    }
  };

  const handleSync = async (feedId: string) => {
    setSyncingId(feedId);
    try {
      const res = await fetch(route("dashboard.bookings.ical.sync", feedId), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ property_id: pid }),
      });
      const data = await res.json();
      if (!res.ok) {
        toast.error(data.error || "Sync failed");
        return;
      }
      toast.success(`Synced ${data.event_count} events`);
      await fetchFeeds();
    } catch {
      toast.error("Failed to sync feed");
    } finally {
      setSyncingId(null);
    }
  };

  const handleDelete = async (feedId: string) => {
    if (!confirm("Delete this iCal feed and remove all imported blocks?")) return;
    try {
      const res = await fetch(route("dashboard.bookings.ical.destroy", feedId), {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ property_id: pid }),
      });
      if (!res.ok) {
        toast.error("Failed to delete feed");
        return;
      }
      toast.success("Feed deleted");
      await fetchFeeds();
    } catch {
      toast.error("Failed to delete feed");
    }
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2 text-xs h-8">
          <CalendarClock className="h-3.5 w-3.5" />
          iCal
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>iCal Import</DialogTitle>
          <DialogDescription>
            Import external calendar feeds from Airbnb, Vrbo, Booking.com, or any iCal URL.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-3 border rounded-lg p-4">
          <div className="grid gap-2">
            <Label htmlFor="ical-url">iCal URL</Label>
            <Input id="ical-url" placeholder="https://www.airbnb.com/calendar/ical/..." value={url} onChange={(e) => setUrl(e.target.value)} />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="ical-name">Name (optional)</Label>
            <Input id="ical-name" placeholder="Airbnb" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <Button onClick={handleImport} disabled={importing || !url.trim()} className="w-full">
            {importing ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Plus className="h-4 w-4 mr-2" />}
            Import Feed
          </Button>
        </div>

        <div className="space-y-2">
          <h4 className="text-sm font-medium">Imported Feeds</h4>
          {loadingFeeds ? (
            <div className="flex justify-center py-4"><Loader2 className="h-5 w-5 animate-spin text-muted-foreground" /></div>
          ) : feeds.length === 0 ? (
            <p className="text-sm text-muted-foreground text-center py-4">No feeds imported yet.</p>
          ) : (
            <div className="space-y-2 max-h-[250px] overflow-y-auto">
              {feeds.map((feed) => (
                <div key={feed.id} className="flex items-center justify-between border rounded-lg p-3">
                  <div className="min-w-0 flex-1 mr-2">
                    <p className="text-sm font-medium truncate">{feed.name}</p>
                    <p className="text-xs text-muted-foreground truncate">{feed.url}</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      {feed.event_count} events &middot; Last sync: {feed.last_sync}
                      {feed.last_sync_status === "error" && <span className="text-destructive ml-1">({feed.last_error})</span>}
                    </p>
                  </div>
                  <div className="flex gap-1 shrink-0">
                    <Button variant="ghost" size="icon" onClick={() => handleSync(feed.id)} disabled={syncingId === feed.id}>
                      <RefreshCw className={`h-4 w-4 ${syncingId === feed.id ? "animate-spin" : ""}`} />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={() => handleDelete(feed.id)}>
                      <Trash2 className="h-4 w-4 text-destructive" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
};

export default IcalImportDialog;
