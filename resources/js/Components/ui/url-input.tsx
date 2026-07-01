import * as React from "react";
import { Input } from "@/Components/ui/input";
import { cn } from "@/lib/utils";
import { Globe } from "lucide-react";

const URL_PATTERN =
  /^https?:\/\/[a-zA-Z0-9][a-zA-Z0-9-]*(\.[a-zA-Z0-9][a-zA-Z0-9-]*)+[\w\-._~:/?#[\]@!$&'()*+,;=.]+$/i;

interface UrlInputProps {
  id?: string;
  value?: string;
  onChange: (_value: string) => void;
  placeholder?: string;
  error?: string;
  disabled?: boolean;
  className?: string;
  [key: string]: unknown;
}

function UrlInput({
  id,
  value,
  onChange,
  placeholder = "https://example.com",
  error,
  disabled,
  ...props
}: UrlInputProps) {
  const [touched, setTouched] = React.useState(false);
  const [localError, setLocalError] = React.useState("");

  const isValidUrl = (val: string) => {
    if (!val) return true;
    return URL_PATTERN.test(val);
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    onChange(newValue);
    if (localError) setLocalError("");
  };

  const handleBlur = () => {
    setTouched(true);
    if (value && !isValidUrl(value)) {
      setLocalError("Please enter a valid URL (e.g., https://example.com)");
    }
  };

  const showError = (error || localError) && touched;

  return (
    <div className="relative">
      <Input
        id={id}
        type="url"
        value={value}
        onChange={handleChange}
        onBlur={handleBlur}
        placeholder={placeholder}
        disabled={disabled}
        aria-invalid={!!showError}
        className={cn(
          "pr-10",
          showError &&
            "border-destructive focus-visible:border-destructive focus-visible:ring-destructive/20",
        )}
        {...props}
      />
      {value && (
        <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
          {isValidUrl(value) ? (
            <Globe className="h-4 w-4 text-green-500 dark:text-green-400" />
          ) : (
            <Globe className="h-4 w-4 text-muted-foreground" />
          )}
        </div>
      )}
    </div>
  );
}

function validateUrl(value: string): { valid: boolean; message: string } {
  if (!value) return { valid: true, message: "" };
  if (!URL_PATTERN.test(value)) {
    return { valid: false, message: "Please enter a valid URL (e.g., https://example.com)" };
  }
  return { valid: true, message: "" };
}

export { UrlInput, validateUrl, URL_PATTERN };
