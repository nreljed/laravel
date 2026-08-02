import { Building2 } from "lucide-react";

interface BrandLogoProps {
  logo?: string | null;
  favicon?: string | null;
  name?: string | null;
  size?: "sm" | "md" | "lg" | "xl";
  showName?: boolean;
}

const SIZE_MAP = {
  sm: { logoH: "h-8", favicon: "h-6 w-6", icon: "h-4 w-4", container: "h-8", text: "text-sm" },
  md: { logoH: "h-10", favicon: "h-7 w-7", icon: "h-5 w-5", container: "h-10", text: "text-base" },
  lg: { logoH: "h-12", favicon: "h-8 w-8", icon: "h-6 w-6", container: "h-12", text: "text-lg" },
  xl: { logoH: "h-16", favicon: "h-10 w-10", icon: "h-8 w-8", container: "h-16", text: "text-xl" },
} as const;

export function BrandLogo({ logo, favicon, name, size = "lg", showName = false }: BrandLogoProps) {
  const s = SIZE_MAP[size] || SIZE_MAP.lg;

  const renderMark = () => {
    if (logo) {
      return (
        <img
          src={logo}
          alt={name || "App Logo"}
          className={`${s.logoH} w-auto object-contain shrink-0`}
        />
      );
    }

    if (favicon) {
      return (
        <img
          src={favicon}
          alt={name || "App"}
          className={`${s.favicon} object-contain rounded-sm shrink-0`}
        />
      );
    }

    return (
      <div
        className={`flex items-center justify-center ${s.container} aspect-square rounded-lg bg-primary shrink-0`}
      >
        <Building2 className={`${s.icon} text-primary-foreground`} />
      </div>
    );
  };

  if (showName) {
    return (
      <div className="flex items-center gap-2.5">
        {renderMark()}
        {name && (
          <span className={`${s.text} font-semibold text-foreground leading-tight`}>{name}</span>
        )}
      </div>
    );
  }

  return <div className="flex items-center justify-center">{renderMark()}</div>;
}

export default BrandLogo;
