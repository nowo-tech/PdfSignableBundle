<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm\Exception;

/**
 * Thrown when AcroForm editing fails (e.g. PDF has no form, field not found, invalid patch).
 */
final class AcroFormEditorException extends \RuntimeException
{
}
