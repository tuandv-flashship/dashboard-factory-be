<?php

namespace App\Ship\Parents\Tasks;

use Apiato\Core\Tasks\Task as AbstractTask;
use App\Ship\Traits\HashIdTrait;

abstract class Task extends AbstractTask
{
    use HashIdTrait;
}
