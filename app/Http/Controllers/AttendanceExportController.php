<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportController extends Controller
{
    /** Monthly attendance sheet as CSV (opens in Excel / Google Sheets). Managers only. */
    public function __invoke(Request $request, AttendanceService $attendance): StreamedResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $month = rescue(fn () => Carbon::createFromFormat('!Y-m', (string) $request->query('month'))->startOfMonth(), today()->startOfMonth(), false);
        $people = User::employees()->orderBy('name')->get();
        $rows = $attendance->month($people, $month);
        $dates = collect(range(1, $month->daysInMonth))->map(fn ($d) => $month->copy()->day($d)->toDateString())->all();

        return response()->streamDownload(function () use ($rows, $dates) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Name', 'Email'], array_map(fn ($d) => Carbon::parse($d)->format('d D'), $dates), ['Working days', 'Present', 'Late', 'Leave', 'Absent', 'Attendance %']));

            foreach ($rows as $row) {
                // Prefix risky leading characters so spreadsheet apps don't treat cells as formulas.
                $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
                fputcsv($out, array_merge(
                    [$safe($row['user']->name), $safe($row['user']->email)],
                    array_map(fn ($d) => $row['days'][$d]['code'], $dates),
                    [$row['summary']['working'], $row['summary']['present'], $row['summary']['late'], $row['summary']['leave'], $row['summary']['absent'], $row['summary']['rate'].'%'],
                ));
            }

            fclose($out);
        }, 'attendance-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }
}
