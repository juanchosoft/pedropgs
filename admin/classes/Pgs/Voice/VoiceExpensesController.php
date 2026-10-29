<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Auth\Principal;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Exceptions\VerificationRequiredException;
use Pgs\Expenses\ExpenseService;
use Pgs\PendingActions\PendingActionService;

final class VoiceExpensesController
{
    private \PDO $pdo;
    private PendingActionService $pendingActions;
    private ExpenseService $expenses;

    public function __construct(
        ?\PDO $pdo = null,
        ?PendingActionService $pendingActions = null,
        ?ExpenseService $expenses = null
    ) {
        $this->pdo = $pdo ?? Db::pdo();
        $this->pendingActions = $pendingActions ?? new PendingActionService($this->pdo);
        $this->expenses = $expenses ?? new ExpenseService($this->pdo);
    }

    /**
     * POST /voice/expenses/draft
     */
    public function createDraft(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }

        // Safeguard D-25: UnitAdmin can never create or view expenses
        if ($principal->isUnitAdmin()) {
            throw new ForbiddenException("Access denied: Property administrators cannot register expenses (D-25)");
        }
        $principal->require('gastos.expenses.create');

        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();

        // Validate draft payload
        $draft = $this->expenses->validateDraft($principal, $body);

        $unitName = $draft['unit_name'];
        $locLabel = !empty($draft['normalized']['location_label']) ? ', ' . $draft['normalized']['location_label'] : '';
        $itemsCount = count($draft['lines']);

        $linePreviews = [];
        foreach ($draft['lines'] as $line) {
            $linePreviews[] = "{$line['quantity']} × {$line['description']} a \${$line['unit_price']} = \${$line['line_total']}";
        }
        $linesText = implode('; ', $linePreviews);
        $totalFormatted = number_format((float) $draft['total'], 2);

        $preview = "Gasto para {$unitName}{$locLabel}: {$itemsCount} artículos: {$linesText}. Total {$totalFormatted} dólares. ¿Lo confirmo?";

        $payload = array_merge($body, [
            'lines' => $draft['lines'],
            'total' => $draft['total'],
        ]);

        $action = $this->pendingActions->create(
            $principal,
            'voice',
            'expense.create',
            $payload,
            $preview,
            null,
            $sessionId
        );

        return Response::ok([
            'draft_id' => $action['action_id'],
            'lines' => $draft['lines'],
            'total' => $draft['total'],
            'currency' => $draft['currency'],
            'unit_name' => $unitName,
            'warnings' => $draft['warnings'] ?? [],
            'preview' => $preview,
        ], 201);
    }

    /**
     * POST /voice/expenses/drafts/{id}/confirm
     */
    public function confirmDraft(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }

        if ($principal->isUnitAdmin()) {
            throw new ForbiddenException("Access denied: Property administrators cannot register expenses (D-25)");
        }
        $principal->require('gastos.expenses.create');

        $draftId = (string) $req->attr('id');
        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];

        $res = $this->pendingActions->confirm($principal, $draftId, null, $sessionId);
        $rData = $res['result'] ?? [];

        return Response::ok([
            'code' => $rData['code'] ?? '',
            'total' => (float) ($rData['total_amount'] ?? 0),
        ], 201);
    }

    /**
     * DELETE /voice/expenses/drafts/{id}
     */
    public function discardDraft(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }

        if ($principal->isUnitAdmin()) {
            throw new ForbiddenException("Access denied: Property administrators cannot manage expenses (D-25)");
        }
        $principal->require('gastos.expenses.create');

        $draftId = (string) $req->attr('id');
        $this->pendingActions->cancel($principal, $draftId);

        return Response::ok(['cancelled' => true]);
    }

    private function requirePinLevel(Request $req): void
    {
        $session = (array) $req->attr('call_session');
        if (($session['verification'] ?? 'none') !== 'pin') {
            throw new VerificationRequiredException("PIN verification required", "pin");
        }
    }
}
