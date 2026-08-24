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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class BankIntegrationService
{
    public function __construct(private BankTransactionRepository $bankTransactions, private TransactionService $transactions) {}

    public function institutions(): array
    {
        $this->ensureConfigured();

        return Http::withToken($this->accessToken())->acceptJson()->get($this->apiUrl('/institutions/'), ['country' => config('services.gocardless.country')])->throw()->json();
    }

    public function authorizationUrl(Workspace $workspace, User $user, string $institutionId): string
    {
        $this->ensureConfigured();
        $institution = collect($this->institutions())->firstWhere('id', $institutionId);
        if (! $institution) {
            throw ValidationException::withMessages(['institution_id' => 'El banco seleccionado no está disponible.']);
        }
        $state = Str::random(64);
        $connection = $workspace->bankConnections()->create(['user_id' => $user->id, 'provider' => 'gocardless', 'institution_id' => $institutionId, 'provider_name' => $institution['name'], 'status' => 'pending']);
        session()->put('gocardless_oauth', ['state' => $state, 'connection_id' => $connection->id, 'user_id' => $user->id]);
        $requisition = Http::withToken($this->accessToken())->acceptJson()->post($this->apiUrl('/requisitions/'), [
            'redirect' => config('services.gocardless.redirect_uri').'?state='.urlencode($state),
            'institution_id' => $institutionId,
            'reference' => 'mislucas-'.$connection->id.'-'.Str::lower(Str::random(12)),
            'user_language' => 'ES',
        ])->throw()->json();
        $connection->update(['external_id' => $requisition['id']]);

        return $requisition['link'];
    }

    public function complete(string $state, User $user): BankConnection
    {
        $oauth = session()->pull('gocardless_oauth');
        if (! is_array($oauth) || ! hash_equals((string) ($oauth['state'] ?? ''), $state) || (int) ($oauth['user_id'] ?? 0) !== $user->id) {
            throw ValidationException::withMessages(['connection' => 'La autorización bancaria ha caducado o no es válida.']);
        }
        $connection = BankConnection::query()->whereKey($oauth['connection_id'])->where('user_id', $user->id)->firstOrFail();
        $requisition = Http::withToken($this->accessToken())->acceptJson()->get($this->apiUrl('/requisitions/'.$connection->external_id.'/'))->throw()->json();
        if (($requisition['status'] ?? null) !== 'LN') {
            throw ValidationException::withMessages(['connection' => 'El banco todavía no ha confirmado la conexión.']);
        }
        foreach ($requisition['accounts'] ?? [] as $externalId) {
            $this->importAccount($connection, $externalId);
        }
        $connection->update(['status' => 'active']);
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
            $response = Http::withToken($this->accessToken())->acceptJson()->get($this->apiUrl('/accounts/'.$account->external_id.'/transactions/'));
            if (! $response->successful()) {
                continue;
            }
            foreach ($response->json('transactions.booked', []) as $remote) {
                $amount = (float) ($remote['transactionAmount']['amount'] ?? 0);
                $externalId = $remote['transactionId'] ?? hash('sha256', implode('|', [$remote['bookingDate'] ?? '', $amount, $remote['remittanceInformationUnstructured'] ?? '']));
                $this->bankTransactions->import($account, [
                    'external_id' => $externalId,
                    'type' => $amount >= 0 ? 'income' : 'expense',
                    'amount' => (int) round(abs($amount) * 100),
                    'occurred_at' => $remote['bookingDate'] ?? $remote['valueDate'] ?? today()->toDateString(),
                    'description' => $remote['creditorName'] ?? $remote['debtorName'] ?? $remote['remittanceInformationUnstructured'] ?? 'Movimiento bancario',
                    'merchant_name' => $remote['creditorName'] ?? $remote['debtorName'] ?? null,
                    'classification' => isset($remote['bankTransactionCode']) ? [$remote['bankTransactionCode']] : [],
                ]);
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

    private function importAccount(BankConnection $connection, string $externalId): BankAccount
    {
        $details = Http::withToken($this->accessToken())->acceptJson()->get($this->apiUrl('/accounts/'.$externalId.'/details/'))->throw()->json('account', []);

        return $connection->accounts()->updateOrCreate(['external_id' => $externalId], ['kind' => 'account', 'display_name' => $details['displayName'] ?? $details['name'] ?? $details['product'] ?? 'Cuenta bancaria', 'currency' => $details['currency'] ?? 'EUR', 'provider_id' => $connection->institution_id]);
    }

    private function accessToken(): string
    {
        $cached = Cache::get('gocardless.access_token');
        if (is_string($cached)) {
            return Crypt::decryptString($cached);
        }
        $tokens = Http::acceptJson()->post($this->apiUrl('/token/new/'), ['secret_id' => config('services.gocardless.secret_id'), 'secret_key' => config('services.gocardless.secret_key')])->throw()->json();
        Cache::put('gocardless.access_token', Crypt::encryptString($tokens['access']), now()->addSeconds(max(60, (int) $tokens['access_expires'] - 300)));

        return $tokens['access'];
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.gocardless.api_url'), '/').$path;
    }

    private function ensureConfigured(): void
    {
        if (! config('services.gocardless.secret_id') || ! config('services.gocardless.secret_key')) {
            throw ValidationException::withMessages(['connection' => 'Configura GOCARDLESS_SECRET_ID y GOCARDLESS_SECRET_KEY para vincular un banco.']);
        }
    }
}
