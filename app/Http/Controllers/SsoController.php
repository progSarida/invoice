<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole; 
use Illuminate\Support\Facades\URL; 
use Illuminate\Support\Facades\Session; // Aggiunto per fix sessione

class SsoController extends Controller
{
    /**
     * Avvia il flusso SSO: genera lo stato CSRF e reindirizza a Passport.
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        
        // Usa l'helper session() per la massima affidabilità
        session()->put('state', $state); 
        
        // 💡 TENTATIVO DI FIX: Forza il salvataggio della sessione prima del reindirizzamento
        // Questo può aiutare se l'ambiente di hosting ha problemi con l'output buffer.
        Session::save();
        
        Log::debug("SSO Redirect: State saved in session. Saved State: " . session('state'));

        // Usa l'URI di reindirizzamento configurato nell'App Cliente
        $redirectUri = env('SSO_REDIRECT_URI') ?? URL::to('/auth/callback'); 

        $query = http_build_query([
            'client_id'     => env('SSO_CLIENT_ID'),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => env('SSO_SCOPE'), 
            'state'         => $state, 
        ]);

        return redirect(env('SSO_AUTH_URL') . '?' . $query);
    }
    
    /**
     * Gestisce il callback e lo scambio di token dopo l'autenticazione.
     */
    public function callback(Request $request)
    {
        // 1. Verifica Stato di Sicurezza (CSRF Protection) e Errori
        $sessionState = session()->pull('state'); // Usa l'helper e pull
        $isError = $request->has('error');

        // Logging dello stato per debug
        Log::error("SSO Callback State Check:", [
            'session_state_retrieved' => $sessionState, 
            'request_state_received' => $request->state,
            'error_from_sso_server' => $request->error, 
            'line' => __LINE__,
        ]);


        if (!$sessionState || $sessionState !== $request->state || $isError) {
            // L'errore 'invalid_scope' è stato inviato dal Portale SSO nel reindirizzamento!
            $errorMessage = $request->error ?? 'Stato Sessione mancante'; 
            
            // Se c'è un errore dallo SSO Server (come invalid_scope), loggiamo quello.
            if ($request->error) {
                 Log::error("SSO Security/Error Failure: SSO Server Error Received: " . $request->error);
            }
            
            return redirect('/admin/login')->withErrors(['sso' => 'Accesso SSO fallito o negato. Errore: ' . $errorMessage]);
        }
        
        // --- SE LA VERIFICA PASSA, PROSEGUIAMO CON LO SCAMBIO TOKEN ---

        // 2. Scambio Codice per Token
        $redirectUri = env('SSO_REDIRECT_URI') ?? URL::to('/auth/callback');

        $response = Http::asForm()
                ->withOptions(['verify' => false]) 
                ->post(env('SSO_TOKEN_URL'), [
                    'grant_type' => 'authorization_code',
                    'client_id' => env('SSO_CLIENT_ID'),
                    'client_secret' => env('SSO_CLIENT_SECRET'),
                    'redirect_uri' => $redirectUri,
                    'code' => $request->code,
                ]);

        $data = $response->json();

        if ($response->failed() || !isset($data['access_token'])) {
             // LOG DETTAGLIATO: Stampa lo stato e il corpo della risposta (errore Passport)
             Log::error('SSO Token Exchange Failed: Status: ' . $response->status());
             Log::error('SSO Token Exchange Failed: Body: ' . $response->body()); 

             return redirect('/admin/login')->withErrors(['sso' => 'Impossibile ottenere il token di accesso. Controlla il log di App Cliente per i dettagli dell\'errore Passport.']);
        }
        $accessToken = $data['access_token'];

        // 3. Recupera i Dati Utente dal Server IdP
        $userResponse = Http::withToken($accessToken)
                ->withOptions(['verify' => false]) 
                ->get(env('SSO_USERINFO_URL'));
        
        $ssoUserData = $userResponse->json();

        if ($userResponse->failed() || !isset($ssoUserData['email'])) {
             Log::error('SSO User Info Failed: ' . $userResponse->body());
             return redirect('/admin/login')->withErrors(['sso' => 'Impossibile recuperare i dati utente validi.']);
        }

        // 4. Provisioning/Shadow User Locale
        $user = User::firstOrCreate(
            ['email' => $ssoUserData['email']],
            [
                'name'     => $ssoUserData['name'] ?? $ssoUserData['email'],
                'password' => Hash::make(Str::random(40)), 
            ]
        );

        // 5. Acquisizione e Sincronizzazione del Ruolo tramite Spatie
        $ssoScope = env('SSO_SCOPE');
        $ssoRoleName = $ssoUserData['application_roles'][$ssoScope] ?? null; 
        
        if ($ssoRoleName && class_exists('Spatie\Permission\Models\Role')) {
            $user->syncRoles([]); 
            
            // Crea il ruolo se non esiste prima di assegnarlo
            $role = SpatieRole::firstOrCreate(
                ['name' => $ssoRoleName, 'guard_name' => 'web']
            );
            
            $user->assignRole($role->name);
            Log::info("SSO Login: User {$user->email} assigned role: {$role->name} (Created if non-existent).");
        }

        // 6. Logga e Reindirizza a Filament
        Auth::login($user, true); 
        return redirect('/admin'); 
    }
}