export interface User {
  id: number;
  username: string;
  name: string;
  email: string;
  email_verified_at?: string;
  display_name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  bio?: string | null;
  avatar?: string | null;
  avatar_url?: string | null;
  url?: string | null;
  contact_email?: string | null;
  social_links?: Record<string, string | null>;
  phone?: string | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User;
  };
};
