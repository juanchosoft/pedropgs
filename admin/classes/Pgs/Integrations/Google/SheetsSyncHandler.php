<?php

declare(strict_types=1);

namespace Pgs\Integrations\Google;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Json;

final class SheetsSyncHandler
{
    private ServiceAccountAuth $auth;
    private ClientInterface $http;
    private string $spreadsheetId;
    private \PDO $pdo;

    public function __construct(
        ?ServiceAccountAuth $auth = null,
        ?ClientInterface $http = null,
        ?\PDO $pdo = null
    ) {
        $this->auth = $auth ?? new ServiceAccountAuth();
        $this->spreadsheetId = (string) Config::get('google', 'spreadsheet_id', '');
        $this->pdo = $pdo ?? Db::pdo();

        $this->http = $http ?? new Client([
            'base_uri' => 'https://sheets.googleapis.com/v4/spreadsheets/',
            'timeout' => 15.0,
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ]);
    }

    /**
     * Tests Google Sheets connection by reading row 1.
     */
    public function testConnection(): array
    {
        if ($this->spreadsheetId === '') {
            return ['ok' => false, 'message' => 'spreadsheet_id is not configured'];
        }

        if (!$this->auth->isConfigured()) {
            return ['ok' => false, 'message' => 'Google Service Account credentials file is not configured'];
        }

        try {
            $token = $this->auth->getAccessToken();
            $res = $this->http->request('GET', "{$this->spreadsheetId}/values/Tasks!A1:N1", [
                'headers' => ['Authorization' => "Bearer {$token}"],
            ]);

            $data = Json::decode((string) $res->getBody());
            return [
                'ok' => true,
                'message' => 'Google Sheets connection successful',
                'range' => $data['range'] ?? 'Tasks!A1:N1',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Google Sheets test error: ' . $e->getMessage()];
        }
    }

    /**
     * Syncs a task row to Google Sheets.
     */
    public function syncTask(int $taskId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, u.nombre as unit_name, 
                    COALESCE(usr.nombre, t.requested_by_name) as requester_name,
                    emp.nombre as assignee_name
             FROM tec_tasks t
             LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
             LEFT JOIN tec_usuarios usr ON usr.id = t.created_by_user_id
             LEFT JOIN tec_employee emp ON emp.id = t.assignee_employee_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$task) {
            return;
        }

        $rowValues = [
            (string) ($task['code'] ?? "TSK-{$taskId}"),
            (string) ($task['unit_name'] ?? 'N/A'),
            (string) ($task['location_label'] ?? ''),
            (string) ($task['title'] ?? ''),
            (string) ($task['priority'] ?? 'normal'),
            (string) ($task['status'] ?? 'pending'),
            (string) ($task['assignee_name'] ?? 'Unassigned'),
            (string) ($task['required_date'] ?? ''),
            (string) ($task['due_date'] ?? ''),
            (string) ($task['origin'] ?? 'web'),
            (string) ($task['requester_name'] ?? ''),
            (string) ($task['created_at'] ?? ''),
            (string) ($task['updated_at'] ?? ''),
            (string) ($task['completed_at'] ?? ''),
        ];

        $key = (string) $rowValues[0];
        $rowHash = hash('sha256', Json::encode($rowValues));

        // Check last hash in tec_sync_links
        $linkStmt = $this->pdo->prepare(
            "SELECT last_hash FROM tec_sync_links 
             WHERE target = 'sheets' AND entity_type = 'task' AND entity_id = :eid LIMIT 1"
        );
        $linkStmt->execute([':eid' => $taskId]);
        $existingHash = $linkStmt->fetchColumn();

        if ($existingHash !== false && $existingHash === $rowHash) {
            return; // No changes, skip remote rewrite
        }

        if ($this->auth->isConfigured() && $this->spreadsheetId !== '') {
            $this->upsertRow('Tasks', $key, $rowValues);
        }

        // Record sync link atomically
        $ins = $this->pdo->prepare(
            "INSERT INTO tec_sync_links (
                target, entity_type, entity_id, external_id, last_hash, synced_at
            ) VALUES (
                'sheets', 'task', :eid, :rem, :h, NOW()
            ) ON DUPLICATE KEY UPDATE 
                external_id = VALUES(external_id), 
                last_hash = VALUES(last_hash), 
                synced_at = VALUES(synced_at)"
        );
        $ins->execute([
            ':eid' => $taskId,
            ':rem' => $key,
            ':h' => $rowHash,
        ]);
    }

    /**
     * Syncs an expense (and its items) to Google Sheets.
     */
    public function syncExpense(int $expenseId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.nombre as unit_name, usr.nombre as user_name
             FROM tec_expenses e
             LEFT JOIN tbl_unidades u ON u.id = e.unidad_id
             LEFT JOIN tec_usuarios usr ON usr.id = e.created_by_user_id
             WHERE e.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $expenseId]);
        $exp = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$exp) {
            return;
        }

        $itemsStmt = $this->pdo->prepare(
            'SELECT * FROM tec_expense_items WHERE expense_id = :id ORDER BY line_number ASC'
        );
        $itemsStmt->execute([':id' => $expenseId]);
        $items = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($items)) {
            $items = [[
                'line_number' => 1,
                'description' => 'General expense',
                'quantity' => 1,
                'unit_price' => $exp['total_amount'],
                'line_total' => $exp['total_amount'],
            ]];
        }

        foreach ($items as $item) {
            $lineNum = (int) ($item['line_number'] ?? 1);
            $key = ($exp['code'] ?? "EXP-{$expenseId}") . "#{$lineNum}";

            $rowValues = [
                $key,
                (string) ($exp['code'] ?? "EXP-{$expenseId}"),
                (string) ($exp['purchase_date'] ?? ''),
                (string) ($exp['unit_name'] ?? 'N/A'),
                (string) ($exp['location_label'] ?? ''),
                (string) ($exp['vendor'] ?? ''),
                (string) ($exp['receipt_number'] ?? ''),
                (string) $lineNum,
                (string) ($item['description'] ?? ''),
                (string) ($item['quantity'] ?? '1'),
                number_format((float) ($item['unit_price'] ?? 0), 2, '.', ''),
                number_format((float) ($item['line_total'] ?? 0), 2, '.', ''),
                number_format((float) ($exp['total_amount'] ?? 0), 2, '.', ''),
                (string) ($exp['currency'] ?? 'USD'),
                (string) ($exp['billing_status'] ?? 'pending'),
                (string) ($exp['invoice_id'] ?? ''),
                (string) ($exp['user_name'] ?? 'Staff'),
                (string) ($exp['channel'] ?? 'web'),
                (string) ($exp['created_at'] ?? ''),
                (string) ($exp['updated_at'] ?? ''),
            ];

            $rowHash = hash('sha256', Json::encode($rowValues));

            if ($this->auth->isConfigured() && $this->spreadsheetId !== '') {
                $this->upsertRow('Expenses', $key, $rowValues);
            }

            // Update sync link atomically
            $ins = $this->pdo->prepare(
                "INSERT INTO tec_sync_links (
                    target, entity_type, entity_id, external_id, last_hash, synced_at
                ) VALUES (
                    'sheets', 'expense', :eid, :rem, :h, NOW()
                ) ON DUPLICATE KEY UPDATE 
                    external_id = VALUES(external_id), 
                    last_hash = VALUES(last_hash), 
                    synced_at = VALUES(synced_at)"
            );
            $ins->execute([
                ':eid' => $expenseId,
                ':rem' => $key,
                ':h' => $rowHash,
            ]);
        }
    }

    /**
     * Upserts a row into Google Sheet tab by matching key in Column A.
     */
    private function upsertRow(string $tab, string $key, array $rowValues): void
    {
        $token = $this->auth->getAccessToken();

        // 1. Read Column A to find key
        $res = $this->http->request('GET', "{$this->spreadsheetId}/values/{$tab}!A:A", [
            'headers' => ['Authorization' => "Bearer {$token}"],
        ]);
        $colData = Json::decode((string) $res->getBody());
        $colValues = $colData['values'] ?? [];

        $foundRow = null;
        foreach ($colValues as $idx => $row) {
            if (isset($row[0]) && trim((string) $row[0]) === $key) {
                $foundRow = $idx + 1; // 1-indexed
                break;
            }
        }

        if ($foundRow !== null) {
            // Update existing row
            $endCol = chr(ord('A') + count($rowValues) - 1);
            $range = "{$tab}!A{$foundRow}:{$endCol}{$foundRow}";
            $this->http->request('PUT', "{$this->spreadsheetId}/values/{$range}", [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'query' => ['valueInputOption' => 'RAW'],
                'json' => [
                    'range' => $range,
                    'majorDimension' => 'ROWS',
                    'values' => [$rowValues],
                ],
            ]);
        } else {
            // Append new row
            $this->http->request('POST', "{$this->spreadsheetId}/values/{$tab}!A1:append", [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'query' => [
                    'valueInputOption' => 'RAW',
                    'insertDataOption' => 'INSERT_ROWS',
                ],
                'json' => [
                    'majorDimension' => 'ROWS',
                    'values' => [$rowValues],
                ],
            ]);
        }
    }

    public function getTaskHeaders(): array
    {
        return [
            'code', 'unit_name', 'location_label', 'title', 'priority', 'status',
            'assignee_name', 'required_date', 'due_date', 'origin', 'requester_name',
            'created_at', 'updated_at', 'completed_at'
        ];
    }

    public function getExpenseHeaders(): array
    {
        return [
            'key', 'code', 'purchase_date', 'unit_name', 'location_label', 'vendor',
            'receipt_number', 'line_number', 'description', 'quantity', 'unit_price',
            'line_total', 'total_amount', 'currency', 'billing_status', 'invoice_id',
            'user_name', 'channel', 'created_at', 'updated_at'
        ];
    }
}
