<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\Parameters;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;

/**
 * The context parameters value object
 *
 * Maybe future: "Node Filter" tree or so as replacement of ReadNodePrivilege?
 * @Flow\Proxy(false)
 */
final class VisibilityConstraints
{
    /**
     * @var \DateTimeImmutable
     */
    protected $currentDateTime;

    /**
     * @var boolean
     */
    protected $disabledContentShown = false;

    protected static $currentDateTimeOnInitialization;


    private function __construct(\DateTimeImmutable $currentDateTime, bool $disabledContentShown)
    {
        $this->currentDateTime = $currentDateTime;
        $this->disabledContentShown = $disabledContentShown;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCurrentDateTime(): \DateTimeImmutable
    {
        return $this->currentDateTime;
    }

    /**
     * @return bool
     */
    public function isDisabledContentShown(): bool
    {
        return $this->disabledContentShown;
    }

    public function getHash(): string
    {
        return md5($this->currentDateTime->format(\DateTime::W3C) . '-disabled' . $this->disabledContentShown);
    }

    public static function withoutRestrictions(): VisibilityConstraints
    {
        if (!self::$currentDateTimeOnInitialization) {
            self::$currentDateTimeOnInitialization = new \DateTimeImmutable();
        }
        return new VisibilityConstraints(self::$currentDateTimeOnInitialization, true);
    }

    public static function frontend(): VisibilityConstraints
    {
        if (!self::$currentDateTimeOnInitialization) {
            self::$currentDateTimeOnInitialization = new \DateTimeImmutable();
        }

        return new VisibilityConstraints(self::$currentDateTimeOnInitialization, false);
    }
}
