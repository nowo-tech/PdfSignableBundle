<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm\Storage;

use Nowo\PdfSignableBundle\AcroForm\AcroFormOverrides;

/**
 * Contract for persisting AcroForm overrides per document.
 *
 * Default implementation uses session; the app can implement this for DB/Redis.
 * When acroform.overrides_storage is not "session", the bundle aliases this interface to the configured service id.
 */
interface AcroFormOverridesStorageInterface
{
    /**
     * Gets stored overrides for the given document key.
     *
     * @param string $documentKey Unique key identifying the document (e.g. from the frontend)
     *
     * @return AcroFormOverrides|null The overrides or null if none stored
     */
    public function get(string $documentKey): ?AcroFormOverrides;

    /**
     * Saves overrides for the given document key.
     *
     * @param string            $documentKey Unique key identifying the document
     * @param AcroFormOverrides $overrides   Overrides to store (field id => override data)
     */
    public function set(string $documentKey, AcroFormOverrides $overrides): void;

    /**
     * Removes stored overrides for the given document key.
     *
     * @param string $documentKey Unique key identifying the document
     */
    public function remove(string $documentKey): void;
}
