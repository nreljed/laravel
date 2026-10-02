# Audits

Master reference for all codebase audits and improvement plans.

| Document | Status | Priority |
|----------|--------|----------|
| [CRM Migration Opportunities](crm-migration-opportunities.md) | ✅ Complete | P0 |

## Running an Audit

When auditing a module or subsystem:

1. Document current state (file structure, routes, methods)
2. Compare against intended behavior or reference implementation
3. List findings with ID, description, severity, and fix
4. Implement fixes
5. Verify with `php artisan test` + `npm run typecheck`
6. Update audit document with resolution status
