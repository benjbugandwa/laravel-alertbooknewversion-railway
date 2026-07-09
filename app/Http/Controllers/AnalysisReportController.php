<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AnalysisReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnalysisReportController extends Controller
{
    public function download(Request $request, AnalysisReportService $service)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'province' => ['nullable', 'string', 'exists:provinces,code_province'],
            'territoire' => ['nullable', 'string', 'exists:territoires,code_territoire'],
        ], [
            'to.after' => 'La date de fin doit etre strictement superieure a la date de debut.',
        ]);

        $user = $request->user();

        if (! $user->hasEffectiveRole('superadmin')) {
            $data['province'] = $user->code_province;
        }

        $this->ensureTerritoireBelongsToProvince($data['territoire'] ?? null, $data['province'] ?? null);

        $report = $service->build($data);
        $generatedAt = now();

        $pdf = Pdf::loadView('pdf.analysis-report', [
            'report' => $report,
            'generatedBy' => $user->loadMissing('organisation'),
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'landscape')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = sprintf(
            'Analyses-%s-%s-au-%s.pdf',
            $report['filters']['province_code'] ?: 'global',
            $report['filters']['from']->format('Ymd'),
            $report['filters']['to']->format('Ymd')
        );

        return $pdf->download($filename);
    }

    private function ensureTerritoireBelongsToProvince(?string $territoire, ?string $province): void
    {
        if (! $territoire || ! $province) {
            return;
        }

        $exists = DB::table('territoires')
            ->where('code_territoire', $territoire)
            ->where('code_province', $province)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'territoire' => 'Le territoire selectionne ne correspond pas a la province choisie.',
            ]);
        }
    }
}
