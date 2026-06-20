import { useState } from "react";
import { Eye, EyeOff } from "lucide-react";
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";

interface PasswordInputProps {
  id?: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  error?: string;
  autoComplete?: string;
  disabled?: boolean;
}

export default function PasswordInput({
  id = "password",
  value,
  onChange,
  placeholder = "Password",
  error,
  autoComplete = "new-password",
  disabled = false,
}: PasswordInputProps) {
  const [show, setShow] = useState(false);

  return (
    <div className="relative">
      <Input
        id={id}
        type={show ? "text" : "password"}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        autoComplete={autoComplete}
        disabled={disabled}
        className="pr-10"
      />
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="absolute right-0 top-0 h-full px-3 text-muted-foreground hover:text-foreground"
        onClick={() => setShow(!show)}
        tabIndex={-1}
      >
        {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
      </Button>
      {error && <p className="mt-1 text-sm text-destructive">{error}</p>}
    </div>
  );
}
