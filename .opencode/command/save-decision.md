---
description: Save an architecture decision record (ADR) to docs/decisions
---

# Save Decision

Creates a new ADR from a template with sequential numbering.

## Usage
/opencode command save-decision "Title" "Context" "Decision"

Or interactively fill fields.

## Steps
1. Find next ADR number (ADR-NNN)
2. Copy template.md to docs/decisions/ADR-NNN-title-slug.md
3. Fill in Title, Date, Status=Accepted/Proposed, Context, Decision
4. Add to docs/decisions/README.md index
5. Return path
