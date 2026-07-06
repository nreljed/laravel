import { router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { Copy, Key, Plus, Trash2 } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { toast } from "sonner";

interface Token {
  id: number;
  name: string;
  last_used_at: string | null;
  created_at: string;
}

export default function ApiTokensSection() {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const { tokens: initialTokens, createdToken: flashToken } = usePage<any>().props;

  const tokens = (initialTokens ?? []) as Token[];
  const [dialogOpen, setDialogOpen] = useState(false);
  const [tokenName, setTokenName] = useState("");
  const [newToken, setNewToken] = useState<string | null>(flashToken ?? null);
  const [creating, setCreating] = useState(false);

  function handleCreate() {
    if (!tokenName.trim()) return;
    setCreating(true);
    router.post(
      route("dashboard.account.tokens.create"),
      { name: tokenName.trim() },
      {
        preserveState: true,
        onSuccess: () => {
          setTokenName("");
          setCreating(false);
          toast.success("Token created");
        },
        onError: () => {
          setCreating(false);
          toast.error("Failed to create token");
        },
      },
    );
  }

  function handleDelete(tokenId: number) {
    router.delete(route("dashboard.account.tokens.destroy", tokenId), {
      preserveState: true,
      onSuccess: () => toast.success("Token revoked"),
      onError: () => toast.error("Failed to revoke token"),
    });
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>API Tokens</CardTitle>
            <CardDescription>Manage tokens for the public REST API.</CardDescription>
          </div>
          <Dialog
            open={dialogOpen}
            onOpenChange={(open) => {
              setDialogOpen(open);
              if (!open) {
                setNewToken(null);
                setTokenName("");
              }
            }}
          >
            <DialogTrigger asChild>
              <Button size="sm">
                <Plus className="mr-2 h-4 w-4" />
                Create Token
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Create API Token</DialogTitle>
                <DialogDescription>
                  Give your token a descriptive name so you can identify it later.
                </DialogDescription>
              </DialogHeader>

              {newToken ? (
                <div className="space-y-4">
                  <p className="text-sm text-muted-foreground">
                    Copy this token now — you won't be able to see it again.
                  </p>
                  <div className="relative">
                    <Input value={newToken} readOnly className="font-mono text-xs pr-10" />
                    <Button
                      variant="ghost"
                      size="icon"
                      className="absolute right-1 top-1/2 -translate-y-1/2"
                      onClick={() => {
                        navigator.clipboard.writeText(newToken);
                        toast.success("Copied to clipboard");
                      }}
                    >
                      <Copy className="h-4 w-4" />
                    </Button>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Use this token in the{" "}
                    <code className="bg-muted px-1 rounded">
                      Authorization: Bearer {"{"}token{"}"}
                    </code>{" "}
                    header.
                  </p>
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="token-name">Token Name</Label>
                    <Input
                      id="token-name"
                      value={tokenName}
                      onChange={(e) => setTokenName(e.target.value)}
                      placeholder="e.g. My App Integration"
                    />
                  </div>
                </div>
              )}

              <DialogFooter>
                {newToken ? (
                  <Button
                    onClick={() => {
                      setDialogOpen(false);
                      setNewToken(null);
                    }}
                  >
                    Done
                  </Button>
                ) : (
                  <>
                    <Button variant="outline" onClick={() => setDialogOpen(false)}>
                      Cancel
                    </Button>
                    <Button onClick={handleCreate} disabled={creating || !tokenName.trim()}>
                      {creating ? "Creating..." : "Create"}
                    </Button>
                  </>
                )}
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </CardHeader>
      <CardContent>
        {tokens.length === 0 && (
          <p className="text-sm text-muted-foreground">
            No tokens yet. Create one above to get started.
          </p>
        )}
        {tokens.length > 0 && (
          <div className="space-y-2">
            {tokens.map((token) => (
              <div
                key={token.id}
                className="flex items-center justify-between p-3 border rounded-lg"
              >
                <div className="flex items-center gap-3">
                  <Key className="h-4 w-4 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium">{token.name}</p>
                    <p className="text-xs text-muted-foreground">
                      Created {token.created_at}
                      {token.last_used_at ? ` · Last used ${token.last_used_at}` : " · Never used"}
                    </p>
                  </div>
                </div>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => handleDelete(token.id)}
                  title="Revoke token"
                >
                  <Trash2 className="h-4 w-4 text-destructive" />
                </Button>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
