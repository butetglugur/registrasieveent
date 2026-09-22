<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @var array<string,string[]> */
    public array $errors;
    public array $old;
    public string $redirectTo;

    public function __construct(array $errors, array $old = [], string $redirectTo = '')
    {
        parent::__construct('Data yang dikirim tidak valid.', 422);
        $this->errors = $errors;
        $this->old = $old;
        $this->redirectTo = $redirectTo;
    }
}
