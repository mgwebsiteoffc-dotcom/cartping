<?php

namespace App\Exceptions;

/**
 * Thrown when the selected WhatsApp provider cannot create message templates
 * via its API (e.g. Whatify's external API only lists templates — they must be
 * created in the Whatify dashboard). Callers surface a friendly message instead
 * of a raw HTTP error.
 */
class TemplateMustBeCreatedInDashboardException extends \RuntimeException
{
}
