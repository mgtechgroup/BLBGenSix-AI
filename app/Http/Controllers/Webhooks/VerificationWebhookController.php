<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Verification;
use App\Models\User;
use Illuminate\Http\Request;

class VerificationWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        match ($payload['event']) {
            'verification.completed' => $this->handleVerificationCompleted($payload),
            'verification.failed' => $this->handleVerificationFailed($payload),
            default => null,
        };

        return response()->json(['message' => 'Webhook handled']);
    }

    protected function handleVerificationCompleted(array $payload): void
    {
        $verification = Verification::find($payload['verification_id']);

        if ($verification) {
            $verification->update([
                'status' => Verification::STATUS_APPROVED,
                'age_verified' => true,
                'reviewed_at' => now(),
            ]);

            $verification->user->update([
                'is_verified_adult' => true,
                'verification_status' => 'approved',
                'verified_at' => now(),
            ]);
        }
    }

    protected function handleVerificationFailed(array $payload): void
    {
        $verification = Verification::find($payload['verification_id']);

        if ($verification) {
            $verification->update([
                'status' => Verification::STATUS_REJECTED,
            ]);

            $verification->user->update([
                'verification_status' => 'rejected',
            ]);
        }
    }
}
