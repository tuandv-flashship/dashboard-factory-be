<?php

namespace App\Ship\Parents\Transformers;

use Apiato\Core\Transformers\Transformer as AbstractTransformer;
use App\Ship\Traits\HashIdTrait;

abstract class Transformer extends AbstractTransformer
{
    use HashIdTrait;
}
