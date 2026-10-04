---
description: Find consumer-specific code that has leaked into upstream packages, with the generic shape it should take instead
agent: build
---

# Audit upstream for consumer-domain leaks

`nreljed/platform` is a reusable engine consumed by several apps. The moment it
hardcodes one consumer's vocabulary, every other consumer inherits a bug and the
engine stops being general. This command finds those leaks and names the generic
shape each one should take.

Work in the **upstream clone**, not the mirror:
`C:\Users\nrelj\Local Sites\platform`

## 1. Search for consumer vocabulary in upstream

```bash
cd "C:/Users/nrelj/Local Sites/platform"
```

Look for each consumer's nouns and any country/locale/brand-specific constant:

```bash
grep -rniE "blog|post\b|shop|store|booking|appointment|product|order|cart|coupon" src/ config/ resources/js/ database/
grep -rniE "'TN'|\"TN\"|tunisia|TND" src/ config/ resources/js/
grep -rniE "laravel\.shop|laravel\.booking|nreljed" src/ config/
```

Do **not** trust this grep alone - it over-matches. Every candidate must be
confirmed by reading the code and judging whether it is genuinely consumer-specific
or a legitimate generic concept.

## 2. Classify each hit

| Shape | Verdict |
| --- | --- |
| Hardcoded consumer name or slug | **Leak.** Must be removed. |
| Consumer domain key in a default/seed | **Leak.** Make it config- or feature-driven. |
| Feature-specific branch inside shared code | **Leak.** Should be a `FeatureProvider`. |
| Generic concept with a consumer-ish name | Fine - leave it, but note the confusion risk. |
| Registered feature package | Fine. Feature packages are *meant* to be consumer-adjacent. |

## 3. Know the shapes already fixed

Do not re-report these; they are the reference examples of what a leak looks like:

- `src/Services/UploadProfiles.php` - `posts()` / `products()` keyed to consumers
  instead of a generic upload-profile registry.
- `src/Database/Seeders/RolesAndPermissionsSeeder.php` - a `migrateLegacyCrmUserRole()`
  migration running unconditionally instead of behind a config flag.
- `src/Services/PermissionGroups.php` - one consumer's permission grouping inside
  the shared catalog.
- `gallery(string $context = 'post')` - a consumer noun as a default argument.
- `src/Models/Setting.php` + `resources/js/hooks/use-formatting.ts` - consumer
  settings (`surface_unit`, `price_empty_text`) living in core.
- `default_country => 'TN'` - a hardcoded country default in shared settings.

## 4. Propose the generic replacement

Each leak needs a concrete target shape:

- **Capability, not consumer** - a registry keyed by feature slug rather than
  `posts()` / `products()`.
- **Overridable config** - `config('core.migrate_legacy_crm_role', false)`,
  published so an app can enable it.
- **Feature package** - the behaviour belongs in `nreljed/shop-module` or
  `nreljed/blog`, discovered by `FeatureRegistry`.
- **Contract test** - when `core-manifest.json` and the provider's actual
  `mergeConfigFrom()` calls disagree, add a test that compares them. Do **not** add
  dead entries to the manifest, and do not forbid config keys a consumer may
  legitimately need later.

## 5. Add the regression guard

A leak found once and fixed by hand will come back. Land one of:

- an upstream contract test asserting the provider publishes exactly the configs
  the manifest documents, or
- a grep-based gate in **this** repo that fails when a known consumer slug appears
  in a generic upstream path.

## Report

A table of leaks: file:line, the hardcoded value, the verdict, and the proposed
generic shape. List the candidates you rejected as false positives with the reason -
that is what makes the next run faster. If nothing leaks, say so.