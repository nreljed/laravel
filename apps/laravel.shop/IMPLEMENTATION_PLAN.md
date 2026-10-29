# Implementation Plan: Remaining Marketing-Shop-Platform Opportunities

## Overview
Implementing: Segment evaluator for purchase data, product selector for campaigns, abandoned cart → Marketing workflows, product reviews → Marketing workflows, email/notification templates in Marketing, revenue attribution.

## 1. Segment Evaluator for Purchase Data
**Goal:** Enable filtering contacts by total_spent, order_count, last_order_at, purchased specific product
**Files to modify:**
- Marketing Module: src/Services/SegmentEvaluator.php (create)
- Marketing Module: src/Models/Segment.php (add helper methods)
- Marketing Module: src/Services/ContactService.php or similar (may need to create)
- Marketing Module: src/Http/Controllers/SegmentsController.php (add preview/evaluate endpoint)
- Marketing Module: Tests

**Condition format:**
`json
[
  [
    {"field": "total_spent", "operator": ">=", "value": 100},
    {"field": "order_count", "operator": ">=", "value": 3}
  ],
  [
    {"field": "has_purchased_product", "operator": "equals", "value": 123}
  ]
]
`
OR groups, AND within groups. Support: total_spent, order_count, last_order_at, has_purchased_product, user_id exists, source, tags.

## 2. Product Selector for Campaigns (UI + Backend)
**Goal:** Use existing product_id on campaigns
**Files to modify:**
- Marketing Module: src/Http/Controllers/CampaignsController.php - add product_id validation
- Marketing Module: src/Requests/StoreCampaignRequest.php, UpdateCampaignRequest.php
- Marketing Module: Resources/JS pages for campaigns (if in module)
- Marketing Module: Model already has fillable

## 3. Abandoned Cart → Marketing Workflows
**Goal:** Trigger workflows when cart abandoned
**Files to modify:**
- Shop Module: src/Services/CartAbandonmentService.php or similar - dispatch event
- Platform/Shop: Create Nreljed\ShopModule\Events\CartAbandoned event
- Marketing Module: Listen to CartAbandoned event in MarketingServiceProvider
- Marketing Module: Add cartAbandoned() to WorkflowEngine
- Marketing Module: Add CartAbandoned to WorkflowTrigger enum

## 4. Product Reviews → Marketing Workflows
**Goal:** Trigger workflows on review submission/approval
**Files to modify:**
- Shop Module: Dispatch event on review creation/approval
- Marketing Module: Listen and add trigger

## 5. Email/Notification Templates in Marketing
**Goal:** Register templates via getEmailTemplates() and getNotificationTypes()
**Files to modify:**
- Marketing Module: MarketingServiceProvider - add methods
- Create template classes if needed

## 6. Revenue Attribution
**Goal:** Track which campaign/workflow led to order
**Files to modify:**
- Add tracking fields/tables
- Link orders to campaigns/workflows

Let us start implementing step by step.
