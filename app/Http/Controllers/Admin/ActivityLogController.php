<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'], 'admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);
        $admin = $request->user('admin');
        $query = AdminActivityLog::with('admin')->latest('created_at')->latest('id');
        if (! $admin->is_super_admin) {
            $query->where('admin_id', $admin->id);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('action', 'like', $search)->orWhere('description', 'like', $search));
        }
        if ($admin->is_super_admin && ! empty($filters['admin_id'])) {
            $query->where('admin_id', $filters['admin_id']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return view('admin.security.activity-logs', [
            'logs' => $query->paginate(15)->withQueryString(),
            'admins' => $admin->is_super_admin ? Admin::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function show(AdminActivityLog $activityLog)
    {
        Gate::authorize('view-audit', $activityLog);

        return view('admin.security.activity-detail', ['log' => $activityLog->load('admin')]);
    }
}
