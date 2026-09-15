import { describe, it, expect } from "vitest";
import {
  loginSchema,
  registrationSchema,
  forgotPasswordSchema,
  resetPasswordSchema,
  confirmPasswordSchema,
  profileSchema,
  passwordSchema,
  emailSchema,
} from "../schemas";

describe("loginSchema", () => {
  it("accepts valid input", () => {
    const result = loginSchema.safeParse({ username: "john", password: "secret" });
    expect(result.success).toBe(true);
  });

  it("rejects empty username", () => {
    const result = loginSchema.safeParse({ username: "", password: "secret" });
    expect(result.success).toBe(false);
  });

  it("rejects empty password", () => {
    const result = loginSchema.safeParse({ username: "john", password: "" });
    expect(result.success).toBe(false);
  });
});

describe("registrationSchema", () => {
  it("accepts valid registration", () => {
    const result = registrationSchema.safeParse({
      username: "john.doe",
      email: "john@example.com",
      password: "password123",
      password_confirmation: "password123",
    });
    expect(result.success).toBe(true);
  });

  it("rejects short username", () => {
    const result = registrationSchema.safeParse({
      username: "ab",
      email: "john@example.com",
      password: "password123",
      password_confirmation: "password123",
    });
    expect(result.success).toBe(false);
  });

  it("rejects invalid email", () => {
    const result = registrationSchema.safeParse({
      username: "john.doe",
      email: "not-an-email",
      password: "password123",
      password_confirmation: "password123",
    });
    expect(result.success).toBe(false);
  });

  it("rejects mismatched passwords", () => {
    const result = registrationSchema.safeParse({
      username: "john.doe",
      email: "john@example.com",
      password: "password123",
      password_confirmation: "different",
    });
    expect(result.success).toBe(false);
  });

  it("rejects short password", () => {
    const result = registrationSchema.safeParse({
      username: "john.doe",
      email: "john@example.com",
      password: "short",
      password_confirmation: "short",
    });
    expect(result.success).toBe(false);
  });
});

describe("forgotPasswordSchema", () => {
  it("accepts valid email", () => {
    const result = forgotPasswordSchema.safeParse({ email: "john@example.com" });
    expect(result.success).toBe(true);
  });

  it("rejects invalid email", () => {
    const result = forgotPasswordSchema.safeParse({ email: "invalid" });
    expect(result.success).toBe(false);
  });

  it("rejects empty email", () => {
    const result = forgotPasswordSchema.safeParse({ email: "" });
    expect(result.success).toBe(false);
  });
});

describe("resetPasswordSchema", () => {
  it("accepts matching passwords", () => {
    const result = resetPasswordSchema.safeParse({
      password: "newpassword123",
      password_confirmation: "newpassword123",
    });
    expect(result.success).toBe(true);
  });

  it("rejects mismatched passwords", () => {
    const result = resetPasswordSchema.safeParse({
      password: "newpassword123",
      password_confirmation: "different",
    });
    expect(result.success).toBe(false);
  });

  it("rejects short password", () => {
    const result = resetPasswordSchema.safeParse({
      password: "short",
      password_confirmation: "short",
    });
    expect(result.success).toBe(false);
  });
});

describe("confirmPasswordSchema", () => {
  it("accepts non-empty password", () => {
    const result = confirmPasswordSchema.safeParse({ password: "mypassword" });
    expect(result.success).toBe(true);
  });

  it("rejects empty password", () => {
    const result = confirmPasswordSchema.safeParse({ password: "" });
    expect(result.success).toBe(false);
  });
});

describe("profileSchema", () => {
  it("accepts valid profile", () => {
    const result = profileSchema.safeParse({
      name: "John Doe",
      username: "john.doe",
    });
    expect(result.success).toBe(true);
  });

  it("rejects empty name", () => {
    const result = profileSchema.safeParse({ name: "", username: "john.doe" });
    expect(result.success).toBe(false);
  });

  it("rejects short username", () => {
    const result = profileSchema.safeParse({ name: "John", username: "ab" });
    expect(result.success).toBe(false);
  });

  it("rejects username with spaces", () => {
    const result = profileSchema.safeParse({ name: "John", username: "john doe" });
    expect(result.success).toBe(false);
  });
});

describe("passwordSchema", () => {
  it("accepts matching passwords", () => {
    const result = passwordSchema.safeParse({
      current_password: "oldpass",
      password: "newpassword123",
      password_confirmation: "newpassword123",
    });
    expect(result.success).toBe(true);
  });

  it("rejects mismatched passwords", () => {
    const result = passwordSchema.safeParse({
      current_password: "oldpass",
      password: "newpassword123",
      password_confirmation: "different",
    });
    expect(result.success).toBe(false);
  });

  it("rejects short new password", () => {
    const result = passwordSchema.safeParse({
      current_password: "oldpass",
      password: "short",
      password_confirmation: "short",
    });
    expect(result.success).toBe(false);
  });

  it("rejects empty current password", () => {
    const result = passwordSchema.safeParse({
      current_password: "",
      password: "newpassword123",
      password_confirmation: "newpassword123",
    });
    expect(result.success).toBe(false);
  });
});

describe("emailSchema", () => {
  it("accepts valid email with password", () => {
    const result = emailSchema.safeParse({
      email: "new@example.com",
      current_password: "mypassword",
    });
    expect(result.success).toBe(true);
  });

  it("rejects invalid email", () => {
    const result = emailSchema.safeParse({
      email: "not-an-email",
      current_password: "mypassword",
    });
    expect(result.success).toBe(false);
  });

  it("rejects empty password", () => {
    const result = emailSchema.safeParse({
      email: "new@example.com",
      current_password: "",
    });
    expect(result.success).toBe(false);
  });
});
