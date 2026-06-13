<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Province;
use App\Services\IncidentDuplicateService;
use App\Services\IncidentQualityService;
use App\Services\IncidentSlaService;
use App\Services\IncidentTimelineService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class IncidentBriefingController extends Controller
{
    public function incident(
        Request $request,
        Incident $incident,
        IncidentSlaService $slaService,
        IncidentQualityService $qualityService,
        IncidentTimelineService $timelineService,
        IncidentDuplicateService $duplicateService
    ) {
        $this->authorizeIncidentAccess($request, $incident);

        $incident->load([
            'province',
            'territoire',
            'chefferie',
            'groupement',
            'zoneSante',
            'aireSante',
            'evenement',
            'violences',
            'victimes.violence',
            'mouvements',
            'referencements.provider',
            'reponses',
            'caseNotes',
        ])->loadCount(['reponses', 'referencements']);

        $pdf = Pdf::loadView('pdf.incident-briefing', [
            'mode' => 'incident',
            'incident' => $incident,
            'sla' => $slaService->statusFor($incident),
            'quality' => $qualityService->report($incident),
            'timeline' => $timelineService->forIncident($incident),
            'duplicates' => $duplicateService->candidatesFor($incident),
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Briefing-' . $incident->code_incident . '.pdf');
    }

    public function province(Request $request, IncidentSlaService $slaService)
    {
        $user = $request->user();
        $provinceCode = $request->query('province');

        if ($user->user_role !== 'superadmin') {
            $provinceCode = $user->code_province;
        }

        abort_if(!$provinceCode, 422, 'Province requise.');

        $province = Province::where('code_province', $provinceCode)->first();
        $incidents = Incident::query()
            ->with(['province', 'territoire', 'zoneSante', 'evenement'])
            ->withCount(['victimes', 'reponses', 'referencements'])
            ->where('code_province', $provinceCode)
            ->whereNotIn('statut_incident', ['Archivé'])
            ->orderByDesc('date_incident')
            ->limit(80)
            ->get();

        $pdf = Pdf::loadView('pdf.province-briefing', [
            'province' => $province,
            'incidents' => $incidents,
            'slaSummary' => $slaService->summary($provinceCode),
            'overdueIncidents' => $slaService->overdueIncidents($provinceCode, null, 20),
            'generatedBy' => $user,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Briefing-province-' . $provinceCode . '.pdf');
    }

    private function authorizeIncidentAccess(Request $request, Incident $incident): void
    {
        $user = $request->user();

        if ($user->user_role !== 'superadmin' && $incident->code_province !== $user->code_province) {
            abort(403);
        }
    }
}
