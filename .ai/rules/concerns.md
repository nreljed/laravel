---
paths:
  - src/Concerns/InteractsWithFeatures.php
---

# Concerns

## InteractsWithFeatures trait is the feature abstraction
Feature providers must extend Illuminate\Support\ServiceProvider (single inheritance), so the no-op-defaults abstraction is a TRAIT (InteractsWithFeatures), not an AbstractFeatureProvider base class. Providers do: `class X extends ServiceProvider implements FeatureProvider { use InteractsWithFeatures; }` and override only the methods they support. Do not add an abstract base — it would conflict with ServiceProvider inheritance.
