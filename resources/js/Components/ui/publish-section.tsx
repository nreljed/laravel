import { router } from "@inertiajs/react";
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group";
import { Label } from "@/Components/ui/label";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  CardFooter,
} from "@/Components/ui/card";
import { Calendar, Send, FileEdit, Eye } from "lucide-react";
import { cn } from "@/lib/utils";

interface PublishSectionProps {
  status?: string;
  publishDate?: string | null;
  onStatusChange?: (status: string) => void;
  onDateChange?: (date: string | null) => void;
  disabled?: boolean;
  showStatusOptions?: boolean;
  onSubmit?: () => void;
  loading?: boolean;
  cancelUrl?: string;
}

export function PublishSection({
  status = "publish",
  publishDate = null,
  onStatusChange,
  onDateChange,
  disabled,
  showStatusOptions = true,
  onSubmit,
  loading,
  cancelUrl,
}: PublishSectionProps) {
  const statusOptions = [
    { value: "publish", label: "Publish Now", icon: Send },
    { value: "future", label: "Schedule", icon: Calendar },
    { value: "draft", label: "Save as Draft", icon: FileEdit },
  ];

  return (
    <Card>
      <CardHeader className="border-b border-border">
        <CardTitle className="flex items-center gap-2 text-foreground">
          <Eye className="h-5 w-5 text-foreground" />
          Publish
        </CardTitle>
        <CardDescription>Manage visibility and scheduling</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 pt-4">
        {showStatusOptions && (
          <RadioGroup value={status} onValueChange={onStatusChange} className="flex flex-col gap-2">
            {statusOptions.map((option) => {
              const Icon = option.icon;
              const isSelected = status === option.value;
              return (
                <Label
                  key={option.value}
                  htmlFor={`pub-status-${option.value}`}
                  className={cn(
                    "cursor-pointer flex items-center justify-center gap-2 px-4 py-3 rounded-lg border-2 transition-all",
                    "hover:border-primary/50 hover:bg-accent",
                    isSelected
                      ? "border-primary bg-primary/10 dark:bg-primary/20"
                      : "border-border bg-background dark:bg-card",
                  )}
                >
                  <RadioGroupItem
                    value={option.value}
                    id={`pub-status-${option.value}`}
                    className="sr-only"
                  />
                  <Icon className="h-4 w-4" />
                  <span className="text-sm font-medium">{option.label}</span>
                </Label>
              );
            })}
          </RadioGroup>
        )}

        {status === "future" && (
          <div className="space-y-2">
            <Label className="text-muted-foreground">Publish on:</Label>
            <Input
              type="datetime-local"
              value={publishDate || ""}
              onChange={(e) => onDateChange?.(e.target.value || null)}
              disabled={disabled}
            />
            <p className="text-sm text-muted-foreground flex items-center gap-1">
              <Calendar className="h-3.5 w-3.5" />
              Will be published on the selected date
            </p>
          </div>
        )}

        {status === "publish" && (
          <p className="text-sm text-muted-foreground">This will be published immediately.</p>
        )}
        {status === "draft" && (
          <p className="text-sm text-muted-foreground">
            This will be saved as a draft and won't be visible.
          </p>
        )}
      </CardContent>

      {onSubmit && (
        <CardFooter className="border-t border-border justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            disabled={disabled}
            onClick={() => {
              if (cancelUrl) router.visit(cancelUrl);
              else history.back();
            }}
          >
            Cancel
          </Button>
          <Button type="submit" disabled={disabled || loading}>
            {loading ? "Saving..." : "Save Changes"}
          </Button>
        </CardFooter>
      )}
    </Card>
  );
}

export default PublishSection;
