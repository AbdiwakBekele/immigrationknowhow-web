<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\CheckrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckrWebhookController extends Controller
{
    public function __construct(
        protected CheckrService $checkrService
    ) {}

    /**
     * Handle incoming Checkr webhook.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Checkr-Signature', '');

        // Verify signature (if configured)
        if (!$this->checkrService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Checkr webhook: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Checkr webhook: Invalid JSON payload');
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info('Checkr webhook received', [
            'type' => $data['type'] ?? 'unknown',
        ]);

        try {
            $this->checkrService->processWebhook($data);
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Checkr webhook processing failed', [
                'error' => $e->getMessage(),
                'type' => $data['type'] ?? 'unknown',
            ]);

            // Return 200 to prevent Checkr from retrying
            // Log the error for manual investigation
            return response()->json(['status' => 'error logged']);
        }
    }
}
