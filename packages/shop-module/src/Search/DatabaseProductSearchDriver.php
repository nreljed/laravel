<?php

namespace Nreljed\ShopModule\Search;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Nreljed\ShopModule\Contracts\ProductSearchDriver;

/**
 * The default product search driver: a LIKE scan over the product columns
 * each surface opts into.
 *
 * LIKE wildcards in the term are escaped so a shopper searching for a literal
 * `50%` matches products containing `50%` rather than the whole catalogue.
 */
class DatabaseProductSearchDriver implements ProductSearchDriver
{
    /**
     * The term is interpolated into a `LIKE '%...%'` pattern, so it is capped
     * the same way the public catalog API always capped it: 100 characters of
     * user input is plenty for a name, and bounded scan work per request.
     */
    public const MAX_TERM_LENGTH = 100;

    public function normalize(string $query): ?string
    {
        $term = trim($query);

        if ($term === '') {
            return null;
        }

        return mb_substr($term, 0, self::MAX_TERM_LENGTH);
    }

    public function constrain(EloquentBuilder $query, string $term, array $fields): EloquentBuilder
    {
        if ($fields === []) {
            return $query;
        }

        $pattern = '%'.$this->escapeLike($term).'%';

        return $query->where(function (EloquentBuilder $where) use ($fields, $pattern) {
            foreach ($fields as $index => $field) {
                $segment = $this->likePredicate($where, $field);
                $index === 0
                    ? $where->whereRaw($segment, [$pattern])
                    : $where->orWhereRaw($segment, [$pattern]);
            }
        });
    }

    /**
     * A `{field} LIKE ?` predicate that matches the term literally.
     *
     * MySQL and Postgres treat backslash as the LIKE escape by default, so the
     * escaped pattern works without a clause there. SQLite only escapes with
     * an explicit `ESCAPE` clause, and its string literals do not unescape
     * backslashes the way MySQL's do - so SQLite gets a clause while the
     * engines with a default escape are left alone.
     */
    private function likePredicate(EloquentBuilder $where, string $field): string
    {
        $grammar = $where->getQuery()->getGrammar();

        // SQLite needs `ESCAPE '\'`, MySQL needs nothing (its default escape
        // is already backslash).
        $clause = $grammar instanceof SQLiteGrammar ? " escape '\\'" : '';

        return "{$field} like ?{$clause}";
    }

    /**
     * Escape the characters a LIKE pattern treats as magic so the term is
     * matched literally. Backslash must be escaped first.
     */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}