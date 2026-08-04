import * as React from "react";
import {
  Controller,
  FormProvider,
  useFormContext,
  type ControllerProps,
  type FieldPath,
  type FieldValues,
} from "react-hook-form";

import { cn } from "@/lib/utils";
import { Field, FieldContent, FieldError, FieldLabel } from "@/Components/ui/field";

const Form = FormProvider;

type FormFieldContextValue<
  TFieldValues extends FieldValues = FieldValues,
  TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
> = {
  name: TName;
};

const FormFieldContext = React.createContext<FormFieldContextValue | null>(null);

function FormField<
  TFieldValues extends FieldValues = FieldValues,
  TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
>({ ...props }: ControllerProps<TFieldValues, TName>) {
  return (
    <FormFieldContext.Provider value={{ name: props.name }}>
      <Controller {...props} />
    </FormFieldContext.Provider>
  );
}

function useFormField() {
  const fieldContext = React.useContext(FormFieldContext);
  const itemContext = React.useContext(FormItemContext);

  if (!fieldContext) {
    throw new Error("useFormField should be used within <FormField>");
  }

  const { getFieldState, formState } = useFormContext();
  const fieldState = getFieldState(fieldContext.name, formState);

  const { id } = itemContext ?? { id: fieldContext.name };

  return {
    id,
    name: fieldContext.name,
    formItemId: `${id}-form-item`,
    formDescriptionId: `${id}-form-item-description`,
    formMessageId: `${id}-form-item-message`,
    ...fieldState,
  };
}

type FormItemContextValue = {
  id: string;
};

const FormItemContext = React.createContext<FormItemContextValue | null>(null);

function FormItem({ className, ...props }: React.ComponentProps<"div">) {
  const id = React.useId();

  return (
    <FormItemContext.Provider value={{ id }}>
      <Field data-slot="form-item" className={cn("gap-3", className)} {...props} />
    </FormItemContext.Provider>
  );
}

function FormLabel({ className, ...props }: React.ComponentProps<typeof FieldLabel>) {
  const { error, formItemId } = useFormField();

  return (
    <FieldLabel
      data-slot="form-label"
      data-invalid={!!error}
      htmlFor={formItemId}
      className={cn(error && "text-destructive", className)}
      {...props}
    />
  );
}

function FormControl({ ...props }: React.ComponentProps<typeof FieldContent>) {
  const { error, formItemId, formDescriptionId, formMessageId } = useFormField();

  const child =
    React.Children.count(props.children) === 1 ? React.Children.only(props.children) : null;

  const fieldProps = {
    id: formItemId,
    "aria-describedby": !error ? formDescriptionId : `${formDescriptionId} ${formMessageId}`,
    "aria-invalid": !!error,
  };

  if (child && React.isValidElement(child)) {
    return React.cloneElement(child as React.ReactElement<Record<string, unknown>>, fieldProps);
  }

  return (
    <FieldContent data-slot="form-control" {...fieldProps} {...props}>
      {props.children}
    </FieldContent>
  );
}

function FormDescription({ className, ...props }: React.ComponentProps<"p">) {
  const { formDescriptionId } = useFormField();

  return (
    <p
      data-slot="form-description"
      id={formDescriptionId}
      className={cn("text-sm text-muted-foreground", className)}
      {...props}
    />
  );
}

function FormMessage({ className, ...props }: React.ComponentProps<typeof FieldError>) {
  const { error, formMessageId } = useFormField();
  const message = error ? String(error?.message ?? "") : "";

  if (!error) {
    return null;
  }

  return (
    <FieldError
      data-slot="form-message"
      id={formMessageId}
      className={cn("mt-1", className)}
      {...props}
    >
      {props.children ?? message}
    </FieldError>
  );
}

export {
  useFormField,
  Form,
  FormItem,
  FormLabel,
  FormControl,
  FormDescription,
  FormMessage,
  FormField,
};
