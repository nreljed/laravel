import { Link } from "@inertiajs/react";
import {
  ArrowLeft,
  Save,
  PanelRightOpen,
  PanelRightClose,
  Monitor,
  Tablet,
  Smartphone,
  Loader2,
  Check,
} from "lucide-react";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Separator } from "@nreljed/platform/Components/ui/separator";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@nreljed/platform/Components/ui/dropdown-menu";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@nreljed/platform/Components/ui/tooltip";

type ViewportSize = "desktop" | "tablet" | "mobile";

const VIEWPORT_OPTIONS: Array<{
  value: ViewportSize;
  label: string;
  description: string;
  icon: React.ComponentType<{ className?: string }>;
}> = [
  { value: "desktop", label: "Desktop", description: "Preview desktop viewport.", icon: Monitor },
  { value: "tablet", label: "Tablet", description: "Preview tablet viewport.", icon: Tablet },
  { value: "mobile", label: "Mobile", description: "Preview mobile viewport.", icon: Smartphone },
];

interface PostEditorToolbarProps {
  title: string;
  subtitle?: string;
  backUrl: string;
  sidebarOpen: boolean;
  onToggleSidebar: () => void;
  onSave: () => void;
  saving?: boolean;
  saveLabel?: string;
  previewUrl?: string;
  viewport?: ViewportSize;
  onViewportChange?: (_size: ViewportSize) => void;
}

function PostEditorToolbar({
  title,
  subtitle,
  backUrl,
  sidebarOpen,
  onToggleSidebar,
  onSave,
  saving = false,
  saveLabel = "Save",
  previewUrl,
  viewport = "desktop",
  onViewportChange,
}: PostEditorToolbarProps) {
  const activeViewport = VIEWPORT_OPTIONS.find((o) => o.value === viewport) ?? VIEWPORT_OPTIONS[0];
  if (!activeViewport) return null;
  const ActiveIcon = activeViewport.icon;

  return (
    <div className="sticky top-16 z-20 flex h-12 shrink-0 items-center justify-between gap-2 border-b bg-background -mx-4 px-4">
      <div className="flex items-center gap-2 min-w-0">
        <Button render={<Link href={backUrl} />} variant="ghost" size="icon-sm" aria-label="Back to posts">
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <Separator
          orientation="vertical"
          className="data-[orientation=vertical]:h-4 data-[orientation=vertical]:self-center"
        />
        <div className="min-w-0">
          <h2 className="text-sm font-medium text-foreground truncate">{title}</h2>
          {subtitle && <p className="text-xs text-muted-foreground truncate">{subtitle}</p>}
        </div>
      </div>

      <div className="flex items-center gap-1">
        {onViewportChange && (
          <div className="hidden lg:flex">
            <TooltipProvider>
              <Tooltip>
                <DropdownMenu>
                  <TooltipTrigger
                    render={
                      <DropdownMenuTrigger
                        render={
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={activeViewport.label}
                          />
                        }
                      />
                    }
                  >
                    <ActiveIcon className="h-4 w-4" />
                  </TooltipTrigger>
                  <DropdownMenuContent align="end" className="w-56">
                    {VIEWPORT_OPTIONS.map((option) => {
                      const Icon = option.icon;
                      const isActive = viewport === option.value;
                      return (
                        <DropdownMenuItem
                          key={option.value}
                          onClick={() => onViewportChange(option.value)}
                          className="gap-3 py-2"
                        >
                          <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                          <div className="flex flex-col gap-0 min-w-0">
                            <span className="text-sm font-medium leading-none">{option.label}</span>
                            <span className="text-xs text-muted-foreground leading-tight mt-1">
                              {option.description}
                            </span>
                          </div>
                          {isActive && <Check className="h-4 w-4 shrink-0 ml-auto text-primary" />}
                        </DropdownMenuItem>
                      );
                    })}
                  </DropdownMenuContent>
                </DropdownMenu>
                <TooltipContent>{activeViewport.label}</TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </div>
        )}

        {previewUrl && (
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger
                render={
                  <Button
                    render={<a href={previewUrl} target="_blank" rel="noopener noreferrer" />}
                    variant="ghost"
                    size="sm"
                  />
                }
              >
                <span className="text-xs font-medium">Preview</span>
              </TooltipTrigger>
              <TooltipContent>Open preview</TooltipContent>
            </Tooltip>
          </TooltipProvider>
        )}

        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger
              render={
                <Button
                  variant="default"
                  size="sm"
                  className="gap-1.5"
                  onClick={onSave}
                  disabled={saving}
                />
              }
            >
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
              <span className="hidden sm:inline">{saving ? "Saving..." : saveLabel}</span>
            </TooltipTrigger>
            <TooltipContent>{saving ? "Saving..." : saveLabel}</TooltipContent>
          </Tooltip>
        </TooltipProvider>

        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger
              render={
                <Button
                  variant={sidebarOpen ? "secondary" : "ghost"}
                  size="icon-sm"
                  onClick={onToggleSidebar}
                />
              }
            >
              {sidebarOpen ? (
                <PanelRightClose className="h-4 w-4" />
              ) : (
                <PanelRightOpen className="h-4 w-4" />
              )}
            </TooltipTrigger>
            <TooltipContent>{sidebarOpen ? "Hide sidebar" : "Show sidebar"}</TooltipContent>
          </Tooltip>
        </TooltipProvider>
      </div>
    </div>
  );
}

export { PostEditorToolbar };
export type { PostEditorToolbarProps, ViewportSize };