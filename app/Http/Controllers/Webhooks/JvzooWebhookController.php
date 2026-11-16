<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\AccessGrantedMail;
use App\Mail\WelcomeMail;
use App\Models\Product;
use App\Models\User;
use App\Services\Jvzoo\JvzooIpnVerifier;
use App\Services\Jvzoo\JvzooUserProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class JvzooWebhookController extends Controller
{
    public function __construct(
        private readonly JvzooIpnVerifier $verifier,
        private readonly JvzooUserProvisioner $provisioner,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            Log::warning('[JVZoo IPN] Verification failed', [
                'product' => $request->input('cproditem'),
                'email' => $request->input('ccustemail'),
            ]);

            return response()->json(['message' => 'Verification Failed!'], 403);
        }

        $data = $request->all();
        $type = isset($data['ctransaction']) ? (string) $data['ctransaction'] : 'SALE';
        $email = isset($data['ccustemail']) ? trim((string) $data['ccustemail']) : null;
        $transactionId = isset($data['ctransreceipt']) ? (string) $data['ctransreceipt'] : null;
        $productId = isset($data['cproditem']) ? (int) $data['cproditem'] : null;

        Log::info('[JVZoo IPN] Received', [
            'type' => $type,
            'product' => $productId,
            'email' => $email,
            'transaction' => $transactionId,
        ]);

        if (! $productId || ! $email || ! $transactionId) {
            return response()->json(['message' => 'Item does not exist.'], 422);
        }

        $product = Product::query()->where('product_id', $productId)->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found!'], 404);
        }

        return match ($type) {
            'SALE' => $this->handleSale($email, $product->funnel),
            'RFND' => $this->handleRefund($email, $product->funnel),
            default => response()->json(['message' => 'Invalid transaction type!'], 422),
        };
    }

    private function handleSale(string $email, string $roleName): JsonResponse
    {
        $result = $this->provisioner->provision($email, $roleName);
        $user = $result['user'];

        if ($result['created'] && is_string($result['password'])) {
            $error = $this->sendMail($email, new WelcomeMail($user, $result['password']));

            $notifyEmail = config('jvzoo.welcome_notify_email');
            if (is_string($notifyEmail) && $notifyEmail !== '') {
                $this->sendMail($notifyEmail, new WelcomeMail($user, $result['password']));
            }

            Log::info('[JVZoo IPN] Sale — account created', [
                'email' => $email,
                'role' => $roleName,
                'user_id' => $user->id,
                'welcome_email_sent' => $error === null,
            ]);

            return response()->json([
                'message' => $error === null
                    ? 'User created successfully!'
                    : 'User created successfully, but the welcome email could not be sent.',
                'email_sent' => $error === null,
            ]);
        }

        $error = $this->sendMail($email, new AccessGrantedMail($user, $roleName));

        Log::info('[JVZoo IPN] Sale — access added to existing account', [
            'email' => $email,
            'role' => $roleName,
            'user_id' => $user->id,
            'access_email_sent' => $error === null,
        ]);

        return response()->json([
            'message' => 'User role updated successfully!',
            'email_sent' => $error === null,
        ]);
    }

    /**
     * Retries briefly so a transient mail API error does not lose the buyer's login details.
     *
     * @return string|null The final error message, or null when sent.
     */
    private function sendMail(string $to, Mailable $mail): ?string
    {
        try {
            retry(3, fn () => Mail::to($to)->send($mail), fn (int $attempt): int => $attempt * 1000);

            return null;
        } catch (Throwable $exception) {
            Log::error('[JVZoo IPN] Email failed to send.', [
                'to' => $to,
                'mail' => class_basename($mail),
                'exception' => $exception->getMessage(),
            ]);

            report($exception);

            return $exception->getMessage();
        }
    }

    private function handleRefund(string $email, string $roleName): JsonResponse
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found!'], 404);
        }

        $this->provisioner->revokeRole($user, $roleName);

        return response()->json(['message' => 'User access revoked successfully!']);
    }
}
