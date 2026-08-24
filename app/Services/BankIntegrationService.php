<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\BankTransactionRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class BankIntegrationService
{
    public function __construct(private BankTransactionRepository $bankTransactions, private TransactionService $transactions) {}

    public function institutions(): array
    {
        $response = $this->http()->get($this->apiUrl('/aspsps'), ['country' => config('services.enable_banking.country'), 'psu_type' => 'personal', 'service' => 'AIS'])->throw()->json('aspsps', []);

        return collect($response)->map(fn (array $bank): array => ['id' => $bank['name'], 'name' => $bank['name'], 'logo' => $bank['logo'] ?? null, 'country' => $bank['country']])->values()->all();
    }

    public function authorizationUrl(Workspace $workspace, User $user, string $institutionId): string
    {
        $institution = collect($this->institutions())->firstWhere('id', $institutionId);
        if (! $institution) {
            throw ValidationException::withMessages(['institution_id' => 'El banco seleccionado no está disponible.']);
        }
        $state = Str::random(64);
        $connection = $workspace->bankConnections()->create(['user_id' => $user->id, 'provider' => 'enable_banking', 'institution_id' => $institutionId, 'provider_name' => $institution['name'], 'status' => 'pending']);
        session()->put('enable_banking_oauth', ['state' => $state, 'connection_id' => $connection->id, 'user_id' => $user->id]);
        $authorization = $this->http()->post($this->apiUrl('/auth'), [
            'access' => ['valid_until' => now()->addDays(89)->toIso8601String(), 'balances' => true, 'transactions' => true],
            'aspsp' => ['name' => $institution['name'], 'country' => $institution['country']],
            'state' => $state,
            'redirect_url' => config('services.enable_banking.redirect_uri'),
            'psu_type' => 'personal',
        ])->throw()->json();
        $connection->update(['external_id' => $authorization['authorization_id'] ?? null]);

        return $authorization['url'];
    }

    public function complete(string $code, string $state, User $user): BankConnection
    {
        $oauth = session()->pull('enable_banking_oauth');
        if (! is_array($oauth) || ! hash_equals((string) ($oauth['state'] ?? ''), $state) || (int) ($oauth['user_id'] ?? 0) !== $user->id) {
            throw ValidationException::withMessages(['connection' => 'La autorización bancaria ha caducado o no es válida.']);
        }
        $connection = BankConnection::query()->whereKey($oauth['connection_id'])->where('user_id', $user->id)->firstOrFail();
        $session = $this->http()->post($this->apiUrl('/sessions'), ['code' => $code])->throw()->json();
        $connection->update(['external_id' => $session['session_id'], 'provider_name' => $session['aspsp']['name'] ?? $connection->provider_name, 'status' => 'active']);
        foreach ($session['accounts'] ?? [] as $remoteAccount) {
            $this->importAccount($connection, $remoteAccount);
        }
        $this->sync($connection);

        return $connection;
    }

    public function sync(BankConnection $connection): int
    {
        if ($connection->status !== 'active') {
            throw ValidationException::withMessages(['connection' => 'La conexión bancaria necesita una nueva autorización.']);
        }
        $count = 0;
        foreach ($connection->accounts as $account) {
            $response = $this->http()->get($this->apiUrl('/accounts/'.$account->external_id.'/transactions'), ['date_from' => now()->subDays($connection->last_synced_at ? 7 : 90)->toDateString(), 'date_to' => today()->toDateString()]);
            if (! $response->successful()) {
                continue;
            }
            foreach ($response->json('transactions', []) as $remote) {
                if (($remote['status'] ?? 'BOOK') !== 'BOOK') {
                    continue;
                }
                $amount = (float) ($remote['transaction_amount']['amount'] ?? 0);
                $type = ($remote['credit_debit_indicator'] ?? 'DBIT') === 'CRDT' ? 'income' : 'expense';
                $externalId = $remote['transaction_id'] ?? $remote['entry_reference'] ?? hash('sha256', implode('|', [$remote['booking_date'] ?? '', $amount, implode(' ', $remote['remittance_information'] ?? [])]));
                $party = $type === 'income' ? ($remote['debtor']['name'] ?? null) : ($remote['creditor']['name'] ?? null);
                $description = $party ?? implode(' ', $remote['remittance_information'] ?? []) ?: 'Movimiento bancario';
                $this->bankTransactions->import($account, ['external_id' => $externalId, 'type' => $type, 'amount' => (int) round(abs($amount) * 100), 'occurred_at' => $remote['booking_date'] ?? $remote['transaction_date'] ?? today()->toDateString(), 'description' => $description, 'merchant_name' => $party, 'classification' => array_values(array_filter([$remote['bank_transaction_code']['code'] ?? null, $remote['bank_transaction_code']['sub_code'] ?? null]))]);
                $count++;
            }
        }
        $connection->update(['last_synced_at' => now()]);

        return $count;
    }

    public function accept(BankTransaction $bankTransaction, Workspace $workspace, array $data, int $userId): Transaction
    {
        return DB::transaction(function () use ($bankTransaction, $workspace, $data, $userId): Transaction {
            $locked = BankTransaction::query()->lockForUpdate()->findOrFail($bankTransaction->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['transaction' => 'Esta operación bancaria ya ha sido gestionada.']);
            }
            $movement = $this->transactions->create($workspace, $data, $userId);
            $locked->update(['status' => 'accepted', 'transaction_id' => $movement->id]);

            return $movement;
        });
    }

    public function dismiss(BankTransaction $transaction): void
    {
        if ($transaction->status === 'pending') {
            $this->bankTransactions->dismiss($transaction);
        }
    }

    private function importAccount(BankConnection $connection, array $remote): BankAccount
    {
        return $connection->accounts()->updateOrCreate(['external_id' => $remote['uid']], ['kind' => 'account', 'display_name' => $remote['details'] ?? $remote['product'] ?? $remote['name'] ?? 'Cuenta bancaria', 'currency' => $remote['currency'] ?? 'EUR', 'provider_id' => $connection->institution_id]);
    }

    private function http(): PendingRequest
    {
        $this->ensureConfigured();

        return Http::withToken($this->jwt())->acceptJson();
    }

    private function jwt(): string
    {
        $now = time();
        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => config('services.enable_banking.application_id')], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode(['iss' => 'enablebanking.com', 'aud' => 'api.enablebanking.com', 'iat' => $now, 'exp' => $now + 3600], JSON_THROW_ON_ERROR));
        $data = $header.'.'.$payload;
        $key = openssl_pkey_get_private(file_get_contents($this->privateKeyPath()));
        if ($key === false || ! openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar la solicitud de Enable Banking.');
        }

        return $data.'.'.$this->base64Url($signature);
    }

    private function privateKeyPath(): string
    {
        $path = (string) config('services.enable_banking.private_key_path');

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.enable_banking.api_url'), '/').$path;
    }

    private function ensureConfigured(): void
    {
        if (! config('services.enable_banking.application_id') || ! is_readable($this->privateKeyPath())) {
            throw ValidationException::withMessages(['connection' => 'Configura ENABLE_BANKING_APPLICATION_ID y la clave privada para vincular un banco.']);
        }
    }
}
