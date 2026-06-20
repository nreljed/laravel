import { Section } from "@/Components/ui/section";
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
      <div className="space-y-4">
        <div className="flex items-center gap-2">
          <p className="text-sm text-muted-foreground min-w-[120px]">User ID:</p>
          <p className="font-medium text-foreground">{String(user?.id ?? "-")}</p>
        </div>
        <div className="flex items-center gap-2">
          <p className="text-sm text-muted-foreground min-w-[120px]">Date Registration:</p>
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
