<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('admin.dashboard', ['activities' => AdminActivityLog::where('admin_id', $request->user('admin')->id)->latest('created_at')->limit(5)->get()]);
    }
}
