<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Exception;

use Exception;
use Throwable;

class ApiException extends Exception
{
    /**
     * @var int[] $errorCodes Cloudflare error codes
     */
    private $errorCodes;

    /**
     * @param string $message
     * @param int $httpStatus
     * @param Throwable|null $previous
     * @param int[] $errorCodes
     */
    public function __construct(string $message, int $httpStatus = 0, ?Throwable $previous = null, array $errorCodes = [])
    {
        parent::__construct($message, $httpStatus, $previous);
        $this->errorCodes = $errorCodes;
    }

    /**
     * @return int
     */
    public function getHttpStatus(): int
    {
        return (int)$this->getCode();
    }

    /**
     * @return int[]
     */
    public function getErrorCodes(): array
    {
        return $this->errorCodes;
    }

    /**
     * @return bool
     */
    public function isNotFound(): bool
    {
        return $this->getHttpStatus() === 404;
    }
}
