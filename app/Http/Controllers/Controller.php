<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve the requested page size from the supported options.
     *
     * @param  array<int, int>  $options
     */
    protected function perPage(Request $request, int $default = 15, array $options = [15, 25, 50, 100]): int
    {
        $perPage = $request->integer('per_page', $default);

        return in_array($perPage, $options, true) ? $perPage : $default;
    }
}
