<?php

namespace Pepilog;

use Psr\Log\Test\LoggerInterfaceTest as PsrLoggerInterfaceTest;
use Pepilog;

/**
 * Provides a base test class for ensuring compliance with the LoggerInterface
 *
 * Implementors can extend the class and implement abstract methods to run this as part of their test suite
 */
class LoggerInterfaceTest extends PsrLoggerInterfaceTest
{
    public $logger;
    /**
     * @return LoggerInterface
     */
    public function getLogger() {
        $this->logger = new Pepilog(Pepilog::BUFFER_ADDRESS,'debug');
        $this->logger->formatter = function($p) {
            return $p['level'].' '.$p['text'];
        };
        return $this->logger;
    }

    /**
     * This must return the log messages in order with a simple formatting: "<LOG LEVEL> <MESSAGE>"
     *
     * Example ->error('Foo') would yield "error Foo"
     *
     * @return string[]
     */
    public function getLogs(){
        return $this->logger->buffer;
    }
}
