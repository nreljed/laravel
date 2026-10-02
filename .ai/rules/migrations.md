---
paths:
  - 'database/migrations/**'
---

# Migrations

## Guard app index migrations against package-owned indexes
Feature packages (nreljed/blog) load their own migrations automatically and may already create an index (e.g. post_categories_parent_id_index in the blog's add_depth_and_seo migration). Any app-owned migration creating an index on a package-owned table must guard with Schema::hasTable(...) AND Schema::hasIndex(...) before Schema::table->index(); otherwise RefreshDatabase/migrate:fresh on a fresh DB throws "index ... already exists".
