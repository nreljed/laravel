import * as React from "react";
import { router, useForm } from "@inertiajs/react";
import type { FormDataType } from "@inertiajs/core";
import { Button } from "../ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../ui/card";
import { toast } from "../ui/toast";
import { ResourceField } from "./fields";
import { flattenFields, isFieldVisible, readPath, type ResourceDefinition, type ResourceRecord } from "../../lib/resource-schema";

/**
 * Renders a resource's form from its declaration, and owns nothing else.
 *
 * Persistence is a prop: `submit` receives the serialised payload and is
 * expected to hand it to Inertia's `router`. That keeps the redirect, the
 * success toast and the validation-bag shape on the page, where they differ
 * between a create and an edit and between two resources that happen to share
 * a field list.
 *
 * The serialisation step is the part worth reading. A declared field's
 * `serialize` runs last, so a control can hand back whatever is convenient for
 * typing - a number as a string, a JSON blob as text - and the declaration
 * still decides what actually goes on the wire. Without that, every numeric
 * field needs a bespoke control, which is the coupling the registry was meant
 * to remove.
 */
export interface ResourceFormProps {
    resource: ResourceDefinition<ResourceRecord>;
    /** Values to edit. Omit for a create form. */
    record?: ResourceRecord;
    /** HTTP verb. Defaults to POST; pass "put"/"patch" for an edit. */
    method?: "post" | "put" | "patch";
    /** Inertia URL. */
    action: string;
    submit?: (payload: ResourceRecord, helpers: ResourceFormHelpers) => void;
    /** Label for the submit button. */
    submitLabel?: string;
    onSuccess?: () => void;
    cancelRoute?: string;
}

export interface ResourceFormHelpers {
    post: (url: string, options?: Record<string, unknown>) => void;
    processing: boolean;
    errors: Record<string, string>;
    clearErrors: (field?: string) => void;
    setError: (field: string, message: string) => void;
}

/**
 * Inertia's `FormDataType` constraint requires every value to be form-encodable,
 * but resource state is deliberately untyped (`unknown`). `FormDataType` of the
 * untyped record degrades every value to `never`, which still satisfies the
 * constraint. The actual values are never read through that index - downstream
 * code reads `form.data` as a `ResourceRecord` (assignable through the
 * `never` values) or the concrete seeded record.
 */
type ResourceFormState = FormDataType<ResourceRecord>;

/**
 * Turn the visible fields into a payload.
 *
 * Only visible fields are sent, which is the reason `visibleWhen` exists at all:
 * a field hidden by a toggle must not keep submitting its stale value, because
 * the server has no way to know it stopped being meaningful. `serialize` runs
 * after that filter so a field can decide its own outbound shape.
 */
export function buildPayload(
    record: ResourceRecord,
    fields: { name: string; serialize?: (value: unknown) => unknown }[],
): ResourceRecord {
    const payload: ResourceRecord = {};

    for (const field of fields) {
        const value = record[field.name];

        // `undefined` is checked on the value, not with `in`: the form seeds
        // every declared field from its default, so an untouched field exists as
        // a key holding `undefined`. Shipping that key would put a null into
        // every optional column the server did not ask to change. A field the
        // user cleared is `""`, not `undefined`, so this cannot swallow a
        // deliberate empty.
        if (value === undefined) {
            continue;
        }

        payload[field.name] = field.serialize ? field.serialize(value) : value;
    }

    return payload;
}

/**
 * Spans are written out rather than interpolated. Tailwind scans source text
 * for class names, so a computed `sm:col-span-${span}` produces no CSS at all -
 * the class is correct and the layout silently collapses to one column. Each
 * span a field can declare is a literal here so the scanner finds it.
 */
const COLUMN_SPANS: Record<number, string> = {
    1: "",
    2: "sm:col-span-2",
    3: "sm:col-span-3",
    4: "sm:col-span-4",
};

export function ResourceForm({
    resource,
    record: initialRecord,
    method = "post",
    action,
    submit,
    submitLabel,
    onSuccess,
    cancelRoute,
}: ResourceFormProps) {
    const sections = resource.form ?? [];
    const allFields = React.useMemo(() => flattenFields(sections), [sections]);

    // Seeded once, from the record and then the declared defaults. `useForm`
    // intentionally ignores later argument changes, which is what stops a
    // re-render from discarding what the user is typing - so the defaults are
    // computed once here rather than on every render.
    const [defaults] = React.useState(() => {
        const seeded: ResourceRecord = { ...(initialRecord ?? {}) };

        for (const field of allFields) {
            if (!(field.name in seeded)) {
                seeded[field.name] = field.defaultValue;
            }
        }

        return seeded;
    });

    const form = useForm<ResourceFormState>(defaults as unknown as ResourceFormState);

    // `ResourceFormState`'s index is `never`; values are only ever read or
    // written through the untyped `ResourceRecord` view above it, so the form's
    // setters are surfaced as accepting any value.
    const setData = form.setData as unknown as (key: string, value: unknown) => void;

    const errorFor = (name: string) => form.errors[name];

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        const visible = allFields.filter((field) => isFieldVisible(field, form.data));
        const payload = buildPayload(form.data, visible);

        if (submit) {
            submit(payload, {
                post: (url, options) => form.submit(method, url, options),
                processing: form.processing,
                errors: form.errors,
                clearErrors: (field) => (field === undefined ? form.clearErrors() : form.clearErrors(field)),
                setError: form.setError,
            });

            return;
        }

        form.submit(method, action, {
            onSuccess: () => {
                toast.add({ title: `${resource.singular ?? resource.label} saved.`, type: "success" });
                onSuccess?.();
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6" data-resource={resource.key}>
            {sections.map((section, index) => {
                const visibleFields = section.fields.filter((field) => isFieldVisible(field, form.data));

                if (visibleFields.length === 0) {
                    return null;
                }

                return (
                    <Card key={section.title ?? `section-${index}`}>
                        {section.title ? (
                            <CardHeader>
                                <CardTitle>{section.title}</CardTitle>
                                {section.description ? <CardDescription>{section.description}</CardDescription> : null}
                            </CardHeader>
                        ) : null}
                        <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {visibleFields.map((field) => (
                                <div key={field.name} className={COLUMN_SPANS[field.colSpan ?? 1] || undefined}>
                                    <ResourceField
                                        context={{
                                            field,
                                            record: form.data,
                                            value: readPath(form.data, field.name),
                                            error: errorFor(field.name),
                                            errors: form.errors,
                                            setValue: (value) => setData(field.name, value),
                                            setField: (path, value) => setData(path, value),
                                        }}
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                );
            })}

            {resource.relations?.map((declared) => {
                const RelationComponent = declared.component;

                return <RelationComponent key={declared.key} record={form.data} errors={form.errors} />;
            })}

            <div className="flex items-center gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? "Saving…" : (submitLabel ?? `Save ${resource.singular ?? resource.label.toLowerCase()}`)}
                </Button>
                {cancelRoute ? (
                    <Button type="button" variant="outline" onClick={() => router.get(cancelRoute)}>
                        Cancel
                    </Button>
                ) : null}
            </div>
        </form>
    );
}
