import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import {
  Activity,
  CheckCircle2,
  CircleDashed,
  Eye,
  EyeOff,
  KeyRound,
  Plug,
  Plus,
  XCircle,
} from "lucide-react";
import { Button } from "../../ui/button";
import { Badge } from "../../ui/badge";
import { StatusBadge } from "../../ui/status-badge";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "../../ui/alert-dialog";
import { SettingsAccordionItem } from "../../shared/settings-accordion";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "../../ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../ui/select";
import { Input } from "../../ui/input";
import { Label } from "../../ui/label";
import { toast } from "../../ui/toast";

interface AiProvider {
  id: number;
  provider: string;
  provider_name: string;
  masked_key: string;
  preferred_model: string | null;
  is_active: boolean;
  created_at: string;
  last_tested_at: string | null;
  last_test_ok: boolean | null;
}

interface ProviderConfig {
  name: string;
  models: string[];
  default_model?: string | null;
  testable?: boolean;
  capabilities?: string[];
}

interface ProviderUsage {
  requests: number;
  input_tokens: number;
  output_tokens: number;
  cost_cents: number;
  last_model: string | null;
  last_run_at: string | null;
  last_latency_ms: number | null;
  last_error: string | null;
}

interface AiUsageSnapshot {
  cap: { requests: number; cost_cents: number };
  today: {
    date: string;
    providers: Record<string, ProviderUsage>;
    totals: { requests: number; tokens: number; cost_cents: number };
  };
  all_time: {
    providers: Record<string, ProviderUsage>;
    totals: { requests: number; tokens: number; cost_cents: number };
  };
}

interface AiKeysSectionProps {
  providers?: AiProvider[];
  supportedProviders?: Record<string, ProviderConfig>;
  usageSnapshot?: AiUsageSnapshot;
}

const CAPABILITY_LABELS: Record<string, string> = {
  chat: "Chat",
  streaming: "Streaming",
  structured_output: "Structured output",
  embeddings: "Embeddings",
  audio: "Audio",
  transcription: "Transcription",
  images: "Images",
  local: "Local",
};

const DEFAULT_SUPPORTED_PROVIDERS: Record<string, ProviderConfig> = {
  openai: {
    name: "OpenAI",
    models: ["gpt-5.6-sol", "gpt-5.6-terra", "gpt-5.6-luna", "gpt-5", "o4-mini"],
    default_model: "gpt-5.6-luna",
    testable: true,
    capabilities: ["chat", "streaming", "structured_output", "embeddings", "audio", "transcription"],
  },
  anthropic: {
    name: "Anthropic",
    models: ["claude-opus-4-8", "claude-sonnet-5", "claude-haiku-4-5"],
    default_model: "claude-sonnet-5",
    testable: true,
    capabilities: ["chat", "streaming", "structured_output"],
  },
  gemini: {
    name: "Google Gemini",
    models: ["gemini-3.6-flash", "gemini-3.5-flash-lite", "gemini-2.5-pro"],
    default_model: "gemini-3.5-flash-lite",
    testable: true,
    capabilities: ["chat", "streaming", "structured_output", "images"],
  },
  mistral: {
    name: "Mistral",
    models: ["mistral-medium-latest", "mistral-small-latest", "mistral-large-latest"],
    default_model: "mistral-small-latest",
    testable: true,
    capabilities: ["chat", "streaming", "structured_output"],
  },
  deepseek: {
    name: "DeepSeek",
    models: ["deepseek-chat", "deepseek-reasoner"],
    default_model: "deepseek-chat",
    testable: true,
    capabilities: ["chat", "streaming", "structured_output"],
  },
  xai: {
    name: "xAI (Grok)",
    models: ["grok-4.5", "grok-4.3", "grok-4.20-reasoning"],
    default_model: "grok-4.3",
    testable: true,
    capabilities: ["chat", "streaming", "structured_output"],
  },
  ollama: {
    name: "Ollama (Local)",
    models: [],
    default_model: "llama3.2",
    testable: false,
    capabilities: ["chat", "streaming", "structured_output", "local"],
  },
};

const fmtInt = (n: number) => n.toLocaleString("en-US");
const fmtCost = (cents: number) => `$${(cents / 100).toFixed(2)}`;
const usagePercent = (used: number, cap: number) =>
  cap > 0 ? Math.min(100, Math.round((used / cap) * 100)) : 0;

function formatLatency(ms: number | null): string | null {
  if (ms == null || ms <= 0) return null;
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

function providerStatus(provider: AiProvider, usage?: AiUsageSnapshot) {
  if (!provider.is_active) return { label: "Disabled" as const, variant: null };
  const usageEntry = usage?.today.providers[provider.provider];
  if (provider.last_test_ok === false || usageEntry?.last_error) {
    return { label: "Failed" as const, variant: "critical" as const };
  }
  return provider.last_test_ok === true
    ? { label: "Connected" as const, variant: "success" as const }
    : { label: "Active" as const, variant: "info" as const };
}

function ProviderStatusPill({
  provider,
  usage,
}: {
  provider: AiProvider;
  usage?: AiUsageSnapshot;
}) {
  const status = providerStatus(provider, usage);
  if (status.variant === null) {
    return (
      <Badge variant="outline" className="shrink-0 text-muted-foreground border-border">
        {status.label}
      </Badge>
    );
  }
  return (
    <StatusBadge variant={status.variant} className="shrink-0">
      {status.label}
    </StatusBadge>
  );
}

function UsageBar({ label, used, cap }: { label: string; used: number; cap: number }) {
  const pct = usagePercent(used, cap);
  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between gap-2 text-xs">
        <span className="text-muted-foreground">{label}</span>
        <span className="text-foreground tabular-nums">
          {fmtInt(used)} / {fmtInt(cap)} ({pct}%)
        </span>
      </div>
      <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
        <div
          className={`h-full rounded-full transition-all ${
            pct >= 100 ? "bg-destructive" : pct >= 80 ? "bg-warning" : "bg-primary"
          }`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}

function CostUsageBar({
  label,
  usedCents,
  capCents,
}: {
  label: string;
  usedCents: number;
  capCents: number;
}) {
  const pct = usagePercent(usedCents, capCents);
  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between gap-2 text-xs">
        <span className="text-muted-foreground">{label}</span>
        <span className="text-foreground tabular-nums">
          {fmtCost(usedCents)} / {fmtCost(capCents)} ({pct}%)
        </span>
      </div>
      <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
        <div
          className={`h-full rounded-full transition-all ${
            pct >= 100 ? "bg-destructive" : pct >= 80 ? "bg-warning" : "bg-primary"
          }`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}

export default function AiKeysSection({
  providers: providersProp = [],
  supportedProviders: supportedProp,
  usageSnapshot,
}: AiKeysSectionProps) {
  const providers = providersProp;
  const supportedProviders = supportedProp ?? DEFAULT_SUPPORTED_PROVIDERS;

  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedProvider, setSelectedProvider] = useState("");
  const [apiKey, setApiKey] = useState("");
  const [preferredModel, setPreferredModel] = useState("");
  const [showKey, setShowKey] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(
    null,
  );
  const [saving, setSaving] = useState(false);

  const [deleteProviderId, setDeleteProviderId] = useState<number | null>(null);

  const [capRequests, setCapRequests] = useState(String(usageSnapshot?.cap.requests ?? 100));
  const [capSpend, setCapSpend] = useState(
    String(((usageSnapshot?.cap.cost_cents ?? 500) / 100).toFixed(2)),
  );
  const [savingCaps, setSavingCaps] = useState(false);

  const [storedTestingId, setStoredTestingId] = useState<number | null>(null);
  const [storedTestResults, setStoredTestResults] = useState<
    Record<number, { success: boolean; message: string }>
  >({});

  useEffect(() => {
    if (!usageSnapshot) return;
    setCapRequests(String(usageSnapshot.cap.requests));
    setCapSpend((usageSnapshot.cap.cost_cents / 100).toFixed(2));
  }, [usageSnapshot?.cap.requests, usageSnapshot?.cap.cost_cents]);

  const configuredProviderKeys = new Set(providers.map((p) => p.provider));
  const unconfiguredEntries = Object.entries(supportedProviders).filter(
    ([key]) => !configuredProviderKeys.has(key),
  );

  function resetDialog() {
    setSelectedProvider("");
    setApiKey("");
    setPreferredModel("");
    setShowKey(false);
    setTesting(false);
    setTestResult(null);
  }

  function openReplaceDialog(provider: AiProvider) {
    setSelectedProvider(provider.provider);
    setApiKey("");
    setPreferredModel(provider.preferred_model ?? "");
    setShowKey(false);
    setTesting(false);
    setTestResult(null);
    setDialogOpen(true);
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

      const data = await response.json().catch(() => ({}));

      if (response.ok && data.success) {
        setTestResult({
          success: true,
          message: data.message || "Connection verified successfully.",
        });
      } else {
        setTestResult({
          success: false,
          message: data.message || "Connection failed. Please check your API key.",
        });
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
        preserveState: true,
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

  async function handleStoredTest(provider: AiProvider) {
    setStoredTestingId(provider.id);

    try {
      const response = await fetch(route("dashboard.account.ai-keys.test-stored", provider.id), {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
      });

      const data = await response.json().catch(() => ({}));

      setStoredTestResults((prev) => ({
        ...prev,
        [provider.id]: {
          success: response.ok && data.success === true,
          message:
            data.message ||
            (response.ok
              ? "Connection verified successfully."
              : "Connection failed. Please check your API key."),
        },
      }));

      // Pull refreshed props so last_test_ok / last_tested_at in the status
      // pill reflect the test that just ran.
      router.reload({ only: ["aiProviders"] });
    } catch {
      setStoredTestResults((prev) => ({
        ...prev,
        [provider.id]: { success: false, message: "Connection failed." },
      }));
    } finally {
      setStoredTestingId(null);
    }
  }

  function handleSaveCaps() {
    const requests = Number(capRequests);
    const spend = Number(capSpend);
    if (
      !Number.isFinite(requests) ||
      requests < 1 ||
      !Number.isFinite(spend) ||
      spend < 0.01 ||
      spend > 1000
    ) {
      toast.add({ title: "Enter valid daily limits (min 1 request / $0.01, max $1,000)", type: "error" });
      return;
    }
    setSavingCaps(true);

    router.put(
      route("dashboard.account.ai-keys.usage-cap"),
      { requests, cost_cents: Math.round(spend * 100) },
      {
        preserveState: true,
        onSuccess: () => toast.add({ title: "Daily AI usage limits updated", type: "success" }),
        onError: () => toast.add({ title: "Failed to update limits", type: "error" }),
        onFinish: () => setSavingCaps(false),
      },
    );
  }

  const selectedConfig = supportedProviders[selectedProvider];
  const isReplacing = providers.some((p) => p.provider === selectedProvider);

  const addProviderButton = (
    <Dialog
      open={dialogOpen}
      onOpenChange={(open) => {
        setDialogOpen(open);
        if (!open) resetDialog();
      }}
    >
      <DialogTrigger render={<Button size="sm" disabled={unconfiguredEntries.length === 0} />}>
        <Plus className="mr-2 h-4 w-4" />
        Add Provider
      </DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {isReplacing
              ? `Replace ${selectedConfig?.name ?? selectedProvider} key`
              : "Add AI Provider"}
          </DialogTitle>
          <DialogDescription>
            Enter your API key to enable AI features. Your key is encrypted and stored securely.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {!isReplacing && (
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
                <SelectTrigger id="provider-select" className="w-full">
                  <SelectValue placeholder="Select a provider" />
                </SelectTrigger>
                <SelectContent>
                  {unconfiguredEntries.map(([key, config]) => (
                    <SelectItem key={key} value={key}>
                      {config.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

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
                    selectedConfig?.testable === false
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
                <SelectTrigger id="preferred-model" className="w-full">
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
                  ? "bg-status-success/10 text-status-success"
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
          {selectedProvider && selectedConfig?.testable !== false && (
            <Button variant="outline" onClick={handleTest} disabled={!apiKey || testing}>
              {testing ? "Testing..." : "Test"}
            </Button>
          )}
          <Button onClick={handleSave} disabled={!selectedProvider || !apiKey || saving}>
            {saving ? "Saving..." : isReplacing ? "Replace" : "Save"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );

  const usageAccordion = usageSnapshot ? (
    <SettingsAccordionItem value="ai-usage" icon={Activity} title="Usage & Limits">
      <div className="space-y-4">
        <div className="flex items-center justify-between gap-2">
          <p className="text-sm font-medium text-foreground">
            Today&apos;s AI usage — all providers
          </p>
          <span className="text-xs text-muted-foreground">{usageSnapshot.today.date}</span>
        </div>

        {usageSnapshot.today.totals.requests === 0 &&
        usageSnapshot.today.totals.tokens === 0 &&
        usageSnapshot.today.totals.cost_cents === 0 ? (
          <p className="text-sm text-muted-foreground">No AI usage recorded yet today.</p>
        ) : (
          <>
            <UsageBar
              label="Requests today"
              used={usageSnapshot.today.totals.requests}
              cap={usageSnapshot.cap.requests}
            />
            <CostUsageBar
              label="Spend today"
              usedCents={usageSnapshot.today.totals.cost_cents}
              capCents={usageSnapshot.cap.cost_cents}
            />
            <p className="text-xs text-muted-foreground">
              {fmtInt(usageSnapshot.today.totals.tokens)} tokens today · All time:{" "}
              {fmtInt(usageSnapshot.all_time.totals.requests)} req ·{" "}
              {fmtInt(usageSnapshot.all_time.totals.tokens)} tokens ·{" "}
              {fmtCost(usageSnapshot.all_time.totals.cost_cents)}
            </p>
          </>
        )}

        <div className="space-y-3 rounded-lg border border-dashed p-3">
          <p className="text-xs font-medium text-muted-foreground">Daily soft limits</p>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="flex-1 space-y-1">
              <Label htmlFor="cap-requests">Requests / day</Label>
              <Input
                id="cap-requests"
                type="number"
                min={1}
                max={100_000}
                value={capRequests}
                onChange={(e) => setCapRequests(e.target.value)}
                className="h-8"
              />
            </div>
            <div className="flex-1 space-y-1">
              <Label htmlFor="cap-spend">Spend / day ($)</Label>
              <Input
                id="cap-spend"
                type="number"
                min={0.01}
                max={1000}
                step={0.5}
                value={capSpend}
                onChange={(e) => setCapSpend(e.target.value)}
                className="h-8"
              />
            </div>
            <Button size="sm" onClick={handleSaveCaps} disabled={savingCaps}>
              {savingCaps ? "Saving..." : "Save limits"}
            </Button>
          </div>
          <p className="text-xs text-muted-foreground">
            Limits count every provider together and reset at 00:00 UTC. Spend is estimated from
            pinned per-model rates and may lag actual provider billing. Reaching either limit
            blocks further AI requests until it resets.
          </p>
        </div>
      </div>
    </SettingsAccordionItem>
  ) : null;

  const deleteProvider = providers.find((p) => p.id === deleteProviderId);

  return (
    <div className="space-y-4">
      <AlertDialog
        open={!!deleteProviderId}
        onOpenChange={(open) => {
          if (!open) setDeleteProviderId(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remove {deleteProvider?.provider_name}?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete the API key for {deleteProvider?.provider_name}. Any AI
              features using this provider will stop working.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteProviderId) handleDelete(deleteProviderId);
                setDeleteProviderId(null);
              }}
            >
              Remove
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <div className="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
          <h2 className="text-sm font-semibold text-foreground">AI Integrations</h2>
          <p className="text-sm text-muted-foreground">
            {providers.length > 0
              ? `${providers.length} provider${providers.length === 1 ? "" : "s"} configured`
              : "No providers configured yet"}
          </p>
        </div>
        {addProviderButton}
      </div>

      {providers.map((provider) => {
        const config = supportedProviders[provider.provider];
        const providerUsage = usageSnapshot?.today.providers[provider.provider];
        const providerAllTime = usageSnapshot?.all_time.providers[provider.provider];
        const storedTest = storedTestResults[provider.id];
        return (
          <SettingsAccordionItem
            key={provider.id}
            value={`ai-provider-${provider.id}`}
            icon={KeyRound}
            title={provider.provider_name}
            trailing={<ProviderStatusPill provider={provider} usage={usageSnapshot} />}
          >
            <div className="space-y-4">
              <div className="flex items-center justify-between gap-2">
                <span className="font-mono text-xs text-muted-foreground">
                  {provider.masked_key}
                </span>
                <div className="flex shrink-0 items-center gap-1">
                  <Button variant="outline" size="sm" onClick={() => openReplaceDialog(provider)}>
                    Replace
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-destructive hover:text-destructive"
                    onClick={() => setDeleteProviderId(provider.id)}
                  >
                    Remove
                  </Button>
                </div>
              </div>

              {config && config.models.length > 0 ? (
                <div className="space-y-1.5">
                  <Label htmlFor={`model-${provider.id}`} className="text-xs text-muted-foreground">
                    Preferred model
                  </Label>
                  <Select
                    value={provider.preferred_model ?? ""}
                    onValueChange={(value) => handleUpdateModel(provider, value ?? "")}
                  >
                    <SelectTrigger id={`model-${provider.id}`} className="h-8 w-full text-xs">
                      <SelectValue
                        placeholder={`Default (${config.default_model ?? "provider default"})`}
                      />
                    </SelectTrigger>
                    <SelectContent>
                      {config.models.map((model) => (
                        <SelectItem key={model} value={model}>
                          {model}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              ) : (
                <p className="text-xs text-muted-foreground">
                  Default model:{" "}
                  {provider.preferred_model ?? config?.default_model ?? "provider default"}
                </p>
              )}

              {config?.testable !== false && (
                <div className="space-y-2">
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={storedTestingId === provider.id}
                      onClick={() => handleStoredTest(provider)}
                    >
                      <Plug className="mr-1.5 h-3.5 w-3.5" />
                      {storedTestingId === provider.id ? "Testing..." : "Test connection"}
                    </Button>
                    {provider.last_tested_at && (
                      <span className="text-xs text-muted-foreground">
                        Last tested {provider.last_tested_at}
                      </span>
                    )}
                  </div>
                  {storedTest && (
                    <div
                      className={`flex items-start gap-2 rounded-md p-2.5 text-xs ${
                        storedTest.success
                          ? "bg-status-success/10 text-status-success"
                          : "bg-destructive/10 text-destructive"
                      }`}
                    >
                      {storedTest.success ? (
                        <CheckCircle2 className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                      ) : (
                        <XCircle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                      )}
                      <p>{storedTest.message}</p>
                    </div>
                  )}
                </div>
              )}

              {config?.capabilities && config.capabilities.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                  {config.capabilities.map((cap) => (
                    <Badge
                      key={cap}
                      variant="outline"
                      className="text-[11px] text-muted-foreground"
                    >
                      {CAPABILITY_LABELS[cap] ?? cap}
                    </Badge>
                  ))}
                </div>
              )}

              {usageSnapshot && providerUsage ? (
                <div className="space-y-2">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-medium text-foreground">Today&apos;s usage</p>
                    <span className="text-[11px] text-muted-foreground">
                      {usageSnapshot.today.date}
                    </span>
                  </div>

                  <UsageBar
                    label="Requests today"
                    used={providerUsage.requests}
                    cap={usageSnapshot.cap.requests}
                  />
                  <CostUsageBar
                    label="Spend today"
                    usedCents={providerUsage.cost_cents}
                    capCents={usageSnapshot.cap.cost_cents}
                  />
                  <p className="text-xs text-muted-foreground">
                    {fmtInt(providerUsage.requests)} requests ·{" "}
                    {fmtInt(providerUsage.input_tokens + providerUsage.output_tokens)} tokens today
                  </p>

                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    {providerUsage.last_run_at ? (
                      <span>Last run {providerUsage.last_run_at}</span>
                    ) : null}
                    {providerUsage.last_model ? (
                      <span>Model: {providerUsage.last_model}</span>
                    ) : null}
                    {formatLatency(providerUsage.last_latency_ms) ? (
                      <span>Latency: {formatLatency(providerUsage.last_latency_ms)}</span>
                    ) : null}
                    <span>
                      All time: {fmtInt(providerAllTime?.requests ?? 0)} req ·{" "}
                      {fmtInt(
                        (providerAllTime?.input_tokens ?? 0) +
                          (providerAllTime?.output_tokens ?? 0),
                      )}{" "}
                      tokens · {fmtCost(providerAllTime?.cost_cents ?? 0)}
                    </span>
                  </div>

                  {providerUsage.last_error ? (
                    <p
                      className="truncate text-xs text-destructive"
                      title={providerUsage.last_error}
                    >
                      {providerUsage.last_error}
                    </p>
                  ) : null}
                </div>
              ) : (
                <p className="text-xs text-muted-foreground">No AI usage recorded yet today.</p>
              )}

              <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
                <span className="text-xs text-muted-foreground">Added {provider.created_at}</span>
                <Button variant="ghost" size="sm" onClick={() => handleToggleActive(provider)}>
                  {provider.is_active ? "Disable" : "Enable"}
                </Button>
              </div>
            </div>
          </SettingsAccordionItem>
        );
      })}

      {unconfiguredEntries.length > 0 && (
        <div className="rounded-lg border border-dashed p-3">
          <p className="mb-2 text-xs font-medium text-muted-foreground">Available providers</p>
          <div className="space-y-1">
            {unconfiguredEntries.map(([key, config]) => (
              <div
                key={key}
                className="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 hover:bg-muted/50"
              >
                <span className="flex min-w-0 items-center gap-2 text-sm text-foreground">
                  <CircleDashed className="h-4 w-4 shrink-0 text-muted-foreground" />
                  {config.name}
                </span>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    resetDialog();
                    setSelectedProvider(key);
                    setDialogOpen(true);
                  }}
                >
                  Configure
                </Button>
              </div>
            ))}
          </div>
        </div>
      )}

      {usageAccordion}
    </div>
  );
}
