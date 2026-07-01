import { Building2 } from "lucide-react";

interface BrandLogoProps {
  logo?: string | null;
  name?: string | null;
  size?: "sm" | "md" | "lg" | "xl";
  showName?: boolean;
}

export function BrandLogo({ logo, name, size = "lg", showName = false }: BrandLogoProps) {
  const sizeConfig = {
    sm: { container: "h-8 w-8", icon: "h-4 w-4", image: "h-8 w-8", text: "text-sm" },
    md: { container: "h-10 w-10", icon: "h-5 w-5", image: "h-10 w-10", text: "text-base" },
    lg: { container: "h-12 w-12", icon: "h-6 w-6", image: "h-12 w-12", text: "text-lg" },
    xl: { container: "h-16 w-16", icon: "h-8 w-8", image: "h-16 w-16", text: "text-xl" },
  };

  const config = sizeConfig[size] || sizeConfig.lg;

  const renderLogoIcon = () => {
    if (logo) {
      return (
        <img
          src={logo}
          alt={name || "App Logo"}
          className={`${config.image} w-auto object-contain`}
          style={{
            maxHeight:
              size === "xl" ? "64px" : size === "lg" ? "48px" : size === "md" ? "40px" : "32px",
          }}
        />
      );
    }

    return (
      <div className={`flex items-center justify-center ${config.container} rounded-lg bg-primary`}>
        <Building2 className={`${config.icon} text-primary-foreground`} />
      </div>
    );
  };

  if (showName) {
    return (
      <div className="flex flex-col items-center gap-2">
        {renderLogoIcon()}
        {name && <span className={`${config.text} font-semibold text-foreground`}>{name}</span>}
      </div>
    );
  }

  return <div className="flex items-center justify-center">{renderLogoIcon()}</div>;
}

export default BrandLogo;
