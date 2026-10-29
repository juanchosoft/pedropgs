<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Availability\AbsenceService;
use Pgs\Availability\AvailabilityService;
use Pgs\Availability\LeaveBalanceService;
use Pgs\Availability\LeaveDayCounter;
use Pgs\Availability\ReservationService;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConfirmationRequiredException;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Exceptions\VerificationRequiredException;
use Pgs\PendingActions\PendingActionService;

final class VoiceAvailabilityController
{
    private \PDO $pdo;
    private PendingActionService $pendingActions;

    public function __construct(?\PDO $pdo = null, ?PendingActionService $pendingActions = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->pendingActions = $pendingActions ?? new PendingActionService($this->pdo);
    }

    /**
     * GET /voice/workers
     */
    public function getWorkers(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->requireAny(['disponibilidad.schedule.view', 'disponibilidad.reservations.create']);

        $unitId = (int) $req->query('unit_id', 0);
        if ($unitId <= 0) {
            throw new ValidationException(['unit_id' => 'Property (unit_id) is required']);
        }
        UnitScope::assertUnit($principal, $unitId);

        $availSvc = new AvailabilityService($this->pdo);
        $workers = $availSvc->workersForUnit($principal, $unitId);

        $out = [];
        foreach ($workers as $w) {
            $parts = explode(' ', trim((string) $w['nombre']));
            $first = $parts[0] ?? '';
            $out[] = [
                'employee_id' => (int) $w['id'],
                'first_name' => $first,
            ];
        }

        return Response::ok($out);
    }

    /**
     * GET /voice/availability
     */
    public function getAvailability(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->requireAny(['disponibilidad.schedule.view', 'disponibilidad.reservations.create']);

        $employeeId = (int) $req->query('employee_id', 0);
        $unitId = (int) $req->query('unit_id', 0);
        $from = trim((string) $req->query('from', ''));
        $to = trim((string) $req->query('to', ''));

        if ($employeeId <= 0 || $unitId <= 0 || $from === '' || $to === '') {
            throw new ValidationException([
                'employee_id' => 'Required',
                'unit_id' => 'Required',
                'from' => 'Required (YYYY-MM-DD)',
                'to' => 'Required (YYYY-MM-DD)',
            ]);
        }
        UnitScope::assertUnit($principal, $unitId);

        $d1 = \DateTimeImmutable::createFromFormat('Y-m-d', $from);
        $d2 = \DateTimeImmutable::createFromFormat('Y-m-d', $to);
        if (!$d1 || !$d2 || $d1 > $d2) {
            throw new ValidationException(['dates' => 'Invalid date range']);
        }

        // Limit up to 14 days
        $diffDays = (int) $d1->diff($d2)->format('%a') + 1;
        if ($diffDays > 14) {
            throw new ValidationException(['to' => 'Maximum date range is 14 days']);
        }

        $session = (array) $req->attr('call_session');
        $lang = (string) ($session['lang'] ?? 'es');

        $weekdayNamesEs = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
        ];
        $weekdayNamesEn = [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
        ];

        $results = [];
        $cur = $d1;
        while ($cur <= $d2) {
            $dateStr = $cur->format('Y-m-d');
            $dow = (int) $cur->format('N');
            $weekday = $lang === 'en' ? $weekdayNamesEn[$dow] : $weekdayNamesEs[$dow];

            $am = AvailabilityService::isFree($this->pdo, $employeeId, $unitId, $dateStr, 'am');
            $pm = AvailabilityService::isFree($this->pdo, $employeeId, $unitId, $dateStr, 'pm');

            $results[] = [
                'date' => $dateStr,
                'weekday_name' => $weekday,
                'am' => (bool) ($am['free'] ?? false),
                'pm' => (bool) ($pm['free'] ?? false),
            ];
            $cur = $cur->modify('+1 day');
        }

        return Response::ok($results);
    }

    /**
     * POST /voice/reservations
     */
    public function createReservation(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('disponibilidad.reservations.create');

        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();
        $token = $body['confirmation_token'] ?? null;

        if (!empty($token)) {
            $res = $this->pendingActions->confirm($principal, (string) $token, null, $sessionId);
            $rData = $res['result'] ?? [];
            return Response::ok([
                'codes' => $rData['codes'] ?? [],
                'dates' => $rData['dates'] ?? [],
            ], 201);
        }

        // Step 1: Validate payload and check availability
        $employeeId = (int) ($body['employee_id'] ?? 0);
        $unitId = (int) ($body['unit_id'] ?? 0);
        $dates = (array) ($body['dates'] ?? []);
        $dayPart = in_array($body['day_part'] ?? '', ['full', 'am', 'pm'], true) ? (string) $body['day_part'] : 'full';
        $notes = isset($body['notes']) ? (string) $body['notes'] : null;

        if ($employeeId <= 0 || $unitId <= 0 || empty($dates)) {
            throw new ValidationException([
                'employee_id' => 'Required',
                'unit_id' => 'Required',
                'dates' => 'At least one date is required',
            ]);
        }
        if (count($dates) > 10) {
            throw new ValidationException(['dates' => 'Maximum 10 dates per reservation request']);
        }

        UnitScope::assertUnit($principal, $unitId);

        // Fetch worker and unit names for preview
        $empStmt = $this->pdo->prepare("SELECT nombre FROM tec_employee WHERE id = :id");
        $empStmt->execute([':id' => $employeeId]);
        $empName = (string) ($empStmt->fetchColumn() ?: 'Trabajador');
        $firstEmpName = explode(' ', trim($empName))[0] ?? 'Trabajador';

        $uStmt = $this->pdo->prepare("SELECT nombre FROM tbl_unidades WHERE id = :id");
        $uStmt->execute([':id' => $unitId]);
        $unitName = (string) ($uStmt->fetchColumn() ?: 'Propiedad');

        // Verify availability before proposing
        $unavailable = [];
        foreach ($dates as $d) {
            $check = AvailabilityService::isFree($this->pdo, $employeeId, $unitId, (string) $d, $dayPart);
            if (!$check['free']) {
                $unavailable[] = ['date' => $d, 'reason' => $check['reason']];
            }
        }

        if (!empty($unavailable)) {
            $alternatives = AvailabilityService::alternatives($this->pdo, $employeeId, $unitId, (string) $dates[0], $dayPart, 5);
            throw new ConflictException(
                'One or more selected dates are unavailable.',
                'slot_unavailable',
                [
                    'unavailable_dates' => $unavailable,
                    'alternatives' => $alternatives,
                ]
            );
        }

        $partText = $dayPart === 'full' ? 'jornada completa' : ($dayPart === 'am' ? 'mañana' : 'tarde');
        $datesText = implode(', ', $dates);
        $preview = "Reservar a {$firstEmpName} en {$unitName} el {$datesText}, {$partText}. ¿Confirmo?";

        $payload = [
            'employee_id' => $employeeId,
            'unit_id' => $unitId,
            'dates' => $dates,
            'day_part' => $dayPart,
            'notes' => $notes,
        ];

        $action = $this->pendingActions->create($principal, 'voice', 'reservation.create', $payload, $preview, null, $sessionId);

        throw new ConfirmationRequiredException(['text' => $preview], $action['action_id']);
    }

    /**
     * GET /voice/leave/balance
     */
    public function getLeaveBalance(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('disponibilidad.absences.request');

        $employeeId = $principal->employeeId;
        if ($employeeId === null || $employeeId <= 0) {
            throw new ForbiddenException("Caller account is not linked to an employee profile");
        }

        $year = (int) $req->query('year', (int) Clock::nowUtc()->format('Y'));
        $bal = LeaveBalanceService::balance($this->pdo, $employeeId, $year);

        // Fetch last 5 requests
        $stmtReq = $this->pdo->prepare(
            "SELECT id, date_from, date_to, days_count as days, status
             FROM tec_worker_absences
             WHERE employee_id = :e
             ORDER BY id DESC LIMIT 5"
        );
        $stmtReq->execute([':e' => $employeeId]);
        $requests = $stmtReq->fetchAll(\PDO::FETCH_ASSOC);

        $outRequests = [];
        foreach ($requests as $r) {
            $outRequests[] = [
                'id' => (int) $r['id'],
                'date_from' => (string) $r['date_from'],
                'date_to' => (string) $r['date_to'],
                'days' => (int) $r['days'],
                'status' => (string) $r['status'],
            ];
        }

        return Response::ok([
            'year' => $year,
            'allocated' => (int) $bal['quota'],
            'used' => (int) $bal['used'],
            'pending' => (int) $bal['pending'],
            'available' => (int) $bal['available'],
            'requests' => $outRequests,
        ]);
    }

    /**
     * POST /voice/absences
     */
    public function requestAbsence(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('disponibilidad.absences.request');

        $employeeId = $principal->employeeId;
        if ($employeeId === null || $employeeId <= 0) {
            throw new ForbiddenException("Caller account is not linked to an employee profile");
        }

        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();
        $token = $body['confirmation_token'] ?? null;

        if (!empty($token)) {
            $res = $this->pendingActions->confirm($principal, (string) $token, null, $sessionId);
            $rData = $res['result'] ?? [];
            return Response::ok([
                'id' => (int) ($rData['id'] ?? 0),
                'status' => 'requested',
                'days' => (int) ($rData['days_count'] ?? $rData['days'] ?? 0),
                'available_after' => (int) ($rData['available_after'] ?? 0),
            ], 201);
        }

        $dateFrom = trim((string) ($body['date_from'] ?? ''));
        $dateTo = trim((string) ($body['date_to'] ?? ''));
        $reasonType = trim((string) ($body['reason_type'] ?? 'vacation'));
        $reasonText = isset($body['reason_text']) ? trim((string) $body['reason_text']) : null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            throw new ValidationException(['dates' => 'Valid date_from and date_to (YYYY-MM-DD) are required']);
        }
        if ($dateFrom > $dateTo) {
            throw new ValidationException(['date_from' => 'date_from cannot be after date_to']);
        }

        // Count working days
        $counter = new LeaveDayCounter();
        $countRes = $counter->count($dateFrom, $dateTo);
        $requestedDays = (int) $countRes['days'];
        $excludedDates = (array) ($countRes['excluded_dates'] ?? []);

        // Deductible determination
        $deductibleStr = (string) Config::get('leave', 'deductible_reasons', 'vacation,personal');
        $deductibles = array_filter(array_map('trim', explode(',', strtolower($deductibleStr))));
        $deductsBalance = in_array(strtolower($reasonType), $deductibles, true);

        $yr = (int) substr($dateFrom, 0, 4);
        $bal = LeaveBalanceService::balance($this->pdo, $employeeId, $yr);
        $availableNow = (int) $bal['available'];

        if ($deductsBalance) {
            if ($requestedDays > $availableNow) {
                throw new ValidationException([
                    'insufficient_balance' => [
                        'year' => $yr,
                        'available' => $availableNow,
                        'requested' => $requestedDays,
                    ],
                ], 'Insufficient leave balance');
            }
            $availableAfter = max(0, $availableNow - $requestedDays);
            $preview = "Solicitaré días libres del {$dateFrom} al {$dateTo}: {$requestedDays} días hábiles. Le quedarían {$availableAfter} días. ¿La envío?";
        } else {
            $availableAfter = $availableNow;
            $preview = "Solicitaré días libres del {$dateFrom} al {$dateTo}: {$requestedDays} días. Este motivo no descuenta de su saldo. ¿La envío?";
        }

        $payload = [
            'employee_id' => $employeeId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'reason_type' => $reasonType,
            'reason_text' => $reasonText,
            'days' => $requestedDays,
            'available_after' => $availableAfter,
            'channel' => 'voice',
        ];

        $action = $this->pendingActions->create($principal, 'voice', 'absence.request', $payload, $preview, null, $sessionId);

        throw new ConfirmationRequiredException([
            'text' => $preview,
            'days' => $requestedDays,
            'excluded_dates' => $excludedDates,
            'available_after' => $availableAfter,
        ], $action['action_id']);
    }

    private function requirePinLevel(Request $req): void
    {
        $session = (array) $req->attr('call_session');
        if (($session['verification'] ?? 'none') !== 'pin') {
            throw new VerificationRequiredException("PIN verification required", "pin");
        }
    }
}
