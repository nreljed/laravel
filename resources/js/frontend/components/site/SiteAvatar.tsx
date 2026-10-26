import { getUserInitials } from "@nreljed/platform/hooks/use-auth";
import { cn } from "cn";

type SiteAvatarSize = "sm" | "md" | "lg";

interface SiteAvatarProps {
  name?: string | null;
  src?: string | null;
  size?: SiteAvatarSize;
  className?: string;
}

const sizeClass: Record<SiteAvatarSize, string> = {
  sm: "h-7 w-7 text-[0.625rem]",
  md: "h-9 w-9 text-sm",
  lg: "h-10 w-10 text-sm",
};

// Author avatar for the public site: the user's image when they have one, and an
// initials monogram in the accent tokens when they do not — so a byline never
// renders a broken or empty image. Decorative (`alt=""`): the author's name is
// always shown as text beside it.
export function SiteAvatar({ name, src, size = "sm", className }: SiteAvatarProps) {
  const base = cn(
    "flex shrink-0 items-center justify-center overflow-hidden rounded-full",
    sizeClass[size],
    className,
  );

  if (src) {
    return (
      <img src={src} alt="" loading="lazy" decoding="async" className={cn(base, "object-cover")} />
    );
  }

  return (
    <span aria-hidden="true" className={cn(base, "bg-site-accent font-bold text-site-accent-ink")}>
      {getUserInitials(name)}
    </span>
  );
}
