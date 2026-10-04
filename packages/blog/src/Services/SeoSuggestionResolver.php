<?php

namespace Nreljed\Blog\Services;

use Nreljed\Blog\Models\PostCategory;
use Nreljed\Blog\Models\PostTag;

/**
 * Enriches an AI SEO payload with the existing category/tag ids matching the
 * agent's term suggestions. Terms are never auto-created: the frontend shows
 * "suggested, not in list" and only existing terms are merged into the post
 * form. Mirrors the WordPress plugin's term suggestion resolution.
 *
 * @return array<string, mixed>
 */
class SeoSuggestionResolver
{
    public function resolve(array $seo): array
    {
        $categoryName = trim((string) ($seo['suggested_category'] ?? ''));
        $categoryId = null;

        if ($categoryName !== '') {
            $found = PostCategory::where('name', $categoryName)->value('id');
            $categoryId = $found !== null ? (int) $found : null;
        }

        $seo['suggested_category'] = $categoryName;
        $seo['suggested_category_id'] = $categoryId;

        $tagNames = [];
        $tagIds = [];

        foreach ((array) ($seo['suggested_tags'] ?? []) as $tag) {
            $name = trim((string) $tag);

            if ($name === '' || in_array($name, $tagNames, true)) {
                continue;
            }

            $tagNames[] = $name;
            $found = PostTag::where('name', $name)->value('id');
            $tagIds[] = $found !== null ? (int) $found : 0;
        }

        $seo['suggested_tag_names'] = $tagNames;
        $seo['suggested_tag_ids'] = $tagIds;

        return $seo;
    }
}