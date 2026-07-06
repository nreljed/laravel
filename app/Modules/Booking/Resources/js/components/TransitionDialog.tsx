import { useState } from "react";
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
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";

export interface TransitionData {
  status: string;
  adjusted_check_in_time?: string;
  adjusted_check_out_time?: string;
  host_notes?: string;
}

interface TransitionDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  newStatus: string;
  checkInTime?: string;
  checkOutTime?: string;
  hostNotes?: string;
  onConfirm: (_data: TransitionData) => void;
  processing?: boolean;
}

const TRANSITION_META: Record<string, { label: string; desc: string; destructive?: boolean }> = {
  checked_in: {
    label: "Check In",
    desc: "Mark this booking as checked in. The guest has arrived and taken possession of the property.",
  },
  checked_out: {
    label: "Check Out",
    desc: "Mark this booking as checked out. The guest has left the property.",
  },
  confirmed: {
    label: "Confirm",
    desc: "Confirm this booking. The reservation is now active and guaranteed.",
  },
  pending: {
    label: "Revert to Pending",
    desc: "Move this booking back to pending status.",
  },
  cancelled: {
    label: "Cancel Booking",
    desc: "This will cancel the booking and may trigger refund calculations. This action cannot be undone.",
    destructive: true,
  },
};

const getTransitionMeta = (status: string) =>
  TRANSITION_META[status] || {
    label: status.replaceAll("_", " "),
    desc: `Change status to "${status.replaceAll("_", " ")}"?`,
  };

export default function TransitionDialog({
  open,
  onOpenChange,
  newStatus,
  checkInTime: defaultCheckIn = "15:00",
  checkOutTime: defaultCheckOut = "11:00",
  hostNotes: defaultNotes = "",
  onConfirm,
  processing = false,
}: TransitionDialogProps) {
  const meta = getTransitionMeta(newStatus);
  const showCheckInTime = newStatus === "checked_in";
  const showCheckOutTime = newStatus === "checked_out";
  const showNotes = newStatus === "checked_in";

  const [checkInTime, setCheckInTime] = useState(defaultCheckIn);
  const [checkOutTime, setCheckOutTime] = useState(defaultCheckOut);
  const [hostNotes, setHostNotes] = useState(defaultNotes);

  const handleConfirm = () => {
    const data: TransitionData = { status: newStatus };
    if (showCheckInTime && checkInTime !== defaultCheckIn) {
      data.adjusted_check_in_time = checkInTime;
    }
    if (showCheckOutTime && checkOutTime !== defaultCheckOut) {
      data.adjusted_check_out_time = checkOutTime;
    }
    if (showNotes && hostNotes !== defaultNotes) {
      data.host_notes = hostNotes;
    }
    onConfirm(data);
  };

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent key={`${open}-${newStatus}`}>
        <AlertDialogHeader>
          <AlertDialogTitle>{meta.label}</AlertDialogTitle>
          <AlertDialogDescription>{meta.desc}</AlertDialogDescription>
        </AlertDialogHeader>

        <div className="space-y-4 py-2">
          {showCheckInTime && (
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Check-in time</label>
              <Input
                type="time"
                value={checkInTime}
                onChange={(e) => setCheckInTime(e.target.value)}
              />
            </div>
          )}

          {showCheckOutTime && (
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Check-out time</label>
              <Input
                type="time"
                value={checkOutTime}
                onChange={(e) => setCheckOutTime(e.target.value)}
              />
            </div>
          )}

          {showNotes && (
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Host notes (internal)</label>
              <Textarea
                placeholder="Notes about this check-in..."
                value={hostNotes}
                onChange={(e) => setHostNotes(e.target.value)}
              />
            </div>
          )}
        </div>

        <AlertDialogFooter>
          <AlertDialogCancel disabled={processing}>Cancel</AlertDialogCancel>
          <AlertDialogAction
            variant={meta.destructive ? "destructive" : "default"}
            disabled={processing}
            onClick={handleConfirm}
          >
            {processing ? "Processing..." : meta.label}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
