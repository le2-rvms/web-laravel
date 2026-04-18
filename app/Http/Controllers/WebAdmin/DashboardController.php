<?php

namespace App\Http\Controllers\WebAdmin;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard');
    }
}
