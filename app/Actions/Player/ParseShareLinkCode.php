<?php

declare(strict_types=1);

namespace App\Actions\Player;

class ParseShareLinkCode
{
    /**
     * The code from a pasted `/join/{code}` link, or the input itself when it is a bare code.
     */
    public function handle(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('#/join/([A-Za-z0-9]+)#', $input, $matches) === 1) {
            return $matches[1];
        }

        return preg_match('/^[A-Za-z0-9]+$/', $input) === 1 ? $input : null;
    }
}
