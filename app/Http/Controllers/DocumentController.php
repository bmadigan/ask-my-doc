<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class DocumentController extends Controller
{
    public function ingest(): View
    {
        return view('ingest');
    }

    public function ask(): View
    {
        return view('ask');
    }
}
