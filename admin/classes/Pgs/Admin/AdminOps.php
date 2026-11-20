<?php

declare(strict_types=1);

namespace Pgs\Admin;

use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Requests\RequestsAdminService;
use Pgs\Responders\VoiceSettingsService;

final class AdminOps
{
    public const OPERATIONS = [
        'req_list', 'req_get', 'req_status', 'req_assign', 'req_note', 'req_review', 'req_property', 'req_summary', 'req_users', 'req_properties',
        'vst_overview', 'vst_responders', 'vst_responder_save', 'vst_responder_delete',
        'vst_phones', 'vst_phone_save', 'vst_phone_toggle', 'vst_phone_delete',
        'vst_discards', 'vst_discard_assign', 'vst_discard_dismiss',
        'vst_settings', 'vst_settings_save', 'vst_users', 'vst_properties',
    ];

    /**
     * @param array<string,mixed> $r
     */
    public static function handle(string $op, AdminContext $ctx, array $r): mixed
    {
        $requests = new RequestsAdminService();
        $voice = new VoiceSettingsService();
        $id = (int) ($r['id'] ?? 0);

        return match ($op) {
            'req_list' => $requests->list($ctx, $r),
            'req_get' => $requests->detail($ctx, self::id($id)),
            'req_status' => self::afterChange($requests, $ctx, self::id($id), fn () => $requests->changeStatus($ctx, $id, (string) ($r['status'] ?? ''), self::text($r, 'note'))),
            'req_assign' => self::afterChange($requests, $ctx, self::id($id), fn () => $requests->assign($ctx, $id, ($r['user_id'] ?? '') === '' ? null : (int) $r['user_id'], self::text($r, 'note'))),
            'req_note' => self::afterChange($requests, $ctx, self::id($id), fn () => $requests->addNote($ctx, $id, (string) ($r['note'] ?? ''))),
            'req_review' => self::afterChange($requests, $ctx, self::id($id), fn () => $requests->markReviewed($ctx, $id)),
            'req_property' => self::afterChange($requests, $ctx, self::id($id), fn () => $requests->setProperty($ctx, $id, (int) ($r['unidad_id'] ?? 0))),
            'req_summary' => $requests->summary($ctx),
            'req_users' => $requests->assignableUsers($ctx),
            'req_properties' => $requests->properties($ctx),
            'vst_overview' => $voice->overview(),
            'vst_responders' => $voice->listResponders(),
            'vst_responder_save' => $voice->saveResponder(self::responderInput($r)),
            'vst_responder_delete' => self::done(fn () => $voice->deleteResponder(self::id($id))),
            'vst_phones' => $voice->phones(self::text($r, 'q'), self::text($r, 'kind')),
            'vst_phone_save' => $voice->savePhone($r),
            'vst_phone_toggle' => self::done(fn () => $voice->setPhoneActive(self::id($id), !empty($r['active']) && $r['active'] !== 'false' && $r['active'] !== '0')),
            'vst_phone_delete' => self::done(fn () => $voice->deletePhone(self::id($id))),
            'vst_discards' => $voice->discards(),
            'vst_discard_assign' => $voice->assignDiscard(self::id($id), (string) ($r['phone'] ?? ''), (int) ($r['unidad_id'] ?? 0)),
            'vst_discard_dismiss' => self::done(fn () => $voice->dismissDiscard(self::id($id))),
            'vst_settings' => $voice->settings(),
            'vst_settings_save' => self::saveSettings($voice, $ctx, $r),
            'vst_users' => $requests->userDirectory(),
            'vst_properties' => $voice->properties(),
            default => throw new NotFoundException('Unknown operation'),
        };
    }

    private static function id(int $id): int
    {
        if ($id <= 0) {
            throw new ValidationException(['id' => 'Missing identifier']);
        }

        return $id;
    }

    /**
     * @param array<string,mixed> $r
     */
    private static function text(array $r, string $key): ?string
    {
        $value = trim((string) ($r[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private static function responderInput(array $r): array
    {
        foreach (['receives_transfers', 'receives_alerts', 'is_owner', 'is_active'] as $flag) {
            if (array_key_exists($flag, $r)) {
                $r[$flag] = $r[$flag] === 'true' || $r[$flag] === '1' || $r[$flag] === 1 || $r[$flag] === true ? 1 : 0;
            }
        }

        return $r;
    }

    /**
     * @param array<string,mixed> $r
     */
    private static function saveSettings(VoiceSettingsService $voice, AdminContext $ctx, array $r): array
    {
        $payload = json_decode((string) ($r['payload'] ?? ''), true);
        if (!is_array($payload)) {
            throw new ValidationException(['payload' => 'Invalid settings payload']);
        }
        $voice->saveSettings($payload, $ctx->userId);

        return $voice->settings();
    }

    /**
     * @return array<string,mixed>
     */
    private static function afterChange(RequestsAdminService $requests, AdminContext $ctx, int $id, callable $change): array
    {
        $change();

        return $requests->detail($ctx, $id);
    }

    /**
     * @return array{done:true}
     */
    private static function done(callable $action): array
    {
        $action();

        return ['done' => true];
    }
}
