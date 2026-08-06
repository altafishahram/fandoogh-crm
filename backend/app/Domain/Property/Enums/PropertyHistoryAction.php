<?php

declare(strict_types=1);

namespace App\Domain\Property\Enums;

enum PropertyHistoryAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Archived = 'archived';
    case Unarchived = 'unarchived';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case OwnersChanged = 'owners_changed';
    case AssignmentChanged = 'assignment_changed';
    case ImagesChanged = 'images_changed';
}
