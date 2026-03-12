<?php

namespace App\Containers\AppSection\AuditLog\Events;

use Illuminate\Http\UploadedFile;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

final class AuditHandlerEvent
{
    use SerializesModels;

    public string|int $referenceUser;

    private const DEFAULT_EXCLUDED_KEYS = [
        'username',
        'password',
        're_password',
        'new_password',
        'current_password',
        'password_confirmation',
        '_token',
        'token',
        'refresh_token',
        'remember_token',
        'client_secret',
        'client_id',
        'api_key',
        'access_key',
        'secret_key',
        'otp',
        'pin',
    ];
    
    public ?string $ip = null;
    public ?string $userAgent = null;
    public ?string $userType = null;
    public array $input = [];

    public function __construct(
        public string $module,
        public string $action,
        public int|string $referenceId,
        public ?string $referenceName,
        public string $type,
        int|string $referenceUser = 0,
    ) {
        $user = Auth::guard()->user() ?: Auth::guard('api')->user();
        
        if ($referenceUser === 0) {
            $referenceUser = $user ? $user->getKey() : 0;
        }

        $this->referenceUser = $referenceUser;
        $this->userType = $user ? $user->getMorphClass() : null;
        
        $this->captureRequestData();
    }
    
    private function captureRequestData(): void
    {
        try {
            $request = request();
            $this->ip = $request->ip();
            $this->userAgent = $request->userAgent();

            if (! in_array($this->action, ['loggedin', 'password'], true)) {
                $excluded = config('audit-log.excluded_request_keys', []);
                if (! is_array($excluded)) {
                    $excluded = [];
                }

                $keys = array_values(array_unique(array_merge(self::DEFAULT_EXCLUDED_KEYS, $excluded)));
                $this->input = $this->stripUnserializableValues($request->except($keys));
            }
        } catch (\Throwable) {
            // Fallback for CLI or errors
        }
    }

    /**
     * Recursively remove UploadedFile instances and other unserializable values.
     */
    private function stripUnserializableValues(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $clean[$key] = '[file:' . $value->getClientOriginalName() . ']';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->stripUnserializableValues($value);
                continue;
            }

            if (is_object($value)) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
