<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(string $action, string $module, string $description, ?Model $subject = null, ?array $old = null, ?array $new = null): void
    {
        ActivityLog::create(['user_id' => Auth::id(), 'action' => $action, 'module' => $module, 'description' => $description, 'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(), 'old_values' => $old, 'new_values' => $new, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now()]);
    }
}
