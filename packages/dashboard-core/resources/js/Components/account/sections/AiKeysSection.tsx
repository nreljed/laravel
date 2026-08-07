import { router } from "@inertiajs/react";
import { useState } from "react";
import {
  Brain,
  Plus,
  MoreHorizontal,
  CheckCircle2,
  XCircle,
  Loader2,
  Eye,
  EyeOff,
} from "lucide-react";
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { SettingsAccordionItem } from "@/Components/shared/settings-accordion";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/Components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { toast } from "@/Components/ui/toast";

interface AiProvider {
  id: number;
  provider: string;
  provider_name: string;
  masked_key: string;
  preferred_model: string | null;
  is_active: boolean;
  created_at: string;
}

interface ProviderConfig {
  name: string;
  models: string[];
}

interface AiKeysSectionProps {
  providers?: AiProvider[];
  supportedProviders?: Record<string, ProviderConfig>;
}

const DEFAULT_SUPPORTED_PROVIDERS: Record<string, ProviderConfig> = {
  openai: {
    name: "OpenAI",
    models: ["gpt-5.6-sol", "gpt-5.6-terra", "gpt-5.6-luna", "gpt-5", "o4-mini"],
  },
  anthropic: {
    name: "Anthropic",
    models: ["claude-opus-4-8", "claude-sonnet-5", "claude-haiku-4-5"],
  },
  gemini: {
    name: "Google Gemini",
    models: ["gemini-3.6-flash", "gemini-3.5-flash-lite", "gemini-2.5-pro"],
  },
  mistral: {
    name: "Mistral",
    models: ["mistral-medium-latest", "mistral-small-latest", "mistral-large-latest"],
  },
  deepseek: { name: "DeepSeek", models: ["deepseek-chat", "deepseek-reasoner"] },
  xai: { name: "xAI (Grok)", models: ["grok-4.5", "grok-4.3", "grok-4.20-reasoning"] },
  ollama: { name: "Ollama (Local)", models: [] },
};

export default function AiKeysSection({
  providers: providersProp,
  supportedProviders: supportedProp,
}: AiKeysSectionProps) {
  const providers = providersProp ?? [];
  const supportedProviders = supportedProp ?? DEFAULT_SUPPORTED_PROVIDERS;

  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedProvider, setSelectedProvider] = useState("");
  const [apiKey, setApiKey] = useState("");
  const [preferredModel, setPreferredModel] = useState("");
  const [showKey, setShowKey] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
  const [saving, setSaving] = useState(false);

  const [deleteProviderId, setDeleteProviderId] = useState<number | null>(null);

  const availableProviders = Object.entries(supportedProviders).filter(([key]) =>
    providers.every((p) => p.provider !== key),
  );

  function resetDialog() {
    setSelectedProvider("");
    setApiKey("");
    setPreferredModel("");
    setShowKey(false);
    setTesting(false);
    setTestResult(null);
  }

  async function handleTest() {
    if (!selectedProvider || !apiKey) return;
    setTesting(true);
    setTestResult(null);

    try {
      const response = await fetch(route("dashboard.account.ai-keys.test"), {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          provider: selectedProvider,
          api_key: apiKey,
          preferred_model: preferredModel || undefined,
        }),
      });

      if (!response.ok) {
        let data: Record<string, string> = {};
        try {
          data = await response.json();
        } catch {
          /*
          ignore parse errors
          */
        }
        const msg =
          data?.api_key ??
          data?.provider ??
          data?.message ??
          "Connection failed. Please check your API key.";
        setTestResult({ success: false, message: msg });
      } else {
        setTestResult({ success: true, message: "Connection verified successfully." });
      }
    } catch {
      setTestResult({ success: false, message: "Connection failed. Please check your API key." });
    } finally {
      setTesting(false);
    }
  }

  function handleSave() {
    if (!selectedProvider || !apiKey) return;
    setSaving(true);

    router.post(
      route("dashboard.account.ai-keys.store"),
      {
        provider: selectedProvider,
        api_key: apiKey,
        preferred_model: preferredModel || undefined,
      },
      {
        onSuccess: () => {
          toast.add({ title: "AI provider key saved", type: "success" });
          setDialogOpen(false);
          resetDialog();
        },
        onError: () => {
          toast.add({ title: "Failed to save provider key", type: "error" });
        },
        onFinish: () => setSaving(false),
      },
    );
  }

  function handleDelete(providerId: number) {
    router.delete(route("dashboard.account.ai-keys.destroy", providerId), {
      onSuccess: () => toast.add({ title: "Provider key removed", type: "success" }),
      onError: () => toast.add({ title: "Failed to remove provider key", type: "error" }),
    });
  }

  function handleToggleActive(provider: AiProvider) {
    router.put(
      route("dashboard.account.ai-keys.update", provider.id),
      { is_active: !provider.is_active },
      {
        onSuccess: () =>
          toast.add({
            title: provider.is_active ? "Provider disabled" : "Provider enabled",
            type: "success",
          }),
        onError: () => toast.add({ title: "Failed to update provider", type: "error" }),
      },
    );
  }

  function handleUpdateModel(provider: AiProvider, model: string) {
    router.put(
      route("dashboard.account.ai-keys.update", provider.id),
      { preferred_model: model || null },
      {
        preserveState: true,
        onSuccess: () => toast.add({ title: "Model preference updated", type: "success" }),
        onError: () => toast.add({ title: "Failed to update model preference", type: "error" }),
      },
    );
  }

  const selectedConfig = supportedProviders[selectedProvider];

  const addProviderButton = (
    <Dialog
      open={dialogOpen}
      onOpenChange={(open) => {
        setDialogOpen(open);
        if (!open) resetDialog();
      }}
    >
      <DialogTrigger render={<Button size="sm" disabled={availableProviders.length === 0} />}>
        <Plus className="mr-2 h-4 w-4" />
        Add Provider
      </DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Add AI Provider</DialogTitle>
          <DialogDescription>
            Enter your API key to enable AI features. Your key is encrypted and stored securely.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="provider-select">Provider</Label>
            <Select
              value={selectedProvider}
              onValueChange={(value) => {
                setSelectedProvider(value ?? "");
                setPreferredModel("");
                setTestResult(null);
              }}
            >
              <SelectTrigger id="provider-select">
                <SelectValue placeholder="Select a provider" />
              </SelectTrigger>
              <SelectContent>
                {availableProviders.map(([key, config]) => (
                  <SelectItem key={key} value={key}>
                    {config.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {selectedProvider && (
            <div className="space-y-2">
              <Label htmlFor="api-key">API Key</Label>
              <div className="relative">
                <Input
                  id="api-key"
                  type={showKey ? "text" : "password"}
                  value={apiKey}
                  onChange={(e) => {
                    setApiKey(e.target.value);
                    setTestResult(null);
                  }}
                  placeholder={
                    selectedProvider === "ollama"
                      ? "Enter any value (e.g. 'ollama')"
                      : `Enter your ${selectedConfig?.name ?? ""} API key`
                  }
                  className="font-mono text-xs pr-10"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
                  onClick={() => setShowKey(!showKey)}
                >
                  {showKey ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                </Button>
              </div>
            </div>
          )}

          {selectedProvider && selectedConfig && selectedConfig.models.length > 0 && (
            <div className="space-y-2">
              <Label htmlFor="preferred-model">Preferred Model (optional)</Label>
              <Select
                value={preferredModel}
                onValueChange={(value) => setPreferredModel(value ?? "")}
              >
                <SelectTrigger id="preferred-model">
                  <SelectValue placeholder="Use default model" />
                </SelectTrigger>
                <SelectContent>
                  {selectedConfig.models.map((model) => (
                    <SelectItem key={model} value={model}>
                      {model}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {testResult && (
            <div
              className={`flex items-start gap-2 text-sm p-3 rounded-md ${
                testResult.success
                  ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400"
                  : "bg-destructive/10 text-destructive"
              }`}
            >
              {testResult.success ? (
                <CheckCircle2 className="h-4 w-4 mt-0.5 shrink-0" />
              ) : (
                <XCircle className="h-4 w-4 mt-0.5 shrink-0" />
              )}
              <p>{testResult.message}</p>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => setDialogOpen(false)}>
            Cancel
          </Button>
          {selectedProvider && selectedProvider !== "ollama" && (
            <Button variant="outline" onClick={handleTest} disabled={!apiKey || testing}>
              {testing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Test
            </Button>
          )}
          <Button onClick={handleSave} disabled={!selectedProvider || !apiKey || saving}>
            {saving ? "Saving..." : "Save"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );

  const deleteProvider = providers.find((p) => p.id === deleteProviderId);

  const providersList =
    providers.length > 0 ? (
      <div className="space-y-3">
        {providers.map((provider) => {
          const config = supportedProviders[provider.provider];
          return (
            <div key={provider.id} className="p-4 border rounded-lg space-y-2">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 min-w-0">
                  <span
                    className={`inline-block h-2.5 w-2.5 rounded-full ring-1 ring-inset shrink-0 ${
                      provider.is_active
                        ? "bg-emerald-500 ring-emerald-500/30 dark:bg-emerald-400 dark:ring-emerald-400/30"
                        : "bg-red-500 ring-red-500/30 dark:bg-red-400 dark:ring-red-400/30"
                    }`}
                  />
                  <p className="text-sm font-medium truncate">{provider.provider_name}</p>
                </div>
                <DropdownMenu>
                  <DropdownMenuTrigger
                    render={<Button variant="ghost" size="icon" className="h-8 w-8 -mr-2" />}
                  >
                    <MoreHorizontal className="h-4 w-4" />
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-40">
                    <DropdownMenuItem onClick={() => handleToggleActive(provider)}>
                      {provider.is_active ? "Disable" : "Enable"}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      variant="destructive"
                      onClick={() => setDeleteProviderId(provider.id)}
                    >
                      Remove
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
              <span className="block text-xs text-muted-foreground font-mono">
                {provider.masked_key}
              </span>
              {config && config.models.length > 0 ? (
                <Select
                  value={provider.preferred_model ?? ""}
                  onValueChange={(value) => handleUpdateModel(provider, value ?? "")}
                >
                  <SelectTrigger className="h-8 text-xs w-full">
                    <SelectValue placeholder="Default model" />
                  </SelectTrigger>
                  <SelectContent>
                    {config.models.map((model) => (
                      <SelectItem key={model} value={model}>
                        {model}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : provider.preferred_model ? (
                <span className="block text-xs text-muted-foreground">
                  Model: {provider.preferred_model}
                </span>
              ) : null}
              <span className="block text-xs text-muted-foreground">
                Added {provider.created_at}
              </span>
            </div>
          );
        })}
      </div>
    ) : (
      <p className="text-sm text-muted-foreground">
        No AI providers configured. Add one above to start using AI features.
      </p>
    );

  return (
    <>
      <AlertDialog
        open={!!deleteProviderId}
        onOpenChange={(open) => {
          if (!open) setDeleteProviderId(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remove Provider Key</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to remove the API key for{" "}
              <strong>{deleteProvider?.provider_name}</strong>? This action cannot be undone and AI
              features using this provider will stop working.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (deleteProviderId) handleDelete(deleteProviderId);
              }}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Remove
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
      <SettingsAccordionItem
        value="ai-provider-keys"
        icon={Brain}
        title="AI Provider Keys"
        description="Add your own API keys to use AI features. Your keys are encrypted and never shared."
        defaultOpen={false}
      >
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-4">
          <span className="text-sm text-muted-foreground">
            {providers.length > 0
              ? `${providers.length} provider${providers.length === 1 ? "" : "s"} configured`
              : "No providers configured"}
          </span>
          {addProviderButton}
        </div>
        {providersList}
      </SettingsAccordionItem>
    </>
  );
}
