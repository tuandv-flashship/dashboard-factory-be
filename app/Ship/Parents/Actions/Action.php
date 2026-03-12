<?php

namespace App\Ship\Parents\Actions;

use Apiato\Core\Actions\Action as AbstractAction;
use App\Ship\Traits\HashIdTrait;

abstract class Action extends AbstractAction
{
    use HashIdTrait;
}
