<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\ReportController;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerController extends Controller
{
    public function dashboard(Request $request): View
    {
        $view = app(DashboardController::class)->index($request);
        return view('owner.dashboard', ['dashboard' => $view->getData()]);
    }

    public function report(Request $request): View
    {
        $view = app(ReportController::class)->index($request);
        return view('owner.report', ['report' => $view->getData()]);
    }
}
