<?php

/**
 * Copyright 2004-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime;

use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Rfc822Parser;
use Horde\Mime\Headers\Addresses;
use Horde\Mime\Headers\DateHeader;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Headers\MessageId;
use Horde\Mime\Headers\UserAgent;

/**
 * Message Disposition Notification (RFC 3798).
 *
 * Builds MDN Part trees — does NOT send them.
 * Caller is responsible for rendering and dispatching via Transport.
 */
final class Mdn
{
    public const MDN_HEADER = 'Disposition-Notification-To';

    private TextFormatter $textFormatter;

    public function __construct(
        private readonly HeaderCollection $originalHeaders,
        private readonly ?string $originalBody = null,
        ?TextFormatter $textFormatter = null,
    ) {
        $this->textFormatter = $textFormatter ?? new FlowedFormatter();
    }

    /**
     * Returns the address(es) to return the MDN to, or null if none requested.
     */
    public function mdnReturnAddress(): ?AddressList
    {
        $header = $this->originalHeaders->get(strtolower(self::MDN_HEADER));
        if ($header === null) {
            return null;
        }

        $parser = new Rfc822Parser();

        return $parser->parseAddressList($header->value());
    }

    /**
     * Is explicit user confirmation needed before sending the MDN?
     * (RFC 3798 section 2.1 heuristics.)
     */
    public function userConfirmationNeeded(): bool
    {
        $returnPath = $this->originalHeaders->get('return-path');
        if ($returnPath === null) {
            return true;
        }

        $mdnHeader = $this->originalHeaders->get(strtolower(self::MDN_HEADER));
        if ($mdnHeader === null) {
            return false;
        }

        $parser = new Rfc822Parser();
        $mdnAddresses = $parser->parseAddressList($mdnHeader->value());

        if ($mdnAddresses->count() === 0) {
            return false;
        }

        if ($mdnAddresses->count() > 1) {
            return true;
        }

        $returnAddresses = $parser->parseAddressList($returnPath->value());
        if ($returnAddresses->count() === 0) {
            return true;
        }

        return !$mdnAddresses->match($returnAddresses->first());
    }

    /**
     * Build the MDN message as a ComposedMessage.
     * Caller is responsible for sending.
     *
     * @param bool   $manualAction   Was this a manual action?
     * @param bool   $manualSending  Was sending manual?
     * @param string $type           'displayed' or 'deleted'.
     * @param string $reportingUa    Name of the reporting user agent.
     * @param array  $opts           Options: 'charset', 'from_addr'.
     * @param array  $modifiers      Disposition modifiers (e.g. 'error').
     * @param array  $errors         If modifier is 'error', error details.
     */
    public function generate(
        bool $manualAction,
        bool $manualSending,
        string $type,
        string $reportingUa,
        array $opts = [],
        array $modifiers = [],
        array $errors = [],
    ): ComposedMessage {
        $charset = $opts['charset'] ?? 'UTF-8';
        $fromAddr = $opts['from_addr'] ?? null;

        $mdnHeader = $this->originalHeaders->get(strtolower(self::MDN_HEADER));
        if ($mdnHeader === null) {
            throw new MimeException('Need at least one address to send MDN to.');
        }

        $parser = new Rfc822Parser();
        $toList = $parser->parseAddressList($mdnHeader->value());
        $ua = UserAgent::create();

        $messageHeaders = new HeaderCollection();
        $messageHeaders = $messageHeaders->with(MessageId::create());
        $messageHeaders = $messageHeaders->with($ua);
        $messageHeaders = $messageHeaders->withRaw('Auto-Submitted', 'auto-replied');
        $messageHeaders = $messageHeaders->with(DateHeader::create());

        if ($fromAddr !== null) {
            $messageHeaders = $messageHeaders->withRaw('From', $fromAddr);
        }

        $messageHeaders = $messageHeaders->withRaw('To', $toList->writeAddress());
        $messageHeaders = $messageHeaders->withRaw('Subject', 'Disposition Notification');

        $humanText = $this->buildHumanText($type, $charset);
        $formatted = ($this->textFormatter)($humanText, $charset);
        $machineText = $this->buildMachineText(
            $manualAction,
            $manualSending,
            $type,
            $reportingUa,
            $ua->value(),
            $fromAddr,
            $modifiers,
            $errors,
        );
        $originalText = $this->buildOriginalPart();

        $part1Params = array_merge(['charset' => $charset], $formatted->params);
        $part1 = (new PartBuilder())
            ->setContentType('text/plain', $part1Params)
            ->setBody($formatted->text)
            ->build();

        $part2 = (new PartBuilder())
            ->setContentType('message/disposition-notification')
            ->setBody($machineText)
            ->build();

        $part3 = (new PartBuilder())
            ->setContentType('message/rfc822')
            ->setBody($originalText)
            ->build();

        $report = (new PartBuilder())
            ->setContentType('multipart/report', ['report-type' => 'disposition-notification'])
            ->addChild($part1)
            ->addChild($part2)
            ->addChild($part3);
        $rootPart = $report->build();

        return new ComposedMessage($rootPart, $messageHeaders, $toList);
    }

    /**
     * Build a HeaderCollection with MDN request header added.
     */
    public static function addMdnRequestHeader(HeaderCollection $headers, string $to): HeaderCollection
    {
        return $headers->withRaw(self::MDN_HEADER, $to);
    }

    private function buildHumanText(string $type, string $charset): string
    {
        if ($type !== 'displayed') {
            return '';
        }

        $date = $this->originalHeaders->get('date');
        $to = $this->originalHeaders->get('to');
        $subject = $this->originalHeaders->get('subject');

        return sprintf(
            "The message sent on %s to %s with subject \"%s\" has been displayed.\n\n"
            . "This is no guarantee that the message has been read or understood.",
            $date !== null ? $date->value() : '(unknown)',
            $to !== null ? $to->value() : '(unknown)',
            $subject !== null ? $subject->value() : '(no subject)',
        );
    }

    private function buildMachineText(
        bool $manualAction,
        bool $manualSending,
        string $type,
        string $reportingUa,
        string $uaValue,
        ?string $fromAddr,
        array $modifiers,
        array $errors,
    ): string {
        $lines = [];
        $lines[] = 'Reporting-UA: ' . $reportingUa . '; ' . $uaValue;

        $origRecip = $this->originalHeaders->get('original-recipient');
        if ($origRecip !== null) {
            $lines[] = 'Original-Recipient: rfc822;' . $origRecip->value();
        }

        if ($fromAddr !== null) {
            $lines[] = 'Final-Recipient: rfc822;' . $fromAddr;
        }

        $msgId = $this->originalHeaders->get('message-id');
        if ($msgId !== null) {
            $lines[] = 'Original-Message-ID: ' . $msgId->value();
        }

        $disposition = ($manualAction ? 'manual-action' : 'automatic-action')
            . '/'
            . ($manualSending ? 'MDN-sent-manually' : 'MDN-sent-automatically')
            . '; '
            . $type;

        if (!empty($modifiers)) {
            $disposition .= '/' . implode(', ', $modifiers);
        }
        $lines[] = 'Disposition: ' . $disposition;

        if (in_array('error', $modifiers, true) && isset($errors['error'])) {
            $lines[] = 'Error: ' . $errors['error'];
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    private function buildOriginalPart(): string
    {
        $text = $this->originalHeaders->toString("\r\n") . "\r\n";
        if ($this->originalBody !== null) {
            $text .= "\r\n" . $this->originalBody;
        }

        return $text;
    }
}
