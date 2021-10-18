<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\MySQL\Driver\Common\Inheritance\STI;

// phpcs:ignore
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\STI\ManyToManyTest as CommonTest;

/**
 * @group driver
 * @group driver-mysql
 */
class ManyToManyTest extends CommonTest
{
    public const DRIVER = 'mysql';
}
