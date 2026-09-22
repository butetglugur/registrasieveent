<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public int $total;
    public int $perPage;
    public int $page;
    public int $lastPage;
    /** @var array<int,array<string,mixed>> */
    public array $items;

    public function __construct(array $items, int $total, int $perPage, int $page)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->lastPage = max(1, (int) ceil($total / $this->perPage));
        $this->page = min(max(1, $page), $this->lastPage);
    }

    public static function offset(int $page, int $perPage): int
    {
        return (max(1, $page) - 1) * max(1, $perPage);
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    /** Nomor halaman yang ditampilkan, null = elipsis. @return array<int,int|null> */
    public function window(int $around = 2): array
    {
        $pages = [];
        for ($i = 1; $i <= $this->lastPage; $i++) {
            if ($i === 1 || $i === $this->lastPage || abs($i - $this->page) <= $around) {
                $pages[] = $i;
            } elseif (end($pages) !== null) {
                $pages[] = null;
            }
        }
        return $pages;
    }

    public function url(int $page): string
    {
        $q = $_GET;
        $q['page'] = $page;
        $path = Request::current()->path();
        return url($path, $q);
    }
}
