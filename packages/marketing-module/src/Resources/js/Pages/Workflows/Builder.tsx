import { Button } from "@nreljed/platform/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@nreljed/platform/Components/ui/select";
import { Head, router } from "@inertiajs/react";
import { useState } from "react";

interface Tag {
    id: number;
    name: string;
}

interface Workflow {
    id: number;
    name: string;
    description: string | null;
    trigger: string;
    action: string;
    condition_tag_id: number | null;
    tag_id: number | null;
    contact_status: string | null;
    webhook_url: string | null;
    steps: Step[] | null;
    is_active: boolean;
}

interface Step {
    action: string;
    delay_minutes?: number | null;
    condition_tag_id?: number | null;
    condition_metric?: string | null;
    condition_operator?: string | null;
    condition_value?: number | null;
    tag_id?: number | null;
    contact_status?: string | null;
    webhook_url?: string | null;
    on_success?: number | null;
    on_failure?: number | null;
}

interface Props {
    workflow: Workflow;
    tags: Tag[];
}

const ACTIONS = [
    { value: "add_tag", label: "Add tag" },
    { value: "remove_tag", label: "Remove tag" },
    { value: "set_status", label: "Set status" },
    { value: "webhook", label: "Webhook" },
];

const METRICS = [
    { value: "", label: "No metric condition" },
    { value: "impressions", label: "Impressions" },
    { value: "reach", label: "Reach" },
    { value: "engagements", label: "Engagements" },
    { value: "clicks", label: "Provider clicks" },
    { value: "tracked_clicks", label: "Tracked clicks" },
    { value: "delivered", label: "Delivered" },
    { value: "failed", label: "Failed" },
    { value: "success_rate", label: "Success rate" },
    { value: "coverage_rate", label: "Coverage rate" },
    { value: "engagement_rate", label: "Engagement rate" },
    { value: "click_through_rate", label: "Click-through rate" },
];

const OPERATORS = [
    { value: ">=", label: ">=" },
    { value: ">", label: ">" },
    { value: "<=", label: "<=" },
    { value: "<", label: "<" },
    { value: "=", label: "=" },
    { value: "!=", label: "!=" },
];

const STATUSES = [
    { value: "lead", label: "Lead" },
    { value: "active", label: "Active" },
    { value: "customer", label: "Customer" },
    { value: "unsubscribed", label: "Unsubscribed" },
];

function emptyStep(): Step {
    return {
        action: "webhook",
        delay_minutes: 0,
        condition_tag_id: null,
        condition_metric: "",
        condition_operator: ">=",
        condition_value: null,
        tag_id: null,
        contact_status: null,
        webhook_url: null,
        on_success: null,
        on_failure: null,
    };
}

export default function WorkflowBuilder({ workflow, tags }: Props) {
    const [steps, setSteps] = useState<Step[]>(workflow.steps ?? []);
    const [saving, setSaving] = useState(false);

    function updateStep(index: number, patch: Partial<Step>) {
        setSteps((current) =>
            current.map((step, stepIndex) =>
                stepIndex === index ? { ...step, ...patch } : step,
            ),
        );
    }

    function addStep() {
        setSteps((current) => [...current, emptyStep()]);
    }

    function removeStep(index: number) {
        setSteps((current) => current.filter((_, stepIndex) => stepIndex !== index));
    }

    function move(index: number, direction: -1 | 1) {
        setSteps((current) => {
            const next = [...current];
            const target = index + direction;

            if (target < 0 || target >= next.length) {
                return current;
            }

            const first = next[index];
            const second = next[target];
            next[index] = second as Step;
            next[target] = first as Step;

            return next;
        });
    }

    function save() {
        setSaving(true);

        router.put(
            route("dashboard.marketing.workflows.update", workflow.id),
            {
                name: workflow.name,
                description: workflow.description,
                trigger: workflow.trigger,
                action: workflow.action,
                condition_tag_id: workflow.condition_tag_id,
                tag_id: workflow.tag_id,
                contact_status: workflow.contact_status,
                webhook_url: workflow.webhook_url,
                is_active: workflow.is_active,
                steps,
            } as any,
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <>
            <Head title={`Workflow builder: ${workflow.name}`} />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Workflow builder</h1>
                        <p className="text-sm text-muted-foreground">
                            {workflow.name} · {workflow.trigger}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={addStep}>
                            Add step
                        </Button>
                        <Button onClick={save} disabled={saving}>
                            {saving ? "Saving..." : "Save steps"}
                        </Button>
                    </div>
                </div>

                {steps.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No steps yet</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                This workflow uses its single legacy action. Add steps to make it a
                                multi-step workflow.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {steps.map((step, index) => (
                            <Card key={index}>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Step {index + 1}</CardTitle>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => move(index, -1)}
                                        >
                                            Up
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => move(index, 1)}
                                        >
                                            Down
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => removeStep(index)}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Action</Label>
                                        <Select
                                            value={step.action}
                                            onValueChange={(value) =>
                                                updateStep(index, { action: value })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {ACTIONS.map((action) => (
                                                    <SelectItem key={action.value} value={action.value}>
                                                        {action.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Delay (minutes)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={step.delay_minutes ?? 0}
                                            onChange={(event) =>
                                                updateStep(index, {
                                                    delay_minutes: Number(event.target.value),
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Condition tag</Label>
                                        <Select
                                            value={step.condition_tag_id ? String(step.condition_tag_id) : "none"}
                                            onValueChange={(value) =>
                                                updateStep(index, {
                                                    condition_tag_id:
                                                        value === "none" ? null : Number(value),
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">None</SelectItem>
                                                {tags.map((tag) => (
                                                    <SelectItem key={tag.id} value={String(tag.id)}>
                                                        {tag.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Metric condition</Label>
                                        <Select
                                            value={step.condition_metric || ""}
                                            onValueChange={(value) =>
                                                updateStep(index, {
                                                    condition_metric: value || null,
                                                })
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="No metric condition" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {METRICS.map((metric) => (
                                                    <SelectItem key={metric.value || "none"} value={metric.value}>
                                                        {metric.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {step.condition_metric ? (
                                        <>
                                            <div className="space-y-2">
                                                <Label>Operator</Label>
                                                <Select
                                                    value={step.condition_operator || ">="}
                                                    onValueChange={(value) =>
                                                        updateStep(index, { condition_operator: value })
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {OPERATORS.map((operator) => (
                                                            <SelectItem key={operator.value} value={operator.value}>
                                                                {operator.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Threshold</Label>
                                                <Input
                                                    type="number"
                                                    value={step.condition_value ?? ""}
                                                    onChange={(event) =>
                                                        updateStep(index, {
                                                            condition_value: Number(event.target.value),
                                                        })
                                                    }
                                                />
                                            </div>
                                        </>
                                    ) : null}

                                    {step.action === "add_tag" || step.action === "remove_tag" ? (
                                        <div className="space-y-2">
                                            <Label>Tag</Label>
                                            <Select
                                                value={step.tag_id ? String(step.tag_id) : "none"}
                                                onValueChange={(value) =>
                                                    updateStep(index, {
                                                        tag_id: value === "none" ? null : Number(value),
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">None</SelectItem>
                                                    {tags.map((tag) => (
                                                        <SelectItem key={tag.id} value={String(tag.id)}>
                                                            {tag.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    ) : null}

                                    {step.action === "set_status" ? (
                                        <div className="space-y-2">
                                            <Label>Contact status</Label>
                                            <Select
                                                value={step.contact_status || ""}
                                                onValueChange={(value) =>
                                                    updateStep(index, { contact_status: value })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select status" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {STATUSES.map((status) => (
                                                        <SelectItem key={status.value} value={status.value}>
                                                            {status.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    ) : null}

                                    {step.action === "webhook" ? (
                                        <div className="space-y-2 md:col-span-2">
                                            <Label>Webhook URL</Label>
                                            <Input
                                                value={step.webhook_url || ""}
                                                onChange={(event) =>
                                                    updateStep(index, { webhook_url: event.target.value })
                                                }
                                            />
                                        </div>
                                    ) : null}

                                    <div className="space-y-2">
                                        <Label>Next step on success</Label>
                                        <Input
                                            type="number"
                                            value={step.on_success ?? ""}
                                            onChange={(event) =>
                                                updateStep(index, {
                                                    on_success:
                                                        event.target.value === ""
                                                            ? null
                                                            : Number(event.target.value),
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Next step on failure</Label>
                                        <Input
                                            type="number"
                                            value={step.on_failure ?? ""}
                                            onChange={(event) =>
                                                updateStep(index, {
                                                    on_failure:
                                                        event.target.value === ""
                                                            ? null
                                                            : Number(event.target.value),
                                                })
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
