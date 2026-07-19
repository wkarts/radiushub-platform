<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class Search
{
    public static function contains(Builder $query, string $column, string $term): Builder
    {
        $pattern = '%'.mb_strtolower(trim($term), 'UTF-8').'%';

        return $query->whereRaw('LOWER('.$query->getModel()->qualifyColumn($column).') LIKE ?', [$pattern]);
    }
}
