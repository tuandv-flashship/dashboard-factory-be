<?php

namespace App\Containers\AppSection\FplatformData\Enums;

enum WorkType: int
{
    case In   = 0;   // Printing
    case Cat  = 2;   // Cutting
    case Pick = 100; // Picking

    /**
     * The work_status value that indicates "done" for this work type.
     *
     * - In (0):   work_status = 1 (đã in xong)
     * - Cat (2):  work_status = 0 (đã nhận vào cắt; chưa cắt = NULL)
     * - Pick (100): work_status = 1 (đã pick xong)
     */
    public function doneStatus(): int
    {
        return match ($this) {
            self::In   => 1,
            self::Cat  => 0,
            self::Pick => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::In   => 'In',
            self::Cat  => 'Cắt',
            self::Pick => 'Pick',
        };
    }
}
