<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use App\Mail\IncidentSlaOverdueMail;
use App\Models\User;
use App\Services\IncidentSlaService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('incidents:notify-sla', function () {
    $slaService = app(IncidentSlaService::class);

    $users = User::query()
        ->where('is_active', true)
        ->whereIn('user_role', ['superadmin', 'admin', 'superviseur'])
        ->get();

    $sent = 0;

    foreach ($users as $user) {
        $province = $user->user_role === 'superadmin' ? null : $user->code_province;
        $incidents = $slaService->overdueIncidents($province, null, 20);

        if ($incidents->isEmpty()) {
            continue;
        }

        Mail::to($user->email)->send(new IncidentSlaOverdueMail(
            incidents: $incidents,
            summary: $slaService->summary($province),
            recipientName: $user->name ?? $user->email
        ));

        $sent++;
    }

    $this->info("Notifications SLA envoyées : {$sent}");
})->purpose('Notify admins and supervisors about overdue incident SLAs');
