import { cn } from "@/lib/utils";

export function DropIndicator({ edge }: { edge: "left" | "right" }) {
  return (
    <div
      aria-hidden
      className={cn(
        "pointer-events-none absolute inset-y-0 z-[1] w-0.5 bg-primary",
        edge === "left" ? "-left-px" : "-right-px",
      )}
    />
  );
}
