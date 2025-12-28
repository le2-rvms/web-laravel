<?php

namespace App\Http\Controllers\Admin\_;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        // Admin home must be protected behind auth.
        $this->middleware('auth');
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    /**
     * Show the application dashboard.
     *
     * @return Renderable
     */
    public function index(Request $request)
    {
        return view('home');
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
