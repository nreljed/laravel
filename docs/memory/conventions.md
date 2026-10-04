# Conventions

- Lazy-load AGENTS.md (referenced from root, not in instructions[])
- Feature modules implement FeatureProvider + InteractsWithFeatures
- Page kits opt-in: spread register{Name}Pages() in page-config.ts; overrides win
- Slug-based RBAC; permissions aggregated from features
- Canonical migrations live in packages
