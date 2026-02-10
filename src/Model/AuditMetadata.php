<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Model;

/**
 * Recommended keys for audit metadata (evidence trail for signatures).
 *
 * The bundle does not fill these automatically except when audit.fill_from_request
 * is enabled (then IP, user_agent, submitted_at are set by the controller).
 * Your app can set the rest in a SIGNATURE_COORDINATES_SUBMITTED listener (e.g. user_id,
 * session_id, TSA token from your timestamp authority).
 *
 * @see SignatureCoordinatesModel::getAuditMetadata()
 * @see docs/SIGNING_ADVANCED.md
 */
final class AuditMetadata
{
    /** Server or client time when the form was submitted (ISO 8601). */
    public const SUBMITTED_AT = 'submitted_at';

    /** Client IP address. */
    public const IP = 'ip';

    /** User-Agent header. */
    public const USER_AGENT = 'user_agent';

    /** Application user identifier (e.g. username or ID). Set by your listener. */
    public const USER_ID = 'user_id';

    /** Session identifier. Set by your listener. */
    public const SESSION_ID = 'session_id';

    /** Optional RFC 3161 timestamp token (base64). Set by your listener after calling your TSA. */
    public const TSA_TOKEN = 'tsa_token';

    /** Optional human-readable signing method (e.g. "draw", "upload", "pades"). */
    public const SIGNING_METHOD = 'signing_method';

    /**
     * Non-instantiable: only key constants are used.
     */
    private function __construct()
    {
    }
}
