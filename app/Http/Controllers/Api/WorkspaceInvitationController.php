<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class WorkspaceInvitationController extends Controller
{
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $member = $workspace->members()->where('user_id', $request->user()->id)->first();
        abort_unless($request->user()->is_superadmin || $member?->role === 'owner', 403);

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower($data['email']);
        abort_if($workspace->members()->whereHas('user', fn ($query) => $query->where('email', $email))->exists(), 422, 'Este usuario ya pertenece al espacio.');

        $token = Str::random(64);
        $invitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'invited_by_user_id' => $request->user()->id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'url' => url('/invitacion/'.$token),
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = $this->findValid($token);

        return response()->json([
            'workspace' => $invitation->workspace->only(['id', 'name', 'type']),
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
            'has_account' => User::query()->where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->findValid($token);
        $user = $request->user();

        if ($user) {
            throw_if(Str::lower($user->email) !== $invitation->email, ValidationException::withMessages([
                'email' => 'La invitación pertenece a otra dirección de correo.',
            ]));
        } else {
            $user = User::query()->where('email', $invitation->email)->first();
            if ($user) {
                $data = $request->validate(['password' => ['required', 'string']]);
                if (! Hash::check($data['password'], $user->password)) {
                    throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
                }
                if (! $user->is_active) {
                    throw ValidationException::withMessages(['email' => 'Esta cuenta está desactivada.']);
                }
            } else {
                $data = $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'password' => ['required', 'confirmed', Password::min(8)],
                ]);
                $user = User::query()->create(['name' => $data['name'], 'email' => $invitation->email, 'password' => Hash::make($data['password'])]);
            }
            Auth::login($user);
            $request->session()->regenerate();
        }

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->workspace->members()->firstOrCreate(
                ['user_id' => $user->id],
                ['display_name' => $user->name, 'role' => 'member'],
            );
            $invitation->update(['accepted_at' => now()]);
        });

        return response()->json(['user' => $user, 'csrf_token' => csrf_token()]);
    }

    private function findValid(string $token): WorkspaceInvitation
    {
        return WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
