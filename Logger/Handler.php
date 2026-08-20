<?php

declare(strict_types=1);

namespace Ideo\Dhl\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Level;

class Handler extends Base
{
    /**
     * File name for DHL API logs.
     *
     * @var string
     */
    protected $fileName = '/var/log/dhl_api.log';

    /**
     * Logging level.
     *
     * @var int
     */
    protected $loggerType = Level::Debug;
}
