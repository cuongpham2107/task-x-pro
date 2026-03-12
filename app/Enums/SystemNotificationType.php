<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum SystemNotificationType: string
{
    use HasEnumOptions;

    case TaskRejected = 'task_rejected';
    case ApprovalRequestCeo = 'approval_request_ceo';
    case ApprovalRequestLeader = 'approval_request_leader';
    case PicOverloadWarning = 'pic_overload_warning';

    public function label(): string
    {
        return match ($this) {
            self::TaskRejected => 'Task bị từ chối',
            self::ApprovalRequestCeo => 'Yêu cầu phê duyệt (CEO)',
            self::ApprovalRequestLeader => 'Yêu cầu phê duyệt (Leader)',
            self::PicOverloadWarning => 'Cảnh báo quá tải PIC',
        };
    }
}
