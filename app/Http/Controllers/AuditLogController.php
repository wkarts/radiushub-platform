<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('audit.view', session(config('tenancy.session_key')), session(config('tenancy.company_session_key'))), 403);

        $logs = AuditLog::query()
            ->where('tenant_id', session(config('tenancy.session_key')))
            ->where('company_id', session(config('tenancy.company_session_key')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->string('action').'%'))
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->string('result')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->string('user_id')))
            ->latest('created_at')->paginate(50)->withQueryString();

        return view('audit.index', compact('logs'));
    }

    public function platform(Request $request): View
    {
        abort_unless($request->user()->is_super_admin, 403);
        $logs = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->string('action').'%'))
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->string('result')))
            ->latest('created_at')->paginate(100)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
