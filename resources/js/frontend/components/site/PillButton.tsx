import type { ReactNode } from "react";
import { Link } from "@inertiajs/react";
import { cn } from "cn";

interface PillButtonProps {
  href: string;
  external?: boolean;
  className?: string;
  children: ReactNode;
}

export function PillButton({ href, external = false, className, children }: PillButtonProps) {
  const classes = cn(
    "group inline-flex h-[53px] items-center gap-2 rounded-site-pill bg-site-ink px-8 text-site-bg-strong transition-colors duration-200 hover:bg-site-ink/90 [&_svg]:transition-transform [&_svg]:duration-300 group-hover:[&_svg]:translate-x-1.5",
    className,
  );

  if (external) {
    return (
      <a href={href} className={classes}>
        {children}
      </a>
    );
  }

  return (
    <Link href={href} className={classes}>
      {children}
    </Link>
  );
}
