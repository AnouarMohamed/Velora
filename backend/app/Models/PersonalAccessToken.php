<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * PersonalAccessToken Model
 *
 * Custom implementation of Sanctum's PersonalAccessToken for MongoDB.
 * It manages API tokens used for user authentication.
 *
 * @property string $_id MongoDB document ID
 * @property string $tokenable_type Class name of the model that owns the token
 * @property string $tokenable_id ID of the model that owns the token
 * @property string $name Friendly name for the token
 * @property string $token Hashed token value
 * @property array|null $abilities List of permissions granted to this token
 * @property Carbon|null $last_used_at Timestamp when the token was last used
 * @property Carbon|null $expires_at Timestamp when the token expires
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $tokenable The owner of the token
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use DocumentModel;

    /**
     * The database connection used by the model.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * Sanctum validates bearer token IDs based on the model key type.
     *
     * MongoDB ObjectIds are hex strings, so this must not use Eloquent's default
     * integer key type or Sanctum will reject valid tokens before lookup.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The table/collection associated with the model.
     *
     * @var string
     */
    protected $table = 'personal_access_tokens';
}
