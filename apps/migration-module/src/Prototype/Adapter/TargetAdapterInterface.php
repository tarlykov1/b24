<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter;

use MigrationModule\Prototype\Adapter\Target\ApplyAdapter;
use MigrationModule\Prototype\Adapter\Target\RestTargetAdapter;

interface TargetAdapterInterface extends RestTargetAdapter, ApplyAdapter
{
}
