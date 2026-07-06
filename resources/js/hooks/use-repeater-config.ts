import { usePage } from "@inertiajs/react";

type RepeaterFieldType =
  "text" | "number" | "textarea" | "select" | "phone" | "url" | "date" | "boolean";

interface RepeaterField {
  type: RepeaterFieldType;
  label: string;
  placeholder?: string;
  required?: boolean;
  options?: { label: string; value: string }[];
  presetMode?: boolean;
  presetOtherValue?: string;
  customPlaceholder?: string;
}

interface RepeaterConfig {
  label: string;
  json_key?: string;
  enable_key?: string;
  min_items?: number;
  max_items?: number;
  fields: Record<string, RepeaterField>;
}

export function useRepeaterConfig() {
  const { repeaterConfigs } = usePage().props as unknown as {
    repeaterConfigs?: Record<string, Record<string, RepeaterConfig>>;
  };

  const allConfigs = repeaterConfigs ?? {};

  function getRepeater(key: string): RepeaterConfig | undefined {
    for (const moduleConfigs of Object.values(allConfigs)) {
      if (Object.hasOwn(moduleConfigs, key)) {
        return moduleConfigs[key];
      }
    }
    return undefined;
  }

  function getFields(key: string): Record<string, RepeaterField> {
    return getRepeater(key)?.fields ?? {};
  }

  function getJsonKey(key: string): string {
    return getRepeater(key)?.json_key ?? `${key}_json`;
  }

  function getEnableKey(key: string): string | undefined {
    return getRepeater(key)?.enable_key;
  }

  function getLimits(key: string): { min: number; max: number } {
    const config = getRepeater(key);
    return { min: config?.min_items ?? 0, max: config?.max_items ?? Infinity };
  }

  function getRepeaterKeys(): string[] {
    const keys: string[] = [];
    for (const moduleConfigs of Object.values(allConfigs)) {
      for (const key of Object.keys(moduleConfigs)) {
        if (!keys.includes(key)) {
          keys.push(key);
        }
      }
    }
    return keys;
  }

  function isRepeater(key: string): boolean {
    return getRepeater(key) !== undefined;
  }

  return {
    getRepeater,
    getFields,
    getJsonKey,
    getEnableKey,
    getLimits,
    getRepeaterKeys,
    isRepeater,
    allConfigs,
  };
}
