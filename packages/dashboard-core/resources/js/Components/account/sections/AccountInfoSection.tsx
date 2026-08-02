import { Section } from "@/Components/shared/section";
import { Badge } from "@/Components/ui/badge";
import { Info } from "lucide-react";

interface AccountInfoSectionProps {
  user: Record<string, unknown>;
  roles?: string[];
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

export function AccountInfoSection({
  user,
  roles,
  asAccordion,
  accordionDefaultOpen,
}: AccountInfoSectionProps) {
  return (
    <Section
      title="Account Information"
      description="Your account details and registration information"
      icon={Info}
      asAccordion={asAccordion}
      accordionDefaultOpen={accordionDefaultOpen}
    >
      <div className="space-y-1">
        <div className="flex items-center gap-2">
          <p className="text-sm text-muted-foreground min-w-[120px]">User ID:</p>
          <p className="font-medium text-foreground">{String(user?.id ?? "-")}</p>
        </div>
        <div className="flex items-center gap-2">
          <p className="text-sm text-muted-foreground min-w-[120px]">Username:</p>
          <p className="font-medium text-foreground">{String(user?.user_login ?? "-")}</p>
        </div>
        <div className="flex items-center gap-2">
          <p className="text-sm text-muted-foreground min-w-[120px]">Email:</p>
          <p className="font-medium text-foreground">{String(user?.user_email ?? "-")}</p>
        </div>
        {!!user?.contact_email && String(user.contact_email) !== String(user.user_email) && (
          <div className="flex items-center gap-2">
            <p className="text-sm text-muted-foreground min-w-[120px]">Contact:</p>
            <p className="font-medium text-foreground">{String(user.contact_email)}</p>
          </div>
        )}
        <div className="flex items-center gap-2">
          <p className="text-sm text-muted-foreground min-w-[120px]">Registered:</p>
          <p className="font-medium text-foreground">
            {user?.user_registered
              ? (() => {
                  const d = new Date(user.user_registered as string);
                  return d.toLocaleDateString("en-GB");
                })()
              : "-"}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <p className="text-sm text-muted-foreground min-w-[120px]">Role:</p>
          <Badge variant={roles?.[0] === "Administrator" ? "destructive" : "secondary"}>
            {roles?.[0] || "Subscriber"}
          </Badge>
        </div>
      </div>
    </Section>
  );
}

export default AccountInfoSection;
