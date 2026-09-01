<?php

declare(strict_types=1);

namespace App\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiController
{
    public function index(): JsonResponse
    {
        view();
        return response()->json([]);
    }
}
