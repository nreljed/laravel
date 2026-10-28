import * as React from "react";
import { useFormatDate } from "../../hooks/use-format-date";
import { DateTimePicker } from "./datetime-picker";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../ui/select";
import { Label } from "../ui/label";
import { Badge } from "../ui/badge";
import { Button } from "../ui/button";
import { Calendar, Clock, Send, FileEdit, Trash2 } from "lucide-react";

interface PostStatusSectionProps {
  status?: string;
  publishDate?: string | null;
  onStatusChange?: (_status: string) => void;
  onDateChange?: (_date: string) => void;
  disabled?: boolean;
  showDelete?: boolean;
  deleteDisabled?: boolean;
  onDelete?: () => void;
}

type BadgeVariant = "default" | "secondary" | "outline" | "destructive" | "ghost" | "link";

interface StatusMeta {
  label: string;
  variant: BadgeVariant;
  description: string;
}

const STATUS_META: Record<string, StatusMeta> = {
  published: {
    label: "Published",
    variant: "default",
    description: "This post is visible to visitors.",
  },
  future: {
    label: "Scheduled",
    variant: "outline",
    description: "This post will be published on the selected date.",
  },
  draft: {
    label: "Draft",
    variant: "secondary",
    description: "This post is hidden from visitors until published.",
  },
};

const STATUS_OPTIONS = [
  {
    value: "published",
    label: "Publish Now",
    description: "Make visible to visitors immediately",
    icon: Send,
  },
  {
    value: "future",
    label: "Schedule",
    description: "Choose a date to publish automatically",
    icon: Calendar,
  },
  {
    value: "draft",
    label: "Save as Draft",
    description: "Keep hidden until you publish it",
    icon: FileEdit,
  },
];

function PostStatusSection({
  status = "published",
  publishDate = null,
  onStatusChange,
  onDateChange,
  disabled,
  showDelete = false,
  deleteDisabled = false,
  onDelete,
}: PostStatusSectionProps) {
  const { formatDateTime } = useFormatDate();
  const [internalStatus, setInternalStatus] = React.useState(
    STATUS_OPTIONS.some((o) => o.value === status) ? status : "published",
  );
  const [internalDate, setInternalDate] = React.useState(publishDate);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setInternalStatus(STATUS_OPTIONS.some((o) => o.value === status) ? status : "published");
    }, 0);
    return () => clearTimeout(timer);
  }, [status]);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setInternalDate(publishDate);
    }, 0);
    return () => clearTimeout(timer);
  }, [publishDate]);

  const handleDateChange = (newDate: string) => {
    setInternalDate(newDate);
    onDateChange?.(newDate);
  };

  const statusMeta =
    STATUS_META[internalStatus] ??
    ({
      label: internalStatus.charAt(0).toUpperCase() + internalStatus.slice(1),
      variant: "secondary",
      description: "",
    } satisfies StatusMeta);

  const formatScheduledDate = () => {
    if (!internalDate || internalStatus !== "future") return null;
    const date = new Date(internalDate);
    if (Number.isNaN(date.getTime())) return null;
    return formatDateTime(date);
  };

  const minDate = React.useMemo(() => {
    const now = new Date();
    now.setMinutes(now.getMinutes() - 1);
    return now;
  }, []);

  return (
    <div className="space-y-4">
      <Select
        value={internalStatus}
        onValueChange={(value: string | null) => {
          if (value === null) return;
          setInternalStatus(value);
          onStatusChange?.(value);
        }}
        disabled={disabled}
      >
        <SelectTrigger size="default" className="w-full justify-between">
          <SelectValue>
            {STATUS_OPTIONS.find((o) => o.value === internalStatus)?.label ?? statusMeta.label}
          </SelectValue>
        </SelectTrigger>
        <SelectContent>
          {STATUS_OPTIONS.map((option) => {
            const Icon = option.icon;
            return (
              <SelectItem key={option.value} value={option.value}>
                <Icon className="h-4 w-4" />
                <span className="flex flex-col">
                  <span className="text-sm font-medium">{option.label}</span>
                  <span className="text-xs text-muted-foreground">{option.description}</span>
                </span>
              </SelectItem>
            );
          })}
        </SelectContent>
      </Select>

      <div className="flex items-center justify-between gap-2 rounded-md border border-border bg-muted/50 px-3 py-2">
        <span className="text-sm font-medium text-foreground">Current status</span>
        <Badge variant={statusMeta.variant}>{statusMeta.label}</Badge>
      </div>

      {internalStatus === "future" && (
        <div className="space-y-2">
          <Label className="text-muted-foreground">Publish on:</Label>
          <DateTimePicker
            value={internalDate ?? undefined}
            onChange={handleDateChange}
            minDate={minDate}
            disabled={disabled}
            placeholder="Select date and time"
          />
          {formatScheduledDate() && (
            <p className="text-sm text-muted-foreground flex items-center gap-1">
              <Clock className="h-3.5 w-3.5" />
              Will be published: {formatScheduledDate()}
            </p>
          )}
        </div>
      )}

      {showDelete && (
        <div className="border-t border-border pt-4">
          <Button
            type="button"
            variant="destructive"
            onClick={onDelete}
            disabled={deleteDisabled}
            className="w-full"
          >
            <Trash2 className="h-4 w-4 mr-2" />
            Delete Post
          </Button>
        </div>
      )}
    </div>
  );
}

export { PostStatusSection };