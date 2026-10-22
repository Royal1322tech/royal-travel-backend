<?php

declare(strict_types=1);

namespace App\Core\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

final class PaginationService
{
    public const DEFAULT_PER_PAGE = 15;
    public const MAX_PER_PAGE = 100;

    public function perPage(Request $request): int
    {
        $raw = $request->query('per_page', self::DEFAULT_PER_PAGE);
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? self::DEFAULT_PER_PAGE : min($value, self::MAX_PER_PAGE);
    }

    public function paginate(EloquentBuilder|QueryBuilder $query, Request $request): LengthAwarePaginator
    {
        // Caller must apply tenant scoping, authorization filters and deterministic ordering first.
        return $query->paginate($this->perPage($request))->withQueryString();
    }

    public function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
