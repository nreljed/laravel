---
name: react-hook-form
description: "React Hook Form patterns for Cost Breaker 360. Activate when working with useForm, form validation, zod schemas, FormProvider, Controller, useFieldArray, or when user mentions forms, form validation, form state, multi-step forms, or form submission. Covers integration with zod, Inertia, Base UI inputs, and server-side validation patterns."
license: MIT
metadata:
  author: laravel
---

# React Hook Form

## Overview

React Hook Form (v7.89) is the primary form library for the Cost Breaker 360 frontend. It integrates with Zod for schema validation and Inertia.js for server-side submission.

## Installation

Already installed: `react-hook-form@^7.89.0`, `zod@^4.6.6`, `@hookform/resolvers@^3.x`

## Basic Usage

```tsx
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  email: z.string().email("Invalid email"),
  age: z.number().min(18, "Must be 18+"),
});

type FormData = z.infer<typeof schema>;

function MyForm() {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", email: "", age: 18 },
  });

  const onSubmit = async (data: FormData) => {
    // Handle submission
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <input {...register("name")} />
      {errors.name && <span className="text-destructive">{errors.name.message}</span>}

      <input {...register("email")} />
      {errors.email && <span className="text-destructive">{errors.email.message}</span>}

      <input type="number" {...register("age", { valueAsNumber: true })} />
      {errors.age && <span className="text-destructive">{errors.age.message}</span>}

      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Saving..." : "Save"}
      </button>
    </form>
  );
}
```

## Inertia Integration

```tsx
import { useForm as useInertiaForm } from "@inertiajs/react";
import { useForm as useRHF } from "react-hook-form";

// Option 1: Use Inertia's useForm (simpler, server-driven)
const { data, setData, post, processing, errors } = useInertiaForm({
  name: "",
  email: "",
});

// Option 2: Use RHF + Inertia router (more control)
const { register, handleSubmit } = useRHF<FormData>();
const onSubmit = (data: FormData) => {
  router.post(route("users.store"), data, {
    onSuccess: () => toast.add({ title: "Saved", type: "success" }),
    onError: (errors) => {
      // Map server errors to RHF
      Object.entries(errors).forEach(([key, msg]) => {
        setError(key as keyof FormData, { message: msg as string });
      });
    },
  });
};
```

## Controller Pattern (Base UI inputs)

```tsx
import { Controller } from "react-hook-form";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";

<Controller
  name="status"
  control={control}
  render={({ field }) => (
    <Select value={field.value} onValueChange={field.onChange}>
      <SelectTrigger><SelectValue /></SelectTrigger>
      <SelectContent>
        <SelectItem value="active">Active</SelectItem>
        <SelectItem value="inactive">Inactive</SelectItem>
      </SelectContent>
    </Select>
  )}
/>
```

## useFieldArray (Dynamic Lists)

```tsx
import { useFieldArray } from "react-hook-form";

const { control, register } = useForm({
  defaultValues: { items: [{ name: "", quantity: 1 }] },
});

const { fields, append, remove } = useFieldArray({
  control,
  name: "items",
});

{fields.map((field, index) => (
  <div key={field.id}>
    <input {...register(`items.${index}.name`)} />
    <input type="number" {...register(`items.${index}.quantity`, { valueAsNumber: true })} />
    <button type="button" onClick={() => remove(index)}>Remove</button>
  </div>
))}
<button type="button" onClick={() => append({ name: "", quantity: 1 })}>Add</button>
```

## Zod Schema Patterns

```tsx
// Conditional validation
const schema = z.object({
  type: z.enum(["personal", "business"]),
  companyName: z.string().optional(),
}).refine((data) => {
  if (data.type === "business") return !!data.companyName;
  return true;
}, { message: "Company name required for business", path: ["companyName"] });

// Nested objects
const schema = z.object({
  address: z.object({
    street: z.string().min(1),
    city: z.string().min(1),
    zip: z.string().regex(/^\d{5}$/),
  }),
});

// Array validation
const schema = z.object({
  tags: z.array(z.string()).min(1, "At least one tag"),
});

// Transform values
const schema = z.object({
  price: z.string().transform((val) => parseFloat(val)),
});
```

## Form State

```tsx
const {
  formState: {
    errors,           // Validation errors
    isDirty,          // Form has unsaved changes
    isValid,          // All fields valid
    isSubmitting,     // Form is submitting
    touchedFields,    // Fields that have been touched
    dirtyFields,      // Fields that have changed
    submitCount,      // Number of submit attempts
  },
  reset,              // Reset to default values
  setValue,           // Set a single field value
  getValues,          // Get current values
  watch,              // Watch field changes
  trigger,            // Trigger validation
} = useForm();
```

## Watch & Dependent Fields

```tsx
const watchType = watch("type");

// Re-render when type changes
{watchType === "business" && (
  <input {...register("companyName")} />
)}
```

## Error Display Pattern (Dark Mode Safe)

```tsx
{errors.name && (
  <p className="text-sm text-destructive mt-1">{errors.name.message}</p>
)}
```

## Common Patterns

### Multi-Step Form
```tsx
const [step, setStep] = useState(1);
const { trigger } = useForm();

const nextStep = async () => {
  const valid = await trigger(["name", "email"]); // validate step 1 fields
  if (valid) setStep(2);
};
```

### Form with Server-Side Validation
```tsx
const onSubmit = async (data: FormData) => {
  try {
    await router.post(route("users.store"), data);
  } catch (e) {
    // Server validation errors are handled by Inertia's error bag
  }
};
```

## Key Files

| File | Purpose |
|---|---|
| `resources/js/Components/ui/input.tsx` | Base UI input wrapper |
| `resources/js/Components/ui/select.tsx` | Base UI select wrapper |
| `resources/js/Components/ui/checkbox.tsx` | Base UI checkbox wrapper |
| `resources/js/lib/utils.ts` | `cn()` utility |

## Common Pitfalls

- **`valueAsNumber`**: Use `{ valueAsNumber: true }` for number inputs, otherwise values are strings.
- **Controller for complex inputs**: Use `<Controller>` for Select, Checkbox, DatePicker — not `register`.
- **Server error mapping**: Map Inertia server errors to RHF via `setError()` in `onError` callback.
- **Default values**: Always provide `defaultValues` to avoid uncontrolled→controlled warnings.
- **Zod transforms**: Use `.transform()` for type coercion, not manual parsing.
- **Re-renders**: Use `watch()` sparingly — it causes re-renders. Prefer `useWatch` for specific fields.
- **Dark mode text**: Error messages must have `text-destructive` class (see base-ui-shadcn skill).
