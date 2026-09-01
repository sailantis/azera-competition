<?php

declare(strict_types=1);

namespace App\Laravel\Http\Controllers;

use Illuminate\View\View;

class BenchController
{
    public function index(): View
    {
        return view('home', []);
    }
}