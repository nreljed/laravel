# Module Integration Audit: Platform, Shop, Marketing

**Date:** 2026-10-30  
**Versions:** Platform 0.1.95, Shop Module 0.1.83, Marketing Module 0.14.5

## Current State

### Feature Registration
- All three registered in config/features.php: PublicSite, Blog, Marketing, Ai, Shop
- Marketing placed before Ai, Shop last
- All enabled by default

## Integration Assessment

### What Works Well
- FeatureRegistry aggregates nav, permissions, shared data, configs from all enabled providers
- Each provider self-loads routes, registers policies, gates by enabled()
- Shop-Marketing integration via Order observer + ProcessMarketingOrders command
- Purchase data added to contacts (user_id, total_spent, order_count, last_order_at)
- Product linking added to campaigns (product_id)
- OrderPlaced workflow trigger implemented
- Scheduled tasks distributed across modules

### Gaps Identified

#### Marketing Module Gaps
1. **Missing getSharedData()** - Frontend can't access marketing config/state
2. **Missing getNavGroupLabel()** - Nav items may not be grouped consistently
3. **Missing getDashboardData()** - No marketing stats on dashboard
4. **Missing getEmailTemplates()** - Campaign/broadcast emails not registered as templates
5. **Missing getNotificationTypes()** - No notifications for marketing events
6. **Missing getComposerPackage()** - Can't identify installed package version properly
7. **SyncMarketingCustomers doesn't populate purchase data** - Only syncs basic contact info

#### Shop Module Gaps
1. Could listen to more marketing events
2. Customer data sync could be bidirectional
3. Order events could be more explicit

#### Integration Gaps
1. **Segment evaluator** - Can't filter by purchase data (total_spent, order_count, last_order_at, purchased product)
2. **Product selector UI** - product_id on campaigns exists but no UI
3. **Abandoned cart → Marketing workflow** - Shop has abandoned cart but no workflow trigger
4. **Reviews → Marketing** - Product reviews could trigger campaigns/workflows
5. **Wishlist → Marketing** - Notify-me/wishlist could feed audience

## Recommended Improvements

### Phase 1: Fill Marketing FeatureProvider Gaps (High Value)
