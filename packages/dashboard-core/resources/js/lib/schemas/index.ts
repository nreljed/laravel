import * as z from "zod";

export const loginSchema = z.object({
  username: z.string().min(1, "Username or email is required"),
  password: z.string().min(1, "Password is required"),
});

export const registrationSchema = z
  .object({
    username: z
      .string()
      .min(3, "Username must be at least 3 characters")
      .max(60, "Username must be less than 60 characters")
      .regex(
        /^[a-zA-Z0-9]+(?:\.[a-zA-Z0-9]+)*$/,
        "Only letters, numbers and periods allowed (no spaces or other symbols)",
      ),
    email: z.string().min(1, "Email is required").email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z.string().min(1, "Password confirmation is required"),
  })
  .superRefine((data, ctx) => {
    if (data.password !== data.password_confirmation) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Passwords do not match",
        path: ["password_confirmation"],
      });
    }
  });

export const forgotPasswordSchema = z.object({
  email: z.string().min(1, "Email is required").email("Invalid email address"),
});

export const resetPasswordSchema = z
  .object({
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z.string().min(1, "Password confirmation is required"),
  })
  .superRefine((data, ctx) => {
    if (data.password !== data.password_confirmation) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Passwords do not match",
        path: ["password_confirmation"],
      });
    }
  });

export const confirmPasswordSchema = z.object({
  password: z.string().min(1, "Password is required"),
});

export const profileSchema = z.object({
  name: z.string().min(1, "Name is required").max(100, "Name must be under 100 characters"),
  username: z
    .string()
    .min(3, "Username must be at least 3 characters")
    .max(60, "Username must be under 60 characters")
    .regex(
      /^[a-zA-Z0-9]+(?:\.[a-zA-Z0-9]+)*$/,
      "Only letters, numbers and periods allowed (no spaces or other symbols)",
    ),
  display_name: z.string().optional(),
});

export const passwordSchema = z
  .object({
    current_password: z.string().min(1, "Current password is required"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z.string().min(1, "Password confirmation is required"),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
  });

export const emailSchema = z.object({
  email: z.string().min(1, "Email is required").email("Valid email required"),
  current_password: z.string().min(1, "Current password is required"),
});
