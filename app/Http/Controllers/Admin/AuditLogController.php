<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $logs = AdminAuditLog::query()
            ->with('admin')
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin.audit-logs.index', compact('logs'));
    }
}
