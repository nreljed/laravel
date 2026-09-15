import {
  FileText,
  LayoutDashboard,
  BarChart3,
  Bell,
  Users,
  UserPlus,
  Settings,
  User,
  Activity,
  Globe,
  IdCard,
  Shield,
  ShieldCheck,
  ScrollText,
  Beaker,
  FlaskConical,
} from "lucide-react";

export type FeatureIcon = React.ComponentType<{ className?: string }>;

export const featureIcons: Record<string, FeatureIcon> = {
  FileText,
  LayoutDashboard,
  BarChart3,
  Bell,
  Users,
  UserPlus,
  Settings,
  User,
  Activity,
  Globe,
  IdCard,
  Shield,
  ShieldCheck,
  ScrollText,
  Beaker,
  FlaskConical,
};

/**
 * Register additional feature icons (e.g. from a host app or feature module).
 * Merges into the shared {@link featureIcons} record so every consumer sees
 * the newly registered icons.
 */
export function registerFeatureIcons(icons: Record<string, FeatureIcon>): void {
  Object.assign(featureIcons, icons);
}

/**
 * Resolve an icon by name, falling back to a safe default so a stale or
 * unknown icon name never breaks rendering.
 */
export function resolveFeatureIcon(name?: string | null): FeatureIcon {
  if (name) {
    const icon = featureIcons[name];
    if (icon) {
      return icon;
    }
  }

  return FileText;
}

export default featureIcons;
