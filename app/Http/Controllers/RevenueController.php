<?php

namespace App\Http\Controllers;

use App\Services\RevenueBoardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function __construct(private readonly RevenueBoardService $revenue) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('revenue.view'), 403);
        $stateId = $request->integer('state_id') ?: null;

        return view('revenue.index', $this->revenue->board($request->user(), $stateId));
    }
}
