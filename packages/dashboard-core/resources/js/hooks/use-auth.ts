import { useMemo } from "react";
import { usePage } from "@inertiajs/react";
import { User } from "@/types";

interface AuthData {
  user?: User | null;
}

interface UserCapabilities {
  isAdmin: boolean;
  can: Record<string, boolean>;
}

interface AuthResult {
  user: AuthData["user"];
  isLoggedIn: boolean;
  isAdmin: boolean;
  isLoading: boolean;
  capabilities: UserCapabilities;
}

export function useAuth(): AuthResult {
  const { props } = usePage();

  return useMemo(() => {
    const auth = (props.auth as AuthData | undefined) || {};
    const user = auth.user || null;
    const isLoggedIn = !!user;
    const capabilities = (props.userCapabilities as UserCapabilities | undefined) || {
      isAdmin: false,
      can: {},
    };
    const isAdmin = capabilities.isAdmin;

    return {
      user,
      isLoggedIn,
      isAdmin,
      isLoading: !props.auth,
      capabilities,
    };
  }, [props.auth, props.userCapabilities]);
}

export function getUserInitials(name?: string | null): string {
  if (!name) return "?";
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2 && parts[0]!.length > 0 && parts[1]!.length > 0) {
    return (parts[0]![0]! + parts[1]![0]!).toUpperCase();
  }
  return name.slice(0, 2).toUpperCase();
}
