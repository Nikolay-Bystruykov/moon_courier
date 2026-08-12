<?php

namespace App\Domain\Lunar;

readonly class ValidationResult
{
    /** @param  RejectionReason[]  $reasons */
    public function __construct(public bool $allowed, public array $reasons)
    {
    }

    /** @return string[] */
    public function messages(): array
    {
        return array_map(fn (RejectionReason $reason) => $reason->message(), $this->reasons);
    }
}
