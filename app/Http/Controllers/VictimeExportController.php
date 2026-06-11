<?php

namespace App\Http\Controllers;

use App\Exports\VictimesExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VictimeExportController extends Controller
{
    public function export(Request $request)
    {
        $incidentId = $request->query('incident_id');
        
        $filename = 'Export_Victimes_' . ($incidentId ? $incidentId . '_' : '') . now()->format('Ymd_His') . '.xlsx';
        
        return Excel::download(new VictimesExport($incidentId), $filename);
    }
}
