# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] - 2026-09-12

- Extracted the blog feature from `nreljed/platform` (`src/Posts`) into the
  standalone `nreljed/blog` package.
- Provider renamed `PostsServiceProvider` → `BlogServiceProvider`
  (`Nreljed\Blog`); slug `posts`, URL prefixes `dashboard/posts` + `/blog`,
  view namespace `posts` unchanged — no route churn for consumers.