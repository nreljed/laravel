import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Section } from "@/Components/shared/section";
import { Shield } from "lucide-react";
import { CurrentPasswordInput } from "@/Components/shared/current-password-input";
import { PasswordInput } from "@/Components/shared/password-input";

interface PasswordSectionProps {
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

export function PasswordSection({
  asAccordion,
  accordionDefaultOpen: _accordionDefaultOpen,
}: PasswordSectionProps) {
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    setErrors({});
    setSuccess("");

    router.post(
      route("dashboard.account.password"),
      {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.add({ title: "Password updated successfully!", type: "success" });
          setSuccess("Password updated successfully");
          setCurrentPassword("");
          setNewPassword("");
          setConfirmPassword("");
          setLoading(false);
        },
        onError: (errs) => {
          const e = errs as Record<string, string>;
          const mapped: Record<string, string[]> = {};
          if (e.password) mapped.new_password = [e.password];
          if (e.current_password) mapped.current_password = [e.current_password];
          setErrors(mapped);
          const msg = e.password || e.current_password || "Failed to update password";
          toast.add({ title: msg, type: "error" });
          setLoading(false);
        },
      },
    );
  };

  return (
    <Section
      title="Change Password"
      description="Update your password to keep your account secure"
      icon={Shield}
      onSubmit={handleSubmit}
      loading={loading}
      error={errors?.new_password?.[0] || errors?.current_password?.[0]}
      fieldErrors={errors}
      success={!!success}
      successMessage={success}
      submitLabel="Update Password"
      asAccordion={asAccordion}
    >
      <div className="md:col-span-2">
        <PasswordInput
          variant="register"
          newPasswordValue={newPassword}
          onNewPasswordChange={(e) => setNewPassword(e.target.value)}
          newPasswordError={errors.new_password?.[0]}
          confirmValue={confirmPassword}
          onConfirmChange={(e) => setConfirmPassword(e.target.value)}
          minLength={8}
          showStrengthBar
          required
        />
      </div>

      <div className="md:col-span-2">
        <CurrentPasswordInput
          value={currentPassword}
          onChange={(e) => setCurrentPassword(e.target.value)}
          error={errors.current_password?.[0]}
          disabled={loading}
          showForgotPasswordLink={false}
          showHelpText={false}
        />
      </div>
    </Section>
  );
}

export default PasswordSection;
